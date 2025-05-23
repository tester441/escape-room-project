<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin
requireAdmin();

$db = connectDb();
$errors = [];
$success = '';

// Get all rooms for dropdown
$stmt = $db->query("SELECT * FROM rooms ORDER BY order_num ASC");
$rooms = $stmt->fetchAll();

// Handle form actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add question
        if ($_POST['action'] == 'add') {
            $roomId = (int)$_POST['room_id'];
            $question = sanitizeInput($_POST['question']);
            $answer = sanitizeInput($_POST['answer']);
            $hint = sanitizeInput($_POST['hint']);
            $orderNum = (int)$_POST['order_num'];

            if (empty($question) || empty($answer) || $roomId <= 0) {
                $errors[] = "Vraag, antwoord en kamer zijn verplicht";
            } else {
                $stmt = $db->prepare("INSERT INTO questions (room_id, question, answer, hint, order_num) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$roomId, $question, $answer, $hint, $orderNum]);
                $success = "Vraag succesvol toegevoegd";
            }
        }
        
        // Edit question
        elseif ($_POST['action'] == 'edit') {
            $questionId = (int)$_POST['question_id'];
            $roomId = (int)$_POST['room_id'];
            $question = sanitizeInput($_POST['question']);
            $answer = sanitizeInput($_POST['answer']);
            $hint = sanitizeInput($_POST['hint']);
            $orderNum = (int)$_POST['order_num'];

            if (empty($question) || empty($answer) || $roomId <= 0) {
                $errors[] = "Vraag, antwoord en kamer zijn verplicht";
            } else {
                $stmt = $db->prepare("UPDATE questions SET room_id = ?, question = ?, answer = ?, hint = ?, order_num = ? WHERE id = ?");
                $stmt->execute([$roomId, $question, $answer, $hint, $orderNum, $questionId]);
                $success = "Vraag succesvol bijgewerkt";
            }
        }
        
        // Delete question
        elseif ($_POST['action'] == 'delete') {
            $questionId = (int)$_POST['question_id'];
            $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $success = "Vraag succesvol verwijderd";
        }
    }
}

// Get question to edit
$questionToEdit = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $questionId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $questionToEdit = $stmt->fetch();
}

// Get all questions with room info
$stmt = $db->query("
    SELECT q.*, r.name as room_name 
    FROM questions q
    JOIN rooms r ON q.room_id = r.id
    ORDER BY r.order_num ASC, q.order_num ASC
");
$questions = $stmt->fetchAll();

// Determine if we're adding or editing
$formAction = isset($_GET['action']) && $_GET['action'] == 'edit' ? 'edit' : 'add';
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer Vragen - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .room-section {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .room-heading {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?> - Admin Panel</h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="teams.php">Beheer Teams</a></li>
                    <li><a href="../auth/logout.php">Uitloggen (<?= $_SESSION['username'] ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Beheer Vragen</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <h3><?= $formAction == 'edit' ? 'Bewerk' : 'Voeg Toe' ?> Vraag</h3>
            
            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="needs-validation">
                <input type="hidden" name="action" value="<?= $formAction ?>">
                
                <?php if ($formAction == 'edit'): ?>
                    <input type="hidden" name="question_id" value="<?= $questionToEdit['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="room_id">Kamer:</label>
                    <select name="room_id" id="room_id" class="form-control" required>
                        <option value="">Selecteer een kamer</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>" <?= ($formAction == 'edit' && $questionToEdit['room_id'] == $room['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($room['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="question">Vraag:</label>
                    <textarea name="question" id="question" class="form-control" required><?= $formAction == 'edit' ? $questionToEdit['question'] : '' ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="answer">Antwoord:</label>
                    <input type="text" name="answer" id="answer" class="form-control" value="<?= $formAction == 'edit' ? $questionToEdit['answer'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="hint">Hint (optioneel):</label>
                    <textarea name="hint" id="hint" class="form-control"><?= $formAction == 'edit' ? $questionToEdit['hint'] : '' ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="order_num">Volgorde in kamer:</label>
                    <input type="number" name="order_num" id="order_num" class="form-control" value="<?= $formAction == 'edit' ? $questionToEdit['order_num'] : 1 ?>" min="1" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><?= $formAction == 'edit' ? 'Update' : 'Voeg Toe' ?></button>
                    <?php if ($formAction == 'edit'): ?>
                        <a href="questions.php" class="btn btn-secondary">Annuleren</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h3>Bestaande Vragen</h3>
        
        <?php if (empty($questions)): ?>
            <div class="alert alert-info">
                Er zijn nog geen vragen toegevoegd.
            </div>
        <?php else: ?>
            <?php 
            $currentRoomId = null;
            foreach ($questions as $index => $question): 
                // Start a new room section when room changes
                if ($question['room_id'] !== $currentRoomId):
                    if ($currentRoomId !== null) {
                        echo '</tbody></table></div>'; // Close previous room section
                    }
                    $currentRoomId = $question['room_id'];
            ?>
                <div class="room-section">
                    <h4 class="room-heading">Kamer: <?= htmlspecialchars($question['room_name']) ?></h4>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Volgorde</th>
                                    <th>Vraag</th>
                                    <th>Antwoord</th>
                                    <th>Hint</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
            <?php endif; ?>
                                <tr>
                                    <td><?= $question['order_num'] ?></td>
                                    <td><?= htmlspecialchars(substr($question['question'], 0, 50)) ?><?= strlen($question['question']) > 50 ? '...' : '' ?></td>
                                    <td><?= htmlspecialchars($question['answer']) ?></td>
                                    <td><?= htmlspecialchars(substr($question['hint'], 0, 30)) ?><?= strlen($question['hint']) > 30 ? '...' : '' ?></td>
                                    <td>
                                        <a href="questions.php?action=edit&id=<?= $question['id'] ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="inline-form" onsubmit="return confirm('Weet je zeker dat je deze vraag wilt verwijderen?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Verwijderen</button>
                                        </form>
                                    </td>
                                </tr>
            <?php 
                // Close the last room section after the loop
                if ($index === count($questions) - 1): 
            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
