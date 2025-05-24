<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin
requireAdmin();

$db = connectDb();
$errors = [];
$success = '';

// Definieer $formAction met een standaardwaarde om undefined waarschuwingen te voorkomen
$formAction = 'add';
$roomId = null;
$roomName = '';
$roomDescription = '';
$roomStyle = 'modern-lab';
$roomOrderNum = 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room'])) {
        // Add room logic
        $roomName = sanitizeInput($_POST['name']);
        $roomDescription = sanitizeInput($_POST['description']);
        $roomStyle = sanitizeInput($_POST['style']);
        $roomOrderNum = (int)$_POST['order_num'];

        if (empty($roomName) || empty($roomDescription)) {
            $errors[] = "Alle velden zijn verplicht.";
        } else {
            $stmt = $db->prepare("INSERT INTO rooms (name, description, room_style, order_num) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$roomName, $roomDescription, $roomStyle, $roomOrderNum]);
            
            if ($result) {
                $success = "Kamer succesvol toegevoegd!";
                $roomName = '';
                $roomDescription = '';
                $roomStyle = 'modern-lab';
                $roomOrderNum = 1;
            } else {
                $errors[] = "Er is een fout opgetreden bij het toevoegen van de kamer.";
            }
        }
    } elseif (isset($_POST['edit_room'])) {
        // Edit room logic
        $roomId = (int)$_POST['room_id'];
        $roomName = sanitizeInput($_POST['name']);
        $roomDescription = sanitizeInput($_POST['description']);
        $roomStyle = sanitizeInput($_POST['style']);
        $roomOrderNum = (int)$_POST['order_num'];

        if (empty($roomName) || empty($roomDescription)) {
            $errors[] = "Alle velden zijn verplicht.";
        } else {
            $stmt = $db->prepare("UPDATE rooms SET name = ?, description = ?, room_style = ?, order_num = ? WHERE id = ?");
            $result = $stmt->execute([$roomName, $roomDescription, $roomStyle, $roomOrderNum, $roomId]);
            
            if ($result) {
                $success = "Kamer succesvol bijgewerkt!";
                $formAction = 'add'; // Reset form to add mode
                $roomId = null;
                $roomName = '';
                $roomDescription = '';
                $roomStyle = 'modern-lab';
                $roomOrderNum = 1;
            } else {
                $errors[] = "Er is een fout opgetreden bij het bijwerken van de kamer.";
            }
        }
    } elseif (isset($_POST['delete_room'])) {
        // Delete room logic
        $roomId = (int)$_POST['room_id'];
        
        // Check if room has puzzles
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM puzzles WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $puzzleCount = $stmt->fetch()['count'];
        
        if ($puzzleCount > 0) {
            $errors[] = "Deze kamer kan niet worden verwijderd omdat er nog puzzels in staan.";
        } else {
            $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
            $result = $stmt->execute([$roomId]);
            
            if ($result) {
                $success = "Kamer succesvol verwijderd!";
            } else {
                $errors[] = "Er is een fout opgetreden bij het verwijderen van de kamer.";
            }
        }
    }
}

// Check for edit action
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $roomId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    
    if ($room) {
        $formAction = 'edit';
        $roomId = $room['id'];
        $roomName = $room['name'];
        $roomDescription = $room['description'];
        $roomStyle = $room['room_style'];
        $roomOrderNum = $room['order_num'];
    }
}

// Get all rooms
$stmt = $db->query("SELECT * FROM rooms ORDER BY order_num ASC");
$rooms = $stmt->fetchAll();

// Get puzzle counts per room
$roomPuzzleCounts = [];
$stmt = $db->query("SELECT room_id, COUNT(*) as count FROM puzzles GROUP BY room_id");
$puzzleCounts = $stmt->fetchAll();
foreach ($puzzleCounts as $count) {
    $roomPuzzleCounts[$count['room_id']] = $count['count'];
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer Kamers - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?> - Admin</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="rooms.php" class="active">Kamers</a></li>
                    <li><a href="puzzles.php">Puzzels</a></li>
                    <li><a href="users.php">Gebruikers</a></li>
                    <li><a href="teams.php">Teams</a></li>
                    <li><a href="../auth/logout.php">Uitloggen</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Beheer Kamers</h2>
        
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
        
        <h3><?= $formAction === 'edit' ? 'Bewerk' : 'Voeg Toe' ?> Kamer</h3>
        <form method="post">
            <?php if ($formAction === 'edit'): ?>
                <input type="hidden" name="room_id" value="<?= $roomId ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="name">Naam:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($roomName) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Beschrijving:</label>
                <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($roomDescription) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="style">Stijl:</label>
                <select id="style" name="style" required>
                    <option value="modern-lab" <?= $roomStyle === 'modern-lab' ? 'selected' : '' ?>>Modern Lab</option>
                    <option value="control-room" <?= $roomStyle === 'control-room' ? 'selected' : '' ?>>Control Room</option>
                    <option value="storage" <?= $roomStyle === 'storage' ? 'selected' : '' ?>>Storage</option>
                    <option value="office" <?= $roomStyle === 'office' ? 'selected' : '' ?>>Office</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="order_num">Volgorde:</label>
                <input type="number" id="order_num" name="order_num" value="<?= $roomOrderNum ?>" min="1" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="<?= $formAction === 'edit' ? 'edit_room' : 'add_room' ?>" class="btn btn-primary">
                    <?= $formAction === 'edit' ? 'Bijwerken' : 'Voeg Toe' ?>
                </button>
                <?php if ($formAction === 'edit'): ?>
                    <a href="rooms.php" class="btn btn-secondary">Annuleren</a>
                <?php endif; ?>
            </div>
        </form>
        
        <h3>Bestaande Kamers</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Volgorde</th>
                        <th>Naam</th>
                        <th>Beschrijving</th>
                        <th>Puzzels</th>
                        <th>Voorbeeld</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Geen kamers gevonden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td><?= $room['order_num'] ?></td>
                                <td><?= htmlspecialchars($room['name']) ?></td>
                                <td><?= htmlspecialchars(substr($room['description'], 0, 50)) ?>...</td>
                                <td><?= isset($roomPuzzleCounts[$room['id']]) ? $roomPuzzleCounts[$room['id']] : 0 ?></td>
                                <td>
                                    <span class="room-preview <?= htmlspecialchars($room['room_style']) ?>">
                                        <?= htmlspecialchars($room['name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="rooms.php?action=edit&id=<?= $room['id'] ?>" class="btn btn-sm btn-info">Bewerken</a>
                                    <form method="post" style="display: inline-block;" onsubmit="return confirm('Weet je zeker dat je deze kamer wilt verwijderen?');">
                                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                        <button type="submit" name="delete_room" class="btn btn-sm btn-danger">Verwijderen</button>
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
