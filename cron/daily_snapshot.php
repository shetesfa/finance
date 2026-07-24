<?php
require_once '../config/database.php';
require_once '../config/functions.php';

// Take daily snapshot
takeDailySnapshot($pdo);

// Check and create alerts
checkAndCreateAlerts($pdo);

// Update all student payment scores
$stmt = $pdo->query("SELECT id FROM students");
foreach ($stmt->fetchAll() as $student) {
    updateStudentPaymentScore($pdo, $student['id']);
}

// Close expired cycles and create new ones
$stmt = $pdo->prepare("UPDATE sales_cycles SET status = 'closed' WHERE end_date < CURDATE() AND status = 'active'");
$stmt->execute();

// Create new cycle if needed
getActiveCycle($pdo);

echo "Daily snapshot completed at " . date('Y-m-d H:i:s') . "\n";
?>