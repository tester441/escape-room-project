<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Admin cannot create teams (they manage them)
if (isAdmin()) {
    header("Location: ../admin/teams.php");
    exit();
}

$errors = [];
$teamName = '';
$teamMembers = ['', '', '']; // Default 3 empty team member fields

// Check if user already has a team
$db = connectDb();
$existingTeam = null;

$stmt = $db->prepare("SELECT * FROM teams WHERE created_by = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$existingTeam = $stmt->fetch();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $teamName = sanitizeInput($_POST['team_name']);
    $teamMembers = isset($_POST['team_members']) ? $_POST['team_members'] : [];
    
    // Filter empty values
    $teamMembers = array_filter($teamMembers, function($value) {
        return !empty(trim($value));
    });
    
    // Validate form data
    if (empty($teamName)) {
        $errors[] = "Teamnaam is verplicht";
    }
    
    if (count($teamMembers) < 1) {
        $errors[] = "Minimaal één teamlid is verplicht";
    }
    
    // Check if team name already exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT * FROM teams WHERE name = ? AND id != ?");
        $stmt->execute([$teamName, $existingTeam ? $existingTeam['id'] : 0]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Teamnaam bestaat al";
        }
    }
    
    // If no errors, create or update the team
    if (empty($errors)) {
        $db->beginTransaction();
        
        try {
            if ($existingTeam) {
                // Update existing team
                $stmt = $db->prepare("UPDATE teams SET name = ? WHERE id = ?");
                $stmt->execute([$teamName, $existingTeam['id']]);
                $teamId = $existingTeam['id'];
                
                // Delete existing members
                $stmt = $db->prepare("DELETE FROM team_members WHERE team_id = ?");
                $stmt->execute([$teamId]);
            } else {
                // Insert team
                $stmt = $db->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
                $stmt->execute([$teamName, $_SESSION['user_id']]);
                $teamId = $db->lastInsertId();
            }
            
            // Insert team members
            $stmt = $db->prepare("INSERT INTO team_members (team_id, name) VALUES (?, ?)");
            foreach ($teamMembers as $member) {
                $memberName = sanitizeInput($member);
                if (!empty($memberName)) {
                    $stmt->execute([$teamId, $memberName]);
                }
            }
            
            $db->commit();
            $_SESSION['team_id'] = $teamId;
            $_SESSION['team_name'] = $teamName;
            $_SESSION['success_message'] = $existingTeam ? "Team succesvol bijgewerkt!" : "Team succesvol aangemaakt!";
            header("Location: ../game/play.php");
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Er is een fout opgetreden: " . $e->getMessage();
        }
    }
}

// Fill form with existing team data if editing
if ($existingTeam && empty($teamName)) {
    $teamName = $existingTeam['name'];
    
    // Get team members
    $stmt = $db->prepare("SELECT name FROM team_members WHERE team_id = ?");
    $stmt->execute([$existingTeam['id']]);
    $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($members) > 0) {
        $teamMembers = array_merge($members, array_fill(0, max(0, 3 - count($members)), ''));
    }
}
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
                    <li><a href="leaderboard.php">Scorebord</a></li>
                    <li><a href="../game/play.php">Speel</a></li>
                    <li><a href="../auth/logout.php">Uitloggen (<?= $_SESSION['username'] ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="form-container">
            <h2><?= $existingTeam ? 'Team Bewerken' : 'Team Aanmaken' ?></h2>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <p><?= $_SESSION['error_message'] ?></p>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="needs-validation">
                <div class="form-group">
                    <label for="team_name">Teamnaam:</label>
                    <input type="text" name="team_name" id="team_name" class="form-control" value="<?= htmlspecialchars($teamName) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Teamleden:</label>
                    <div id="team_members_container">
                        <?php foreach ($teamMembers as $index => $member): ?>
                            <div class="team-member-input">
                                <input type="text" name="team_members[]" class="form-control" value="<?= htmlspecialchars($member) ?>" placeholder="Naam teamlid" <?= $index === 0 ? 'required' : '' ?>>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add_member" class="btn btn-secondary">Teamlid Toevoegen</button>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><?= $existingTeam ? 'Team Bijwerken' : 'Team Aanmaken' ?></button>
                </div>
                
                <?php if ($existingTeam): ?>
                    <div class="alert alert-info">
                        <p><strong>Let op:</strong> Je hebt al een team. Als je dit formulier verstuurt, wordt je bestaande team bijgewerkt.</p>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('add_member').addEventListener('click', function() {
                const container = document.getElementById('team_members_container');
                const input = document.createElement('div');
                input.className = 'team-member-input';
                input.innerHTML = '<input type="text" name="team_members[]" class="form-control" placeholder="Naam teamlid">';
                container.appendChild(input);
            });
        });
    </script>
</body>
</html>
