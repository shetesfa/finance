<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

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
$is_approver = ($is_collector || $is_deputy || $is_secretary || $is_admin);

$balance = getBalance($pdo);

// Get pending approvals for this user (weighted system)
$pending_approvals = [];
if ($is_approver) {
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as requester_name,
               COALESCE(SUM(ea.weight_value), 0) as approved_weight
        FROM expenses e
        JOIN users u ON e.requested_by = u.id
        LEFT JOIN expense_approvals ea ON e.id = ea.expense_id AND ea.status = 'approved'
        WHERE e.status NOT IN ('FULLY_APPROVED', 'REJECTED')
        GROUP BY e.id
        HAVING approved_weight < 100
        ORDER BY e.requested_at DESC
    ");
    $stmt->execute();
    $all_pending = $stmt->fetchAll();
    
    foreach ($all_pending as $exp) {
        // Check if this user can approve
        $user_can_approve = false;
        if ($is_collector && $user_role == 'Collector') $user_can_approve = true;
        if ($is_deputy && $user_role == 'Deputy') $user_can_approve = true;
        if ($is_secretary && $user_role == 'Secretary') $user_can_approve = true;
        if ($is_admin) $user_can_approve = true;
        
        if ($user_can_approve) {
            $stmt = $pdo->prepare("SELECT status FROM expense_approvals WHERE expense_id = ? AND approver_role = ?");
            $stmt->execute([$exp['id'], $user_role]);
            $existing = $stmt->fetch();
            
            if (!$existing || $existing['status'] == 'pending') {
                $pending_approvals[] = $exp;
            }
        }
    }
}

// Get this month's stats
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM income WHERE MONTH(recorded_date) = MONTH(CURDATE()) AND YEAR(recorded_date) = YEAR(CURDATE()) AND amount > 0");
$stmt->execute();
$this_month_income = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM income WHERE source = 'sale' AND MONTH(recorded_date) = MONTH(CURDATE())");
$stmt->execute();
$this_month_sales = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM contributions WHERE MONTH(payment_date) = MONTH(CURDATE())");
$stmt->execute();
$this_month_contributions = $stmt->fetch()['total'] ?? 0;

// Get weekly growth
$weekly_growth = getWeekOverWeekGrowth($pdo, 'contributions');

// Get unread alerts
$alert_count = getUnreadAlertCount($pdo, 'nibret');

