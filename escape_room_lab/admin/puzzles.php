<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin privileges
requireAdmin();

$db = connectDb();
$errors = [];
$success = '';

// Standaardwaarden voor formulier om undefined variabelen te voorkomen
$formAction = 'add';
$puzzleId = null;
$puzzleRoomId = '';
$puzzleTitle = '';
$puzzleDescription = '';
$puzzleEmoji = '🧩';
$puzzlePositionTop = 50;
$puzzlePositionLeft = 50;
$puzzleOptionA = '';
$puzzleOptionB = '';
$puzzleOptionC = '';
$puzzleOptionD = '';
$puzzleCorrectAnswer = 'A';
$puzzleMaxAttempts = 2;
$puzzleOrderNum = 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_puzzle'])) {
        // Add puzzle logic
        $puzzleRoomId = (int)$_POST['room_id'];
        $puzzleTitle = sanitizeInput($_POST['title']);
        $puzzleDescription = sanitizeInput($_POST['description']);
        $puzzleEmoji = sanitizeInput($_POST['emoji']);
        $puzzlePositionTop = (int)$_POST['position_top'];
        $puzzlePositionLeft = (int)$_POST['position_left'];
        $puzzleOptionA = sanitizeInput($_POST['option_a']);
        $puzzleOptionB = sanitizeInput($_POST['option_b']);
        $puzzleOptionC = sanitizeInput($_POST['option_c']);
        $puzzleOptionD = sanitizeInput($_POST['option_d']);
        $puzzleCorrectAnswer = sanitizeInput($_POST['correct_answer']);
        $puzzleMaxAttempts = (int)$_POST['max_attempts'];
        $puzzleOrderNum = (int)$_POST['order_num'];
        
        $options = json_encode([
            'A' => $puzzleOptionA,
            'B' => $puzzleOptionB,
            'C' => $puzzleOptionC,
            'D' => $puzzleOptionD
        ]);
        
        if (empty($puzzleTitle) || empty($puzzleDescription)) {
            $errors[] = "Titel en beschrijving zijn verplicht.";
        } else {
            $stmt = $db->prepare("INSERT INTO puzzles (room_id, title, description, emoji, position_top, position_left, options, correct_answer, max_attempts, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$puzzleRoomId, $puzzleTitle, $puzzleDescription, $puzzleEmoji, $puzzlePositionTop, $puzzlePositionLeft, $options, $puzzleCorrectAnswer, $puzzleMaxAttempts, $puzzleOrderNum]);
            
            if ($result) {
                $success = "Puzzel succesvol toegevoegd!";
                // Reset form fields
                $puzzleTitle = '';
                $puzzleDescription = '';
                $puzzleEmoji = '🧩';
                $puzzleOptionA = '';
                $puzzleOptionB = '';
                $puzzleOptionC = '';
                $puzzleOptionD = '';
            } else {
                $errors[] = "Er is een fout opgetreden bij het toevoegen van de puzzel.";
            }
        }
    } elseif (isset($_POST['edit_puzzle'])) {
        // Edit puzzle logic
        $puzzleId = (int)$_POST['puzzle_id'];
        $puzzleRoomId = (int)$_POST['room_id'];
        $puzzleTitle = sanitizeInput($_POST['title']);
        $puzzleDescription = sanitizeInput($_POST['description']);
        $puzzleEmoji = sanitizeInput($_POST['emoji']);
        $puzzlePositionTop = (int)$_POST['position_top'];
        $puzzlePositionLeft = (int)$_POST['position_left'];
        $puzzleOptionA = sanitizeInput($_POST['option_a']);
        $puzzleOptionB = sanitizeInput($_POST['option_b']);
        $puzzleOptionC = sanitizeInput($_POST['option_c']);
        $puzzleOptionD = sanitizeInput($_POST['option_d']);
        $puzzleCorrectAnswer = sanitizeInput($_POST['correct_answer']);
        $puzzleMaxAttempts = (int)$_POST['max_attempts'];
        $puzzleOrderNum = (int)$_POST['order_num'];
        
        $options = json_encode([
            'A' => $puzzleOptionA,
            'B' => $puzzleOptionB,
            'C' => $puzzleOptionC,
            'D' => $puzzleOptionD
        ]);
        
        if (empty($puzzleTitle) || empty($puzzleDescription)) {
            $errors[] = "Titel en beschrijving zijn verplicht.";
        } else {
            $stmt = $db->prepare("UPDATE puzzles SET room_id = ?, title = ?, description = ?, emoji = ?, position_top = ?, position_left = ?, options = ?, correct_answer = ?, max_attempts = ?, order_num = ? WHERE id = ?");
            $result = $stmt->execute([$puzzleRoomId, $puzzleTitle, $puzzleDescription, $puzzleEmoji, $puzzlePositionTop, $puzzlePositionLeft, $options, $puzzleCorrectAnswer, $puzzleMaxAttempts, $puzzleOrderNum, $puzzleId]);
            
            if ($result) {
                $success = "Puzzel succesvol bijgewerkt!";
                $formAction = 'add'; // Reset to add mode
                // Reset form fields
                $puzzleId = null;
                $puzzleTitle = '';
                $puzzleDescription = '';
                $puzzleEmoji = '🧩';
                $puzzleOptionA = '';
                $puzzleOptionB = '';
                $puzzleOptionC = '';
                $puzzleOptionD = '';
            } else {
                $errors[] = "Er is een fout opgetreden bij het bijwerken van de puzzel.";
            }
        }
    } elseif (isset($_POST['delete_puzzle'])) {
        // Delete puzzle logic
        $puzzleId = (int)$_POST['puzzle_id'];
        
        // Delete related solved_puzzles entries first
        $stmt = $db->prepare("DELETE FROM solved_puzzles WHERE puzzle_id = ?");
        $stmt->execute([$puzzleId]);
        
        // Then delete the puzzle
        $stmt = $db->prepare("DELETE FROM puzzles WHERE id = ?");
        $result = $stmt->execute([$puzzleId]);
        
        if ($result) {
            $success = "Puzzel succesvol verwijderd!";
        } else {
            $errors[] = "Er is een fout opgetreden bij het verwijderen van de puzzel.";
        }
    }
}

