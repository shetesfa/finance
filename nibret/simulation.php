<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id']) || !canAccess('nibret') || $_SESSION['role'] != 'Nibret_Admin') {
    header("Location: index.php");
    exit();
}

$balance = getBalance($pdo);

// Get historical monthly averages
$stmt = $pdo->query("
    SELECT 
        AVG(monthly_income) as avg_income,
        AVG(monthly_expense) as avg_expense,
        AVG(monthly_contributions) as avg_contributions
    FROM (
        SELECT 
            DATE_FORMAT(recorded_date, '%Y-%m') as month,
            SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as monthly_income,
            SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) as monthly_expense,
            SUM(CASE WHEN source = 'contribution' THEN amount ELSE 0 END) as monthly_contributions
        FROM income
        WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(recorded_date, '%Y-%m')
    ) as monthly_data
");
$averages = $stmt->fetch();

$avg_monthly_income = $averages['avg_income'] ?? 5000;
$avg_monthly_expense = $averages['avg_expense'] ?? 3000;

$simulation_result = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $scenario_type = $_POST['scenario_type'];
    $months = intval($_POST['months'] ?? 3);
    $additional_expense = floatval($_POST['additional_expense'] ?? 0);
    $income_change = floatval($_POST['income_change'] ?? 0);
    
    $current_balance = $balance;
    $projected_balances = [];
    $running_balance = $current_balance;
    
    for ($i = 1; $i <= $months; $i++) {
        if ($scenario_type == 'normal') {
            $monthly_net = $avg_monthly_income - $avg_monthly_expense;
        } elseif ($scenario_type == 'optimistic') {
            $monthly_net = ($avg_monthly_income * 1.2) - ($avg_monthly_expense * 0.9);
        } elseif ($scenario_type == 'pessimistic') {
            $monthly_net = ($avg_monthly_income * 0.8) - ($avg_monthly_expense * 1.1);
        } else {
            $monthly_net = ($avg_monthly_income * (1 + $income_change/100)) - $avg_monthly_expense;
        }
        
        $monthly_net -= $additional_expense;
        $running_balance += $monthly_net;
        
        $projected_balances[] = [
            'month' => $i,
            'date' => date('M Y', strtotime("+$i months")),
            'net' => $monthly_net,
            'balance' => $running_balance
        ];
    }
    
    $simulation_result = [
        'current_balance' => $current_balance,
        'final_balance' => $running_balance,
        'total_change' => $running_balance - $current_balance,
        'projections' => $projected_balances,
        'risk_level' => $running_balance < 0 ? 'critical' : ($running_balance < 5000 ? 'high' : ($running_balance < 20000 ? 'medium' : 'low')),
        'months_until_zero' => null
    ];
    
    // Calculate months until balance reaches zero
    $test_balance = $current_balance;
    $months_to_zero = 0;
    $avg_net = array_sum(array_column($projected_balances, 'net')) / count($projected_balances);
    if ($avg_net < 0) {
        $months_to_zero = ceil(abs($current_balance / $avg_net));
        $simulation_result['months_until_zero'] = $months_to_zero;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ሁኔታ ማስመሰል - ንብረት ክፍል</title>
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
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 { margin-bottom: 20px; }
        
        .current-balance {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
        }
        .current-balance .amount { font-size: 36px; font-weight: bold; }
        
        .scenario-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .scenario-btn {
            padding: 15px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .scenario-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-primary { background: var(--primary); color: white; width: 100%; }
        
        .result-card {
            margin-top: 20px;
            padding: 20px;
            border-radius: 12px;
        }
        .result-critical { background: #f8d7da; border-left: 4px solid var(--danger); }
        .result-high { background: #fff3cd; border-left: 4px solid var(--warning); }
        .result-medium { background: #e2e3e5; border-left: 4px solid var(--info); }
        .result-low { background: #d4edda; border-left: 4px solid var(--success); }
        
        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .result-stat { text-align: center; }
        .result-value { font-size: 24px; font-weight: bold; }
        .result-label { font-size: 12px; color: #666; }
        
        .chart-container { height: 300px; margin-top: 20px; }
        
        .warning-box {
            background: var(--warning);
            color: #333;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-calculator"></i> የፋይናንስ ሁኔታ ማስመሰል</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <div class="current-balance">
            <div style="font-size: 14px; opacity: 0.9;">የአሁን ቀሪ ሂሳብ</div>
            <div class="amount">ETB <?php echo number_format($balance, 2); ?></div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-sliders-h"></i> የማስመሰያ መለኪያዎች</h2>
            <form method="POST" id="simulationForm">
                <div class="scenario-buttons">
                    <button type="button" class="scenario-btn active" onclick="setScenario('normal')">መደበኛ</button>
                    <button type="button" class="scenario-btn" onclick="setScenario('optimistic')">ብሩህ ተስፋ</button>
                    <button type="button" class="scenario-btn" onclick="setScenario('pessimistic')">አፍራሽ</button>
                    <button type="button" class="scenario-btn" onclick="setScenario('custom')">ብጁ</button>
                </div>
                <input type="hidden" name="scenario_type" id="scenarioType" value="normal">
                
                <div class="form-group">
                    <label>የሚተነበዩ ወራት ብዛት</label>
                    <input type="number" name="months" value="6" min="1" max="24" required>
                </div>
                
                <div id="customFields" style="display: none;">
                    <div class="form-group">
                        <label>የገቢ ለውጥ (%)</label>
                        <input type="number" name="income_change" value="0" step="1" min="-100" max="100">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>ተጨማሪ ወርሃዊ ወጪ (ETB)</label>
                    <input type="number" name="additional_expense" value="0" step="100">
                </div>
                
                <button type="submit" name="simulate" class="btn btn-primary"><i class="fas fa-play"></i> አስመስል</button>
            </form>
        </div>
        
        <?php if ($simulation_result): ?>
        <div class="card result-card result-<?php echo $simulation_result['risk_level']; ?>">
            <h2><i class="fas fa-chart-line"></i> የማስመሰል ውጤት</h2>
            
            <div class="result-stats">
                <div class="result-stat">
                    <div class="result-value">ETB <?php echo number_format($simulation_result['current_balance'], 2); ?></div>
                    <div class="result-label">የአሁን ቀሪ ሂሳብ</div>
                </div>
                <div class="result-stat">
                    <div class="result-value" style="color: <?php echo $simulation_result['final_balance'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                        ETB <?php echo number_format($simulation_result['final_balance'], 2); ?>
                    </div>
                    <div class="result-label">የመጨረሻ ቀሪ ሂሳብ</div>
                </div>
                <div class="result-stat">
                    <div class="result-value" style="color: <?php echo $simulation_result['total_change'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                        <?php echo $simulation_result['total_change'] >= 0 ? '+' : ''; ?>ETB <?php echo number_format($simulation_result['total_change'], 2); ?>
                    </div>
                    <div class="result-label">ጠቅላላ ለውጥ</div>
                </div>
            </div>
            
            <?php if ($simulation_result['months_until_zero']): ?>
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>ማስጠንቀቂያ:</strong> በዚህ አዝማሚያ ከሄደ ቀሪ ሂሳቡ በ 
                <strong><?php echo $simulation_result['months_until_zero']; ?> ወራት</strong> ውስጥ ዜሮ ይደርሳል!
            </div>
            <?php endif; ?>
            
            <div class="chart-container">
                <canvas id="projectionChart"></canvas>
            </div>
            
            <div style="margin-top: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 10px;">ወር</th>
                            <th style="padding: 10px;">ቀን</th>
                            <th style="padding: 10px;">የተጣራ ለውጥ</th>
                            <th style="padding: 10px;">ቀሪ ሂሳብ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulation_result['projections'] as $proj): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><?php echo $proj['month']; ?></td>
                            <td style="padding: 10px;"><?php echo $proj['date']; ?></td>
                            <td style="padding: 10px; color: <?php echo $proj['net'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo $proj['net'] >= 0 ? '+' : ''; ?>ETB <?php echo number_format($proj['net'], 2); ?>
                            </td>
                            <td style="padding: 10px; font-weight: bold;">ETB <?php echo number_format($proj['balance'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function setScenario(type) {
            document.getElementById('scenarioType').value = type;
            document.querySelectorAll('.scenario-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            if (type === 'custom') {
                document.getElementById('customFields').style.display = 'block';
            } else {
                document.getElementById('customFields').style.display = 'none';
            }
        }
        
        <?php if ($simulation_result): ?>
        const projectionData = <?php echo json_encode($simulation_result['projections']); ?>;
        
        new Chart(document.getElementById('projectionChart'), {
            type: 'line',
            data: {
                labels: projectionData.map(p => p.date),
                datasets: [{
                    label: 'የሚገመት ቀሪ ሂሳብ',
                    data: projectionData.map(p => p.balance),
                    borderColor: '#DAA520',
                    backgroundColor: '#DAA52020',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: ctx => 'ETB ' + ctx.raw.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: value => 'ETB ' + value
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>