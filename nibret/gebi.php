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
$is_admin = ($user_role == 'Nibret_Admin');

// Add manual income (only admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_income']) && $is_admin) {
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    
    $stmt = $pdo->prepare("INSERT INTO income (source, amount, description, recorded_by, recorded_date) 
                           VALUES ('manual', ?, ?, ?, ?)");
    $stmt->execute([$amount, $description, $_SESSION['user_id'], $date]);
    
    header("Location: gebi.php?msg=added");
    exit();
}

// Get LMAT sales total for CURRENT MONTH (April)
$current_month = date('m');
$current_year = date('Y');
$month_name = date('F', mktime(0,0,0,$current_month,1));

$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM income WHERE source = 'sale' AND MONTH(recorded_date) = ? AND YEAR(recorded_date) = ?");
$stmt->execute([$current_month, $current_year]);
$this_month_lmat_sales = $stmt->fetch()['total'] ?? 0;

// Get all OTHER income (contributions, manual, expense) - NO sale details
$stmt = $pdo->prepare("SELECT i.*, u.name as recorded_by_name 
                       FROM income i 
                       JOIN users u ON i.recorded_by = u.id 
                       WHERE i.source != 'sale'
                       ORDER BY i.recorded_date DESC, i.created_at DESC");
$stmt->execute();
$income_list = $stmt->fetchAll();

// Get totals by source (excluding sale)
$stmt = $pdo->query("SELECT source, SUM(amount) as total FROM income WHERE source != 'sale' GROUP BY source");
$source_totals = $stmt->fetchAll();

$total_income = array_sum(array_column($income_list, 'amount')) + $this_month_lmat_sales;

// Get this month's contributions
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM income WHERE source = 'contribution' AND MONTH(recorded_date) = MONTH(CURDATE()) AND YEAR(recorded_date) = YEAR(CURDATE())");
$stmt->execute();
$this_month_contributions = $stmt->fetch()['total'] ?? 0;

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ገቢ - ንብረት ክፍል</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; --primary-light: #FFD700; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .back-btn { background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 8px; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .flash { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .flash-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-box h3 { font-size: 13px; color: #666; margin-bottom: 10px; }
        .stat-number { font-size: 28px; font-weight: bold; color: var(--primary-dark); }
        .lmat-card {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            color: white;
        }
        .lmat-card .stat-number { color: white; }
        .lmat-card h3 { color: rgba(255,255,255,0.95); }
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
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; color: #666; }
        .source-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .source-contribution { background: #cfe2ff; color: #084298; }
        .source-manual { background: #fff3cd; color: #856404; }
        .source-expense { background: #f8d7da; color: #721c24; }
        .btn-print { background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-top: 15px; }
        @media print { .header, .card:first-child, .btn-print, .back-btn { display: none; } }
        @media (max-width: 768px) { table { font-size: 0.7rem; } th, td { padding: 6px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-money-bill-wave"></i> ገቢ - Income Management</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <?php if ($msg == 'added'): ?>
        <div class="flash flash-success">✅ ገቢ ተመዝግቧል!</div>
        <?php endif; ?>
        
        <div class="stats-row">
            <div class="stat-box">
                <h3><i class="fas fa-chart-line"></i> ጠቅላላ ገቢ</h3>
                <div class="stat-number">ETB <?php echo number_format($total_income, 2); ?></div>
            </div>
            <div class="stat-box">
                <h3><i class="fas fa-hand-holding-heart"></i> የዚህ ወር መዋጮ</h3>
                <div class="stat-number">ETB <?php echo number_format($this_month_contributions, 2); ?></div>
            </div>
            <div class="stat-box lmat-card">
                <h3><i class="fas fa-store"></i> የልማት ክፍል ሽያጭ - <?php echo $month_name; ?> <?php echo $current_year; ?></h3>
                <div class="stat-number">ETB <?php echo number_format($this_month_lmat_sales, 2); ?></div>
            </div>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> አዲስ ገቢ መዝግብ</h2>
            <form method="POST">
                <div class="form-group">
                    <label>መጠን (ETB)</label>
                    <input type="number" name="amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>ቀን</label>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>ማብራሪያ</label>
                    <textarea name="description" rows="2" required placeholder="ለምን እንደሆነ ያስረዱ..."></textarea>
                </div>
                <button type="submit" name="add_income" class="btn btn-primary"><i class="fas fa-save"></i> መዝግብ</button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-list"></i> ሌሎች ገቢዎች (መዋጮ፣ የእጅ ገቢ፣ ወጪ)</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ቀን</th><th>ምንጭ</th><th>መጠን</th><th>ማብራሪያ</th><th>የመዘገበው</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($income_list as $inc): ?>
                        <tr>
                            <td><?php echo $inc['recorded_date']; ?></td>
                            <td>
                                <span class="source-badge source-<?php echo $inc['source']; ?>">
                                    <?php 
                                    if ($inc['source'] == 'contribution') echo 'መዋጮ';
                                    elseif ($inc['source'] == 'manual') echo 'የእጅ ገቢ';
                                    elseif ($inc['source'] == 'expense') echo 'ወጪ';
                                    else echo $inc['source'];
                                    ?>
                                </span>
                             </div>
                            <td style="color: <?php echo $inc['amount'] < 0 ? '#dc3545' : '#28a745'; ?>; font-weight: bold;">
                                ETB <?php echo number_format($inc['amount'], 2); ?>
                             </div>
                            <td><?php echo htmlspecialchars($inc['description']); ?> </div>
                            <td><?php echo htmlspecialchars($inc['recorded_by_name']); ?> </div>
                          </tr>
                        <?php endforeach; ?>
                        <?php if (empty($income_list)): ?>
                         <tr><td colspan="5" style="text-align: center; padding: 40px;">
                            <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                            ምንም ሌላ ገቢ የለም
                          </div></tr>
                        <?php endif; ?>
                    </tbody>
                 </table>
            </div>
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> አትም</button>
        </div>
    </div>
</body>
</html>