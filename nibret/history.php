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
    header("Location: index.php");
    exit();
}

$student_id = $_GET['student_id'] ?? 0;
$student = null;
$payments = [];

if ($student_id) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM contributions WHERE student_id = ? ORDER BY year DESC, month DESC");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
}

$stmt = $pdo->query("SELECT id, name FROM students ORDER BY name");
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የክፍያ ታሪክ - ንብረት ክፍል</title>
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
        .back-btn { background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 8px; color: white; text-decoration: none; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .card h2 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; color: #666; }
        .total { font-size: 1.2rem; font-weight: bold; color: #28a745; margin-top: 20px; text-align: right; }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: white; }
        @media print { .header, .back-btn, .action-buttons, .form-group, .btn-primary { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-history"></i> የክፍያ ታሪክ</h1>
        <a href="mewatewo.php" class="back-btn">← ወደ መዋጮ</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>ተማሪ ምረጥ</h2>
            <form method="GET">
                <div class="form-group">
                    <select name="student_id" onchange="this.form.submit()" required>
                        <option value="">-- ተማሪ ምረጥ --</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $student_id == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <?php if ($student): ?>
        <div class="card" id="printArea">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2><?php echo htmlspecialchars($student['name']); ?></h2>
                <p><?php echo $student['phone'] ? 'ስልክ: ' . $student['phone'] : ''; ?> <?php echo $student['grade'] ? ' | ክፍል: ' . $student['grade'] : ''; ?></p>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ቀን</th><th>ዓመት</th><th>ወር</th><th>መጠን</th><th>ደረሰኝ ቁጥር</th></tr>
                    </thead>
                    <tbody>
                        <?php $total = 0; foreach ($payments as $payment): $total += $payment['amount']; ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($payment['recorded_at'])); ?></td>
                            <td><?php echo $payment['year']; ?></td>
                            <td><?php echo date('F', mktime(0,0,0,$payment['month'],1)); ?></td>
                            <td>ETB <?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo $payment['receipt_number']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="5" style="text-align: center;">ምንም ክፍያ የለም</div></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="total">ጠቅላላ የተከፈለ: ETB <?php echo number_format($total, 2); ?></div>
            <div class="action-buttons">
                <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> አትም</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>