// Check for edit action
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $puzzleId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM puzzles WHERE id = ?");
    $stmt->execute([$puzzleId]);
    $puzzle = $stmt->fetch();
    
    if ($puzzle) {
        $formAction = 'edit';
        $puzzleId = $puzzle['id'];
        $puzzleRoomId = $puzzle['room_id'];
        $puzzleTitle = $puzzle['title'];
        $puzzleDescription = $puzzle['description'];
        $puzzleEmoji = $puzzle['emoji'];
        $puzzlePositionTop = $puzzle['position_top'];
        $puzzlePositionLeft = $puzzle['position_left'];
        $options = json_decode($puzzle['options'], true);
        $puzzleOptionA = $options['A'] ?? '';
        $puzzleOptionB = $options['B'] ?? '';
        $puzzleOptionC = $options['C'] ?? '';
        $puzzleOptionD = $options['D'] ?? '';
        $puzzleCorrectAnswer = $puzzle['correct_answer'];
        $puzzleMaxAttempts = $puzzle['max_attempts'];
        $puzzleOrderNum = $puzzle['order_num'];
    }
}

// Get all rooms for dropdown
$stmt = $db->query("SELECT id, name FROM rooms ORDER BY order_num ASC");
$rooms = $stmt->fetchAll();

// Get all puzzles with room names
$stmt = $db->query("
    SELECT p.*, r.name as room_name 
    FROM puzzles p
    JOIN rooms r ON p.room_id = r.id
    ORDER BY r.order_num ASC, p.order_num ASC
");
$puzzles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer Puzzels - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?> - Admin</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="rooms.php">Kamers</a></li>
                    <li><a href="puzzles.php" class="active">Puzzels</a></li>
                    <li><a href="users.php">Gebruikers</a></li>
                    <li><a href="teams.php">Teams</a></li>
                    <li><a href="../auth/logout.php">Uitloggen</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Beheer Puzzels</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= $error ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <p><?= $success ?></p>
            </div>
        <?php endif; ?>
        
        <h3><?= $formAction === 'edit' ? 'Bewerk' : 'Voeg Toe' ?> Puzzel</h3>
        <form method="post">
            <?php if ($formAction === 'edit'): ?>
                <input type="hidden" name="puzzle_id" value="<?= $puzzleId ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="room_id">Kamer:</label>
                <select id="room_id" name="room_id" required>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= $room['id'] ?>" <?= $puzzleRoomId == $room['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($room['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="title">Titel:</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($puzzleTitle) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Beschrijving:</label>
                <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($puzzleDescription) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="emoji">Emoji:</label>
                <input type="text" id="emoji" name="emoji" value="<?= $puzzleEmoji ?>" required>
                <small>Bijvoorbeeld: 🧪, 🔬, 📊, etc.</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="position_top">Positie Top (%):</label>
                    <input type="number" id="position_top" name="position_top" value="<?= $puzzlePositionTop ?>" min="0" max="100" required>
                </div>
                
                <div class="form-group">
                    <label for="position_left">Positie Links (%):</label>
                    <input type="number" id="position_left" name="position_left" value="<?= $puzzlePositionLeft ?>" min="0" max="100" required>
                </div>
            </div>
            
            <div class="form-group">
                <h4>Antwoordopties:</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="option_a">Optie A:</label>
                        <input type="text" id="option_a" name="option_a" value="<?= htmlspecialchars($puzzleOptionA) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_b">Optie B:</label>
                        <input type="text" id="option_b" name="option_b" value="<?= htmlspecialchars($puzzleOptionB) ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="option_c">Optie C:</label>
                        <input type="text" id="option_c" name="option_c" value="<?= htmlspecialchars($puzzleOptionC) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_d">Optie D:</label>
                        <input type="text" id="option_d" name="option_d" value="<?= htmlspecialchars($puzzleOptionD) ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="correct_answer">Juiste Antwoord:</label>
                <select id="correct_answer" name="correct_answer" required>
                    <option value="A" <?= $puzzleCorrectAnswer === 'A' ? 'selected' : '' ?>>A</option>
                    <option value="B" <?= $puzzleCorrectAnswer === 'B' ? 'selected' : '' ?>>B</option>
                    <option value="C" <?= $puzzleCorrectAnswer === 'C' ? 'selected' : '' ?>>C</option>
                    <option value="D" <?= $puzzleCorrectAnswer === 'D' ? 'selected' : '' ?>>D</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="max_attempts">Maximale Pogingen:</label>
                    <input type="number" id="max_attempts" name="max_attempts" value="<?= $puzzleMaxAttempts ?>" min="1" max="5" required>
                </div>
                
                <div class="form-group">
                    <label for="order_num">Volgorde:</label>
                    <input type="number" id="order_num" name="order_num" value="<?= $puzzleOrderNum ?>" min="1" required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="<?= $formAction === 'edit' ? 'edit_puzzle' : 'add_puzzle' ?>" class="btn btn-primary">
                    <?= $formAction === 'edit' ? 'Bijwerken' : 'Voeg Toe' ?>
                </button>
                <?php if ($formAction === 'edit'): ?>
                    <a href="puzzles.php" class="btn btn-secondary">Annuleren</a>
                <?php endif; ?>
            </div>
        </form>
        
        <h3>Bestaande Puzzels</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kamer</th>
                        <th>Titel</th>
                        <th>Emoji</th>
                        <th>Juiste Antwoord</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($puzzles)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Geen puzzels gevonden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($puzzles as $puzzle): ?>
                            <tr>
                                <td><?= $puzzle['id'] ?></td>
                                <td><?= htmlspecialchars($puzzle['room_name']) ?></td>
                                <td><?= htmlspecialchars($puzzle['title']) ?></td>
                                <td class="text-center"><?= $puzzle['emoji'] ?></td>
                                <td><?= $puzzle['correct_answer'] ?></td>
                                <td>
                                    <a href="puzzles.php?action=edit&id=<?= $puzzle['id'] ?>" class="btn btn-sm btn-info">Bewerken</a>
                                    <form method="post" style="display: inline-block;" onsubmit="return confirm('Weet je zeker dat je deze puzzel wilt verwijderen?');">
                                        <input type="hidden" name="puzzle_id" value="<?= $puzzle['id'] ?>">
                                        <button type="submit" name="delete_puzzle" class="btn btn-sm btn-danger">Verwijderen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>
</body>
</html>
