<?php
/**
 * Database connection (PDO).
 * Default XAMPP settings: user "root" with an empty password.
 * Change these if your MySQL setup is different.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'student_cookbook');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('<h1>Database connection failed</h1><p>Start MySQL in XAMPP and import <code>database/cookbook.sql</code>, then check the settings in <code>config/db.php</code>.</p>');
}
