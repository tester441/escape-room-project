<?php
require_once 'config.php';
require_once 'database.php';

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    // Haal de gebruikersrol op uit de database in plaats van te vertrouwen op sessie
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $db = connectDb();
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Update de sessie met de correcte admin status
    $_SESSION['is_admin'] = ($user && $user['role'] == 'admin') ? 1 : 0;
    
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
    $db->exec("CREATE TABLE IF NOT EXISTS solved_puzzles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT NOT NULL,
        puzzle_id INT NOT NULL,
        attempts INT DEFAULT 1,
        solved BOOLEAN DEFAULT FALSE,
        solved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_solve (team_id, puzzle_id),
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE
    )");
}

// Ensure team_members table exists
function ensureTeamMembersTable() {
    $db = connectDb();
    $db->exec("CREATE TABLE IF NOT EXISTS team_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT NOT NULL,
        user_id INT NOT NULL,
        is_captain BOOLEAN DEFAULT FALSE,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_member (team_id, user_id)
    )");
}

// Call this function to ensure the tables exist
ensureSolvedQuestionsTable();
ensureTeamMembersTable();
?>
