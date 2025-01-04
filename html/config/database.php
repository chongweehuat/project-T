<?php
// html/config/database.php

$host = '172.19.0.14';
$port = '3306';
$user = 'root';
$password = 'CF26D23C453D3EB6';
$database = 'my369transactions';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
