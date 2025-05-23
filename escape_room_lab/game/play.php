<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Admin cannot play
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$db = connectDb();
$errors = [];
$success = '';
$gameOver = false;
$currentQuestion = null;
$currentRoom = null;
$totalRooms = 0;
$questionNumber = 0;
$questionsInRoom = 0;
$roomProgress = 0;

// Check if user has a team
if (!isset($_SESSION['team_id'])) {
    // Try to find if user created any teams
    $stmt = $db->prepare("SELECT * FROM teams WHERE created_by = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $team = $stmt->fetch();
    
    if ($team) {
        $_SESSION['team_id'] = $team['id'];
        $_SESSION['team_name'] = $team['name'];
    } else {
        // Redirect to team creation page with a message
        $_SESSION['error_message'] = "Je moet eerst een team aanmaken voordat je kunt spelen!";
        header("Location: ../teams/create.php");
        exit();
    }
}

// Get the team
$stmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
$stmt->execute([$_SESSION['team_id']]);
$team = $stmt->fetch();

if (!$team) {
    // Team no longer exists, clear session and redirect
    unset($_SESSION['team_id']);
    unset($_SESSION['team_name']);
    $_SESSION['error_message'] = "Je team bestaat niet meer. Maak een nieuw team aan.";
    header("Location: ../teams/create.php");
    exit();
}

// Get total rooms
$stmt = $db->query("SELECT COUNT(*) as count FROM rooms");
$totalRooms = $stmt->fetch()['count'];

// Handle game finish
if (isset($_POST['finish_game'])) {
    $escapeTime = time() - $team['start_time'];
    $stmt = $db->prepare("UPDATE teams SET escape_time = ? WHERE id = ?");
    $stmt->execute([$escapeTime, $team['id']]);
    $success = "Gefeliciteerd! Je bent ontsnapt uit het laboratorium in " . formatTime($escapeTime) . "!";
    $gameOver = true;
}

// Handle time up
if (isset($_POST['time_up'])) {
    $gameOver = true;
    $errors[] = "Tijd is op! Je hebt het niet gered om op tijd uit het laboratorium te ontsnappen.";
}

// Check if game is already completed
if ($team['escape_time'] !== null) {
    $gameOver = true;
    $success = "Je hebt deze escape room al voltooid in " . formatTime($team['escape_time']) . "!";
}

// Start the game if not started already
if (($team['start_time'] === null || $team['current_room'] === null) && !$gameOver && !isset($_POST['answer'])) {
    $stmt = $db->prepare("UPDATE teams SET start_time = ?, current_room = 1 WHERE id = ?");
    $stmt->execute([time(), $team['id']]);
    $team['start_time'] = time();
    $team['current_room'] = 1;
}

// Ensure the current_room is set
if ($team['current_room'] === null && !$gameOver) {
    $stmt = $db->prepare("UPDATE teams SET current_room = 1 WHERE id = ?");
    $stmt->execute([$team['id']]);
    $team['current_room'] = 1;
}

// Get current room
if (!$gameOver && $team['current_room']) {
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$team['current_room']]);
    $currentRoom = $stmt->fetch();
    
    if (!$currentRoom) {
        // If room doesn't exist, reset to first room
        $stmt = $db->query("SELECT * FROM rooms ORDER BY order_num ASC LIMIT 1");
        $currentRoom = $stmt->fetch();
        
        if ($currentRoom) {
            $stmt = $db->prepare("UPDATE teams SET current_room = ? WHERE id = ?");
            $stmt->execute([$currentRoom['id'], $team['id']]);
            $team['current_room'] = $currentRoom['id'];
        }
    }
    
    // Get questions in current room
    if ($currentRoom) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM questions WHERE room_id = ?");
        $stmt->execute([$currentRoom['id']]);
        $questionsInRoom = $stmt->fetch()['count'];
        
        // Get solved questions in this room
        $stmt = $db->prepare("
            SELECT COUNT(*) as solved 
            FROM solved_questions sq 
            JOIN questions q ON sq.question_id = q.id 
            WHERE sq.team_id = ? AND q.room_id = ?
        ");
        $stmt->execute([$team['id'], $currentRoom['id']]);
        $solvedCount = $stmt->fetch()['solved'];
        
        // Calculate progress
        $roomProgress = $questionsInRoom > 0 ? ($solvedCount / $questionsInRoom) * 100 : 0;
        
        // Get next unsolved question in this room
        $stmt = $db->prepare("
            SELECT * FROM questions 
            WHERE room_id = ? AND id NOT IN (
                SELECT question_id FROM solved_questions WHERE team_id = ?
            )
            ORDER BY order_num ASC LIMIT 1
        ");
        $stmt->execute([$currentRoom['id'], $team['id']]);
        $currentQuestion = $stmt->fetch();
        
        if ($currentQuestion) {
            $questionNumber = $currentQuestion['order_num'];
        } elseif ($questionsInRoom > 0) {
            // All questions solved in this room
            if ($team['current_room'] >= $totalRooms) {
                // This was the last room, game completed
                $escapeTime = time() - $team['start_time'];
                $stmt = $db->prepare("UPDATE teams SET escape_time = ? WHERE id = ?");
                $stmt->execute([$escapeTime, $team['id']]);
                $success = "Gefeliciteerd! Je hebt de sleutel gevonden en bent ontsnapt uit het laboratorium in " . formatTime($escapeTime) . "!";
                $gameOver = true;
            } else {
                // Move to next room
                $nextRoomId = $team['current_room'] + 1;
                $stmt = $db->prepare("UPDATE teams SET current_room = ? WHERE id = ?");
                $stmt->execute([$nextRoomId, $team['id']]);
                header("Location: play.php");
                exit();
            }
        }
    }
}

