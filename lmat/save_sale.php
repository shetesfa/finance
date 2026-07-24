<?php
error_reporting(0);
header('Content-Type: application/json');

session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'No items in cart']);
    exit();
}

$user_id = $_SESSION['user_id'];
$total = floatval($data['total']);
$paid = floatval($data['paid'] ?? 0);
$change = floatval($data['change'] ?? 0);
$payment_method = $data['payment_method'];
$cycle_id = $data['cycle_id'] ?? null;
$receipt = generateReceiptNumber('SALE');

try {
    $pdo->beginTransaction();
    
    foreach ($data['items'] as $item) {
        $product_id = intval($item['id']);
        $quantity = floatval($item['quantity']);
        $unit_price = floatval($item['price']);
        $total_amount = floatval($item['subtotal']);
        
        $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity, unit_price, total_amount, seller_id, payment_method, amount_paid, change_amount, receipt_number, sale_date, cycle_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)");
        $stmt->execute([$product_id, $quantity, $unit_price, $total_amount, $user_id, $payment_method, $paid, $change, $receipt, $cycle_id]);
        
        $sale_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$quantity, $product_id]);
        
        $stmt = $pdo->prepare("INSERT INTO income (source, reference_id, amount, description, recorded_by, recorded_date) 
                               VALUES ('sale', ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$sale_id, $total_amount, "ሽያጭ: " . $item['name'], $user_id]);
    }
    
    // Update cycle total
    if ($cycle_id) {
        $pdo->prepare("UPDATE sales_cycles SET total_sales = total_sales + ? WHERE id = ?")
            ->execute([$total, $cycle_id]);
    }
    
    // Update seller activity
    updateSellerActivity($pdo, $user_id);
    
    // Check if target achieved
    $perf = calculateSellerPerformance($pdo, $user_id, 'today');
    if ($perf['percentage'] >= 100) {
        createAlert($pdo, 'target_achieved', 'success', 'የዕለት እቅድ ተሳክቷል!', 
            "እንኳን ደስ አለዎት! የዛሬውን የሽያጭ እቅድዎን አሳክተዋል።", $user_id, 'seller', 'lmat');
    }
    
    logAudit($pdo, $user_id, 'INSERT', 'sales', $sale_id, null, ['total' => $total, 'receipt' => $receipt]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'receipt' => $receipt]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>