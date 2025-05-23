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
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate form data
    if (empty($username)) {
        $errors[] = "Gebruikersnaam is verplicht";
    }

    if (empty($email)) {
        $errors[] = "E-mail is verplicht";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Ongeldig e-mailformaat";
    }

    if (empty($password)) {
        $errors[] = "Wachtwoord is verplicht";
    } elseif (strlen($password) < 6) {
        $errors[] = "Wachtwoord moet minimaal 6 tekens bevatten";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Wachtwoorden komen niet overeen";
    }

    // Check if username or email already exists
    if (empty($errors)) {
        $db = connectDb();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['username'] === $username) {
                $errors[] = "Gebruikersnaam bestaat al";
            }
            if ($user['email'] === $email) {
                $errors[] = "E-mailadres bestaat al";
            }
        }
    }

    // If no errors, create the account
    if (empty($errors)) {
        $db = connectDb();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        
        try {
            $stmt->execute([$username, $email, $hashedPassword]);
            $_SESSION['success_message'] = "Account succesvol aangemaakt! Je kunt nu inloggen.";
            header("Location: login.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Er is een fout opgetreden bij het aanmaken van je account: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="login.php">Inloggen</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="form-container">
            <h2>Account Aanmaken</h2>

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
                    <label for="email">E-mail:</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= $email ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Wachtwoord:</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                    <small>Moet minimaal 6 tekens bevatten</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Bevestig Wachtwoord:</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Registreren</button>
                </div>
                
                <p>Heb je al een account? <a href="login.php">Log hier in</a></p>
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
