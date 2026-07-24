<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $password = $_POST['password'];
    
    if (empty($name) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both name and password!";
        header("Location: index.php");
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u 
                           JOIN roles r ON u.role_id = r.id 
                           WHERE u.name = ? AND u.is_active = 1");
    $stmt->execute([$name]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role_name'];
        $_SESSION['department'] = $user['department'];
        
        // Redirect based on department
        if ($user['department'] == 'lmat') {
            header("Location: lmat/index.php");
        } else {
            header("Location: nibret/index.php");
        }
        exit();
    } else {
        $_SESSION['login_error'] = "Invalid name or password!";
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>