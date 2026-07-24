<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id']) || !canAccess('lmat')) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$is_admin = ($user_role == 'Lmat_Admin');

$period = $_GET['period'] ?? 'week';

// Get date range
if ($period == 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
    $prev_start = date('Y-m-d', strtotime('-1 day'));
    $prev_end = date('Y-m-d', strtotime('-1 day'));
} elseif ($period == 'week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
    $prev_start = date('Y-m-d', strtotime('monday last week'));
    $prev_end = date('Y-m-d', strtotime('sunday last week'));
} else {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $prev_start = date('Y-m-01', strtotime('last month'));
    $prev_end = date('Y-m-t', strtotime('last month'));
}

// Get all sellers with their performance
$stmt = $pdo->prepare("
    SELECT 
        u.id, u.name,
        COALESCE(SUM(CASE WHEN s.sale_date BETWEEN ? AND ? THEN s.total_amount ELSE 0 END), 0) as current_sales,
        COALESCE(SUM(CASE WHEN s.sale_date BETWEEN ? AND ? THEN s.total_amount ELSE 0 END), 0) as previous_sales,
        COALESCE(t.target_amount, 0) as target_amount,
        COUNT(DISTINCT CASE WHEN s.sale_date BETWEEN ? AND ? THEN s.sale_date END) as active_days,
        MAX(s.created_at) as last_sale
    FROM users u
    LEFT JOIN sales s ON u.id = s.seller_id
    LEFT JOIN seller_targets t ON u.id = t.seller_id 
        AND t.period_type = ? 
        AND t.period_start <= ? AND t.period_end >= ?
    WHERE u.department = 'lmat' AND u.role_id = 2 AND u.is_active = 1
    GROUP BY u.id, u.name
    ORDER BY current_sales DESC
");
$period_type = $period == 'today' ? 'daily' : ($period == 'week' ? 'weekly' : 'monthly');
$stmt->execute([$start_date, $end_date, $prev_start, $prev_end, $start_date, $end_date, $period_type, $end_date, $end_date]);
$sellers = $stmt->fetchAll();

// Calculate rankings and performance metrics
$rank = 1;
foreach ($sellers as &$seller) {
    $seller['rank'] = $rank++;
    $seller['performance'] = $seller['target_amount'] > 0 ? round(($seller['current_sales'] / $seller['target_amount']) * 100) : 0;
    $seller['growth'] = $seller['previous_sales'] > 0 ? round(($seller['current_sales'] - $seller['previous_sales']) / $seller['previous_sales'] * 100) : 0;
    $seller['status'] = $seller['performance'] >= 100 ? 'excellent' : ($seller['performance'] >= 75 ? 'good' : ($seller['performance'] >= 50 ? 'average' : 'poor'));
    $seller['activity_status'] = $seller['last_sale'] && strtotime($seller['last_sale']) > strtotime('-24 hours') ? 'active' : 'inactive';
}

// Get cycle performance
$stmt = $pdo->query("
    SELECT cycle_name, start_date, end_date, total_sales, status,
           DATEDIFF(end_date, start_date) as duration_days
    FROM sales_cycles 
    ORDER BY start_date DESC 
    LIMIT 5
");
$cycles = $stmt->fetchAll();

// Get hourly sales pattern for today
$stmt = $pdo->query("
    SELECT HOUR(created_at) as hour, COUNT(*) as count, SUM(total_amount) as total
    FROM sales 
    WHERE sale_date = CURDATE()
    GROUP BY HOUR(created_at)
    ORDER BY hour
");
$hourly_sales = $stmt->fetchAll();
$hourly_data = array_fill(0, 24, 0);
foreach ($hourly_sales as $h) {
    $hourly_data[$h['hour']] = $h['total'];
}

// Get top products
$stmt = $pdo->prepare("
    SELECT p.name, SUM(s.quantity) as total_qty, SUM(s.total_amount) as total_sales
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_sales DESC
    LIMIT 5
");
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የሻጮች አፈጻጸም - ልማት ክፍል</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; --success: #28a745; --danger: #dc3545; --warning: #ffc107; }
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
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .period-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .period-btn {
            padding: 10px 20px;
            border: none;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .period-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-value { font-size: 28px; font-weight: bold; color: var(--primary-dark); }
        .stat-label { font-size: 13px; color: #666; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 { margin-bottom: 15px; font-size: 18px; }
        
        .leaderboard-table { width: 100%; border-collapse: collapse; }
        .leaderboard-table th, .leaderboard-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .leaderboard-table th { background: #f8f9fa; }
        
        .rank-1 { background: linear-gradient(90deg, #FFD70020, transparent); }
        .rank-2 { background: linear-gradient(90deg, #C0C0C020, transparent); }
        .rank-3 { background: linear-gradient(90deg, #CD7F3220, transparent); }
        
        .medal { font-size: 20px; margin-right: 5px; }
        
        .progress-bar {
            width: 100px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill { height: 100%; border-radius: 4px; }
        .fill-excellent { background: var(--success); }
        .fill-good { background: var(--primary); }
        .fill-average { background: var(--warning); }
        .fill-poor { background: var(--danger); }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .trend-up { color: var(--success); }
        .trend-down { color: var(--danger); }
        
        .chart-container { height: 250px; margin-top: 20px; }
        
        .cycle-card {
            display: inline-block;
            padding: 15px 25px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-right: 15px;
            margin-bottom: 10px;
        }
        .cycle-active { border-left: 4px solid var(--success); }
        .cycle-closed { border-left: 4px solid var(--gray); opacity: 0.7; }
        
        @media (max-width: 768px) {
            .leaderboard-table { font-size: 12px; }
            .leaderboard-table th, .leaderboard-table td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-trophy"></i> የሻጮች አፈጻጸም ዳሽቦርድ</h1>
        <a href="index.php" class="back-btn">← ወደ መሸጫ</a>
    </div>
    
    <div class="container">
        <div class="period-selector">
            <a href="?period=today" class="period-btn <?php echo $period == 'today' ? 'active' : ''; ?>">ዛሬ</a>
            <a href="?period=week" class="period-btn <?php echo $period == 'week' ? 'active' : ''; ?>">በሳምንት</a>
            <a href="?period=month" class="period-btn <?php echo $period == 'month' ? 'active' : ''; ?>">በወር</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($sellers); ?></div>
                <div class="stat-label">ጠቅላላ ሻጮች</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">ETB <?php echo number_format(array_sum(array_column($sellers, 'current_sales')), 2); ?></div>
                <div class="stat-label">ጠቅላላ ሽያጭ</div>
            </div>
            <div class="stat-card">
                <?php 
                $active_count = count(array_filter($sellers, fn($s) => $s['activity_status'] == 'active'));
                ?>
                <div class="stat-value"><?php echo $active_count; ?>/<?php echo count($sellers); ?></div>
                <div class="stat-label">ንቁ ሻጮች</div>
            </div>
            <div class="stat-card">
                <?php 
                $avg_performance = count($sellers) > 0 ? round(array_sum(array_column($sellers, 'performance')) / count($sellers)) : 0;
                ?>
                <div class="stat-value"><?php echo $avg_performance; ?>%</div>
                <div class="stat-label">አማካይ አፈጻጸም</div>
            </div>
        </div>
        
        <!-- Leaderboard -->
        <div class="card">
            <h2><i class="fas fa-medal"></i> የሻጮች ደረጃ</h2>
            <div style="overflow-x: auto;">
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th>ደረጃ</th>
                            <th>ሻጭ</th>
                            <th>ሽያጭ</th>
                            <th>እቅድ</th>
                            <th>አፈጻጸም</th>
                            <th>እድገት</th>
                            <th>ሁኔታ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sellers as $seller): ?>
                        <tr class="rank-<?php echo $seller['rank'] <= 3 ? $seller['rank'] : ''; ?>">
                            <td>
                                <?php if ($seller['rank'] == 1): ?>
                                    <span class="medal">🥇</span>
                                <?php elseif ($seller['rank'] == 2): ?>
                                    <span class="medal">🥈</span>
                                <?php elseif ($seller['rank'] == 3): ?>
                                    <span class="medal">🥉</span>
                                <?php else: ?>
                                    #<?php echo $seller['rank']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($seller['name']); ?></strong>
                                <?php if ($seller['last_sale']): ?>
                                <br><small>መጨረሻ: <?php echo date('H:i', strtotime($seller['last_sale'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong>ETB <?php echo number_format($seller['current_sales'], 2); ?></strong></td>
                            <td>ETB <?php echo number_format($seller['target_amount'], 2); ?></td>
                            <td style="min-width: 120px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span><?php echo $seller['performance']; ?>%</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill fill-<?php echo $seller['status']; ?>" style="width: <?php echo min($seller['performance'], 100); ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="<?php echo $seller['growth'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <i class="fas fa-<?php echo $seller['growth'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    <?php echo abs($seller['growth']); ?>%
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $seller['activity_status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $seller['activity_status'] == 'active' ? 'ንቁ' : 'እንቅስቃሴ የለም'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Top Products -->
            <div class="card">
                <h2><i class="fas fa-box"></i> ከፍተኛ ሽያጭ ያላቸው ምርቶች</h2>
                <table style="width: 100%;">
                    <thead>
                        <tr><th>ምርት</th><th>ብዛት</th><th>ጠቅላላ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo number_format($p['total_qty'], 1); ?></td>
                            <td><strong>ETB <?php echo number_format($p['total_sales'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Cycles -->
            <div class="card">
                <h2><i class="fas fa-sync-alt"></i> የሽያጭ ዑደቶች</h2>
                <?php foreach ($cycles as $cycle): ?>
                <div class="cycle-card <?php echo $cycle['status'] == 'active' ? 'cycle-active' : 'cycle-closed'; ?>">
                    <div><strong><?php echo htmlspecialchars($cycle['cycle_name']); ?></strong></div>
                    <div style="font-size: 12px; color: #666;">
                        <?php echo date('M d', strtotime($cycle['start_date'])); ?> - 
                        <?php echo date('M d', strtotime($cycle['end_date'])); ?>
                        (<?php echo $cycle['duration_days']; ?> ቀናት)
                    </div>
                    <div style="margin-top: 8px; font-size: 18px; font-weight: bold;">
                        ETB <?php echo number_format($cycle['total_sales'], 2); ?>
                    </div>
                    <span style="font-size: 11px; padding: 2px 8px; border-radius: 10px; background: <?php echo $cycle['status'] == 'active' ? '#d4edda' : '#e9ecef'; ?>;">
                        <?php echo $cycle['status'] == 'active' ? 'በሂደት ላይ' : 'ተጠናቋል'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Hourly Sales Chart -->
        <div class="card">
            <h2><i class="fas fa-chart-line"></i> የሰዓት ሽያጭ ስርጭት (ዛሬ)</h2>
            <div class="chart-container">
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        const hourlyData = <?php echo json_encode(array_values($hourly_data)); ?>;
        
        new Chart(document.getElementById('hourlyChart'), {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'ሽያጭ (ETB)',
                    data: hourlyData,
                    backgroundColor: '#DAA520',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => 'ETB ' + value
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>