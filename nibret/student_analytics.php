<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id']) || !canAccess('nibret')) {
    header("Location: ../index.php");
    exit();
}

// Get all students with their payment scores
$stmt = $pdo->query("
    SELECT 
        s.*,
        COALESCE(ps.consistency_score, 100) as consistency_score,
        COALESCE(ps.total_months_paid, 0) as total_paid,
        COALESCE(ps.total_months_expected, 0) as total_expected,
        COALESCE(ps.on_time_payments, 0) as on_time,
        COALESCE(ps.late_payments, 0) as late,
        COALESCE(ps.missed_payments, 0) as missed,
        COALESCE(ps.longest_gap_days, 0) as longest_gap,
        COALESCE(ps.current_streak, 0) as streak,
        ps.last_payment_date,
        ps.risk_level
    FROM students s
    LEFT JOIN student_payment_scores ps ON s.id = ps.student_id
    WHERE s.is_active = 1
    ORDER BY 
        CASE ps.risk_level 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            ELSE 5 
        END,
        ps.consistency_score ASC
");
$students = $stmt->fetchAll();

// Risk summary
$risk_counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
foreach ($students as $s) {
    $risk = $s['risk_level'] ?? 'low';
    $risk_counts[$risk]++;
}

// Get payment trends over time
$stmt = $pdo->query("
    SELECT 
        YEAR(payment_date) as year,
        MONTH(payment_date) as month,
        COUNT(*) as payment_count,
        SUM(amount) as total_amount
    FROM contributions
    WHERE payment_date IS NOT NULL
    GROUP BY YEAR(payment_date), MONTH(payment_date)
    ORDER BY year DESC, month DESC
    LIMIT 12
");
$monthly_trends = array_reverse($stmt->fetchAll());

// Get top performers
$top_performers = array_filter($students, fn($s) => ($s['consistency_score'] ?? 0) >= 90);
$at_risk = array_filter($students, fn($s) => in_array($s['risk_level'] ?? 'low', ['critical', 'high']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የተማሪ ክፍያ ትንተና - ንብረት ክፍል</title>
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
        .stat-value { font-size: 28px; font-weight: bold; }
        .stat-label { font-size: 13px; color: #666; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 { margin-bottom: 15px; font-size: 18px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; cursor: pointer; }
        th:hover { background: #e9ecef; }
        
        .risk-critical { background: #f8d7da !important; }
        .risk-high { background: #fff3cd !important; }
        .risk-medium { background: #e2e3e5 !important; }
        .risk-low { background: #d4edda !important; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-critical { background: #dc3545; color: white; }
        .badge-high { background: #fd7e14; color: white; }
        .badge-medium { background: #ffc107; color: #333; }
        .badge-low { background: #28a745; color: white; }
        
        .progress-bar {
            width: 80px;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill { height: 100%; border-radius: 3px; }
        
        .search-box { margin-bottom: 15px; }
        .search-box input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .chart-container { height: 250px; }
        
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .filter-tab {
            padding: 8px 16px;
            border: none;
            background: #e9ecef;
            border-radius: 20px;
            cursor: pointer;
        }
        .filter-tab.active { background: var(--primary); color: white; }
        
        @media (max-width: 768px) {
            table { font-size: 12px; }
            th, td { padding: 8px 4px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-chart-line"></i> የተማሪ ክፍያ ትንተና</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);"><?php echo count($top_performers); ?></div>
                <div class="stat-label">ከፍተኛ አፈጻጸም</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--danger);"><?php echo count($at_risk); ?></div>
                <div class="stat-label">ስጋት ላይ ያሉ</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $risk_counts['critical']; ?></div>
                <div class="stat-label">አሳሳቢ</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $risk_counts['high']; ?></div>
                <div class="stat-label">ከፍተኛ ስጋት</div>
            </div>
            <div class="stat-card">
                <?php 
                $avg_consistency = count($students) > 0 ? 
                    round(array_sum(array_column($students, 'consistency_score')) / count($students)) : 0;
                ?>
                <div class="stat-value"><?php echo $avg_consistency; ?>%</div>
                <div class="stat-label">አማካይ ወጥነት</div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Risk Distribution Chart -->
            <div class="card">
                <h2><i class="fas fa-chart-pie"></i> የስጋት ስርጭት</h2>
                <div class="chart-container">
                    <canvas id="riskChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Trend Chart -->
            <div class="card">
                <h2><i class="fas fa-chart-bar"></i> ወርሃዊ የክፍያ አዝማሚያ</h2>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="card">
            <h2><i class="fas fa-users"></i> የተማሪ ዝርዝር ትንተና</h2>
            
            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterTable('all')">ሁሉም</button>
                <button class="filter-tab" onclick="filterTable('critical')">አሳሳቢ</button>
                <button class="filter-tab" onclick="filterTable('high')">ከፍተኛ</button>
                <button class="filter-tab" onclick="filterTable('medium')">መካከለኛ</button>
                <button class="filter-tab" onclick="filterTable('low')">ዝቅተኛ</button>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 ተማሪ ፈልግ..." onkeyup="searchTable()">
            </div>
            
            <div style="overflow-x: auto;">
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">ስም <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(1)">ክፍል <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(2)">ወጥነት <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(3)">የተከፈለ/የሚጠበቅ <i class="fas fa-sort"></i></th>
                            <th>በሰዓቱ</th>
                            <th>ዘግይቷል</th>
                            <th>ያልተከፈለ</th>
                            <th>ረጅሙ ክፍተት</th>
                            <th>ተከታታይ</th>
                            <th>ስጋት</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr class="risk-<?php echo $student['risk_level'] ?? 'low'; ?>" data-risk="<?php echo $student['risk_level'] ?? 'low'; ?>">
                            <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['grade'] ?? '-'); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span><?php echo $student['consistency_score'] ?? 100; ?>%</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $student['consistency_score'] ?? 100; ?>%; background: <?php echo ($student['consistency_score'] ?? 100) >= 80 ? 'var(--success)' : (($student['consistency_score'] ?? 100) >= 50 ? 'var(--warning)' : 'var(--danger)'); ?>;"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo ($student['total_paid'] ?? 0); ?>/<?php echo ($student['total_expected'] ?? 0); ?></td>
                            <td style="color: var(--success);"><?php echo $student['on_time'] ?? 0; ?></td>
                            <td style="color: var(--warning);"><?php echo $student['late'] ?? 0; ?></td>
                            <td style="color: var(--danger);"><?php echo $student['missed'] ?? 0; ?></td>
                            <td><?php echo $student['longest_gap'] ?? 0; ?> ቀናት</td>
                            <td><?php echo $student['streak'] ?? 0; ?> ወራት</td>
                            <td>
                                <span class="badge badge-<?php echo $student['risk_level'] ?? 'low'; ?>">
                                    <?php 
                                    $risk_labels = [
                                        'critical' => 'አሳሳቢ',
                                        'high' => 'ከፍተኛ',
                                        'medium' => 'መካከለኛ',
                                        'low' => 'ዝቅተኛ'
                                    ];
                                    echo $risk_labels[$student['risk_level'] ?? 'low'] ?? 'ዝቅተኛ';
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Risk Chart
        new Chart(document.getElementById('riskChart'), {
            type: 'doughnut',
            data: {
                labels: ['አሳሳቢ', 'ከፍተኛ', 'መካከለኛ', 'ዝቅተኛ'],
                datasets: [{
                    data: [<?php echo $risk_counts['critical']; ?>, <?php echo $risk_counts['high']; ?>, <?php echo $risk_counts['medium']; ?>, <?php echo $risk_counts['low']; ?>],
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
        
        // Trend Chart
        const trendData = <?php echo json_encode($monthly_trends); ?>;
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendData.map(d => d.year + '-' + String(d.month).padStart(2, '0')),
                datasets: [{
                    label: 'የተከፈለ መጠን (ETB)',
                    data: trendData.map(d => d.total_amount),
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
                    legend: { display: false }
                }
            }
        });
        
        // Filter functions
        function filterTable(risk) {
            document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            const rows = document.querySelectorAll('#studentTable tbody tr');
            rows.forEach(row => {
                if (risk === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.risk === risk ? '' : 'none';
                }
            });
        }
        
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('#studentTable tbody tr');
            
            rows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                row.style.display = name.includes(filter) ? '' : 'none';
            });
        }
        
        function sortTable(col) {
            const table = document.getElementById('studentTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                let aVal = a.cells[col].textContent.trim();
                let bVal = b.cells[col].textContent.trim();
                
                // Remove % and convert to number if numeric
                aVal = aVal.replace('%', '');
                bVal = bVal.replace('%', '');
                
                const aNum = parseFloat(aVal);
                const bNum = parseFloat(bVal);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return bNum - aNum;
                }
                return aVal.localeCompare(bVal);
            });
            
            rows.forEach(row => tbody.appendChild(row));
        }
    </script>
</body>
</html>