<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$is_admin = ($_SESSION['role'] == 'Lmat_Admin' || $_SESSION['role'] == 'Nibret_Admin');
$department = $_SESSION['department'];

// Filter parameters
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$sql = "
    SELECT 
        a.*,
        u.name as user_name,
        u.department
    FROM audit_logs a
    JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!$is_admin) {
    $sql .= " AND u.department = ?";
    $params[] = $department;
}

if ($action_filter) {
    $sql .= " AND a.action = ?";
    $params[] = $action_filter;
}

if ($table_filter) {
    $sql .= " AND a.table_name = ?";
    $params[] = $table_filter;
}

if ($user_filter) {
    $sql .= " AND a.user_id = ?";
    $params[] = $user_filter;
}

$sql .= " AND DATE(a.created_at) BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

$sql .= " ORDER BY a.created_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get users for filter
$stmt = $pdo->query("SELECT id, name, department FROM users ORDER BY name");
$users = $stmt->fetchAll();

// Get unique actions and tables
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
$tables = $pdo->query("SELECT DISTINCT table_name FROM audit_logs WHERE table_name IS NOT NULL ORDER BY table_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ኦዲት ትራክ - የእንቅስቃሴ ታሪክ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; }
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
        
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group { min-width: 150px; }
        .filter-group label { display: block; font-size: 12px; color: #666; margin-bottom: 5px; }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .btn-filter {
            padding: 8px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        
        .action-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .action-INSERT { background: #d4edda; color: #155724; }
        .action-UPDATE { background: #fff3cd; color: #856404; }
        .action-DELETE { background: #f8d7da; color: #721c24; }
        .action-APPROVE { background: #cfe2ff; color: #084298; }
        .action-REJECT { background: #f8d7da; color: #721c24; }
        .action-LOGIN { background: #e2e3e5; color: #383d41; }
        
        .json-view {
            max-width: 300px;
            max-height: 100px;
            overflow: auto;
            font-size: 11px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 4px;
        }
        
        .pagination { margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-history"></i> ኦዲት ትራክ - የእንቅስቃሴ ታሪክ</h1>
        <a href="../<?php echo $_SESSION['department']; ?>/index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <div class="filter-bar">
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%;">
                <div class="filter-group">
                    <label>እርምጃ</label>
                    <select name="action">
                        <option value="">ሁሉም</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?php echo $a['action']; ?>" <?php echo $action_filter == $a['action'] ? 'selected' : ''; ?>>
                            <?php echo $a['action']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>ሰንጠረዥ</label>
                    <select name="table">
                        <option value="">ሁሉም</option>
                        <?php foreach ($tables as $t): ?>
                        <option value="<?php echo $t['table_name']; ?>" <?php echo $table_filter == $t['table_name'] ? 'selected' : ''; ?>>
                            <?php echo $t['table_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>ተጠቃሚ</label>
                    <select name="user_id">
                        <option value="">ሁሉም</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?> (<?php echo $u['department']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>ከ</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label>እስከ</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div>
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> አጣራ</button>
                    <a href="audit_trail.php" class="btn-filter" style="background: #6c757d; text-decoration: none;">አጽዳ</a>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ቀን እና ሰዓት</th>
                            <th>ተጠቃሚ</th>
                            <th>ክፍል</th>
                            <th>እርምጃ</th>
                            <th>ሰንጠረዥ</th>
                            <th>መዝገብ ቁጥር</th>
                            <th>የቀድሞ ዋጋ</th>
                            <th>አዲስ ዋጋ</th>
                            <th>IP አድራሻ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                            <td><?php echo $log['department']; ?></td>
                            <td>
                                <span class="action-badge action-<?php echo $log['action']; ?>">
                                    <?php 
                                    $action_labels = [
                                        'INSERT' => 'አክሏል',
                                        'UPDATE' => 'አርትዕ',
                                        'DELETE' => 'ሰርዟል',
                                        'APPROVE' => 'አጽድቋል',
                                        'REJECT' => 'ውድቅ አድርጓል',
                                        'LOGIN' => 'ገብቷል',
                                        'LOGOUT' => 'ወጥቷል'
                                    ];
                                    echo $action_labels[$log['action']] ?? $log['action'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $log['table_name'] ?? '-'; ?></td>
                            <td><?php echo $log['record_id'] ?? '-'; ?></td>
                            <td>
                                <?php if ($log['old_values']): ?>
                                <div class="json-view"><?php echo htmlspecialchars($log['old_values']); ?></div>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['new_values']): ?>
                                <div class="json-view"><?php echo htmlspecialchars($log['new_values']); ?></div>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><?php echo $log['ip_address'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px;">ምንም መረጃ አልተገኘም</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>