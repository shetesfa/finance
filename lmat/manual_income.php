<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check admin access
if (!isset($_SESSION['department']) || $_SESSION['department'] != 'lmat' || $_SESSION['role'] != 'Lmat_Admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    
    $stmt = $pdo->prepare("INSERT INTO income (source, amount, description, recorded_by, recorded_date) 
                           VALUES ('manual', ?, ?, ?, ?)");
    $stmt->execute([$amount, $description, $_SESSION['user_id'], $date]);
    
    header("Location: index.php?msg=income_added");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የእጅ ገቢ - ልማት ክፍል</title>
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
        .container { max-width: 600px; margin: 50px auto; padding: 0 20px; }
        .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card h2 { margin-bottom: 20px; color: #333; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-hand-holding-usd"></i> የእጅ ገቢ</h1>
        <a href="index.php" class="back-btn">← ወደ መሸጫ</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>➕ አዲስ ገቢ መዝግብ</h2>
            <form method="POST">
                <div class="form-group">
                    <label>መጠን (ETB)</label>
                    <input type="number" name="amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>ቀን</label>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>ማብራሪያ</label>
                    <textarea name="description" rows="3" required placeholder="ለምን እንደሆነ ያስረዱ..."></textarea>
                </div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> መዝግብ</button>
            </form>
        </div>
    </div>
</body>
</html>