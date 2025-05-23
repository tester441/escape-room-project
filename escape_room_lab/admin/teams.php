<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin
requireAdmin();

$db = connectDb();
$errors = [];
$success = '';

// Handle form actions (delete team, reset time)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Delete team
        if ($_POST['action'] == 'delete') {
            $teamId = (int)$_POST['team_id'];
            $stmt = $db->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$teamId]);
            $success = "Team deleted successfully";
        }
        
        // Reset team time
        elseif ($_POST['action'] == 'reset_time') {
            $teamId = (int)$_POST['team_id'];
            $stmt = $db->prepare("UPDATE teams SET escape_time = NULL, start_time = NULL WHERE id = ?");
            $stmt->execute([$teamId]);
            $success = "Team time reset successfully";
        }
    }
}

// Get all teams with user info
$stmt = $db->query("
    SELECT t.*, u.username as creator, COUNT(tm.id) as member_count 
    FROM teams t
    JOIN users u ON t.created_by = u.id
    LEFT JOIN team_members tm ON t.id = tm.team_id
    GROUP BY t.id
    ORDER BY t.escape_time ASC, t.created_at DESC
");
$teams = $stmt->fetchAll();

// Get team details if viewing a specific team
$teamDetails = null;
if (isset($_GET['id'])) {
    $teamId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $teamDetails = $stmt->fetch();
    
    if ($teamDetails) {
        $stmt = $db->prepare("SELECT * FROM team_members WHERE team_id = ?");
        $stmt->execute([$teamId]);
        $teamMembers = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teams - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?> - Admin Panel</h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="questions.php">Manage Questions</a></li>
                    <li><a href="../auth/logout.php">Logout (<?= $_SESSION['username'] ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Manage Teams</h2>

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

        <?php if ($teamDetails): ?>
            <div class="form-container">
                <h3>Team Details: <?= htmlspecialchars($teamDetails['name']) ?></h3>
                <p><strong>Created By:</strong> <?= htmlspecialchars($teamDetails['creator'] ?? 'Unknown') ?></p>
                <p><strong>Created At:</strong> <?= date('F j, Y, g:i a', strtotime($teamDetails['created_at'])) ?></p>
                <p><strong>Escape Time:</strong> <?= $teamDetails['escape_time'] ? formatTime($teamDetails['escape_time']) : 'Not completed' ?></p>
                
                <h4>Team Members:</h4>
                <?php if (empty($teamMembers)): ?>
                    <p>No team members found.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($teamMembers as $member): ?>
                            <li><?= htmlspecialchars($member['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <div class="action-buttons mt-3">
                    <a href="teams.php" class="btn btn-secondary">Back to Teams</a>
                    
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="inline-form" onsubmit="return confirm('Are you sure you want to reset this team\'s time?');">
                        <input type="hidden" name="action" value="reset_time">
                        <input type="hidden" name="team_id" value="<?= $teamDetails['id'] ?>">
                        <button type="submit" class="btn btn-primary">Reset Time</button>
                    </form>
                    
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this team? This action cannot be undone.');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="team_id" value="<?= $teamDetails['id'] ?>">
                        <button type="submit" class="btn btn-danger">Delete Team</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <h3>All Teams</h3>
            
            <?php if (empty($teams)): ?>
                <div class="alert alert-info">
                    No teams have been created yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Team Name</th>
                                <th>Created By</th>
                                <th>Members</th>
                                <th>Escape Time</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td><?= htmlspecialchars($team['name']) ?></td>
                                    <td><?= htmlspecialchars($team['creator']) ?></td>
                                    <td><?= $team['member_count'] ?></td>
                                    <td><?= $team['escape_time'] ? formatTime($team['escape_time']) : 'Not completed' ?></td>
                                    <td><?= date('F j, Y', strtotime($team['created_at'])) ?></td>
                                    <td>
                                        <a href="teams.php?id=<?= $team['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                                        
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="inline-form" onsubmit="return confirm('Are you sure you want to reset this team\'s time?');">
                                            <input type="hidden" name="action" value="reset_time">
                                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Reset</button>
                                        </form>
                                        
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this team?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | All Rights Reserved</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
