<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id']) || !canAccess('nibret')) {
    header("Location: ../index.php");
    exit();
}

$is_admin = ($_SESSION['role'] == 'Nibret_Admin');

// Handle event actions
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_event'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $budget = $_POST['budget'];
        
        $stmt = $pdo->prepare("INSERT INTO events (name, description, start_date, end_date, budget, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $start, $end, $budget, $_SESSION['user_id']]);
        header("Location: events.php?msg=created");
        exit();
    }
    if (isset($_POST['update_status'])) {
        $event_id = $_POST['event_id'];
        $status = $_POST['status'];
        $pdo->prepare("UPDATE events SET status = ? WHERE id = ?")->execute([$status, $event_id]);
        header("Location: events.php?msg=updated");
        exit();
    }
}

// Get all events with financial data
$stmt = $pdo->query("
    SELECT 
        e.*,
        COALESCE(SUM(i.amount), 0) as total_income,
        COALESCE(SUM(exp.amount), 0) as total_expense,
        u.name as creator_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN income i ON e.id = i.event_id
    LEFT JOIN expenses exp ON e.id = exp.event_id AND exp.status = 'FULLY_APPROVED'
    GROUP BY e.id
    ORDER BY e.start_date DESC
");
$events = $stmt->fetchAll();

// Calculate summary
$total_events = count($events);
$active_events = count(array_filter($events, fn($e) => $e['status'] == 'active'));
$total_profit = array_sum(array_map(fn($e) => $e['total_income'] - $e['total_expense'], $events));

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ክስተቶች አስተዳደር - ንብረት ክፍል</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; --success: #28a745; --danger: #dc3545; --warning: #ffc107; --info: #17a2b8; }
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        .card h2 { margin-bottom: 15px; }
        
        .event-card {
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            background: white;
        }
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .event-title { font-size: 18px; font-weight: bold; }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-planned { background: var(--info); color: white; }
        .status-active { background: var(--success); color: white; }
        .status-completed { background: var(--gray); color: white; }
        .status-cancelled { background: var(--danger); color: white; }
        
        .financials {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .financial-item { text-align: center; }
        .financial-value { font-size: 20px; font-weight: bold; }
        .financial-label { font-size: 12px; color: #666; }
        .profit { color: var(--success); }
        .loss { color: var(--danger); }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-primary { background: var(--primary); color: white; }
        
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill { height: 100%; background: var(--primary); }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-calendar-alt"></i> ክስተቶች እና የትርፍ/ኪሳራ ክትትል</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <?php if ($msg == 'created'): ?>
        <div class="flash flash-success">ክስተት በተሳካ ሁኔታ ተፈጥሯል!</div>
        <?php elseif ($msg == 'updated'): ?>
        <div class="flash flash-success">ሁኔታ ተሻሽሏል!</div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_events; ?></div>
                <div class="stat-label">ጠቅላላ ክስተቶች</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_events; ?></div>
                <div class="stat-label">ንቁ ክስተቶች</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: <?php echo $total_profit >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                    ETB <?php echo number_format($total_profit, 2); ?>
                </div>
                <div class="stat-label">ጠቅላላ ትርፍ/ኪሳራ</div>
            </div>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> አዲስ ክስተት ፍጠር</h2>
            <form method="POST">
                <div class="form-group">
                    <label>የክስተት ስም</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>ማብራሪያ</label>
                    <textarea name="description" rows="2"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>የሚጀምርበት ቀን</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>የሚያበቃበት ቀን</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>በጀት (ETB)</label>
                    <input type="number" name="budget" step="0.01" value="0">
                </div>
                <button type="submit" name="create_event" class="btn btn-primary"><i class="fas fa-save"></i> ክስተት ፍጠር</button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-list"></i> ሁሉም ክስተቶች</h2>
            <?php foreach ($events as $event): 
                $profit = $event['total_income'] - $event['total_expense'];
                $budget_usage = $event['budget'] > 0 ? ($event['total_expense'] / $event['budget']) * 100 : 0;
            ?>
            <div class="event-card">
                <div class="event-header">
                    <div>
                        <div class="event-title"><?php echo htmlspecialchars($event['name']); ?></div>
                        <div style="color: #666; font-size: 13px; margin-top: 5px;">
                            <i class="far fa-calendar"></i> 
                            <?php echo date('M d, Y', strtotime($event['start_date'])); ?> - 
                            <?php echo date('M d, Y', strtotime($event['end_date'])); ?>
                        </div>
                        <?php if ($event['description']): ?>
                        <div style="margin-top: 10px; color: #555;"><?php echo htmlspecialchars($event['description']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo $event['status']; ?>">
                            <?php 
                            $status_labels = [
                                'planned' => 'ታቅዷል',
                                'active' => 'በሂደት ላይ',
                                'completed' => 'ተጠናቋል',
                                'cancelled' => 'ተሰርዟል'
                            ];
                            echo $status_labels[$event['status']] ?? $event['status'];
                            ?>
                        </span>
                        <?php if ($is_admin && $event['status'] != 'completed' && $event['status'] != 'cancelled'): ?>
                        <form method="POST" style="display: inline; margin-left: 10px;">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 4px;">
                                <option value="">ሁኔታ ቀይር</option>
                                <option value="planned">ታቅዷል</option>
                                <option value="active">በሂደት ላይ</option>
                                <option value="completed">ተጠናቋል</option>
                                <option value="cancelled">ተሰርዟል</option>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($event['budget'] > 0): ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px;">
                        <span>በጀት አጠቃቀም</span>
                        <span>ETB <?php echo number_format($event['total_expense'], 2); ?> / ETB <?php echo number_format($event['budget'], 2); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min($budget_usage, 100); ?>%; background: <?php echo $budget_usage > 100 ? 'var(--danger)' : 'var(--primary)'; ?>;"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="financials">
                    <div class="financial-item">
                        <div class="financial-value" style="color: var(--success);">ETB <?php echo number_format($event['total_income'], 2); ?></div>
                        <div class="financial-label">ጠቅላላ ገቢ</div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-value" style="color: var(--danger);">ETB <?php echo number_format($event['total_expense'], 2); ?></div>
                        <div class="financial-label">ጠቅላላ ወጪ</div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-value <?php echo $profit >= 0 ? 'profit' : 'loss'; ?>">
                            ETB <?php echo number_format($profit, 2); ?>
                        </div>
                        <div class="financial-label">ትርፍ/ኪሳራ</div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-value">
                            <?php echo $event['total_income'] > 0 ? round(($profit / $event['total_income']) * 100, 1) : 0; ?>%
                        </div>
                        <div class="financial-label">የትርፍ መጠን</div>
                    </div>
                </div>
                
                <div style="margin-top: 10px; font-size: 12px; color: #999;">
                    <i class="far fa-user"></i> የፈጠረው: <?php echo htmlspecialchars($event['creator_name']); ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($events)): ?>
            <p style="text-align: center; padding: 40px; color: #999;">
                <i class="fas fa-calendar" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                ምንም ክስተት የለም
            </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>