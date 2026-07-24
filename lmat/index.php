<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION['department']) || $_SESSION['department'] != 'lmat') {
    header("Location: ../dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_admin = ($user_role == 'Lmat_Admin');
$seller_name = $_SESSION['user_name'];

// Get active cycle
$cycle = getActiveCycle($pdo);

// Get all products
$stmt = $pdo->query("SELECT * FROM products ORDER BY name");
$products = $stmt->fetchAll();

// Get today's sales total
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total, COUNT(*) as count FROM sales WHERE seller_id = ? AND sale_date = CURDATE()");
$stmt->execute([$user_id]);
$today_sales = $stmt->fetch();

// Get seller performance
$performance = calculateSellerPerformance($pdo, $user_id, 'today');
$weekly_perf = calculateSellerPerformance($pdo, $user_id, 'week');

// Get unread alerts
$alert_count = getUnreadAlertCount($pdo, 'lmat');

// Update seller activity
updateSellerActivity($pdo, $user_id);

$products_json = json_encode($products);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ልማት ክፍል - Smart POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #DAA520;
            --primary-dark: #B8860B;
            --primary-light: #FFD700;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            min-height: 100vh;
        }
        
        /* Header */
        .top-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin: 15px;
            padding: 15px 25px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        .performance-badge {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .perf-item {
            text-align: center;
            padding: 8px 15px;
            background: var(--light);
            border-radius: 8px;
        }
        .perf-value {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary-dark);
        }
        .perf-label { font-size: 11px; color: var(--gray); }
        .alert-badge {
            position: relative;
            cursor: pointer;
        }
        .alert-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Main Layout */
        .container {
            display: grid;
            grid-template-columns: 320px 1fr 320px;
            gap: 15px;
            padding: 0 15px 15px 15px;
        }
        
        /* Left Sidebar */
        .left-sidebar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            height: fit-content;
        }
        .cycle-info {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            padding: 15px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
            text-align: center;
        }
        .cycle-name { font-size: 18px; font-weight: bold; }
        .cycle-dates { font-size: 12px; opacity: 0.9; margin-top: 5px; }
        .total-box {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--success), #20c997);
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
        }
        #total-amount { font-size: 32px; font-weight: bold; }
        .payment-methods { margin-bottom: 20px; }
        .payment-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        .payment-btn {
            padding: 12px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .payment-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: transparent;
        }
        .finish-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .finish-btn:disabled {
            background: var(--gray);
            cursor: not-allowed;
        }
        
        /* Center Area */
        .center-area {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cart-table th, .cart-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .cart-table th { background: var(--light); }
        .quantity-controls {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .quantity-controls button {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
        }
        .quantity-controls input {
            width: 60px;
            padding: 6px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .remove-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        /* Right Sidebar */
        .right-sidebar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        .product-btn {
            width: 100%;
            padding: 12px;
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        .product-btn:hover {
            border-color: var(--primary);
            transform: translateX(3px);
        }
        .product-stock {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .stock-available { background: #d4edda; color: #155724; }
        .stock-low { background: #fff3cd; color: #856404; }
        .stock-critical { background: #f8d7da; color: #721c24; }
        .stock-out { background: #e2e3e5; color: #383d41; }
        
        /* Footer */
        .footer {
            grid-column: 1 / -1;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .footer-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        
        /* Mobile */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 20px;
            z-index: 1000;
            cursor: pointer;
        }
        
        /* Alerts Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .alert-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }
        .alert-warning { background: #fff3cd; border-left: 4px solid var(--warning); }
        .alert-critical { background: #f8d7da; border-left: 4px solid var(--danger); }
        .alert-info { background: #d1ecf1; border-left: 4px solid var(--info); }
        
        @media (max-width: 900px) {
            .container { display: block; }
            .left-sidebar, .right-sidebar { display: none; }
            .left-sidebar.active, .right-sidebar.active { display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100vh; z-index: 1001; overflow-y: auto; }
            .mobile-menu-toggle { display: flex; align-items: center; justify-content: center; }
            .center-area { margin-bottom: 70px; }
        }
    </style>
</head>
<body>
    <div class="top-header">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($seller_name, 0, 1)); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($seller_name); ?>
                    <span class="role-badge" style="background:var(--primary); color:white; padding:2px 10px; border-radius:20px; font-size:12px; margin-left:10px;"><?php echo $user_role; ?></span>
                </div>
            </div>
        </div>
        
        <div class="performance-badge">
            <div class="perf-item">
                <div class="perf-value">ETB <?php echo number_format($performance['actual'], 2); ?></div>
                <div class="perf-label">የዛሬ ሽያጭ</div>
            </div>
            <div class="perf-item" style="background: <?php echo $performance['percentage'] >= 100 ? '#d4edda' : ($performance['percentage'] >= 75 ? '#fff3cd' : '#f8d7da'); ?>">
                <div class="perf-value"><?php echo $performance['percentage']; ?>%</div>
                <div class="perf-label">የዕለት እቅድ</div>
            </div>
            <div class="perf-item">
                <div class="perf-value"><?php echo $weekly_perf['percentage']; ?>%</div>
                <div class="perf-label">የሳምንት እቅድ</div>
            </div>
        </div>
        
        <div style="display: flex; gap: 15px; align-items: center;">
            <div class="alert-badge" onclick="openAlertsModal()">
                <i class="fas fa-bell" style="font-size: 24px; color: var(--gray);"></i>
                <?php if ($alert_count > 0): ?>
                <span class="alert-count"><?php echo $alert_count; ?></span>
                <?php endif; ?>
            </div>
            <a href="../logout.php" style="background: var(--danger); color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <button class="mobile-menu-toggle" id="mobileMenuToggle"><i class="fas fa-bars"></i></button>
    
    <div class="container">
        <div class="left-sidebar" id="leftSidebar">
            <div class="cycle-info">
                <div class="cycle-name"><?php echo htmlspecialchars($cycle['cycle_name']); ?></div>
                <div class="cycle-dates">
                    <?php echo date('M d', strtotime($cycle['start_date'])); ?> - 
                    <?php echo date('M d', strtotime($cycle['end_date'])); ?>
                </div>
            </div>
            
            <div class="total-box">
                <h3><i class="fas fa-shopping-cart"></i> ጠቅላላ ድምር</h3>
                <div id="total-amount">0.00 ETB</div>
            </div>
            
            <div class="payment-methods">
                <h3><i class="fas fa-credit-card"></i> የመክፈያ መንገድ</h3>
                <div class="payment-options">
                    <button class="payment-btn active" onclick="selectPayment('cash')"><i class="fas fa-money-bill-wave"></i> ካሽ</button>
                    <button class="payment-btn" onclick="selectPayment('telebirr')"><i class="fas fa-mobile-alt"></i> ቴሌብር</button>
                    <button class="payment-btn" onclick="selectPayment('cbe')"><i class="fas fa-university"></i> CBE</button>
                    <button class="payment-btn" onclick="selectPayment('abyssinia')"><i class="fas fa-landmark"></i> አቢሲንያ</button>
                </div>
            </div>
            
            <button class="finish-btn" id="finishBtn" onclick="finishSale()" disabled>
                <i class="fas fa-check-circle"></i> ሽያጩን መዝግብ
            </button>
            
            <div style="margin-top: 20px; padding: 15px; background: var(--light); border-radius: 10px;">
                <h4><i class="fas fa-calculator"></i> ቀሪ ለማወቅ</h4>
                <input type="number" id="calcAmount" placeholder="የተቀበሉት ገንዘብ" step="0.01" style="width:100%; padding:10px; margin:10px 0; border:1px solid #ddd; border-radius:8px;">
                <div id="calcResult" style="padding:10px; border-radius:8px; text-align:center;"></div>
            </div>
        </div>
        
        <div class="center-area">
            <h2><i class="fas fa-shopping-cart"></i> የተመረጡ እቃዎች</h2>
            <div style="overflow-x: auto;">
                <table class="cart-table">
                    <thead>
                        <tr><th>የእቃው ስም</th><th>ብዛት</th><th>ዋጋ</th><th>ጠቅላላ</th><th></th></tr>
                    </thead>
                    <tbody id="cartBody"></tbody>
                </table>
            </div>
        </div>
        
        <div class="right-sidebar" id="rightSidebar">
            <h2><i class="fas fa-store"></i> ምርቶች</h2>
            <div id="productsList">
                <?php foreach ($products as $p): 
                    $stockClass = 'stock-available';
                    $stockText = number_format($p['stock'], 1) . ' ' . $p['unit'];
                    if ($p['stock'] <= 0) { $stockClass = 'stock-out'; $stockText = 'አልቋል'; }
                    elseif ($p['stock'] <= 5) { $stockClass = 'stock-critical'; }
                    elseif ($p['stock'] <= 10) { $stockClass = 'stock-low'; }
                ?>
                <div class="product-btn" onclick="addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['price']; ?>, <?php echo $p['stock']; ?>)">
                    <div>
                        <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div class="product-price">ETB <?php echo number_format($p['price'], 2); ?></div>
                    </div>
                    <div class="product-stock <?php echo $stockClass; ?>"><?php echo $stockText; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="footer">
            <?php if ($is_admin): ?>
            <a href="reports.php" class="footer-btn"><i class="fas fa-chart-bar"></i> ሪፖርት</a>
            <a href="products.php" class="footer-btn"><i class="fas fa-boxes"></i> ምርቶች</a>
            <a href="manual_income.php" class="footer-btn"><i class="fas fa-hand-holding-usd"></i> የእጅ ገቢ</a>
            <a href="performance.php" class="footer-btn"><i class="fas fa-trophy"></i> አፈጻጸም</a>
            <?php endif; ?>
            <a href="history.php" class="footer-btn"><i class="fas fa-history"></i> ታሪክ</a>
            <a href="cycles.php" class="footer-btn"><i class="fas fa-sync-alt"></i> ዑደቶች</a>
        </div>
    </div>
    
    <!-- Alerts Modal -->
    <div id="alertsModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom:20px;"><i class="fas fa-bell"></i> ማስጠንቀቂያዎች</h2>
            <div id="alertsList"></div>
            <button onclick="closeAlertsModal()" style="padding:10px 20px; background:var(--primary); color:white; border:none; border-radius:8px; margin-top:20px; width:100%;">ዝጋ</button>
        </div>
    </div>
    
    <script>
        let cart = [];
        let total = 0;
        let selectedPayment = 'cash';
        let productsData = <?php echo $products_json; ?>;
        let userId = <?php echo $user_id; ?>;
        let cycleId = <?php echo $cycle['id']; ?>;
        
        function addToCart(id, name, price, stock) {
            let existing = cart.find(item => item.id === id);
            if (existing) {
                let newQty = existing.quantity + 1;
                if (stock > 0 && newQty > stock) {
                    alert(`በቂ ክምችት የለም! ያለው: ${stock}`);
                    return;
                }
                existing.quantity = newQty;
                existing.subtotal = existing.quantity * price;
            } else {
                if (stock > 0 && 1 > stock) {
                    alert('ክምችት አልቋል!');
                    return;
                }
                cart.push({ id, name, price, quantity: 1, subtotal: price });
            }
            updateCart();
        }
        
        function updateQuantity(index, change) {
            let item = cart[index];
            let newQty = item.quantity + change;
            if (newQty <= 0) {
                cart.splice(index, 1);
            } else {
                let product = productsData.find(p => p.id === item.id);
                if (product && change > 0 && newQty > product.stock) {
                    alert(`በቂ ክምችት የለም! ያለው: ${product.stock}`);
                    return;
                }
                item.quantity = newQty;
                item.subtotal = item.quantity * item.price;
            }
            updateCart();
        }
        
        function setQuantity(index, value) {
            let qty = parseFloat(value);
            if (isNaN(qty) || qty <= 0) {
                cart.splice(index, 1);
            } else {
                let item = cart[index];
                let product = productsData.find(p => p.id === item.id);
                if (product && qty > product.stock && product.stock > 0) {
                    alert(`በቂ ክምችት የለም! ያለው: ${product.stock}`);
                    document.querySelectorAll('.qty-input')[index].value = item.quantity;
                    return;
                }
                item.quantity = qty;
                item.subtotal = item.quantity * item.price;
            }
            updateCart();
        }
        
        function removeItem(index) {
            cart.splice(index, 1);
            updateCart();
        }
        
        function updateCart() {
            let tbody = document.getElementById('cartBody');
            tbody.innerHTML = '';
            total = 0;
            
            cart.forEach((item, index) => {
                total += item.subtotal;
                let row = tbody.insertRow();
                row.insertCell(0).innerHTML = `<strong>${escapeHtml(item.name)}</strong>`;
                row.insertCell(1).innerHTML = `
                    <div class="quantity-controls">
                        <button onclick="updateQuantity(${index}, -1)">-1</button>
                        <button onclick="updateQuantity(${index}, -0.1)">-0.1</button>
                        <input type="number" class="qty-input" value="${item.quantity.toFixed(2)}" step="0.01" onchange="setQuantity(${index}, this.value)">
                        <button onclick="updateQuantity(${index}, 0.1)">+0.1</button>
                        <button onclick="updateQuantity(${index}, 1)">+1</button>
                    </div>
                `;
                row.insertCell(2).innerHTML = `ETB ${item.price.toFixed(2)}`;
                row.insertCell(3).innerHTML = `<strong>ETB ${item.subtotal.toFixed(2)}</strong>`;
                row.insertCell(4).innerHTML = `<button class="remove-btn" onclick="removeItem(${index})"><i class="fas fa-trash"></i></button>`;
            });
            
            if (cart.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;">ምንም እቃ አልተመረጠም</td></tr>';
            }
            
            document.getElementById('total-amount').innerHTML = total.toFixed(2) + ' ETB';
            document.getElementById('finishBtn').disabled = cart.length === 0;
            calculateChange();
        }
        
        function selectPayment(method) {
            selectedPayment = method;
            document.querySelectorAll('.payment-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
        
        function calculateChange() {
            let paid = parseFloat(document.getElementById('calcAmount').value) || 0;
            let resultDiv = document.getElementById('calcResult');
            let change = paid - total;
            
            if (paid > 0 && paid >= total) {
                resultDiv.innerHTML = `ቀሪ: ETB ${change.toFixed(2)}`;
                resultDiv.style.background = '#d4edda';
                resultDiv.style.color = '#155724';
            } else if (paid > 0 && paid < total) {
                resultDiv.innerHTML = `ተጨማሪ: ETB ${(total - paid).toFixed(2)} ያስፈልጋል`;
                resultDiv.style.background = '#fff3cd';
                resultDiv.style.color = '#856404';
            } else {
                resultDiv.innerHTML = 'ቁጥሩን ያስገቡ';
                resultDiv.style.background = '';
                resultDiv.style.color = '';
            }
        }
        
        function finishSale() {
            if (cart.length === 0) {
                alert('እቃ ይምረጡ!');
                return;
            }
            
            let paid = parseFloat(document.getElementById('calcAmount').value) || 0;
            let change = paid >= total ? paid - total : 0;
            
            if (!confirm(`ጠቅላላ: ETB ${total.toFixed(2)}\nሽያጩን መዝግብ?`)) return;
            
            const btn = document.getElementById('finishBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> በመዝገብ ላይ...';
            
            fetch('save_sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    items: cart,
                    total: total,
                    paid: paid,
                    change: change,
                    payment_method: selectedPayment,
                    cycle_id: cycleId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`ሽያጭ ተመዝግቧል!\nደረሰኝ: ${data.receipt}`);
                    location.reload();
                } else {
                    alert('ስህተት: ' + data.error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> ሽያጩን መዝግብ';
                }
            })
            .catch(error => {
                alert('ስህተት: ' + error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> ሽያጩን መዝግብ';
            });
        }
        
        function escapeHtml(text) {
            let div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function openAlertsModal() {
            fetch('../shared/get_alerts.php?department=lmat')
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    data.alerts.forEach(alert => {
                        html += `<div class="alert-item alert-${alert.severity}">
                            <div><i class="fas fa-${alert.severity == 'critical' ? 'exclamation-triangle' : (alert.severity == 'warning' ? 'exclamation-circle' : 'info-circle')}"></i></div>
                            <div>
                                <strong>${alert.title}</strong>
                                <p style="margin-top:5px;">${alert.message}</p>
                                <small>${alert.created_at}</small>
                            </div>
                        </div>`;
                    });
                    document.getElementById('alertsList').innerHTML = html || '<p>ምንም ማስጠንቀቂያ የለም</p>';
                    document.getElementById('alertsModal').style.display = 'flex';
                });
        }
        
        function closeAlertsModal() {
            document.getElementById('alertsModal').style.display = 'none';
        }
        
        document.getElementById('calcAmount').addEventListener('input', calculateChange);
        
        // Mobile menu
        let mobileToggle = document.getElementById('mobileMenuToggle');
        let leftSidebar = document.getElementById('leftSidebar');
        let rightSidebar = document.getElementById('rightSidebar');
        let currentView = 'center';
        
        mobileToggle.addEventListener('click', function() {
            if (currentView === 'center') {
                leftSidebar.classList.add('active');
                currentView = 'left';
                mobileToggle.innerHTML = '<i class="fas fa-times"></i>';
            } else if (currentView === 'left') {
                leftSidebar.classList.remove('active');
                rightSidebar.classList.add('active');
                currentView = 'right';
            } else {
                rightSidebar.classList.remove('active');
                currentView = 'center';
                mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
    </script>
</body>
</html>