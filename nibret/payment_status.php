<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id']) || !canAccess('nibret')) {
    header("Location: ../index.php");
    exit();
}

$year = $_GET['year'] ?? date('Y');

// Get all active students
$stmt = $pdo->query("SELECT * FROM students WHERE is_active = 1 ORDER BY name");
$students = $stmt->fetchAll();

// Get all payments for the year
$stmt = $pdo->prepare("
    SELECT c.*, 
           DATE(CONCAT(c.year, '-', LPAD(c.month, 2, '0'), '-10')) as due_date
    FROM contributions c 
    WHERE c.year = ?
");
$stmt->execute([$year]);
$payments = $stmt->fetchAll();

// Build paid array with status
$paid_status = [];
foreach ($payments as $p) {
    $status = getPaymentStatus(true, $p['payment_date'], $p['due_date']);
    $paid_status[$p['student_id']][$p['month']] = [
        'amount' => $p['amount'],
        'status' => $status
    ];
}

// Get monthly totals
$monthly_totals = array_fill(1, 12, 0);
foreach ($payments as $p) {
    $monthly_totals[$p['month']] += $p['amount'];
}

// Get student scores for risk indicators
$stmt = $pdo->query("SELECT * FROM student_payment_scores");
$scores = [];
foreach ($stmt->fetchAll() as $s) {
    $scores[$s['student_id']] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የክፍያ ሁኔታ - ንብረት ክፍል</title>
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
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .year-selector { margin-bottom: 20px; }
        .year-selector select { padding: 8px 20px; border-radius: 8px; border: 1px solid #ddd; }
        .card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-container { overflow-x: auto; max-height: 70vh; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px 8px; text-align: center; border: 1px solid #e0e0e0; }
        th {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        td:first-child, th:first-child {
            position: sticky;
            left: 0;
            background: white;
            font-weight: 600;
            text-align: left;
            min-width: 200px;
            z-index: 5;
        }
        th:first-child { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); z-index: 15; }
        
        .status-paid { background: #d4edda !important; }
        .status-late { background: #fff3cd !important; }
        .status-missing { background: #f8d7da !important; }
        .status-pending { background: #e2e3e5 !important; }
        
        .risk-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: 8px;
        }
        .risk-low { background: #28a745; }
        .risk-medium { background: #ffc107; }
        .risk-high { background: #fd7e14; }
        .risk-critical { background: #dc3545; }
        
        .legend { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .legend-color { width: 20px; height: 20px; border-radius: 4px; }
        
        .back-btn { background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 8px; color: white; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-clipboard-list"></i> የክፍያ ሁኔታ ማትሪክስ</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <div class="legend">
            <div class="legend-item"><div class="legend-color" style="background:#d4edda;"></div> ተከፍሏል (በሰዓቱ)</div>
            <div class="legend-item"><div class="legend-color" style="background:#fff3cd;"></div> ዘግይቷል</div>
            <div class="legend-item"><div class="legend-color" style="background:#f8d7da;"></div> አልተከፈለም</div>
            <div class="legend-item"><div class="legend-color" style="background:#e2e3e5;"></div> በመጠባበቅ ላይ</div>
            <div class="legend-item"><span class="risk-indicator risk-critical"></span> ከፍተኛ ስጋት</div>
        </div>
        
        <div class="year-selector">
            <label>ዓመት: </label>
            <select onchange="window.location.href='?year='+this.value">
                <?php for($y = 2024; $y <= date('Y')+1; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>የተማሪ ስም</th>
                            <?php for($m = 1; $m <= 12; $m++): ?>
                            <th><?php echo date('M', mktime(0,0,0,$m,1)); ?></th>
                            <?php endfor; ?>
                            <th>አጠቃላይ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $student_total = 0;
                            $risk = $scores[$student['id']]['risk_level'] ?? 'low';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                <span class="risk-indicator risk-<?php echo $risk; ?>" title="ስጋት ደረጃ: <?php echo $risk; ?>"></span>
                                <br><small style="color:#888;"><?php echo htmlspecialchars($student['grade']); ?></small>
                            </td>
                            <?php for($m = 1; $m <= 12; $m++): 
                                $payment = $paid_status[$student['id']][$m] ?? null;
                                $status_class = '';
                                $display = '-';
                                
                                if ($payment) {
                                    $student_total += $payment['amount'];
                                    $status_class = 'status-' . $payment['status']['status'];
                                    $display = number_format($payment['amount'], 0);
                                } else {
                                    $today = date('Y-m-d');
                                    $due_date = date('Y-m-d', mktime(0,0,0,$m,10,$year));
                                    if ($today > $due_date) {
                                        $status_class = 'status-missing';
                                    } else {
                                        $status_class = 'status-pending';
                                    }
                                }
                            ?>
                            <td class="<?php echo $status_class; ?>">
                                <?php if ($payment): ?>
                                <strong><?php echo $display; ?></strong>
                                <br><small><?php echo $payment['status']['label']; ?></small>
                                <?php else: ?>
                                <?php echo $display; ?>
                                <?php endif; ?>
                            </td>
                            <?php endfor; ?>
                            <td><strong>ETB <?php echo number_format($student_total, 0); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f8f9fa; font-weight:bold;">
                            <td>ወርሃዊ ድምር</td>
                            <?php $year_total = 0; for($m = 1; $m <= 12; $m++): $year_total += $monthly_totals[$m]; ?>
                            <td>ETB <?php echo number_format($monthly_totals[$m], 0); ?></td>
                            <?php endfor; ?>
                            <td>ETB <?php echo number_format($year_total, 0); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>