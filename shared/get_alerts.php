<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$department = $_GET['department'] ?? $_SESSION['department'] ?? 'both';

$stmt = $pdo->prepare("SELECT * FROM alerts WHERE (department = ? OR department = 'both') AND is_read = 0 AND resolved_at IS NULL ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$department]);
$alerts = $stmt->fetchAll();

// Mark as read
if (isset($_GET['mark_read']) && $_GET['mark_read']) {
    $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE (department = ? OR department = 'both')")
        ->execute([$department]);
}

header('Content-Type: application/json');
echo json_encode(['alerts' => $alerts, 'count' => count($alerts)]);
?>