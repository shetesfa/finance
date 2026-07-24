<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check department and approver role
if (!isset($_SESSION['department']) || $_SESSION['department'] != 'nibret') {
    header("Location: index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$is_approver = (hasRole('Collector') || hasRole('Deputy') || hasRole('Secretary') || hasRole('Nibret_Admin'));

if (!$is_approver) {
    header("Location: index.php");
    exit();
}

$withdraw_limit = getUserAutoApproveLimit($pdo, $user_id);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'];
    $reason = $_POST['reason'];
    
    if ($amount > $withdraw_limit) {
        header("Location: withdraw.php?error=limit&limit=" . $withdraw_limit);
        exit();
    }
    
    $balance = getBalance($pdo);
    if ($amount > $balance) {
        header("Location: withdraw.php?error=balance&balance=" . $balance);
        exit();
    }
    
    $stmt = $pdo->prepare("INSERT INTO withdrawals (amount, reason, withdrawn_by) VALUES (?, ?, ?)");
    $stmt->execute([$amount, $reason, $user_id]);
    
    header("Location: index.php?msg=withdrawn&amount=" . $amount);
    exit();
}

$balance = getBalance($pdo);
$error = $_GET['error'] ?? '';
$limit = $_GET['limit'] ?? $withdraw_limit;
$balance_error = $_GET['balance'] ?? $balance;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ገንዘብ አውጣ - ንብረት ክፍል</title>
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
        .container { max-width: 600px; margin: 50px auto; padding: 0 20px; }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .limit-info {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .balance-info {
            background: #d4edda;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .balance-amount { font-size: 28px; font-weight: bold; color: #28a745; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .flash { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .flash-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-money-bill-alt"></i> ገንዘብ አውጣ</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <?php if ($error == 'limit'): ?>
        <div class="flash flash-error">መጠኑ ከገደብ በላይ ነው! ገደብ: ETB <?php echo number_format($limit, 2); ?></div>
        <?php elseif ($error == 'balance'): ?>
        <div class="flash flash-error">በቂ ገንዘብ የለም! ያለው: ETB <?php echo number_format($balance_error, 2); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="limit-info">
                <i class="fas fa-info-circle"></i> የእርስዎ ማውጫ ገደብ: <strong>ETB <?php echo number_format($withdraw_limit, 2); ?></strong>
                <br><small>ከዚህ በላይ ከሆነ የወጪ ጥያቄ ያስገቡ</small>
            </div>
            
            <div class="balance-info">
                <div>ያለው ገንዘብ</div>
                <div class="balance-amount">ETB <?php echo number_format($balance, 2); ?></div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>ማውጫ መጠን (ETB)</label>
                    <input type="number" name="amount" step="0.01" min="1" max="<?php echo min($withdraw_limit, $balance); ?>" required>
                </div>
                <div class="form-group">
                    <label>ምክንያት</label>
                    <textarea name="reason" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn"><i class="fas fa-check-circle"></i> አውጣ</button>
            </form>
        </div>
    </div>
</body>
</html>