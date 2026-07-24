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

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_admin = ($user_role == 'Nibret_Admin');

// Handle contribution via AJAX or POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_contribution'])) {
    $student_id = $_POST['student_id'];
    $year = $_POST['year'];
    $month = $_POST['month'];
    $amount = $_POST['amount'];
    
    // Check if already recorded
    $stmt = $pdo->prepare("SELECT id FROM contributions WHERE student_id = ? AND year = ? AND month = ?");
    $stmt->execute([$student_id, $year, $month]);
    
    if (!$stmt->fetch()) {
        $receipt = 'CONT-' . date('Ymd') . '-' . rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO contributions (student_id, year, month, amount, receipt_number, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $year, $month, $amount, $receipt, $user_id]);
        $contribution_id = $pdo->lastInsertId();
        
        // Add to income
        $month_name = date('F', mktime(0,0,0,$month,1));
        $stmt = $pdo->prepare("INSERT INTO income (source, reference_id, amount, description, recorded_by, recorded_date) 
                               VALUES ('contribution', ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$contribution_id, $amount, "ወርሃዊ መዋጮ - $month_name $year", $user_id]);
        
        // Return JSON for AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true, 'amount' => $amount]);
            exit();
        }
        
        header("Location: mewatewo.php?msg=added");
        exit();
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => 'already_paid']);
            exit();
        }
        header("Location: mewatewo.php?error=exists");
        exit();
    }
}

// Get all students
$stmt = $pdo->query("SELECT * FROM students WHERE is_active = 1 ORDER BY name");
$students = $stmt->fetchAll();

// Get current year or selected year
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get contributions for selected year
$stmt = $pdo->prepare("SELECT student_id, month, amount FROM contributions WHERE year = ?");
$stmt->execute([$year]);
$contributions = $stmt->fetchAll();

// Build paid array
$paid = [];
foreach ($contributions as $c) {
    $paid[$c['student_id']][$c['month']] = $c['amount'];
}

// Get monthly totals
$stmt = $pdo->prepare("SELECT month, SUM(amount) as total FROM contributions WHERE year = ? GROUP BY month");
$stmt->execute([$year]);
$monthly_totals = $stmt->fetchAll();
$monthly_data = [];
foreach ($monthly_totals as $mt) {
    $monthly_data[$mt['month']] = $mt['total'];
}

