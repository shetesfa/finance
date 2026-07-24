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
if (!isset($_SESSION['department']) || $_SESSION['department'] != 'nibret') {
    header("Location: ../dashboard.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$is_admin = ($user_role == 'Nibret_Admin');
$is_collector = ($user_role == 'Collector');
$is_deputy = ($user_role == 'Deputy');
$is_secretary = ($user_role == 'Secretary');
$is_approver = ($is_collector || $is_deputy || $is_secretary);

$auto_approve_limit = getUserAutoApproveLimit($pdo, $user_id);

// Handle expense request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_expense'])) {
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    
    // Check if auto-approve applies
    if ($amount <= $auto_approve_limit) {
        // Auto approve
        $stmt = $pdo->prepare("INSERT INTO expenses (amount, category, description, requested_by, 
                               collector_approved, deputy_approved, secretary_approved, 
                               collector_id, deputy_id, secretary_id,
                               collector_approved_at, deputy_approved_at, secretary_approved_at,
                               status, fully_approved_at) 
                               VALUES (?, ?, ?, ?, 1, 1, 1, ?, ?, ?, NOW(), NOW(), NOW(), 'FULLY_APPROVED', NOW())");
        $stmt->execute([$amount, $category, $description, $user_id, $user_id, $user_id, $user_id]);
        $expense_id = $pdo->lastInsertId();
        
        // Add to income as negative
        $stmt = $pdo->prepare("INSERT INTO income (source, reference_id, amount, description, recorded_by, recorded_date) 
                               VALUES ('expense', ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$expense_id, -$amount, "ወጪ: " . $category . " - " . $description, $user_id]);
        
        header("Location: wechi.php?msg=auto_approved");
        exit();
    } else {
        // Needs approval
        $stmt = $pdo->prepare("INSERT INTO expenses (amount, category, description, requested_by, status) 
                               VALUES (?, ?, ?, ?, 'PENDING')");
        $stmt->execute([$amount, $category, $description, $user_id]);
        header("Location: wechi.php?msg=requested");
        exit();
    }
}

// Get all expenses with approval info
$stmt = $pdo->prepare("SELECT e.*, u.name as requester_name,
                       c.name as collector_name, d.name as deputy_name, s.name as secretary_name
                       FROM expenses e 
                       JOIN users u ON e.requested_by = u.id 
                       LEFT JOIN users c ON e.collector_id = c.id
                       LEFT JOIN users d ON e.deputy_id = d.id
                       LEFT JOIN users s ON e.secretary_id = s.id
                       ORDER BY e.requested_at DESC");
$stmt->execute();
$expenses = $stmt->fetchAll();

// Get totals
$stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses WHERE status = 'FULLY_APPROVED'");
$total_expenses = $stmt->fetch()['total'] ?? 0;
$stmt = $pdo->query("SELECT COUNT(*) as pending FROM expenses WHERE status NOT IN ('FULLY_APPROVED', 'REJECTED')");
$pending_count = $stmt->fetch()['pending'];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ወጪ - ንብረት ክፍል</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; --danger: #dc3545; --success: #28a745; }
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
        .flash { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .flash-success { background: #d4edda; color: #155724; border-left: 4px solid var(--success); }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card h3 { font-size: 13px; color: #666; margin-bottom: 10px; }
        .stat-value { font-size: 28px; font-weight: bold; }
        .stat-value.expense { color: var(--danger); }
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 { margin-bottom: 20px; color: #333; font-size: 18px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-danger { background: var(--danger); color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; color: #666; }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .approval-steps { display: flex; gap: 8px; margin-top: 5px; }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }
        .step-approved { background: var(--success); color: white; }
        .step-pending { background: #e9ecef; color: var(--gray); }
        .limit-info {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 13px;
        }
        @media (max-width: 768px) { table { font-size: 0.7rem; } th, td { padding: 6px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-shopping-cart"></i> ወጪ - Expense Management</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <?php if ($msg == 'auto_approved'): ?>
        <div class="flash flash-success">ወጪ በራስ-ሰር ጸድቋል!</div>
        <?php elseif ($msg == 'requested'): ?>
        <div class="flash flash-success">የወጪ ጥያቄ ቀርቧል! ማፅደቂያ ይጠብቁ.</div>
        <?php endif; ?>
        
        <div class="stats-row">
            <div class="stat-card">
                <h3><i class="fas fa-chart-line"></i> ጠቅላላ ወጪ</h3>
                <div class="stat-value expense">ETB <?php echo number_format($total_expenses, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> በመጠባበቅ ላይ</h3>
                <div class="stat-value"><?php echo $pending_count; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-bolt"></i> ራስ-ሰር ማፅደቂያ ገደብ</h3>
                <div class="stat-value">ETB <?php echo number_format($auto_approve_limit, 2); ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> አዲስ ወጪ ጥያቄ</h2>
            <div class="limit-info">
                <i class="fas fa-info-circle"></i> ከ ETB <?php echo number_format($auto_approve_limit, 2); ?> በታች ያለ ወጪ በራስ-ሰር ይፀድቃል
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>መጠን (ETB)</label>
                    <input type="number" name="amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>ምድብ</label>
                    <select name="category" required>
                        <option value="">-- ምረጥ --</option>
                        <option value="Utilities">መገልገያ (Electricity, Water)</option>
                        <option value="Maintenance">ጥገና</option>
                        <option value="Charity">ለግስና</option>
                        <option value="Staff">የሰራተኛ ደሞዝ</option>
                        <option value="Office">ቢሮ እቃ</option>
                        <option value="Other">ሌላ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ማብራሪያ</label>
                    <textarea name="description" rows="3" required></textarea>
                </div>
                <button type="submit" name="request_expense" class="btn btn-danger"><i class="fas fa-paper-plane"></i> ጥያቄ አቅርብ</button>
            </form>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-list"></i> ሁሉም ወጪዎች</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ቀን</th><th>አቅራቢ</th><th>ምድብ</th><th>መጠን</th><th>ማብራሪያ</th><th>ማፅደቂያ</th><th>ሁኔታ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $exp): ?>
                        <?php
                        $collector_approved = $exp['collector_approved'];
                        $deputy_approved = $exp['deputy_approved'];
                        $secretary_approved = $exp['secretary_approved'];
                        ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($exp['requested_at'])); ?></td>
                            <td><?php echo htmlspecialchars($exp['requester_name']); ?></td>
                            <td><?php echo $exp['category']; ?></td>
                            <td><strong>ETB <?php echo number_format($exp['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($exp['description']); ?></td>
                            <td>
                                <div class="approval-steps">
                                    <span class="step <?php echo $collector_approved ? 'step-approved' : 'step-pending'; ?>" title="ሰብሳቢ">ሰ</span>
                                    <span class="step <?php echo $deputy_approved ? 'step-approved' : 'step-pending'; ?>" title="ምክትል ሰብሳቢ">ም</span>
                                    <span class="step <?php echo $secretary_approved ? 'step-approved' : 'step-pending'; ?>" title="ፀሀፊ">ጸ</span>
                                </div>
                                <?php if ($exp['status'] == 'REJECTED' && $exp['rejection_reason']): ?>
                                <small style="color:#721c24;">ውድቅ ምክንያት: <?php echo htmlspecialchars($exp['rejection_reason']); ?></small>
                                <?php endif; ?>
                             </div>
                            <td>
                                <span class="badge 
                                    <?php echo $exp['status'] == 'FULLY_APPROVED' ? 'badge-approved' : ($exp['status'] == 'REJECTED' ? 'badge-rejected' : 'badge-pending'); ?>">
                                    <?php 
                                    if ($exp['status'] == 'FULLY_APPROVED') echo 'ተፈቅዷል';
                                    elseif ($exp['status'] == 'REJECTED') echo 'ውድቅ ተደርጓል';
                                    else echo 'በመጠባበቅ ላይ';
                                    ?>
                                </span>
                             </div>
                          </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>