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

// Add product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $unit = $_POST['unit'];
    
    $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, unit, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $price, $stock, $unit, $_SESSION['user_id']]);
    header("Location: products.php?msg=added");
    exit();
}

// Edit product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $id = $_POST['product_id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $unit = $_POST['unit'];
    
    $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ?, unit = ? WHERE id = ?");
    $stmt->execute([$name, $price, $stock, $unit, $id]);
    header("Location: products.php?msg=updated");
    exit();
}

// Delete product
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: products.php?msg=deleted");
    exit();
}

$stmt = $pdo->query("SELECT * FROM products ORDER BY name");
$products = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ምርቶች አስተዳደር - ልማት ክፍል</title>
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
        .flash { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .flash-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card h2 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: #dc3545; color: white; font-size: 12px; padding: 5px 10px; text-decoration: none; border-radius: 5px; }
        .btn-warning { background: #ffc107; color: #333; font-size: 12px; padding: 5px 10px; text-decoration: none; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; color: #666; }
        @media (max-width: 768px) { table { font-size: 0.7rem; } th, td { padding: 6px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-boxes"></i> ምርቶች አስተዳደር</h1>
        <a href="index.php" class="back-btn">← ወደ መሸጫ</a>
    </div>
    <div class="container">
        <?php if ($msg == 'added'): ?>
        <div class="flash flash-success">ምርት ተጨምሯል!</div>
        <?php elseif ($msg == 'updated'): ?>
        <div class="flash flash-success">ምርት ተሻሽሏል!</div>
        <?php elseif ($msg == 'deleted'): ?>
        <div class="flash flash-success">ምርት ተሰርዟል!</div>
        <?php endif; ?>
        
        <div class="card">
            <h2>➕ አዲስ ምርት</h2>
            <form method="POST">
                <div class="form-group">
                    <label>የምርት ስም</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>ዋጋ (ETB)</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>ክምችት</label>
                    <input type="number" name="stock" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>ክፍል</label>
                    <select name="unit">
                        <option value="pcs">ቁራጭ (pcs)</option>
                        <option value="kg">ኪሎ ግራም (kg)</option>
                        <option value="liter">ሊትር (L)</option>
                    </select>
                </div>
                <button type="submit" name="add_product" class="btn btn-primary">ምርት ጨምር</button>
            </form>
        </div>
        
        <div class="card">
            <h2>📋 ሁሉም ምርቶች</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ስም</th><th>ዋጋ</th><th>ክምችት</th><th>ክፍል</th><th>ድርጊት</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td>ETB <?php echo number_format($p['price'], 2); ?></td>
                            <td><?php echo number_format($p['stock'], 2); ?> <?php echo $p['unit']; ?></td>
                            <td><?php echo $p['unit']; ?></td>
                            <td>
                                <button class="btn-warning" onclick="editProduct(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['price']; ?>, <?php echo $p['stock']; ?>, '<?php echo $p['unit']; ?>')">አርትዕ</button>
                                <a href="?delete=<?php echo $p['id']; ?>" class="btn-danger" onclick="return confirm('ሰርዝ?')">ሰርዝ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function editProduct(id, name, price, stock, unit) {
            let newName = prompt('አዲስ ስም:', name);
            if (newName === null) return;
            let newPrice = prompt('አዲስ ዋጋ:', price);
            if (newPrice === null) return;
            let newStock = prompt('አዲስ ክምችት:', stock);
            if (newStock === null) return;
            
            let form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="product_id" value="${id}">
                <input type="hidden" name="name" value="${newName}">
                <input type="hidden" name="price" value="${newPrice}">
                <input type="hidden" name="stock" value="${newStock}">
                <input type="hidden" name="unit" value="${unit}">
                <input type="hidden" name="edit_product" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>