// Get student risk summary
$stmt = $pdo->query("SELECT risk_level, COUNT(*) as count FROM student_payment_scores GROUP BY risk_level");
$risk_summary = $stmt->fetchAll();
$risk_data = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
foreach ($risk_summary as $r) $risk_data[$r['risk_level']] = $r['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ንብረት ክፍል - Smart Finance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #DAA520;
            --primary-dark: #B8860B;
            --primary-light: #FFD700;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            min-height: 100vh;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 15px;
            border-radius: 12px;
        }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .role-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .app-container { display: flex; padding: 0 15px 15px 15px; gap: 15px; }
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: fit-content;
        }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 25px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu li a:hover, .sidebar-menu li a.active {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            color: white;
        }
        
        .main-content { flex: 1; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-value { font-size: 28px; font-weight: bold; color: var(--primary-dark); }
        .stat-label { font-size: 13px; color: var(--gray); margin-top: 5px; }
        .trend-up { color: var(--success); }
        .trend-down { color: var(--danger); }
        
        .section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section h2 { margin-bottom: 15px; font-size: 18px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: var(--light); }
        
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        .progress-fill {
            height: 100%;
            background: var(--success);
            border-radius: 4px;
        }
        
        .btn-sm {
            padding: 5px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            margin: 0 3px;
            display: inline-block;
        }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        .alert-badge {
            position: relative;
            cursor: pointer;
        }
        .alert-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .app-container { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="user-info">
            <h1><i class="fas fa-church"></i> ንብረት ክፍል</h1>
            <span class="role-badge"><?php echo $user_role; ?></span>
        </div>
        <div style="display: flex; gap: 20px; align-items: center;">
            <div class="alert-badge" onclick="openAlertsModal()">
                <i class="fas fa-bell" style="font-size: 24px; color: var(--gray);"></i>
                <?php if ($alert_count > 0): ?>
                <span class="alert-count"><?php echo $alert_count; ?></span>
                <?php endif; ?>
            </div>
            <span><?php echo $_SESSION['user_name']; ?></span>
            <a href="../logout.php" style="background: var(--danger); color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="app-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> ዳሽቦርድ</a></li>
                <li><a href="gebi.php"><i class="fas fa-money-bill-wave"></i> ገቢ</a></li>
                <li><a href="wechi.php"><i class="fas fa-shopping-cart"></i> ወጪ</a></li>
                <li><a href="mewatewo.php"><i class="fas fa-hand-holding-heart"></i> መዋጮ</a></li>
                <li><a href="payment_status.php"><i class="fas fa-clipboard-list"></i> የክፍያ ሁኔታ</a></li>
                <li><a href="student_analytics.php"><i class="fas fa-chart-line"></i> የተማሪ ትንተና</a></li>
                <?php if ($is_admin): ?>
                <li><a href="students.php"><i class="fas fa-users"></i> ተማሪዎች</a></li>
                <li><a href="events.php"><i class="fas fa-calendar-alt"></i> ክስተቶች</a></li>
                <li><a href="simulation.php"><i class="fas fa-calculator"></i> ማስመሰል</a></li>
                <?php endif; ?>
                <?php if ($is_approver): ?>
                <li><a href="withdraw.php"><i class="fas fa-money-bill-alt"></i> ገንዘብ አውጣ</a></li>
                <?php endif; ?>
                <li><a href="transparency.php"><i class="fas fa-eye"></i> ግልጽነት</a></li>
                <li><a href="../shared/audit_trail.php"><i class="fas fa-history"></i> ኦዲት</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">ETB <?php echo number_format($balance, 2); ?></div>
                    <div class="stat-label"><i class="fas fa-coins"></i> ቀሪ ሂሳብ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">ETB <?php echo number_format($this_month_income, 2); ?></div>
                    <div class="stat-label"><i class="fas fa-chart-line"></i> የዚህ ወር ገቢ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">ETB <?php echo number_format($this_month_sales, 2); ?></div>
                    <div class="stat-label"><i class="fas fa-store"></i> ልማት ሽያጭ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">ETB <?php echo number_format($this_month_contributions, 2); ?></div>
                    <div class="stat-label">
                        <i class="fas fa-hand-holding-heart"></i> መዋጮ 
                        <span class="<?php echo $weekly_growth['growth'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <?php echo $weekly_growth['growth']; ?>%
                        </span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $pending_approvals ? count($pending_approvals) : 0; ?></div>
                    <div class="stat-label"><i class="fas fa-clock"></i> በመጠባበቅ ላይ</div>
                </div>
            </div>
            
            <!-- Risk Summary -->
            <div class="section">
                <h2><i class="fas fa-exclamation-triangle"></i> የተማሪ ስጋት ማጠቃለያ</h2>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="padding:10px 20px; background:#d4edda; border-radius:8px;">ዝቅተኛ: <?php echo $risk_data['low']; ?></div>
                    <div style="padding:10px 20px; background:#fff3cd; border-radius:8px;">መካከለኛ: <?php echo $risk_data['medium']; ?></div>
                    <div style="padding:10px 20px; background:#f8d7da; border-radius:8px;">ከፍተኛ: <?php echo $risk_data['high']; ?></div>
                    <div style="padding:10px 20px; background:#721c24; color:white; border-radius:8px;">አሳሳቢ: <?php echo $risk_data['critical']; ?></div>
                </div>
            </div>
            
            <?php if (!empty($pending_approvals)): ?>
            <div class="section">
                <h2><i class="fas fa-clock"></i> የእርስዎ በመጠባበቅ ላይ ያሉ ማፅደቂያዎች</h2>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>ቀን</th><th>አቅራቢ</th><th>ምድብ</th><th>መጠን</th><th>ማብራሪያ</th><th>የፀደቀ %</th><th>ድርጊት</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_approvals as $exp): 
                                $progress = getApprovalProgress($pdo, $exp['id']);
                            ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($exp['requested_at'])); ?></td>
                                <td><?php echo htmlspecialchars($exp['requester_name']); ?></td>
                                <td><?php echo $exp['category']; ?></td>
                                <td><strong>ETB <?php echo number_format($exp['amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($exp['description'], 0, 30)) . '...'; ?></td>
                                <td style="min-width:120px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress['total_approved']; ?>%;"></div>
                                    </div>
                                    <small><?php echo $progress['total_approved']; ?>% ጸድቋል</small>
                                </td>
                                <td>
                                    <a href="approve_expense.php?id=<?php echo $exp['id']; ?>&action=approve" class="btn-sm btn-success" onclick="return confirm('ፈቅደዋል? (የእርስዎ ክብደት: <?php echo $is_collector ? '50%' : ($is_deputy ? '30%' : '20%'); ?>)')"><i class="fas fa-check"></i> ፍቀድ</a>
                                    <a href="approve_expense.php?id=<?php echo $exp['id']; ?>&action=reject" class="btn-sm btn-danger" onclick="let reason = prompt('ምክንያት ያስገቡ:'); if(reason) this.href += '&reason=' + encodeURIComponent(reason); else return false;"><i class="fas fa-times"></i> ውድቅ</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Alerts Modal -->
    <div id="alertsModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-bell"></i> ማስጠንቀቂያዎች</h2>
            <div id="alertsList"></div>
            <button onclick="closeAlertsModal()" style="padding:10px 20px; background:var(--primary); color:white; border:none; border-radius:8px; margin-top:20px; width:100%;">ዝጋ</button>
        </div>
    </div>
    
    <script>
        function openAlertsModal() {
            fetch('../shared/get_alerts.php?department=nibret')
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    data.alerts.forEach(alert => {
                        html += `<div style="padding:15px; border-radius:10px; margin-bottom:10px; background:${alert.severity=='critical'?'#f8d7da':(alert.severity=='warning'?'#fff3cd':'#d1ecf1')}; border-left:4px solid ${alert.severity=='critical'?'var(--danger)':(alert.severity=='warning'?'var(--warning)':'var(--info)')};">
                            <strong>${alert.title}</strong>
                            <p style="margin-top:5px;">${alert.message}</p>
                            <small>${alert.created_at}</small>
                        </div>`;
                    });
                    document.getElementById('alertsList').innerHTML = html || '<p>ምንም ማስጠንቀቂያ የለም</p>';
                    document.getElementById('alertsModal').style.display = 'flex';
                });
        }
        
        function closeAlertsModal() {
            document.getElementById('alertsModal').style.display = 'none';
        }
    </script>
</body>
</html>