<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id']) || !canAccess('lmat')) {
    header("Location: ../index.php");
    exit();
}

$is_admin = ($_SESSION['role'] == 'Lmat_Admin');

// Handle cycle actions (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['close_cycle'])) {
        $cycle_id = $_POST['cycle_id'];
        $pdo->prepare("UPDATE sales_cycles SET status = 'closed' WHERE id = ?")->execute([$cycle_id]);
        header("Location: cycles.php?msg=closed");
        exit();
    }
    if (isset($_POST['create_cycle'])) {
        $name = $_POST['name'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $stmt = $pdo->prepare("INSERT INTO sales_cycles (cycle_name, start_date, end_date, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$name, $start, $end]);
        header("Location: cycles.php?msg=created");
        exit();
    }
}

// Get all cycles with detailed stats
$stmt = $pdo->query("
    SELECT 
        c.*,
        COALESCE(SUM(s.total_amount), 0) as total_sales,
        COUNT(DISTINCT s.id) as transaction_count,
        COUNT(DISTINCT s.seller_id) as seller_count,
        AVG(s.total_amount) as avg_transaction
    FROM sales_cycles c
    LEFT JOIN sales s ON c.id = s.cycle_id
    GROUP BY c.id
    ORDER BY c.start_date DESC
");
$cycles = $stmt->fetchAll();

// Get current active cycle details
$active_cycle = getActiveCycle($pdo);
$stmt = $pdo->prepare("
    SELECT 
        u.name as seller_name,
        COUNT(s.id) as sales_count,
        SUM(s.total_amount) as total_sales
    FROM sales s
    JOIN users u ON s.seller_id = u.id
    WHERE s.cycle_id = ?
    GROUP BY s.seller_id
    ORDER BY total_sales DESC
");
$stmt->execute([$active_cycle['id']]);
$cycle_sellers = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የሽያጭ ዑደቶች - ልማት ክፍል</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; --success: #28a745; --danger: #dc3545; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-btn { background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 8px; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .flash { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .flash-success { background: #d4edda; color: #155724; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 { margin-bottom: 15px; }
        
        .active-cycle {
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .active-cycle h2 { color: white; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        
        .cycle-card {
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
        }
        .cycle-active-card { border-left: 4px solid var(--success); background: #f8fff9; }
        .cycle-closed-card { opacity: 0.7; }
        
        .cycle-stats {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .stat-item { text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; color: var(--primary-dark); }
        .stat-label { font-size: 12px; color: #666; }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        
        @media (max-width: 768px) {
            .cycle-card { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-sync-alt"></i> የሽያጭ ዑደቶች አስተዳደር</h1>
        <a href="index.php" class="back-btn">← ወደ መሸጫ</a>
    </div>
    
    <div class="container">
        <?php if ($msg == 'closed'): ?>
        <div class="flash flash-success">ዑደት ተዘግቷል!</div>
        <?php elseif ($msg == 'created'): ?>
        <div class="flash flash-success">አዲስ ዑደት ተፈጥሯል!</div>
        <?php endif; ?>
        
        <!-- Active Cycle Details -->
        <div class="active-cycle">
            <h2><i class="fas fa-play-circle"></i> የአሁን ንቁ ዑደት: <?php echo htmlspecialchars($active_cycle['cycle_name']); ?></h2>
            <div style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 20px;">
                <div>ጀምሮ: <strong><?php echo date('M d, Y', strtotime($active_cycle['start_date'])); ?></strong></div>
                <div>የሚያበቃው: <strong><?php echo date('M d, Y', strtotime($active_cycle['end_date'])); ?></strong></div>
                <div>ቀሪ ቀናት: <strong><?php 
                    $remaining = max(0, ceil((strtotime($active_cycle['end_date']) - time()) / 86400));
                    echo $remaining;
                ?> ቀናት</strong></div>
            </div>
            
            <h3 style="margin-bottom: 15px;">በዚህ ዑደት የሻጮች አፈጻጸም</h3>
            <table style="background: rgba(255,255,255,0.1); border-radius: 8px;">
                <thead>
                    <tr><th>ሻጭ</th><th>የሽያጭ ብዛት</th><th>ጠቅላላ ሽያጭ</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($cycle_sellers as $cs): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cs['seller_name']); ?></td>
                        <td><?php echo $cs['sales_count']; ?></td>
                        <td><strong>ETB <?php echo number_format($cs['total_sales'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cycle_sellers)): ?>
                    <tr><td colspan="3" style="text-align:center;">እስካሁን ምንም ሽያጭ የለም</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Create New Cycle (Admin) -->
        <?php if ($is_admin): ?>
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> አዲስ ዑደት ፍጠር</h2>
            <form method="POST">
                <div class="form-group">
                    <label>የዑደት ስም</label>
                    <input type="text" name="name" value="ዑደት <?php echo date('W'); ?>" required>
                </div>
                <div class="form-group">
                    <label>የሚጀምርበት ቀን</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>የሚያበቃበት ቀን</label>
                    <input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                </div>
                <button type="submit" name="create_cycle" class="btn btn-primary"><i class="fas fa-save"></i> ዑደት ፍጠር</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- All Cycles History -->
        <div class="card">
            <h2><i class="fas fa-history"></i> ሁሉም ዑደቶች</h2>
            <?php foreach ($cycles as $cycle): ?>
            <div class="cycle-card <?php echo $cycle['status'] == 'active' ? 'cycle-active-card' : 'cycle-closed-card'; ?>">
                <div>
                    <h3 style="margin-bottom: 10px;">
                        <?php echo htmlspecialchars($cycle['cycle_name']); ?>
                        <span style="font-size: 12px; margin-left: 10px; padding: 2px 10px; border-radius: 20px; background: <?php echo $cycle['status'] == 'active' ? '#d4edda' : '#e9ecef'; ?>;">
                            <?php echo $cycle['status'] == 'active' ? 'ንቁ' : 'ተዘግቷል'; ?>
                        </span>
                    </h3>
                    <div style="color: #666; margin-bottom: 15px;">
                        <?php echo date('M d, Y', strtotime($cycle['start_date'])); ?> - 
                        <?php echo date('M d, Y', strtotime($cycle['end_date'])); ?>
                    </div>
                    <div class="cycle-stats">
                        <div class="stat-item">
                            <div class="stat-value">ETB <?php echo number_format($cycle['total_sales'], 2); ?></div>
                            <div class="stat-label">ጠቅላላ ሽያጭ</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cycle['transaction_count']; ?></div>
                            <div class="stat-label">ግብይቶች</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cycle['seller_count']; ?></div>
                            <div class="stat-label">ሻጮች</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">ETB <?php echo number_format($cycle['avg_transaction'] ?? 0, 2); ?></div>
                            <div class="stat-label">አማካይ ግብይት</div>
                        </div>
                    </div>
                </div>
                <?php if ($is_admin && $cycle['status'] == 'active'): ?>
                <div>
                    <form method="POST" onsubmit="return confirm('ይህን ዑደት መዝጋት ይፈልጋሉ?')">
                        <input type="hidden" name="cycle_id" value="<?php echo $cycle['id']; ?>">
                        <button type="submit" name="close_cycle" class="btn btn-danger"><i class="fas fa-lock"></i> ዝጋ</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>