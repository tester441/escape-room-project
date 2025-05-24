<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// If user is already logged in, redirect to home page
if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$errors = [];
$username = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $errors[] = "Vul alstublieft zowel de gebruikersnaam als het wachtwoord in.";
    } else {
        $db = connectDb();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Aangepast om zowel gehashte als niet-gehashte wachtwoorden te accepteren
        if ($user) {
            $passwordMatch = false;
            
            // Controleer of het een eenvoudig wachtwoord is (zoals admin123)
            if ($password === $user['password']) {
                $passwordMatch = true;
            } 
            // Controleer anders de gehashte versie (voor oudere accounts)
            else if (password_verify($password, $user['password'])) {
                $passwordMatch = true;
            }
            
            if ($passwordMatch) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = ($user['role'] == 'admin') ? 1 : 0;
                
                if ($_SESSION['is_admin']) {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../index.php");
                }
                exit();
            } else {
                $errors[] = "Ongeldige gebruikersnaam of wachtwoord.";
            }
        } else {
            $errors[] = "Ongeldige gebruikersnaam of wachtwoord.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="register.php">Registreren</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="form-container">
            <h2>Inloggen</h2>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success_message'] ?>
                    <?php unset($_SESSION['success_message']); ?>
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
                    <label for="username">Gebruikersnaam:</label>
                    <input type="text" name="username" id="username" class="form-control" value="<?= $username ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Wachtwoord:</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Inloggen</button>
                </div>
                
                <p>Nog geen account? <a href="register.php">Registreer hier</a></p>
            </form>
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