// Get total for year
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM contributions WHERE year = ?");
$stmt->execute([$year]);
$total_year = $stmt->fetch()['total'] ?? 0;

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>መዋጮ - ንብረት ክፍል</title>
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
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .flash { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; animation: slideDown 0.3s ease; }
        .flash-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .flash-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
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
        .card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 { margin-bottom: 20px; color: #333; font-size: 18px; }
        .year-selector {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .year-selector select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .history-link {
            background: #17a2b8;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .contributions-table {
            overflow-x: auto;
            max-height: 70vh;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            padding: 12px 8px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        th {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            color: white;
            font-weight: 600;
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
            min-width: 180px;
            z-index: 5;
        }
        th:first-child {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            z-index: 15;
        }
        .month-cell {
            cursor: pointer;
            transition: all 0.2s;
            background: #f9f9f9;
        }
        .month-cell:hover {
            background: var(--primary-light);
            transform: scale(1.02);
        }
        .paid-cell {
            background: #d4edda;
            cursor: default;
        }
        .paid-cell:hover {
            background: #d4edda;
            transform: none;
        }
        .check-icon {
            color: #28a745;
            font-size: 20px;
        }
        .cross-icon {
            color: #dc3545;
            font-size: 16px;
        }
        .amount-text {
            font-size: 11px;
            display: block;
            margin-top: 3px;
            color: #155724;
        }
        .month-total {
            background: #e9ecef;
            font-weight: bold;
        }
        .month-total td {
            background: #e9ecef;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }
        .modal-content input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .modal-content button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 5px;
        }
        .btn-save {
            background: var(--primary);
            color: white;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 0.5s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            th, td { font-size: 11px; padding: 6px 3px; }
            td:first-child, th:first-child { min-width: 120px; }
            .stat-number { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-hand-holding-heart"></i> መዋጮ - Monthly Contributions</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <?php if ($msg == 'added'): ?>
        <div class="flash flash-success">✅ መዋጮ ተመዝግቧል!</div>
        <?php elseif ($error == 'exists'): ?>
        <div class="flash flash-error">⚠️ ለዚህ ወር መዋጮ አስቀድሞ ተመዝግቧል!</div>
        <?php endif; ?>
        
        <div class="stats-row">
            <div class="stat-box">
                <h3><i class="fas fa-calendar"></i> ጠቅላላ መዋጮ (<?php echo $year; ?>)</h3>
                <div class="stat-number">ETB <?php echo number_format($total_year, 2); ?></div>
            </div>
            <div class="stat-box">
                <h3><i class="fas fa-users"></i> ንቁ ተማሪዎች</h3>
                <div class="stat-number"><?php echo count($students); ?></div>
            </div>
            <div class="stat-box">
                <h3><i class="fas fa-hand-pointer"></i> መመዝገቢያ</h3>
                <div class="stat-number" style="font-size: 16px;">በሴል ላይ ጠቅ ያድርጉ</div>
            </div>
        </div>
        
        <div class="card">
            <div class="year-selector">
                <label><i class="fas fa-calendar-alt"></i> ዓመት ምረጥ: </label>
                <select id="yearSelect" onchange="changeYear(this.value)">
                    <?php for($y = 2020; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <a href="history.php" class="history-link"><i class="fas fa-history"></i> የክፍያ ታሪክ ይመልከቱ</a>
                <?php if ($is_admin): ?>
                <a href="students.php" class="history-link" style="background: var(--primary);"><i class="fas fa-user-plus"></i> አዲስ ተማሪ ጨምር</a>
                <?php endif; ?>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchStudent" placeholder="🔍 ተማሪ ፈልግ..." onkeyup="filterTable()">
            </div>
            
            <div class="contributions-table">
                <table id="contributionsTable">
                    <thead>
                        <tr>
                            <th>የተማሪ ስም</th>
                            <?php for($m = 1; $m <= 12; $m++): ?>
                            <th><?php echo date('M', mktime(0,0,0,$m,1)); ?></th>
                            <?php endfor; ?>
                         </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) > 0): ?>
                            <?php foreach ($students as $student): ?>
                            <tr class="student-row" data-name="<?php echo strtolower($student['name']); ?>" data-id="<?php echo $student['id']; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                    <?php if ($student['grade']): ?>
                                    <br><small style="color:#888;"><?php echo htmlspecialchars($student['grade']); ?></small>
                                    <?php endif; ?>
                                 </div>
                                <?php for($m = 1; $m <= 12; $m++): ?>
                                    <?php $is_paid = isset($paid[$student['id']][$m]); ?>
                                    <td class="month-cell <?php echo $is_paid ? 'paid-cell' : ''; ?>" 
                                        onclick="<?php echo $is_paid ? '' : "openModal({$student['id']}, '{$student['name']}', {$year}, {$m});"; ?>"
                                        style="<?php echo $is_paid ? '' : 'cursor: pointer;'; ?>">
                                        <?php if ($is_paid): ?>
                                            <i class="fas fa-check-circle check-icon"></i>
                                            <span class="amount-text"><?php echo number_format($paid[$student['id']][$m], 2); ?></span>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle cross-icon"></i>
                                            <span class="amount-text" style="color:#999;">አልተመዘገበም</span>
                                        <?php endif; ?>
                                     </div>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" style="text-align: center; padding: 50px;">
                                    <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                    ምንም ተማሪ የለም<br>
                                    <small>በመጀመሪያ ተማሪዎችን ይመዝግቡ</small>
                                 </div>
                            </tr>
                        <?php endif; ?>
                        <?php if (count($students) > 0): ?>
                        <tr class="month-total">
                            <td><strong>ወርሃዊ ድምር</strong></td>
                            <?php for($m = 1; $m <= 12; $m++): ?>
                            <td><strong>ETB <?php echo number_format($monthly_data[$m] ?? 0, 2); ?></strong></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal for recording contribution -->
    <div id="contributionModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">📝 መዋጮ መዝግብ</h3>
            <p id="modalInfo" style="margin-bottom: 15px; color: #666;"></p>
            <input type="number" id="contributionAmount" step="0.01" value="100" placeholder="መጠን (ETB)">
            <div>
                <button class="btn-save" onclick="saveContribution()"><i class="fas fa-save"></i> መዝግብ</button>
                <button class="btn-cancel" onclick="closeModal()"><i class="fas fa-times"></i> ዝጋ</button>
            </div>
            <div id="modalLoading" style="display: none; margin-top: 15px;">
                <div class="loading"></div> በመዝገብ ላይ...
            </div>
        </div>
    </div>

    <script>
        let currentStudent = null;
        let currentStudentName = null;
        let currentYear = null;
        let currentMonth = null;
        
        function changeYear(year) {
            window.location.href = 'mewatewo.php?year=' + year;
        }
        
        function filterTable() {
            const input = document.getElementById('searchStudent');
            const filter = input.value.toLowerCase();
            const rows = document.getElementsByClassName('student-row');
            for (let i = 0; i < rows.length; i++) {
                const name = rows[i].getAttribute('data-name');
                if (name && name.includes(filter)) {
                    rows[i].style.display = '';
                } else if (rows[i]) {
                    rows[i].style.display = 'none';
                }
            }
        }
        
        function openModal(studentId, studentName, year, month) {
            currentStudent = studentId;
            currentStudentName = studentName;
            currentYear = year;
            currentMonth = month;
            
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                'July', 'August', 'September', 'October', 'November', 'December'];
            
            document.getElementById('modalInfo').innerHTML = 
                `<strong>${studentName}</strong><br>
                 ወር: ${monthNames[month-1]} ${year}<br>
                 መዋጮ መጠን ያስገቡ`;
            document.getElementById('contributionAmount').value = 100;
            document.getElementById('contributionModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('contributionModal').style.display = 'none';
            currentStudent = null;
        }
        
        function saveContribution() {
            if (!currentStudent) return;
            
            const amount = document.getElementById('contributionAmount').value;
            if (!amount || amount <= 0) {
                alert('እባክዎ ትክክለኛ መጠን ያስገቡ!');
                return;
            }
            
            // Show loading
            document.getElementById('modalLoading').style.display = 'block';
            document.querySelector('.btn-save').disabled = true;
            document.querySelector('.btn-cancel').disabled = true;
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('record_contribution', '1');
            formData.append('student_id', currentStudent);
            formData.append('year', currentYear);
            formData.append('month', currentMonth);
            formData.append('amount', amount);
            
            fetch('mewatewo.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success and reload page to update table
                    alert(`✅ መዋጮ ተመዝግቧል!\nመጠን: ETB ${parseFloat(amount).toFixed(2)}`);
                    window.location.reload();
                } else {
                    if (data.error === 'already_paid') {
                        alert('⚠️ ለዚህ ወር መዋጮ አስቀድሞ ተመዝግቧል!');
                    } else {
                        alert('❌ ስህተት: ' + (data.error || 'Unknown error'));
                    }
                    closeModal();
                    document.getElementById('modalLoading').style.display = 'none';
                    document.querySelector('.btn-save').disabled = false;
                    document.querySelector('.btn-cancel').disabled = false;
                }
            })
            .catch(error => {
                alert('❌ ስህተት: ' + error);
                document.getElementById('modalLoading').style.display = 'none';
                document.querySelector('.btn-save').disabled = false;
                document.querySelector('.btn-cancel').disabled = false;
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('contributionModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>