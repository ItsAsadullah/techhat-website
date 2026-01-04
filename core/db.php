<?php
require_once __DIR__ . '/config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    if (DB_TIMEZONE) {
        $pdo->exec("SET time_zone = '" . DB_TIMEZONE . "'");
    }
} catch (PDOException $e) {
    // Do NOT expose DB error in production
    die('Database connection failed.');
}
