<?php

// Database configuration
// define('DB_HOST', '62.72.14.157');
define('DB_HOST', 'localhost');
define('DB_USER', 'auction_user');
define('DB_PASS', 'JbkJl-z1O5|U');
define('DB_NAME', 'auction_portal');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Get PDO instance
$pdo = getDBConnection();
?>