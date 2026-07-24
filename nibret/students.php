<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check department and admin role
if (!isset($_SESSION['department']) || $_SESSION['department'] != 'nibret' || $_SESSION['role'] != 'Nibret_Admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $grade = $_POST['grade'];
    
    $stmt = $pdo->prepare("INSERT INTO students (name, phone, grade) VALUES (?, ?, ?)");
    $stmt->execute([$name, $phone, $grade]);
    header("Location: students.php?msg=added");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: students.php?msg=deleted");
    exit();
}

$stmt = $pdo->query("SELECT * FROM students ORDER BY name");
$students = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ተማሪዎች - ንብረት ክፍል</title>
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
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .card h2 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: #dc3545; color: white; font-size: 12px; padding: 5px 10px; text-decoration: none; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f8f9fa; color: #666; }
        .search-box { margin-bottom: 20px; }
        .search-box input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        @media (max-width: 768px) { table { font-size: 0.8rem; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-users"></i> ተማሪዎች አስተዳደር</h1>
        <a href="index.php" class="back-btn">← ወደ ዳሽቦርድ</a>
    </div>
    
    <div class="container">
        <?php if ($msg == 'added'): ?>
        <div class="flash flash-success">ተማሪ ተጨምሯል!</div>
        <?php elseif ($msg == 'deleted'): ?>
        <div class="flash flash-success">ተማሪ ተሰርዟል!</div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> አዲስ ተማሪ</h2>
            <form method="POST">
                <div class="form-group">
                    <label>ሙሉ ስም</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>ስልክ</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>ክፍል</label>
                    <input type="text" name="grade">
                </div>
                <button type="submit" name="add_student" class="btn btn-primary"><i class="fas fa-save"></i> ጨምር</button>
            </form>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-list"></i> ሁሉም ተማሪዎች</h2>
            <div class="search-box">
                <input type="text" id="searchStudent" placeholder="🔍 ተማሪ ፈልግ..." onkeyup="filterTable()">
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ስም</th><th>ስልክ</th><th>ክፍል</th><th>ድርጊት</th></tr>
                    </thead>
                    <tbody id="studentTable">
                        <?php foreach ($students as $student): ?>
                        <tr class="student-row" data-name="<?php echo strtolower($student['name']); ?>">
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['phone']); ?></td>
                            <td><?php echo htmlspecialchars($student['grade']); ?></td>
                            <td><a href="?delete=<?php echo $student['id']; ?>" class="btn-danger" onclick="return confirm('ሰርዝ?')"><i class="fas fa-trash"></i> ሰርዝ</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function filterTable() {
            const input = document.getElementById('searchStudent');
            const filter = input.value.toLowerCase();
            const rows = document.getElementsByClassName('student-row');
            for (let i = 0; i < rows.length; i++) {
                const name = rows[i].getAttribute('data-name');
                rows[i].style.display = name.includes(filter) ? '' : 'none';
            }
        }
    </script>
</body>
</html>