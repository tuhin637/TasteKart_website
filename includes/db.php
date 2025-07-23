<?php
try {
    $host = 'localhost';
    $dbname = 'tastekart';
    $username = 'root'; // Update with your MySQL username
    $password = '';     // Update with your MySQL password
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>