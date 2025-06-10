<?php
// Session configuration
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'escape_room_lab');

// Application settings
define('SITE_NAME', 'Escape De Laboratorium');
define('GAME_TIME_LIMIT', 10 * 60); // 10 minuten in seconden

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>