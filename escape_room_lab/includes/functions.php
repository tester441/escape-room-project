<?php
require_once 'config.php';
require_once 'database.php';

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /escape_room_lab/auth/login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /escape_room_lab/index.php?error=unauthorized");
        exit();
    }
}

function getUserById($userId) {
    $db = connectDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getTeamById($teamId) {
    $db = connectDb();
    $stmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    return $stmt->fetch();
}

function formatTime($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $seconds);
}

// Sanitize and validate user input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input));
}

function displayAlert($message, $type = 'info') {
    return "<div class='alert alert-{$type}'>{$message}</div>";
}

// Ensure solved_questions table exists
function ensureSolvedQuestionsTable() {
    $db = connectDb();
    $db->exec("CREATE TABLE IF NOT EXISTS solved_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT NOT NULL,
        question_id INT NOT NULL,
        solved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_solve (team_id, question_id),
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    )");
}

// Call this function to ensure the table exists
ensureSolvedQuestionsTable();
?>
