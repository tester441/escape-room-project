<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get all teams with escape times
$db = connectDb();
$stmt = $db->prepare("
    SELECT t.id, t.name, t.escape_time, t.created_at, u.username as creator 
    FROM teams t
    JOIN users u ON t.created_by = u.id
    WHERE t.escape_time IS NOT NULL
    ORDER BY t.escape_time ASC
");
$stmt->execute();
$teams = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scorebord - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li><a href="../admin/dashboard.php">Admin Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="../teams/create.php">Team Aanmaken</a></li>
                            <li><a href="../game/play.php">Spelen</a></li>
                        <?php endif; ?>
                        <li><a href="../auth/logout.php">Uitloggen (<?= $_SESSION['username'] ?>)</a></li>
                    <?php else: ?>
                        <li><a href="../auth/login.php">Inloggen</a></li>
                        <li><a href="../auth/register.php">Registreren</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Scorebord</h2>

        <?php if (empty($teams)): ?>
            <div class="alert alert-info">
                Nog geen teams hebben de escape room voltooid.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Team</th>
                            <th>Aangemaakt Door</th>
                            <th>Ontsnappingstijd</th>
                            <th>Voltooid Op</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $index => $team): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($team['name']) ?></td>
                                <td><?= htmlspecialchars($team['creator']) ?></td>
                                <td><?= formatTime($team['escape_time']) ?></td>
                                <td><?= date('j F Y', strtotime($team['created_at'])) ?></td>
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
</body>
</html>
