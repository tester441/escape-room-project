<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= SITE_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="teams/leaderboard.php">Scorebord</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li><a href="admin/dashboard.php">Admin Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="teams/create.php">Team Aanmaken</a></li>
                            <li><a href="game/play.php">Speel</a></li>
                        <?php endif; ?>
                        <li><a href="auth/logout.php">Uitloggen (<?= $_SESSION['username'] ?>)</a></li>
                    <?php else: ?>
                        <li><a href="auth/login.php">Inloggen</a></li>
                        <li><a href="auth/register.php">Registreren</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <section class="hero">
            <h2>Welkom bij <?= SITE_NAME ?></h2>
            <p>Kun jij de puzzels oplossen en ontsnappen uit het laboratorium voordat de tijd op is?</p>
            
            <div class="cta-buttons">
                <?php if (isLoggedIn()): ?>
                    <?php if (!isAdmin()): ?>
                        <a href="game/play.php" class="btn btn-primary">Nu Spelen</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="auth/register.php" class="btn btn-primary">Registreren</a>
                    <a href="auth/login.php" class="btn btn-secondary">Inloggen</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="about">
            <div class="two-column">
                <div>
                    <h3>Over Het Spel</h3>
                    <p>Je zit vast in een mysterieus laboratorium! Om te ontsnappen moet je een reeks puzzels en uitdagingen oplossen met behulp van je intelligentie en teamwerk voordat de tijd op is.</p>
                    <p>Maak een team aan of sluit je aan, los puzzels op, vind aanwijzingen en probeer te ontsnappen met de snelste tijd om bovenaan het scorebord te komen!</p>
                </div>
                <div class="image-container">
                    <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Laboratorium Afbeelding">
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
