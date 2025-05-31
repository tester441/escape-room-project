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
$currentRoom = null;
$totalRooms = 0;
$roomProgress = 0;
$currentPuzzle = null;
$showPuzzle = false;

// Functie om de speldatabase te resetten - helpt bij het oplossen van structuur- en datafouten
function resetGameDatabase() {
    $db = connectDb();
    
    try {
        // Disable foreign key checks temporarily for clean table drops
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Maak tabellen opnieuw aan
        $db->exec("DROP TABLE IF EXISTS solved_puzzles");
        $db->exec("DROP TABLE IF EXISTS puzzles");
        $db->exec("DROP TABLE IF EXISTS rooms");
        
        // Maak rooms tabellen met verbeterde lab stijlen
        $db->exec("CREATE TABLE rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            room_style VARCHAR(20) NOT NULL,
            order_num INT NOT NULL DEFAULT 1
        )");
        
        // Maak puzzles tabellen
        $db->exec("CREATE TABLE puzzles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            emoji VARCHAR(10) NOT NULL,
            position_top INT NOT NULL,
            position_left INT NOT NULL,
            options TEXT NOT NULL,
            correct_answer VARCHAR(20) NOT NULL,
            max_attempts INT DEFAULT 2,
            order_num INT NOT NULL DEFAULT 1
        )");
        
        // Maak solved_puzzles tabellen
        $db->exec("CREATE TABLE solved_puzzles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            puzzle_id INT NOT NULL,
            attempts INT DEFAULT 1,
            solved BOOLEAN DEFAULT FALSE,
            solved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_solve (team_id, puzzle_id)
        )");
        
        // Voeg basisgegevens toe met verbeterde beschrijvingen
        // Kamers
        $db->query("INSERT INTO rooms (id, name, description, room_style, order_num) VALUES
            (1, 'Laboratorium', 'Een hightech laboratorium met diverse chemische stoffen en apparatuur. Ontdek de wetenschappelijke geheimen.', 'modern-lab', 1),
            (2, 'Controleruimte', 'De centrale controleruimte met computersystemen. De uitgang is vergrendeld met wetenschappelijke codes.', 'control-room', 2)
        ");
        
        // NIEUWE WETENSCHAPPELIJKE VRAGEN
        $db->query("INSERT INTO puzzles (room_id, title, description, emoji, position_top, position_left, options, correct_answer, max_attempts, order_num) VALUES
            (1, 'Chemische Test', 'Je ziet een reeks gekleurde vloeistoffen. Welke vloeistof geeft een groene kleur aan een vlam?', '🧪', 40, 20, '{\"A\":\"Rood (Lithiumchloride)\",\"B\":\"Groen (Koperchloride)\",\"C\":\"Paars (Kaliumchloride)\",\"D\":\"Geel (Natriumchloride)\"}', 'B', 2, 1),
            
            (1, 'Materiaalonderzoek', 'Op het computerscherm zie je een analyse van metalen. Welke vloeistof kan aluminium verzwakken en smelten bij kamertemperatuur?', '💻', 35, 65, '{\"A\":\"Water\",\"B\":\"Alcohol\",\"C\":\"Gallium\",\"D\":\"Azijnzuur\"}', 'C', 2, 2),
            
            (2, 'Chemische Reactie', 'Op een post-it bij de kluis staat: \"Element dat heftig reageert met water\". Welk element is dit?', '🔒', 60, 25, '{\"A\":\"Natrium (Na)\",\"B\":\"Zuurstof (O)\",\"C\":\"Helium (He)\",\"D\":\"IJzer (Fe)\"}', 'A', 2, 1),
            
            (2, 'Labwaarden', 'Het controlepaneel vraagt om de exacte temperatuur waarop water kookt op zeeniveau.', '🎛️', 30, 70, '{\"A\":\"0°C\",\"B\":\"100°C\",\"C\":\"50°C\",\"D\":\"200°C\"}', 'B', 2, 2)
        ");
        
        // Reset team voortgang
        $db->query("UPDATE teams SET start_time = NULL, current_room = NULL, escape_time = NULL");
        
        // Re-enable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        // Controleer of de 'hint' kolom bestaat, zo niet, voeg deze toe
        try {
            $db->query("SELECT hint FROM puzzles LIMIT 1");
        } catch (PDOException $e) {
            // Hint kolom bestaat nog niet, voeg toe
            $db->exec("ALTER TABLE puzzles ADD COLUMN hint TEXT DEFAULT NULL");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database reset error: " . $e->getMessage());
        return false;
    }
}

// IMPORTANT: Run database reset only once, then redirect to avoid running it multiple times
if (isset($_GET['reset_db']) || !isset($_SESSION['db_reset_done'])) {
    resetGameDatabase();
    $_SESSION['db_reset_done'] = true;
    header("Location: play.php");
    exit();
}

// Verify tables exist before continuing
try {
    $db->query("SELECT 1 FROM rooms LIMIT 1");
    $db->query("SELECT 1 FROM puzzles LIMIT 1");
    $db->query("SELECT 1 FROM solved_puzzles LIMIT 1");
} catch (PDOException $e) {
    // If tables don't exist, force a reset
    resetGameDatabase();
    header("Location: play.php");
    exit();
}

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

// IMPORTANT FIX: First check game status right after getting the team
if ($team && $team['escape_time'] !== null) {
    $gameOver = true;
    if ($team['escape_time'] > 0) {
        $success = "Gefeliciteerd! Je hebt de escape room voltooid in " . formatTime($team['escape_time']) . "!";
    } else {
        $errors[] = "Game over! Je hebt te veel fouten gemaakt bij een van de puzzels.";
    }
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
    if ($team['escape_time'] > 0) {
        // Only show completion message for successful completion (escape_time > 0)
        $success = "Je hebt deze escape room al voltooid in " . formatTime($team['escape_time']) . "!";
    } else {
        // For failed games (escape_time = 0), show a failure message
        $errors[] = "Je hebt deze escape room al geprobeerd, maar niet kunnen voltooien.";
    }
}

// Start the game if not started already
if (($team['start_time'] === null || $team['current_room'] === null) && !$gameOver && !isset($_POST['answer'])) {
    $stmt = $db->prepare("UPDATE teams SET start_time = ?, current_room = 1 WHERE id = ?");
    $stmt->execute([time(), $team['id']]);
    $team['start_time'] = time();
    $team['current_room'] = 1;
}

// Add a handler for reset game action
if (isset($_POST['reset_game'])) {
    // Reset the team's progress
    $stmt = $db->prepare("UPDATE teams SET start_time = NULL, current_room = 1, escape_time = NULL WHERE id = ?");
    $stmt->execute([$team['id']]);
    
    // Delete all solved puzzles for this team
    $stmt = $db->prepare("DELETE FROM solved_puzzles WHERE team_id = ?");
    $stmt->execute([$team['id']]);
    
    // Redirect to start fresh
    header("Location: play.php");
    exit();
}

// FIXED NEXT ROOM HANDLER - This was causing the room navigation issue
if (isset($_POST['next_room']) && !$gameOver) {
    $nextRoomId = $team['current_room'] + 1;
    
    if ($nextRoomId <= $totalRooms) {
        $stmt = $db->prepare("UPDATE teams SET current_room = ? WHERE id = ?");
        $stmt->execute([$nextRoomId, $team['id']]);
        
        // Redirect to refresh the page with new room - This ensures the room updates
        header("Location: play.php");
        exit();
    }
}

// Handle previous room navigation
if (isset($_POST['prev_room']) && !$gameOver) {
    $prevRoomId = $team['current_room'] - 1;
    
    if ($prevRoomId >= 1) {
        $stmt = $db->prepare("UPDATE teams SET current_room = ? WHERE id = ?");
        $stmt->execute([$prevRoomId, $team['id']]);
        $team['current_room'] = $prevRoomId;
        
        // Redirect to refresh the page with new room
        header("Location: play.php");
        exit();
    }
}

// Handle puzzle selection - Check if game is over first
if (isset($_GET['puzzle_id']) && !$gameOver) {
    $puzzleId = (int)$_GET['puzzle_id'];
    $stmt = $db->prepare("SELECT * FROM puzzles WHERE id = ? AND room_id = ?");
    $stmt->execute([$puzzleId, $team['current_room']]);
    $currentPuzzle = $stmt->fetch();
    
    // Check if already solved
    $stmt = $db->prepare("SELECT * FROM solved_puzzles WHERE team_id = ? AND puzzle_id = ?");
    $stmt->execute([$team['id'], $puzzleId]);
    $puzzleStatus = $stmt->fetch();
    
    if ($puzzleStatus && $puzzleStatus['solved']) {
        $errors[] = "Je hebt deze puzzel al opgelost!";
    } else if ($gameOver) {
        // Redirect to main game page if game is over
        header("Location: play.php");
        exit();
    } else {
        $showPuzzle = true;
        $puzzleOptions = json_decode($currentPuzzle['options'], true);
    }
}

// Handle puzzle answer - Fix the completion logic
if (isset($_POST['submit_answer']) && isset($_POST['puzzle_id']) && !$gameOver) {
    $puzzleId = (int)$_POST['puzzle_id'];
    $selectedAnswer = $_POST['selected_answer'];
    
    // Get puzzle details
    $stmt = $db->prepare("SELECT * FROM puzzles WHERE id = ?");
    $stmt->execute([$puzzleId]);
    $puzzle = $stmt->fetch();
    
    if ($puzzle) {
        // Check current status
        $stmt = $db->prepare("SELECT * FROM solved_puzzles WHERE team_id = ? AND puzzle_id = ?");
        $stmt->execute([$team['id'], $puzzleId]);
        $puzzleStatus = $stmt->fetch();
        
        if ($puzzleStatus) {
            // Update existing record
            if ($puzzleStatus['solved']) {
                $errors[] = "Je hebt deze puzzel al opgelost!";
                $showPuzzle = false;
            } else {
                $attempts = $puzzleStatus['attempts'] + 1;
                $solved = ($selectedAnswer == $puzzle['correct_answer']);
                
                // Check if max attempts reached
                if (!$solved && $attempts >= $puzzle['max_attempts']) {
                    $errors[] = "Je hebt alle pogingen verbruikt! De puzzel is mislukt.";
                    // Mark as game over with escape_time=0 (failed)
                    $db->prepare("UPDATE teams SET escape_time = 0 WHERE id = ?")->execute([$team['id']]);
                    $errors[] = "Game over! Je hebt te veel fouten gemaakt bij een van de puzzels.";
                    $gameOver = true;
                    $showPuzzle = false;
                    // Redirect to main page to show game over screen
                    echo "<script>setTimeout(function(){ window.location.href='play.php'; }, 1500);</script>";
                } else if ($solved) {
                    $success = "Correct! Je hebt de puzzel opgelost.";
                    $stmt = $db->prepare("UPDATE solved_puzzles SET attempts = ?, solved = 1, solved_at = NOW() WHERE team_id = ? AND puzzle_id = ?");
                    $stmt->execute([$attempts, $team['id'], $puzzleId]);
                    $showPuzzle = false;
                } else {
                    $errors[] = "Onjuist antwoord! Je hebt nog " . ($puzzle['max_attempts'] - $attempts) . " poging(en) over.";
                    $stmt = $db->prepare("UPDATE solved_puzzles SET attempts = ? WHERE team_id = ? AND puzzle_id = ?");
                    $stmt->execute([$attempts, $team['id'], $puzzleId]);
                    $showPuzzle = true;
                    $currentPuzzle = $puzzle;
                    $puzzleOptions = json_decode($puzzle['options'], true);
                }
            }
        } else {
            // Create new record
            $solved = ($selectedAnswer == $puzzle['correct_answer']);
            if ($solved) {
                $success = "Correct! Je hebt de puzzel opgelost.";
                $stmt = $db->prepare("INSERT INTO solved_puzzles (team_id, puzzle_id, attempts, solved) VALUES (?, ?, 1, 1)");
                $stmt->execute([$team['id'], $puzzleId]);
                $showPuzzle = false;
            } else {
                $errors[] = "Onjuist antwoord! Je hebt nog " . ($puzzle['max_attempts'] - 1) . " poging(en) over.";
                $stmt = $db->prepare("INSERT INTO solved_puzzles (team_id, puzzle_id, attempts, solved) VALUES (?, ?, 1, 0)");
                $stmt->execute([$team['id'], $puzzleId]);
                $showPuzzle = true;
                $currentPuzzle = $puzzle;
                $puzzleOptions = json_decode($puzzle['options'], true);
            }
        }
        
        // Check if all puzzles in the room are solved - Fix completion logic
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total_puzzles,
            (SELECT COUNT(*) FROM solved_puzzles 
             WHERE team_id = ? AND solved = 1 AND puzzle_id IN 
             (SELECT id FROM puzzles WHERE room_id = ?)) AS solved_puzzles
            FROM puzzles WHERE room_id = ?
        ");
        $stmt->execute([$team['id'], $team['current_room'], $team['current_room']]);
        $result = $stmt->fetch();
        
        // FIXED COMPLETION LOGIC - Fix the check for puzzle completion
        if ($result['total_puzzles'] > 0 && $result['total_puzzles'] <= $result['solved_puzzles']) {
            // All puzzles in this room solved
            if ($team['current_room'] >= $totalRooms) {
                // This was the last room, game completed
                $escapeTime = time() - $team['start_time'];
                $stmt = $db->prepare("UPDATE teams SET escape_time = ? WHERE id = ?");
                $stmt->execute([$escapeTime, $team['id']]);
                $success = "Gefeliciteerd! Je hebt alle puzzels opgelost en bent ontsnapt in " . formatTime($escapeTime) . "!";
                $gameOver = true;
                // Redirect to show success screen
                header("Location: play.php");
                exit();
            } else {
                // Move to next room
                $nextRoomId = $team['current_room'] + 1;
                $stmt = $db->prepare("UPDATE teams SET current_room = ? WHERE id = ?");
                $stmt->execute([$nextRoomId, $team['id']]);
                $success = "Je hebt alle puzzels in deze kamer opgelost! Je gaat naar de volgende kamer.";
                // Redirect to refresh the page with new room
                header("Location: play.php");
                exit();
            }
        }
    }
}

// Check if game is over from session (for page reloads after game over)
if (isset($_SESSION['game_over']) && $_SESSION['game_over'] === true) {
    $gameOver = true;
    // Keep this flag only for one page load
    unset($_SESSION['game_over']);
}

// Get current room
if (!$gameOver && isset($team['current_room'])) {
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$team['current_room']]);
    $currentRoom = $stmt->fetch();
    
    if ($currentRoom) {
        try {
            // Get puzzles in current room
            $stmt = $db->prepare("
                SELECT p.*, 
                       (SELECT solved FROM solved_puzzles WHERE team_id = ? AND puzzle_id = p.id) AS is_solved
                FROM puzzles p 
                WHERE p.room_id = ?
                ORDER BY p.order_num ASC
            ");
            $stmt->execute([$team['id'], $currentRoom['id']]);
            $puzzles = $stmt->fetchAll();
            
            // Calculate room progress
            $stmt = $db->prepare("
                SELECT COUNT(*) AS total_puzzles,
                      (SELECT COUNT(*) FROM solved_puzzles 
                       WHERE team_id = ? AND solved = 1 AND puzzle_id IN 
                       (SELECT id FROM puzzles WHERE room_id = ?)) AS solved_puzzles
                FROM puzzles WHERE room_id = ?
            ");
            $stmt->execute([$team['id'], $currentRoom['id'], $currentRoom['id']]);
            $progressData = $stmt->fetch();
            
            if ($progressData && $progressData['total_puzzles'] > 0) {
                $roomProgress = ($progressData['solved_puzzles'] / $progressData['total_puzzles']) * 100;
            }
        } catch (PDOException $e) {
            // Handle database errors gracefully
            $errors[] = "Er is een probleem met de database. Probeer de pagina te vernieuwen.";
            error_log("Database error: " . $e->getMessage());
        }
    }
}

// REMOVE ALL REDUNDANT GAME STATUS CHECKS
// Remove any code blocks here that check $team['escape_time']
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escape De Laboratorium</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: #000;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Algemene stijlen */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header stijl */
        header {
            background-color: #000;
            padding: 10px 0;
        }
        
        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            margin: 0;
            font-size: 24px;
            color: #fff;
        }
        
        header nav ul {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        header nav ul li {
            margin-left: 20px;
        }
        
        header nav ul li a {
            color: #fff;
            text-decoration: none;
        }

        /* Game Container */
        .game-container {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            padding: 5px;
            margin-top: 20px;
        }
        
        /* Verbeterde Room Container met getekende/gestileerde achtergronden */
        .room-container {
            min-height: 500px;
            padding: 20px;
            border-radius: 0;
            position: relative;
            color: white;
            overflow: hidden;
            border: 1px solid #333;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        /* Vervang de achtergrondafbeeldingen met lokale afbeeldingen in de assets map */
        .modern-lab-room {
            background-image: url('../assets/images/backgrounds/laboratory-background.png');
            background-color: #162035; /* Fallback kleur */
            position: relative;
            background-size: cover;
            background-position: center;
        }
        
        .control-room-room {
            background-image: url('../assets/images/backgrounds/laboratory-background.png');
            background-color: #1a2336; /* Fallback kleur */
            position: relative;
            background-size: cover;
            background-position: center;
        }
        
        /* Donkere overlay aanpassen voor deze lokale afbeelding */
        .room-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 20, 0.6); /* Aangepaste overlay voor betere leesbaarheid */
            z-index: 1;
        }
        
        /* Zorg dat alle kinderen boven de overlay komen */
        .room-container > * {
            position: relative;
            z-index: 2; /* Hoger dan de overlay */
        }
        
        /* Maak de puzzelknoppen nog duidelijker zichtbaar */
        .puzzle-object {
            position: absolute;
            width: 90px; /* Iets groter */
            height: 90px; /* Iets groter */
            cursor: pointer;
            transition: all 0.3s;
            background-color: rgba(0, 0, 0, 0.7); /* Bijna zwarte achtergrond */
            border: 4px solid rgba(255, 255, 255, 0.9); /* Witte rand */
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 40px;
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.5), 0 0 15px rgba(0, 0, 0, 0.8); /* Fel wit glow effect */
            z-index: 10;
        }
        
        .puzzle-object:hover {
            transform: scale(1.15);
            box-shadow: 0 0 40px rgba(255, 255, 255, 0.8), 0 0 20px rgba(0, 0, 0, 0.8);
        }
        
        .puzzle-object.solved {
            background-color: rgba(0, 0, 0, 0.7);
            border-color: rgba(46, 204, 113, 0.9); /* Heldere groene rand voor opgeloste puzzels */
            box-shadow: 0 0 30px rgba(46, 204, 113, 0.7), 0 0 15px rgba(0, 0, 0, 0.8);
        }
        
        /* Maak de info-box duidelijker zichtbaar */
        .room-info {
            background-color: rgba(0, 0, 0, 0.85);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #555;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.8);
        }
        
        /* Game Header - update to match the screenshot */
        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .game-header h2 {
            margin: 0;
            color: #fff;
        }
        
        /* Timer - update to match the screenshot */
        .timer {
            font-size: 24px;
            font-weight: bold;
            background-color: #0a0a0a;
            padding: 10px 15px;
            border-radius: 4px;
            color: #fff;
            border: 1px solid #333;
        }
        
        /* Progress - update to match the screenshot */
        .progress-container {
            margin: 10px 0; /* Kleinere marges */
            background-color: rgba(0, 0, 0, 0.3);
            height: 6px; /* Kleinere hoogte (was 10px) */
            border-radius: 0;
            overflow: hidden;
            border: 1px solid #444;
            max-width: 75%; /* Beperkt de breedte */
        }
        
        .progress-bar {
            height: 100%;
            background-color: #3498db;
            transition: width 0.5s;
        }
        
        /* Puzzle Objects - improve visibility */
        .puzzle-object {
            position: absolute;
            width: 80px;
            height: 80px;
            cursor: pointer;
            transition: all 0.3s;
            background-color: rgba(20, 20, 20, 0.8); /* Donkerdere achtergrond */
            border: 3px solid rgba(52, 152, 219, 0.8); /* Helderdere rand */
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 35px;
            box-shadow: 0 0 20px rgba(52, 152, 219, 0.7), 0 0 10px rgba(0, 0, 0, 0.5); /* Glow effect en schaduw */
            z-index: 10; /* Zorg dat ze boven alles uitkomen */
        }
        
        .puzzle-object:hover {
            transform: scale(1.1);
            background-color: rgba(25, 25, 25, 0.9);
            box-shadow: 0 0 30px rgba(52, 152, 219, 0.9), 0 0 15px rgba(0, 0, 0, 0.7);
        }
        
        .puzzle-object.solved {
            background-color: rgba(20, 20, 20, 0.8);
            border-color: rgba(46, 204, 113, 0.8); /* Groene rand voor opgeloste puzzels */
            box-shadow: 0 0 20px rgba(46, 204, 113, 0.7), 0 0 10px rgba(0, 0, 0, 0.5);
        }
        
        /* PUZZLE MODAL - EXACT ZOALS OP DE SCREENSHOTS */
        .puzzle-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 100;
        }
        
        .puzzle-content {
            background-color: #fff;
            border-radius: 4px;
            width: 500px;
            max-width: 90%;
            padding: 20px;
            color: #333;
            position: relative;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .close-puzzle {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #777;
            line-height: 20px;
        }
        
        .puzzle-content h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            color: #333;
            padding-right: 20px;
        }
        
        .puzzle-content p {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        
        .puzzle-image {
            text-align: center;
            margin: 30px 0;
        }
        
        .puzzle-image img {
            height: 80px;
            width: auto;
        }
        
        /* Antwoord opties exact zoals in de screenshots */
        .answer-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        
        .answer-option {
            background-color: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            color: #333;
            font-weight: normal;
        }
        
        .answer-option input {
            display: none;
        }
        
        .answer-option.selected {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
        
        /* Submit knop exact zoals op de screenshots */
        button[name="submit_answer"] {
            background-color: #2196f3;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            display: block;
            margin: 20px auto 0;
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .alert-danger {
            background-color: #f44336;
            color: white;
        }
        
        .alert-success {
            background-color: #4caf50;
            color: white;
        }
        
        /* Game over screens */
        .success-screen {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            min-height: 400px;
            padding: 40px;
            border-radius: 5px;
            position: relative;
            text-align: center;
        }
        
        .failure-screen {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            min-height: 400px;
            padding: 40px;
            border-radius: 5px;
            position: relative;
            text-align: center;
        }
        
        .screen-content {
            background-color: rgba(0, 0, 0, 0.6);
            padding: 30px;
            border-radius: 10px;
            color: white;
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Sterren rating stijl */
        .rating {
            margin-top: 20px;
            padding: 15px;
            background-color: rgba(0, 0, 0, 0.4);
            border-radius: 10px;
            display: inline-block;
        }

        .stars {
            font-size: 32px;
            letter-spacing: 5px;
            margin: 10px 0;
        }

        .star {
            color: #aaa;
            transition: all 0.3s ease;
        }

        .star.filled {
            color: #ffdd00;
            text-shadow: 0 0 5px rgba(255, 221, 0, 0.7);
        }

        .stats-container {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .stat-box {
            background-color: rgba(0, 0, 0, 0.4);
            padding: 15px;
            border-radius: 10px;
            min-width: 130px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }

        .action-buttons {
            margin-top: 25px;
        }

        .action-buttons .btn {
            font-size: 18px;
            padding: 12px 24px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        /* Footer */
        footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        .next-room-container {
            text-align: right;
            margin-top: 15px;
        }
        
        .btn-next-room {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .btn-next-room:hover {
            background-color: #45a049;
            transform: translateX(5px);
        }
        
        .btn-next-room .arrow {
            margin-left: 10px;
            font-size: 20px;
        }
        
        /* Verbeterde stijl voor next-room arrow - meer zichtbaarheid */
        .next-room-arrow {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(76, 175, 80, 0.9);
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            animation: pulse 1.5s infinite;
            box-shadow: 0 0 25px rgba(76, 175, 80, 0.7);
            z-index: 50;
            border: 3px solid white;
            transition: all 0.3s ease;
        }
        
        .next-room-arrow:hover {
            transform: translateY(-50%) scale(1.1);
            background-color: rgba(76, 175, 80, 1);
        }
        
        .next-room-arrow .arrow-icon {
            font-size: 40px;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.8);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }
        
        .hint-container {
            margin: 15px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            border: 1px dashed #ccc;
        }
        
        .hint-button {
            background-color: #ff9800;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .hint-button:hover {
            background-color: #f57c00;
        }
        
        .hint-text {
            margin-top: 10px;
            padding: 10px;
            background-color: #fff3e0;
            border-radius: 4px;
            border-left: 3px solid #ff9800;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Escape De Laboratorium</h1>
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
            <?php if (!$gameOver && isset($team['start_time'])): ?>
                <div class="timer" id="gameTimer" data-start-time="<?= $team['start_time'] ?>" data-time-limit="<?= GAME_TIME_LIMIT ?>">
                    10:00
                </div>
                <form id="timeUpForm" method="post">
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
                    <?php if ($team['escape_time'] !== null && $team['escape_time'] > 0): ?>
                        <div class="success-screen">
                            <div class="screen-content">
                                <h3>Gefeliciteerd! Je bent ontsnapt!</h3>
                                <p>Je team heeft de escape room voltooid in 
                                    <?php 
                                    // Correcte weergave van de escape tijd als uren, minuten en seconden
                                    $seconds = $team['escape_time'];
                                    $hours = floor($seconds / 3600);
                                    $minutes = floor(($seconds % 3600) / 60);
                                    $secs = $seconds % 60;
                                    
                                    if ($hours > 0) {
                                        echo "$hours uur, $minutes minuten en $secs seconden";
                                    } else if ($minutes > 0) {
                                        echo "$minutes minuten en $secs seconden";
                                    } else {
                                        echo "$secs seconden";
                                    }
                                    ?>!
                                </p>
                                <div style="font-size: 100px; text-align: center;">🏆</div>
                                <p>Je hebt de sleutel gevonden en bent veilig ontsnapt uit het laboratorium!</p>
                                
                                <?php
                                // Bereken sterren-rating gebaseerd op de ontsnappingstijd
                                $timeLimit = GAME_TIME_LIMIT;
                                $escapeTime = $team['escape_time'];
                                $percentage = min(100, max(0, ($timeLimit - $escapeTime) / $timeLimit * 100));
                                $stars = 0;
                                
                                if ($percentage >= 80) $stars = 5;
                                else if ($percentage >= 60) $stars = 4;
                                else if ($percentage >= 40) $stars = 3;
                                else if ($percentage >= 20) $stars = 2;
                                else $stars = 1;
                                
                                // Haal de opgeloste puzzels op
                                $stmt = $db->prepare("SELECT COUNT(*) as count FROM solved_puzzles WHERE team_id = ? AND solved = 1");
                                $stmt->execute([$team['id']]);
                                $solvedCount = $stmt->fetch()['count'];
                                
                                // Haal alle puzzels op
                                $stmt = $db->query("SELECT COUNT(*) as count FROM puzzles");
                                $totalPuzzles = $stmt->fetch()['count'];
                                
                                // Bereken de gemiddelde tijd per puzzel
                                $avgTimePerPuzzle = $solvedCount > 0 ? round($escapeTime / $solvedCount) : 0;
                                ?>
                                
                                <div class="rating">
                                    <p>Jouw score:</p>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?= ($i <= $stars) ? 'filled' : '' ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <p><?= $stars ?> van de 5 sterren!</p>
                                </div>
                                
                                <div class="stats-container">
                                    <div class="stat-box">
                                        <div>Totale tijd</div>
                                        <div class="stat-value"><?= formatTime($escapeTime) ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div>Opgeloste puzzels</div>
                                        <div class="stat-value"><?= $solvedCount ?>/<?= $totalPuzzles ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div>Gemiddelde tijd per puzzel</div>
                                        <div class="stat-value"><?= formatTime($avgTimePerPuzzle) ?></div>
                                    </div>
                                </div>
                                
                                <p style="margin-top: 20px;">
                                    <?php if ($stars >= 4): ?>
                                        Uitstekend werk! Jullie zijn echte escape room experts!
                                    <?php elseif ($stars >= 3): ?>
                                        Goed gedaan! Jullie hebben het goed opgelost!
                                    <?php else: ?>
                                        Gelukt! Volgende keer kunnen jullie vast nog sneller!
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="failure-screen">
                            <div class="screen-content">
                                <h3>Game Over</h3>
                                
                                <?php if (isset($_POST['time_up'])): ?>
                                    <div style="font-size: 100px; text-align: center;">⏱️</div>
                                    <p>De tijd is op! Je hebt het niet gered om op tijd uit het laboratorium te ontsnappen.</p>
                                    
                                    <?php
                                    // Bereken voortgang bij tijdsoverschrijding
                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM solved_puzzles WHERE team_id = ? AND solved = 1");
                                    $stmt->execute([$team['id']]);
                                    $solvedCount = $stmt->fetch()['count'];
                                    
                                    $stmt = $db->query("SELECT COUNT(*) as count FROM puzzles");
                                    $totalPuzzles = $stmt->fetch()['count'];
                                    
                                    $progressPercentage = round(($solvedCount / $totalPuzzles) * 100);
                                    ?>
                                    
                                    <div class="stats-container">
                                        <div class="stat-box">
                                            <div>Voortgang</div>
                                            <div class="stat-value"><?= $progressPercentage ?>%</div>
                                        </div>
                                        <div class="stat-box">
                                            <div>Opgeloste puzzels</div>
                                            <div class="stat-value"><?= $solvedCount ?>/<?= $totalPuzzles ?></div>
                                        </div>
                                    </div>
                                    
                                    <p style="margin-top: 20px;">Tip: Verdeel taken en werk samen om tijd te besparen!</p>
                                <?php else: ?>
                                    <div style="font-size: 100px; text-align: center;">❌</div>
                                    <p>Je hebt te veel fouten gemaakt bij één van de puzzels.</p>
                                    
                                    <?php
                                    // Bereken voortgang bij fouten
                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM solved_puzzles WHERE team_id = ? AND solved = 1");
                                    $stmt->execute([$team['id']]);
                                    $solvedCount = $stmt->fetch()['count'];
                                    
                                    $stmt = $db->query("SELECT COUNT(*) as count FROM puzzles");
                                    $stmt->execute();
                                    $totalPuzzles = $stmt->fetch()['count'];
                                    
                                    $progressPercentage = round(($solvedCount / $totalPuzzles) * 100);
                                    
                                    // Haal mislukte puzzels op om tips te geven
                                    $stmt = $db->prepare("
                                        SELECT p.title, p.room_id 
                                        FROM solved_puzzles sp 
                                        JOIN puzzles p ON sp.puzzle_id = p.id
                                        WHERE sp.team_id = ? AND sp.solved = 0 AND sp.attempts >= p.max_attempts
                                        LIMIT 1
                                    ");
                                    $stmt->execute([$team['id']]);
                                    $failedPuzzle = $stmt->fetch();
                                    ?>
                                    
                                    <div class="stats-container">
                                        <div class="stat-box">
                                            <div>Voortgang</div>
                                            <div class="stat-value"><?= $progressPercentage ?>%</div>
                                        </div>
                                        <div class="stat-box">
                                            <div>Opgeloste puzzels</div>
                                            <div class="stat-value"><?= $solvedCount ?>/<?= $totalPuzzles ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($failedPuzzle): ?>
                                        <p style="margin-top: 20px;">Je had moeite met: <?= htmlspecialchars($failedPuzzle['title']) ?></p>
                                    <?php endif; ?>
                                    
                                    <p>Tip: Lees de puzzels goed en denk logisch na over de wetenschappelijke principes!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons" style="margin-top: 20px; text-align: center;">
                        <form method="post" style="display: inline-block; margin-right: 10px;">
                            <input type="hidden" name="reset_game" value="1">
                            <button type="submit" class="btn btn-primary">Opnieuw Spelen</button>
                        </form>
                        <a href="../teams/leaderboard.php" class="btn btn-secondary">Bekijk Scorebord</a>
                        <a href="../index.php" class="btn btn-secondary">Terug naar Home</a>
                    </div>
                </div>
            <?php elseif ($currentRoom): ?>
                <div class="room-container <?= isset($currentRoom['room_style']) ? $currentRoom['room_style'] : 'lab' ?>-room">
                    <div class="room-info">
                        <h3>Kamer <?= $currentRoom['order_num'] ?> van <?= $totalRooms ?>: <?= htmlspecialchars($currentRoom['name']) ?></h3>
                        <p><?= htmlspecialchars($currentRoom['description']) ?></p>
                        
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?= $roomProgress ?>%"></div>
                        </div>
                        <p style="font-size: 14px; margin-top: 5px;">Voortgang in deze kamer: <?= number_format($roomProgress) ?>%</p>
                        
                        <div class="next-room-container" style="display: flex; justify-content: space-between; margin-top: 15px;">
                            <?php if ($currentRoom['order_num'] > 1): ?>
                                <form method="post">
                                    <input type="hidden" name="prev_room" value="1">
                                    <button type="submit" class="btn btn-prev-room" style="background-color: #607D8B; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; display: inline-flex; align-items: center;">
                                        <span class="arrow" style="margin-right: 10px;">←</span>
                                        <span>Naar vorige kamer</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($currentRoom['order_num'] < $totalRooms): ?>
                                <form method="post" style="<?= $currentRoom['order_num'] > 1 ? 'margin-left: auto;' : '' ?>">
                                    <button type="submit" name="next_room" class="btn btn-next-room">
                                        <span>Naar volgende kamer</span>
                                        <span class="arrow">→</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($puzzles)): ?>
                        <?php foreach ($puzzles as $puzzle): ?>
                            <div class="puzzle-object <?= isset($puzzle['is_solved']) && $puzzle['is_solved'] ? 'solved' : '' ?>" 
                                style="top: <?= $puzzle['position_top'] ?>%; left: <?= $puzzle['position_left'] ?>%; position: absolute; width: 80px; height: 80px; cursor: pointer; z-index: 10; display: flex; justify-content: center; align-items: center; font-size: 40px;"
                                onclick="window.location.href='play.php?puzzle_id=<?= $puzzle['id'] ?>';">
                                <?= isset($puzzle['emoji']) ? $puzzle['emoji'] : '❓' ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <p>Er zijn geen kamers beschikbaar. Klik op de knop hieronder om het spel te resetten.</p>
                    <form method="post" style="margin-top: 15px;">
                        <button type="submit" name="reset_game" value="1" class="btn btn-primary">Reset Game</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($showPuzzle && $currentPuzzle): ?>
            <div class="puzzle-modal">
                <div class="puzzle-content">
                    <h3><?= htmlspecialchars($currentPuzzle['title']) ?></h3>
                    <span class="close-puzzle" onclick="window.location.href='play.php'">×</span>
                    
                    <div class="puzzle-image">
                        <?php if ($currentPuzzle['title'] === 'Microscoop'): ?>
                            <img src="data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M50,10 L50,20 L60,30 L60,65 L50,80 Q50,82 45,82 Q40,82 40,80 L30,65 L30,30 L40,20 L40,10 Z" fill="#ccc" stroke="#999"/><circle cx="50" cy="20" r="10" fill="#eee" stroke="#999"/><path d="M40,80 L60,80 L55,90 L45,90 Z" fill="#aaa" stroke="#999"/></svg>') ?>" alt="Microscoop">
                        <?php elseif ($currentPuzzle['title'] === 'Computer'): ?>
                            <img src="data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect x="15" y="15" width="70" height="50" rx="2" fill="#ccc" stroke="#999"/><rect x="20" y="20" width="60" height="40" fill="#29b6f6"/><rect x="35" y="65" width="30" height="5" fill="#aaa"/><rect x="30" y="70" width="40" height="15" fill="#999"/></svg>') ?>" alt="Computer">
                        <?php elseif ($currentPuzzle['title'] === 'Kluis'): ?>
                            <img src="data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect x="20" y="20" width="60" height="60" rx="2" fill="#999" stroke="#777"/><circle cx="50" cy="50" r="20" fill="#777" stroke="#666"/><circle cx="50" cy="50" r="15" fill="#666"/><circle cx="50" cy="50" r="2" fill="#ccc"/><rect x="70" y="48" width="10" height="4" fill="#777"/></svg>') ?>" alt="Kluis">
                        <?php else: ?>
                            <img src="data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect x="20" y="30" width="60" height="40" rx="3" fill="#999"/><circle cx="35" cy="40" r="5" fill="#f44336"/><circle cx="35" cy="60" r="5" fill="#4caf50"/><circle cx="65" cy="40" r="5" fill="#2196f3"/><circle cx="65" cy="60" r="5" fill="#ffeb3b"/><rect x="30" y="70" width="40" height="5" fill="#777"/></svg>') ?>" alt="Controlepaneel">
                        <?php endif; ?>
                    </div>
                    
                    <p><?= htmlspecialchars($currentPuzzle['description']) ?></p>
                    
                    <?php if (!empty($currentPuzzle['hint'])): ?>
                    <div class="hint-container">
                        <button type="button" class="hint-button" onclick="toggleHint()">Toon Hint</button>
                        <div id="hint-text" class="hint-text" style="display: none;">
                            <p><strong>Hint:</strong> <?= htmlspecialchars($currentPuzzle['hint']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <input type="hidden" name="puzzle_id" value="<?= $currentPuzzle['id'] ?>">
                        
                        <div class="answer-options">
                            <?php foreach ($puzzleOptions as $key => $option): ?>
                                <label class="answer-option">
                                    <input type="radio" name="selected_answer" value="<?= $key ?>" required>
                                    <?= $key ?>: <?= htmlspecialchars($option) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" name="submit_answer">Antwoord indienen</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> Escape De Laboratorium | Alle Rechten Voorbehouden</p>
        </div>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Timer functionality
            const timerElement = document.getElementById('gameTimer');
            if (timerElement) {
                const startTime = parseInt(timerElement.dataset.startTime);
                const timeLimit = parseInt(timerElement.dataset.timeLimit);
                const timeUpForm = document.getElementById('timeUpForm');
                
                function updateTimer() {
                    const currentTime = Math.floor(Date.now() / 1000);
                    const elapsedTime = currentTime - startTime;
                    const remainingTime = timeLimit - elapsedTime;
                    
                    if (remainingTime <= 0) {
                        timerElement.textContent = "00:00";
                        timeUpForm.submit();
                    } else {
                        const minutes = Math.floor(remainingTime / 60);
                        const seconds = remainingTime % 60;
                        timerElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    }
                }
                
                // Update every second
                updateTimer();
                setInterval(updateTimer, 1000);
            }
            
            // Verbeterde optie selectie - zoals in screenshot
            const answerOptions = document.querySelectorAll('.answer-option');
            answerOptions.forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    answerOptions.forEach(opt => opt.classList.remove('selected'));
                    // Add selected class to clicked option
                    option.classList.add('selected');
                    // Check radio button
                    radio.checked = true;
                });
            });
        });
        
        // Voeg JavaScript toe voor het tonen/verbergen van hints
        function toggleHint() {
            const hintText = document.getElementById('hint-text');
            const hintButton = document.querySelector('.hint-button');
            
            if (hintText.style.display === 'none') {
                hintText.style.display = 'block';
                hintButton.textContent = 'Verberg Hint';
            } else {
                hintText.style.display = 'none';
                hintButton.textContent = 'Toon Hint';
            }
        }
    </script>
</body>
</html>
