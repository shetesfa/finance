<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';
checkAuth();

// Check if department is set, if not redirect to login
if (!isset($_SESSION['department'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$user_department = $_SESSION['department'];
$user_role = $_SESSION['role'];

// Redirect based on role
if ($user_department == 'lmat') {
    header("Location: lmat/index.php");
    exit();
} elseif ($user_department == 'nibret') {
    header("Location: nibret/index.php");
    exit();
} else {
    // If department is not recognized, redirect to login
    session_destroy();
    header("Location: index.php");
    exit();
}
?>