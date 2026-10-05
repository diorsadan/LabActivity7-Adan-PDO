<?php
/**
 * PDO connection to the blog_site database.
 * Adjust DB_USER / DB_PASS if your MySQL setup differs from XAMPP defaults.
 */

const DB_HOST = '127.0.0.1';
const DB_NAME = 'blog_site';
const DB_USER = 'root';
const DB_PASS = '';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please check db.php settings and make sure schema.sql has been imported.');
}
