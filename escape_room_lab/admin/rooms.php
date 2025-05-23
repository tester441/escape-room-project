<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin
requireAdmin();

$db = connectDb();
$errors = [];
$success = '';

// Handle form actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add room
        if ($_POST['action'] == 'add') {
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $backgroundImage = sanitizeInput($_POST['background_image']);
            $orderNum = (int)$_POST['order_num'];

            if (empty($name) || empty($description) || empty($backgroundImage)) {
                $errors[] = "Naam, beschrijving en achtergrondafbeelding zijn verplicht";
            } else {
                $stmt = $db->prepare("INSERT INTO rooms (name, description, background_image, order_num) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $backgroundImage, $orderNum]);
                $success = "Kamer succesvol toegevoegd";
            }
        }
        
        // Edit room
        elseif ($_POST['action'] == 'edit') {
            $roomId = (int)$_POST['room_id'];
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $backgroundImage = sanitizeInput($_POST['background_image']);
            $orderNum = (int)$_POST['order_num'];

            if (empty($name) || empty($description) || empty($backgroundImage)) {
                $errors[] = "Naam, beschrijving en achtergrondafbeelding zijn verplicht";
            } else {
                $stmt = $db->prepare("UPDATE rooms SET name = ?, description = ?, background_image = ?, order_num = ? WHERE id = ?");
                $stmt->execute([$name, $description, $backgroundImage, $orderNum, $roomId]);
                $success = "Kamer succesvol bijgewerkt";
            }
        }
        
        // Delete room
        elseif ($_POST['action'] == 'delete') {
            $roomId = (int)$_POST['room_id'];
            
            // Check if there are questions in this room
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM questions WHERE room_id = ?");
            $stmt->execute([$roomId]);
            $questionCount = $stmt->fetch()['count'];
            
            if ($questionCount > 0) {
                $errors[] = "Deze kamer bevat vragen. Verwijder eerst alle vragen in deze kamer.";
            } else {
                $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
                $stmt->execute([$roomId]);
                $success = "Kamer succesvol verwijderd";
            }
        }
    }
}

// Get room to edit
$roomToEdit = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $roomId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $roomToEdit = $stmt->fetch();
}

// Get all rooms
$stmt = $db->query("SELECT r.*, (SELECT COUNT(*) FROM questions WHERE room_id = r.id) as question_count FROM rooms r ORDER BY r.order_num ASC");
$rooms = $stmt->fetchAll();

// Determine if we're adding or editing
$formAction = isset($_GET['action']) && $_GET['action'] == 'edit' ? 'edit' : 'add';
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer Kamers - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .room-preview {
            width: 100%;
            height: 150px;
            background-size: cover;
            background-position: center;
            border-radius: 5px;
            margin-top: 10px;
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
                    <li><a href="questions.php">Beheer Vragen</a></li>
                    <li><a href="teams.php">Beheer Teams</a></li>
                    <li><a href="../auth/logout.php">Uitloggen (<?= $_SESSION['username'] ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Beheer Kamers</h2>

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
            <h3><?= $formAction == 'edit' ? 'Bewerk' : 'Voeg Toe' ?> Kamer</h3>
            
            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="needs-validation">
                <input type="hidden" name="action" value="<?= $formAction ?>">
                
                <?php if ($formAction == 'edit'): ?>
                    <input type="hidden" name="room_id" value="<?= $roomToEdit['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Naam:</label>
                    <input type="text" name="name" id="name" class="form-control" value="<?= $formAction == 'edit' ? $roomToEdit['name'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Beschrijving:</label>
                    <textarea name="description" id="description" class="form-control" required><?= $formAction == 'edit' ? $roomToEdit['description'] : '' ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="background_image">URL Achtergrondafbeelding:</label>
                    <input type="url" name="background_image" id="background_image" class="form-control" value="<?= $formAction == 'edit' ? $roomToEdit['background_image'] : '' ?>" required>
                    <?php if ($formAction == 'edit' && !empty($roomToEdit['background_image'])): ?>
                        <div class="room-preview" style="background-image: url('<?= htmlspecialchars($roomToEdit['background_image']) ?>')"></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="order_num">Volgorde:</label>
                    <input type="number" name="order_num" id="order_num" class="form-control" value="<?= $formAction == 'edit' ? $roomToEdit['order_num'] : count($rooms) + 1 ?>" min="1" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><?= $formAction == 'edit' ? 'Update' : 'Voeg Toe' ?></button>
                    <?php if ($formAction == 'edit'): ?>
                        <a href="rooms.php" class="btn btn-secondary">Annuleren</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h3>Bestaande Kamers</h3>
        
        <?php if (empty($rooms)): ?>
            <div class="alert alert-info">
                Er zijn nog geen kamers toegevoegd.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Volgorde</th>
                            <th>Naam</th>
                            <th>Beschrijving</th>
                            <th>Vragen</th>
                            <th>Voorbeeld</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td><?= $room['order_num'] ?></td>
                                <td><?= htmlspecialchars($room['name']) ?></td>
                                <td><?= htmlspecialchars(substr($room['description'], 0, 50)) ?><?= strlen($room['description']) > 50 ? '...' : '' ?></td>
                                <td><?= $room['question_count'] ?></td>
                                <td>
                                    <div class="room-preview" style="background-image: url('<?= htmlspecialchars($room['background_image']) ?>')"></div>
                                </td>
                                <td>
                                    <a href="rooms.php?action=edit&id=<?= $room['id'] ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="inline-form" onsubmit="return confirm('Weet je zeker dat je deze kamer wilt verwijderen? Dit kan niet ongedaan worden gemaakt.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" <?= $room['question_count'] > 0 ? 'disabled' : '' ?>>Verwijderen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
        // Preview background image on input
        document.getElementById('background_image').addEventListener('input', function() {
            const url = this.value;
            if (url) {
                const preview = document.querySelector('.room-preview') || document.createElement('div');
                preview.className = 'room-preview';
                preview.style.backgroundImage = `url('${url}')`;
                
                if (!document.querySelector('.room-preview')) {
                    this.parentNode.appendChild(preview);
                }
            }
        });
    </script>
</body>
</html>
