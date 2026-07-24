<?php
// config/database.php
$host = 'localhost';
$dbname = 'church_finance';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("set names utf8mb4");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('Africa/Addis_Ababa');
?>