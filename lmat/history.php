<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check department
if (!isset($_SESSION['department']) || $_SESSION['department'] != 'lmat') {
    header("Location: ../dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'Lmat_Admin');

if ($is_admin) {
    $stmt = $pdo->query("SELECT s.*, p.name as product_name, u.name as seller_name 
                         FROM sales s 
                         JOIN products p ON s.product_id = p.id 
                         JOIN users u ON s.seller_id = u.id 
                         ORDER BY s.created_at DESC LIMIT 50");
} else {
    $stmt = $pdo->prepare("SELECT s.*, p.name as product_name 
                          FROM sales s 
                          JOIN products p ON s.product_id = p.id 
                          WHERE s.seller_id = ? 
                          ORDER BY s.created_at DESC LIMIT 50");
    $stmt->execute([$user_id]);
}
$sales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ሽያጭ ታሪክ - ልማት ክፍል</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-btn { background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 8px; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 15px; padding: 20px; }
        .card h2 { margin-bottom: 20px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; color: #666; }
        @media (max-width: 768px) { table { font-size: 0.7rem; } th, td { padding: 6px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-history"></i> ሽያጭ ታሪክ</h1>
        <a href="index.php" class="back-btn">← ወደ መሸጫ</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>📋 የተመዘገቡ ሽያጮች</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ቀን</th><th>ምርት</th><th>ብዛት</th><th>ዋጋ</th><th>ጠቅላላ</th><th>ክፍያ</th><?php if ($is_admin): ?><th>ሻጭ</th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?php echo $sale['sale_date']; ?></td>
                            <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td>ETB <?php echo number_format($sale['unit_price'], 2); ?></td>
                            <td>ETB <?php echo number_format($sale['total_amount'], 2); ?></td>
                            <td><?php echo ucfirst($sale['payment_method']); ?></td>
                            <?php if ($is_admin): ?><td><?php echo htmlspecialchars($sale['seller_name']); ?></td><?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sales)): ?>
                        <tr><td colspan="6" style="text-align:center;">ምንም ሽያጭ የለም</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>