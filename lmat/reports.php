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

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT s.*, p.name as product_name, u.name as seller_name 
          FROM sales s 
          JOIN products p ON s.product_id = p.id 
          JOIN users u ON s.seller_id = u.id 
          WHERE YEAR(s.sale_date) = ? AND MONTH(s.sale_date) = ?";
$params = [$year, $month];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY s.sale_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Get total for the month
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM sales WHERE YEAR(sale_date) = ? AND MONTH(sale_date) = ?");
$stmt->execute([$year, $month]);
$monthly_total = $stmt->fetch()['total'] ?? 0;

// Get available years
$stmt = $pdo->query("SELECT DISTINCT YEAR(sale_date) as year FROM sales ORDER BY year DESC");
$years = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ሽያጭ ሪፖርት - ልማት ክፍል</title>
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
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; margin-bottom: 5px; color: #666; font-size: 12px; }
        .filter-group select, .filter-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 8px; }
        .btn-filter { padding: 8px 20px; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer; }
        .total-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .total-card h3 { font-size: 14px; margin-bottom: 10px; opacity: 0.9; }
        .total-card .amount { font-size: 32px; font-weight: bold; }
        .card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .card h2 { margin-bottom: 20px; color: #333; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; color: #666; }
        .btn-print { background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-top: 15px; }
        @media print { .header, .filter-bar, .btn-print, .back-btn { display: none; } }
        @media (max-width: 768px) { table { font-size: 0.7rem; } th, td { padding: 6px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-chart-bar"></i> ሽያጭ ሪፖርት</h1>
        <a href="index.php" class="back-btn">← ወደ መሸጫ</a>
    </div>
    
    <div class="container">
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <div class="filter-group">
                    <label>ዓመት</label>
                    <select name="year" onchange="this.form.submit()">
                        <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['year']; ?>" <?php echo $year == $y['year'] ? 'selected' : ''; ?>><?php echo $y['year']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>ወር</label>
                    <select name="month" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>ፈልግ</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ምርት ወይም ሻጭ...">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> ፈልግ</button>
                </div>
            </form>
        </div>
        
        <div class="total-card">
            <h3><i class="fas fa-calendar-alt"></i> ጠቅላላ ሽያጭ - <?php echo date('F', mktime(0,0,0,$month,1)) . ' ' . $year; ?></h3>
            <div class="amount">ETB <?php echo number_format($monthly_total, 2); ?></div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-list"></i> ዝርዝር ሽያጮች</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ቀን</th><th>ምርት</th><th>ብዛት</th><th>ዋጋ</th><th>ጠቅላላ</th><th>ክፍያ</th><th>ሻጭ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?php echo $sale['sale_date']; ?></td>
                            <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td>ETB <?php echo number_format($sale['unit_price'], 2); ?></td>
                            <td><strong>ETB <?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                            <td><?php echo ucfirst($sale['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars($sale['seller_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sales)): ?>
                        <tr><td colspan="7" style="text-align:center;">ምንም ሽያጭ የለም</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> አትም</button>
        </div>
    </div>
</body>
</html>