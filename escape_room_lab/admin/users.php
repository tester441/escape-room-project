<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require admin privileges
requireAdmin();

$db = connectDb();
$errors = [];
$success = '';

// Standaardwaarden voor formulier
$formAction = 'add';
$userId = null;
$userName = '';
$userEmail = '';
$userPassword = '';
$userRole = 'user';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Add user logic
        $userName = sanitizeInput($_POST['username']);
        $userEmail = sanitizeInput($_POST['email']);
        $userPassword = $_POST['password'];
        $userRole = sanitizeInput($_POST['role']);
        
        if (empty($userName) || empty($userEmail) || empty($userPassword)) {
            $errors[] = "Alle velden zijn verplicht.";
        } else {
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$userName]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Deze gebruikersnaam bestaat al.";
            } else {
                // Insert new user
                $stmt = $db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([$userName, $userPassword, $userEmail, $userRole]);
                
                if ($result) {
                    $success = "Gebruiker succesvol toegevoegd!";
                    // Reset form fields
                    $userName = '';
                    $userEmail = '';
                    $userPassword = '';
                    $userRole = 'user';
                } else {
                    $errors[] = "Er is een fout opgetreden bij het toevoegen van de gebruiker.";
                }
            }
        }
    } elseif (isset($_POST['edit_user'])) {
        // Edit user logic
        $userId = (int)$_POST['user_id'];
        $userName = sanitizeInput($_POST['username']);
        $userEmail = sanitizeInput($_POST['email']);
        $userPassword = $_POST['password'];
        $userRole = sanitizeInput($_POST['role']);
        
        if (empty($userName) || empty($userEmail)) {
            $errors[] = "Gebruikersnaam en e-mail zijn verplicht.";
        } else {
            // Check if username already exists (except for current user)
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$userName, $userId]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Deze gebruikersnaam bestaat al.";
            } else {
                // Update user with or without password
                if (empty($userPassword)) {
                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                    $result = $stmt->execute([$userName, $userEmail, $userRole, $userId]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                    $result = $stmt->execute([$userName, $userEmail, $userPassword, $userRole, $userId]);
                }
                
                if ($result) {
                    $success = "Gebruiker succesvol bijgewerkt!";
                    $formAction = 'add'; // Reset to add mode
                    // Reset form fields
                    $userId = null;
                    $userName = '';
                    $userEmail = '';
                    $userPassword = '';
                    $userRole = 'user';
                } else {
                    $errors[] = "Er is een fout opgetreden bij het bijwerken van de gebruiker.";
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        // Delete user logic
        $userId = (int)$_POST['user_id'];
        
        // Check if it's the last admin
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $stmt->execute();
        $adminCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userToDelete = $stmt->fetch();
        
        if ($userToDelete && $userToDelete['role'] === 'admin' && $adminCount <= 1) {
            $errors[] = "Kan de laatste administrator niet verwijderen.";
        } else {
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                $success = "Gebruiker succesvol verwijderd!";
            } else {
                $errors[] = "Er is een fout opgetreden bij het verwijderen van de gebruiker.";
            }
        }
    }
}

// Check for edit action
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        $formAction = 'edit';
        $userId = $user['id'];
        $userName = $user['username'];
        $userEmail = $user['email'];
        $userRole = $user['role'];
        // Password is not pre-filled for security reasons
    }
}

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheer Gebruikers - <?= SITE_NAME ?></title>
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
                    <li><a href="puzzles.php">Puzzels</a></li>
                    <li><a href="users.php" class="active">Gebruikers</a></li>
                    <li><a href="teams.php">Teams</a></li>
                    <li><a href="../auth/logout.php">Uitloggen</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Beheer Gebruikers</h2>
        
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
        
        <h3><?= $formAction === 'edit' ? 'Bewerk' : 'Voeg Toe' ?> Gebruiker</h3>
        <form method="post">
            <?php if ($formAction === 'edit'): ?>
                <input type="hidden" name="user_id" value="<?= $userId ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Gebruikersnaam:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($userName) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($userEmail) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Wachtwoord: <?= $formAction === 'edit' ? '(laat leeg om ongewijzigd te laten)' : '' ?></label>
                <input type="password" id="password" name="password" <?= $formAction === 'add' ? 'required' : '' ?>>
            </div>
            
            <div class="form-group">
                <label for="role">Rol:</label>
                <select id="role" name="role" required>
                    <option value="user" <?= $userRole === 'user' ? 'selected' : '' ?>>Gebruiker</option>
                    <option value="admin" <?= $userRole === 'admin' ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="<?= $formAction === 'edit' ? 'edit_user' : 'add_user' ?>" class="btn btn-primary">
                    <?= $formAction === 'edit' ? 'Bijwerken' : 'Voeg Toe' ?>
                </button>
                <?php if ($formAction === 'edit'): ?>
                    <a href="users.php" class="btn btn-secondary">Annuleren</a>
                <?php endif; ?>
            </div>
        </form>
        
        <h3>Bestaande Gebruikers</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gebruikersnaam</th>
                        <th>E-mail</th>
                        <th>Rol</th>
                        <th>Aangemaakt op</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Geen gebruikers gevonden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= $user['role'] === 'admin' ? 'Administrator' : 'Gebruiker' ?></td>
                                <td><?= date('d-m-Y H:i', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <a href="users.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-info">Bewerken</a>
                                    <?php if ($_SESSION['user_id'] !== $user['id']): ?>
                                        <form method="post" style="display: inline-block;" onsubmit="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger">Verwijderen</button>
                                        </form>
                                    <?php endif; ?>
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
