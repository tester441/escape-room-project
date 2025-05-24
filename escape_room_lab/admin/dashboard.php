<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin
requireAdmin();

// Get counts for dashboard
$db = connectDb();

$stmt = $db->query("SELECT COUNT(*) as user_count FROM users");
$userCount = $stmt->fetch()['user_count'];

$stmt = $db->query("SELECT COUNT(*) as team_count FROM teams");
$teamCount = $stmt->fetch()['team_count'];

$stmt = $db->query("SELECT COUNT(*) as room_count FROM rooms");
$roomCount = $stmt->fetch()['room_count'];

$stmt = $db->query("SELECT COUNT(*) as question_count FROM puzzles"); // FIX: Vervang 'questions' door 'puzzles'
$questionCount = $stmt->fetch()['question_count'];

$stmt = $db->query("SELECT COUNT(*) as completed_count FROM teams WHERE escape_time IS NOT NULL");
$completedCount = $stmt->fetch()['completed_count'];
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?> - Admin</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="rooms.php">Kamers</a></li>
                    <li><a href="puzzles.php">Puzzels</a></li> <!-- Veranderd van questions.php naar puzzles.php -->
                    <li><a href="users.php">Gebruikers</a></li>
                    <li><a href="teams.php">Teams</a></li>
                    <li><a href="../auth/logout.php">Uitloggen</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Admin Dashboard</h2>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Gebruikers</h3>
                <p class="stat-number"><?= $userCount ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Teams</h3>
                <p class="stat-number"><?= $teamCount ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Kamers</h3>
                <p class="stat-number"><?= $roomCount ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Vragen</h3>
                <p class="stat-number"><?= $questionCount ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Voltooide Spellen</h3>
                <p class="stat-number"><?= $completedCount ?></p>
            </div>
        </div>

        <div class="admin-actions">
            <h3>Snelle Acties</h3>
            <div class="action-buttons">
                <a href="rooms.php?action=add" class="btn btn-primary">Kamer Toevoegen</a>
                <a href="puzzles.php?action=add" class="btn btn-primary">Vraag Toevoegen</a> <!-- Gewijzigd van questions.php naar puzzles.php -->
                <a href="teams.php" class="btn btn-secondary">Teams Bekijken</a>
                <a href="../teams/leaderboard.php" class="btn btn-secondary">Scorebord Bekijken</a>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
