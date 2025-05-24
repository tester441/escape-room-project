<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin
requireAdmin();

$db = connectDb();
$teams = [];
$errors = [];
$success = '';

// Handle delete team action
if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['team_id'])) {
    $teamId = (int)$_POST['team_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        $success = "Team succesvol verwijderd";
    } catch (PDOException $e) {
        $errors[] = "Er is een fout opgetreden bij het verwijderen van het team: " . $e->getMessage();
    }
}

// Get all teams with creator info and completion status
$stmt = $db->prepare("
    SELECT t.*, u.username as creator_name, 
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
    FROM teams t
    JOIN users u ON t.created_by = u.id
    ORDER BY t.created_at DESC
");
$stmt->execute();
$teams = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer Teams - <?= SITE_NAME ?></title>
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
                    <li><a href="questions.php">Beheer Vragen</a></li>
                    <li><a href="../auth/logout.php">Uitloggen (<?= $_SESSION['username'] ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Beheer Teams</h2>
        
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
        
        <div class="section">
            <h3>Alle Teams</h3>
            
            <?php if (empty($teams)): ?>
                <div class="alert alert-info">
                    Er zijn nog geen teams aangemaakt.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Naam</th>
                                <th>Aangemaakt door</th>
                                <th>Teamleden</th>
                                <th>Status</th>
                                <th>Aangemaakt op</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td><?= $team['id'] ?></td>
                                    <td><?= htmlspecialchars($team['name']) ?></td>
                                    <td><?= htmlspecialchars($team['creator_name']) ?></td>
                                    <td><?= $team['member_count'] ?></td>
                                    <td>
                                        <?php if ($team['escape_time']): ?>
                                            <span class="status completed">Voltooid in <?= formatTime($team['escape_time']) ?></span>
                                        <?php elseif ($team['start_time']): ?>
                                            <span class="status in-progress">In Uitvoering</span>
                                        <?php else: ?>
                                            <span class="status not-started">Niet Gestart</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d-m-Y H:i', strtotime($team['created_at'])) ?></td>
                                    <td>
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="inline-form" onsubmit="return confirm('Weet je zeker dat je dit team wilt verwijderen?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Verwijderen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
