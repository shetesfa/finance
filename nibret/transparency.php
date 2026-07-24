<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

$data = getTransparencyData($pdo);

// Check access code if set
$access_code = $_GET['code'] ?? '';
$settings = $pdo->query("SELECT * FROM transparency_settings LIMIT 1")->fetch();

if ($settings && $settings['access_code'] && $access_code !== $settings['access_code']) {
    // Show access code form
    if (!$access_code) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>የግልጽነት ሁነታ</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #DAA520, #FFD700); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .card { background: white; padding: 40px; border-radius: 15px; text-align: center; max-width: 400px; }
                input { padding: 12px; border: 1px solid #ddd; border-radius: 8px; width: 100%; margin: 15px 0; }
                button { padding: 12px 30px; background: #DAA520; color: white; border: none; border-radius: 8px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>🔒 የመግቢያ ኮድ ያስገቡ</h2>
                <form method="GET">
                    <input type="text" name="code" placeholder="ኮድ ያስገቡ" required>
                    <button type="submit">ግባ</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// Admin can update settings
$is_admin = isset($_SESSION['user_id']) && $_SESSION['role'] == 'Nibret_Admin';
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $show_income = isset($_POST['show_income']) ? 1 : 0;
    $show_expense = isset($_POST['show_expense']) ? 1 : 0;
    $show_balance = isset($_POST['show_balance']) ? 1 : 0;
    $show_comparison = isset($_POST['show_comparison']) ? 1 : 0;
    $code = $_POST['access_code'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE transparency_settings SET 
        public_view_enabled = ?, show_income_total = ?, show_expense_total = ?, 
        show_balance = ?, show_monthly_comparison = ?, access_code = ?, updated_by = ? 
        WHERE id = 1");
    $stmt->execute([$enabled, $show_income, $show_expense, $show_balance, $show_comparison, $code, $_SESSION['user_id']]);
    
    header("Location: transparency.php?msg=updated");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ግልጽነት - የቤተክርስቲያን ፋይናንስ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DAA520; --primary-dark: #B8860B; --success: #28a745; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        
        .header {
            text-align: center;
            color: white;
            padding: 30px 0;
        }
        .header h1 { font-size: 2rem; margin-bottom: 10px; }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        .stat-value { font-size: 32px; font-weight: bold; color: var(--primary-dark); }
        .stat-label { font-size: 14px; color: #666; margin-top: 5px; }
        
        .growth { margin-top: 10px; font-size: 14px; }
        .growth-up { color: var(--success); }
        .growth-down { color: #dc3545; }
        
        .footer {
            text-align: center;
            color: white;
            padding: 20px;
            font-size: 14px;
            opacity: 0.8;
        }
        
        .admin-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .admin-section h3 { margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .form-group input[type="checkbox"] { width: 20px; height: 20px; }
        .form-group input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer; }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-church"></i> የቤተክርስቲያን ፋይናንስ ግልጽነት</h1>
            <p>የገቢ እና ወጪ ማጠቃለያ</p>
        </div>
        
        <?php if (!$data['enabled']): ?>
        <div class="card">
            <h2 style="text-align: center;">🔒 ግልጽነት ሁነታ አልነቃም</h2>
            <p style="text-align: center; margin-top: 20px;">እባክዎ በኋላ ይሞክሩ።</p>
        </div>
        <?php else: ?>
        <div class="card">
            <h2 style="text-align: center; margin-bottom: 30px;">
                <i class="fas fa-chart-pie"></i> የፋይናንስ ማጠቃለያ
            </h2>
            
            <div class="stats-grid">
                <?php if (isset($data['total_income'])): ?>
                <div class="stat-box">
                    <div class="stat-value">ETB <?php echo number_format($data['total_income'], 2); ?></div>
                    <div class="stat-label">ጠቅላላ ገቢ</div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($data['total_expense'])): ?>
                <div class="stat-box">
                    <div class="stat-value">ETB <?php echo number_format($data['total_expense'], 2); ?></div>
                    <div class="stat-label">ጠቅላላ ወጪ</div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($data['balance'])): ?>
                <div class="stat-box">
                    <div class="stat-value" style="color: <?php echo $data['balance'] >= 0 ? 'var(--success)' : '#dc3545'; ?>">
                        ETB <?php echo number_format($data['balance'], 2); ?>
                    </div>
                    <div class="stat-label">ቀሪ ሂሳብ</div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (isset($data['this_month_income'])): ?>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <h3 style="margin-bottom: 20px;">የወርሃዊ ንፅፅር</h3>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value">ETB <?php echo number_format($data['this_month_income'], 2); ?></div>
                        <div class="stat-label">የዚህ ወር ገቢ</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">ETB <?php echo number_format($data['last_month_income'], 2); ?></div>
                        <div class="stat-label">ያለፈው ወር ገቢ</div>
                        <div class="growth <?php echo $data['monthly_growth'] >= 0 ? 'growth-up' : 'growth-down'; ?>">
                            <i class="fas fa-<?php echo $data['monthly_growth'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs($data['monthly_growth']); ?>% ካለፈው ወር
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($is_admin): ?>
        <div class="card">
            <div class="admin-section">
                <h3><i class="fas fa-cog"></i> የግልጽነት ቅንብሮች</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="enabled" <?php echo $settings['public_view_enabled'] ? 'checked' : ''; ?>>
                            ግልጽነት ሁነታን አንቃ
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_income" <?php echo $settings['show_income_total'] ? 'checked' : ''; ?>>
                            ጠቅላላ ገቢ አሳይ
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_expense" <?php echo $settings['show_expense_total'] ? 'checked' : ''; ?>>
                            ጠቅላላ ወጪ አሳይ
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_balance" <?php echo $settings['show_balance'] ? 'checked' : ''; ?>>
                            ቀሪ ሂሳብ አሳይ
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_comparison" <?php echo $settings['show_monthly_comparison'] ? 'checked' : ''; ?>>
                            ወርሃዊ ንፅፅር አሳይ
                        </label>
                    </div>
                    <div class="form-group">
                        <label>የመግቢያ ኮድ (ባዶ ቢሆን አይጠየቅም)</label>
                        <input type="text" name="access_code" value="<?php echo htmlspecialchars($settings['access_code'] ?? ''); ?>" placeholder="አማራጭ">
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-save"></i> አስቀምጥ</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_id'])): ?>
        <div style="text-align: center;">
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> ወደ ዳሽቦርድ ተመለስ</a>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            የቤተክርስቲያን ፋይናንስ አስተዳደር ስርዓት
        </div>
    </div>
</body>
</html>