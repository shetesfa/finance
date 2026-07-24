<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id']) || !canAccess('nibret')) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$expense_id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';
$reason = $_GET['reason'] ?? '';

if (!$expense_id || !in_array($action, ['approve', 'reject'])) {
    header("Location: index.php?error=invalid");
    exit();
}

// Get expense
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if (!$expense) {
    header("Location: index.php?error=notfound");
    exit();
}

// Determine weight based on role
$weight_map = ['Collector' => 50, 'Deputy' => 30, 'Secretary' => 20];
$weight = $weight_map[$user_role] ?? 0;

if ($action == 'approve') {
    // Check if already approved by this role
    $stmt = $pdo->prepare("SELECT id, status FROM expense_approvals WHERE expense_id = ? AND approver_role = ?");
    $stmt->execute([$expense_id, $user_role]);
    $existing = $stmt->fetch();
    
    if ($existing && $existing['status'] == 'approved') {
        header("Location: index.php?error=already_approved");
        exit();
    }
    
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE expense_approvals SET status = 'approved', approved_at = NOW() WHERE id = ?");
        $stmt->execute([$existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO expense_approvals (expense_id, approver_id, approver_role, weight_value, status, approved_at) 
                               VALUES (?, ?, ?, ?, 'approved', NOW())");
        $stmt->execute([$expense_id, $user_id, $user_role, $weight]);
    }
    
    logAudit($pdo, $user_id, 'APPROVE', 'expenses', $expense_id, null, ['weight' => $weight]);
    
    // Calculate total approval weight
    $total_weight = calculateApprovalWeight($pdo, $expense_id);
    
    header("Location: index.php?msg=approved&weight=" . $total_weight);
    exit();
    
} elseif ($action == 'reject') {
    if (empty($reason)) {
        header("Location: index.php?error=reason_required");
        exit();
    }
    
    // Record rejection
    $stmt = $pdo->prepare("INSERT INTO expense_approvals (expense_id, approver_id, approver_role, weight_value, status, rejection_reason) 
                           VALUES (?, ?, ?, ?, 'rejected', ?)
                           ON DUPLICATE KEY UPDATE status = 'rejected', rejection_reason = ?, approved_at = NULL");
    $stmt->execute([$expense_id, $user_id, $user_role, $weight, $reason, $reason]);
    
    // Count rejections
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM expense_approvals WHERE expense_id = ? AND status = 'rejected'");
    $stmt->execute([$expense_id]);
    $reject_count = $stmt->fetch()['count'];
    
    logAudit($pdo, $user_id, 'REJECT', 'expenses', $expense_id, null, ['reason' => $reason]);
    
    if ($reject_count >= 2) {
        $pdo->prepare("UPDATE expenses SET status = 'REJECTED', rejected_by = ?, rejection_reason = ?, rejected_at = NOW() WHERE id = ?")
            ->execute([$user_id, $reason, $expense_id]);
        createAlert($pdo, 'approval_delay', 'warning', 'ወጪ ውድቅ ተደርጓል', 
            "ወጪ ቁጥር #{$expense_id} ውድቅ ተደርጓል። ምክንያት: {$reason}", $expense_id, 'expense', 'nibret');
    }
    
    header("Location: index.php?msg=rejected");
    exit();
}
?>