// Process answer submission
if (isset($_POST['answer']) && !$gameOver && isset($_POST['question_id'])) {
    $questionId = (int)$_POST['question_id'];
    $userAnswer = sanitizeInput($_POST['answer']);
    
    // Get the correct answer
    $stmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch();
    
    if ($question) {
        // Case-insensitive comparison
        if (strtolower($userAnswer) === strtolower($question['answer'])) {
            // Mark question as solved
            try {
                $stmt = $db->prepare("INSERT INTO solved_questions (team_id, question_id) VALUES (?, ?)");
                $stmt->execute([$team['id'], $questionId]);
            } catch (PDOException $e) {
                // Question already solved, ignore
            }
            
            // Get room of this question
            $roomId = $question['room_id'];
            
            // Check if all questions in this room are solved
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_questions, 
                (SELECT COUNT(*) FROM solved_questions sq JOIN questions q ON sq.question_id = q.id 
                 WHERE sq.team_id = ? AND q.room_id = ?) as solved_questions 
                FROM questions WHERE room_id = ?
            ");
            $stmt->execute([$team['id'], $roomId, $roomId]);
            $result = $stmt->fetch();
            
            if ($result['total_questions'] <= $result['solved_questions']) {
                // All questions in room solved
                
                if ($roomId >= $totalRooms) {
                    // This was the last room, game completed
                    $escapeTime = time() - $team['start_time'];
                    $stmt = $db->prepare("UPDATE teams SET escape_time = ? WHERE id = ?");
                    $stmt->execute([$escapeTime, $team['id']]);
                    $success = "Gefeliciteerd! Je hebt de sleutel gevonden en bent ontsnapt uit het laboratorium in " . formatTime($escapeTime) . "!";
                    $gameOver = true;
                } else {
                    // Move to next room
                    $nextRoomId = $roomId + 1;
                    $stmt = $db->prepare("UPDATE teams SET current_room = ? WHERE id = ?");
                    $stmt->execute([$nextRoomId, $team['id']]);
                    
                    // Get new room info
                    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
                    $stmt->execute([$nextRoomId]);
                    $nextRoom = $stmt->fetch();
                    
                    $success = "Je hebt alle puzzels in deze kamer opgelost! Je gaat door naar: " . $nextRoom['name'];
                    
                    // Redirect to refresh page with new room
                    header("Location: play.php");
                    exit();
                }
            } else {
                // Get next question in current room
                $stmt = $db->prepare("
                    SELECT * FROM questions 
                    WHERE room_id = ? AND id NOT IN (
                        SELECT question_id FROM solved_questions WHERE team_id = ?
                    )
                    ORDER BY order_num ASC LIMIT 1
                ");
                $stmt->execute([$roomId, $team['id']]);
                $nextQuestion = $stmt->fetch();
                
                $success = "Goed! Je hebt deze puzzel opgelost.";
                
                if ($nextQuestion) {
                    $currentQuestion = $nextQuestion;
                    $questionNumber = $currentQuestion['order_num'];
                } else {
                    // No more questions in this room, reload page to move to next room
                    header("Location: play.php");
                    exit();
                }
            }
        } else {
            $errors[] = "Onjuist antwoord. Probeer opnieuw!";
            $currentQuestion = $question;
            $questionNumber = $question['order_num'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spelen - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .room-container {
            background-size: cover;
            background-position: center;
            min-height: 500px;
            padding: 20px;
            border-radius: 5px;
            position: relative;
            color: white;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.8);
        }
        .room-info {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .question-container {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 20px;
            border-radius: 5px;
        }
        .progress-container {
            margin: 20px 0;
            background-color: rgba(255, 255, 255, 0.2);
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background-color: #4a7c59;
            transition: width 0.5s;
        }
        .room-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .hint {
            display: none;
            background-color: rgba(255, 255, 255, 0.9);
            color: #333;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            text-shadow: none;
        }
    </style>
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
        <div class="game-header">
            <div>
                <h2>Ontsnap Uit Het Laboratorium</h2>
                <p>Team: <?= htmlspecialchars($_SESSION['team_name']) ?></p>
            </div>
            <?php if (!$gameOver && $team['start_time']): ?>
                <div class="timer" id="gameTimer" data-start-time="<?= $team['start_time'] ?>" data-time-limit="<?= GAME_TIME_LIMIT ?>">
                    00:00
                </div>
                <form id="timeUpForm" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <input type="hidden" name="time_up" value="1">
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= $error ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="game-container">
            <?php if ($gameOver): ?>
                <div class="game-over">
                    <?php if ($team['escape_time']): ?>
                        <div style="background-image: url('https://images.unsplash.com/photo-1519834022362-6936338d5269?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'); background-size: cover; padding: 40px; border-radius: 5px; position: relative; text-align: center;">
                            <div style="background-color: rgba(0, 0, 0, 0.6); padding: 30px; border-radius: 10px; color: white;">
                                <h3>Gefeliciteerd! Je bent ontsnapt!</h3>
                                <p>Je team heeft de escape room voltooid in <?= formatTime($team['escape_time']) ?>!</p>
                                <img src="https://www.freepnglogos.com/uploads/key-png/key-icon-symbol-9.png" alt="Sleutel" style="width: 100px; margin: 15px 0;">
                                <p>Je hebt de sleutel gevonden en bent veilig ontsnapt uit het laboratorium!</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background-image: url('https://images.unsplash.com/photo-1584824486509-112e4181ff6b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'); background-size: cover; padding: 40px; border-radius: 5px; position: relative; text-align: center;">
                            <div style="background-color: rgba(0, 0, 0, 0.6); padding: 30px; border-radius: 10px; color: white;">
                                <h3>Game Over</h3>
                                <p>Je hebt het niet gered om op tijd uit het laboratorium te ontsnappen.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons" style="margin-top: 20px; text-align: center;">
                        <a href="../teams/leaderboard.php" class="btn btn-primary">Bekijk Scorebord</a>
                        <a href="../index.php" class="btn btn-secondary">Terug naar Home</a>
                    </div>
                </div>
            <?php elseif ($currentRoom && $currentQuestion): ?>
                <div class="room-container" style="background-image: url('<?= htmlspecialchars($currentRoom['background_image']) ?>');">
                    <div class="room-info">
                        <h3>Kamer <?= $currentRoom['order_num'] ?> van <?= $totalRooms ?>: <?= htmlspecialchars($currentRoom['name']) ?></h3>
                        <p><?= htmlspecialchars($currentRoom['description']) ?></p>
                        
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?= $roomProgress ?>%"></div>
                        </div>
                        <p>Voortgang in deze kamer: <?= number_format($roomProgress) ?>%</p>
                    </div>
                    
                    <div class="question-container">
                        <h4>Puzzel <?= $questionNumber ?>/<?= $questionsInRoom ?></h4>
                        
                        <div class="question">
                            <p><?= nl2br(htmlspecialchars($currentQuestion['question'])) ?></p>
                        </div>
                        
                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                            <input type="hidden" name="question_id" value="<?= $currentQuestion['id'] ?>">
                            
                            <div class="form-group">
                                <label for="answer">Jouw Antwoord:</label>
                                <input type="text" name="answer" id="answer" class="form-control" required autofocus>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Verzenden</button>
                                <?php if (!empty($currentQuestion['hint'])): ?>
                                    <button type="button" class="btn btn-secondary show-hint" data-hint="hint-<?= $currentQuestion['id'] ?>">Toon Hint</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if (!empty($currentQuestion['hint'])): ?>
                            <div class="hint" id="hint-<?= $currentQuestion['id'] ?>">
                                <strong>Hint:</strong> <?= nl2br(htmlspecialchars($currentQuestion['hint'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!$currentRoom): ?>
                <div class="alert alert-danger">
                    <p>Geen kamers beschikbaar. Neem contact op met een beheerder.</p>
                </div>
                <a href="../index.php" class="btn btn-primary">Terug naar Home</a>
            <?php elseif (!$currentQuestion): ?>
                <div class="alert alert-info">
                    <p>Er zijn geen puzzels beschikbaar in deze kamer. Probeer een andere kamer of neem contact op met een beheerder.</p>
                </div>
                <a href="../index.php" class="btn btn-primary">Terug naar Home</a>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> | Alle Rechten Voorbehouden</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Timer functionality is already in main.js
            
            // Show hint functionality
            const hintButtons = document.querySelectorAll('.show-hint');
            hintButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const hintId = this.getAttribute('data-hint');
                    const hintElement = document.getElementById(hintId);
                    
                    if (hintElement.style.display === 'none' || !hintElement.style.display) {
                        hintElement.style.display = 'block';
                        this.textContent = 'Verberg Hint';
                    } else {
                        hintElement.style.display = 'none';
                        this.textContent = 'Toon Hint';
                    }
                });
            });
        });
    </script>
</body>
</html>
