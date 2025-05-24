<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Admin cannot create teams
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$db = connectDb();
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamName = sanitizeInput($_POST['team_name']);
    
    if (empty($teamName)) {
        $errors[] = "Teamnaam is verplicht.";
    } else {
        // Check if team name already exists
        $stmt = $db->prepare("SELECT id FROM teams WHERE name = ?");
        $stmt->execute([$teamName]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Deze teamnaam bestaat al.";
        } else {
            // Create team
            $stmt = $db->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
            $result = $stmt->execute([$teamName, $_SESSION['user_id']]);
            
            if ($result) {
                $teamId = $db->lastInsertId();
                
                // Add creator as team member and captain
                $stmt = $db->prepare("INSERT INTO team_members (team_id, user_id, is_captain) VALUES (?, ?, 1)");
                $stmt->execute([$teamId, $_SESSION['user_id']]);
                
                // Set session
                $_SESSION['team_id'] = $teamId;
                $_SESSION['team_name'] = $teamName;
                
                $success = "Team succesvol aangemaakt!";
                header("Location: ../game/play.php");
                exit();
            } else {
                $errors[] = "Er is een fout opgetreden bij het aanmaken van het team.";
            }
        }
    }
}

// Check for error message from play.php
if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get user's existing teams
$stmt = $db->prepare("
    SELECT t.* 
    FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    WHERE tm.user_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$userTeams = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Aanmaken - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../teams/leaderboard.php">Scorebord</a></li>
                    <li><a href="../auth/logout.php">Uitloggen (<?= $_SESSION['username'] ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Team Aanmaken</h2>
        
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
        
        <div class="card">
            <h3>Nieuw Team</h3>
            <form method="post">
                <div class="form-group">
                    <label for="team_name">Teamnaam:</label>
                    <input type="text" id="team_name" name="team_name" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Team Aanmaken</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($userTeams)): ?>
            <h3>Jouw Teams</h3>
            <div class="team-list">
                <?php foreach ($userTeams as $team): ?>
                    <div class="team-card">
                        <h4><?= htmlspecialchars($team['name']) ?></h4>
                        <p>Aangemaakt op: <?= date('d-m-Y H:i', strtotime($team['created_at'])) ?></p>
                        <?php if ($team['escape_time']): ?>
                            <p>Escape tijd: <?= formatTime($team['escape_time']) ?></p>
                        <?php endif; ?>
                        <div class="team-actions">
                            <form method="post" action="../game/play.php">
                                <input type="hidden" name="select_team" value="<?= $team['id'] ?>">
                                <button type="submit" class="btn btn-primary">Spelen</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>
</body>
</html>
