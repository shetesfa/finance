<?php
// config/functions.php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function checkAuth() {
    if (!isLoggedIn()) {
        header("Location: ../index.php");
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function canAccess($department) {
    return isset($_SESSION['department']) && $_SESSION['department'] === $department;
}

function getBalance($pdo) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM income");
    $income = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status = 'FULLY_APPROVED'");
    $expense = $stmt->fetch()['total'];
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM withdrawals");
    $withdrawals = $stmt->fetch()['total'];
    return $income - $expense - $withdrawals;
}

function generateReceiptNumber($prefix = 'SALE') {
    return $prefix . '-' . date('Ymd') . '-' . rand(1000, 9999);
}

function getUserAutoApproveLimit($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $role = $stmt->fetch()['role_name'];
    return ($role == 'Nibret_Admin') ? 500 : 1000;
}

// ============= NEW FUNCTIONS =============

function logAudit($pdo, $user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id, $action, $table_name, $record_id,
        $old_values ? json_encode($old_values) : null,
        $new_values ? json_encode($new_values) : null,
        $ip, $user_agent
    ]);
}

function createAlert($pdo, $type, $severity, $title, $message, $reference_id = null, $reference_type = null, $department = 'both') {
    // Check if similar alert already exists (avoid duplicates)
    $stmt = $pdo->prepare("SELECT id FROM alerts WHERE type = ? AND reference_id = ? AND is_read = 0 AND resolved_at IS NULL");
    $stmt->execute([$type, $reference_id]);
    if ($stmt->fetch()) return;
    
    $stmt = $pdo->prepare("INSERT INTO alerts (type, severity, title, message, reference_id, reference_type, department) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$type, $severity, $title, $message, $reference_id, $reference_type, $department]);
}

function getUnreadAlertCount($pdo, $department = null) {
    $sql = "SELECT COUNT(*) as count FROM alerts WHERE is_read = 0 AND resolved_at IS NULL";
    if ($department && $department != 'both') {
        $sql .= " AND (department = ? OR department = 'both')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$department]);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetch()['count'];
}

function updateSellerActivity($pdo, $seller_id) {
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as count 
                           FROM sales WHERE seller_id = ? AND sale_date = ?");
    $stmt->execute([$seller_id, $today]);
    $sales = $stmt->fetch();
    
    $stmt = $pdo->prepare("INSERT INTO seller_activity_log (seller_id, activity_date, last_activity, sales_count, total_amount) 
                           VALUES (?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                           last_activity = ?, sales_count = ?, total_amount = ?, updated_at = NOW()");
    $stmt->execute([$seller_id, $today, $now, $sales['count'], $sales['total'], $now, $sales['count'], $sales['total']]);
    
    // Clear no-activity alert if exists
    $pdo->prepare("UPDATE alerts SET resolved_at = NOW() WHERE type = 'no_activity' AND reference_id = ? AND resolved_at IS NULL")
        ->execute([$seller_id]);
}

function calculateSellerPerformance($pdo, $seller_id, $period = 'today') {
    $today = date('Y-m-d');
    
    if ($period == 'today') {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as actual FROM sales WHERE seller_id = ? AND sale_date = ?");
        $stmt->execute([$seller_id, $today]);
        $actual = $stmt->fetch()['actual'];
        
        $stmt = $pdo->prepare("SELECT target_amount FROM seller_targets WHERE seller_id = ? AND period_type = 'daily' AND period_start <= ? AND period_end >= ?");
        $stmt->execute([$seller_id, $today, $today]);
        $target = $stmt->fetch();
        $target_amount = $target ? $target['target_amount'] : 500;
    } else {
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as actual FROM sales WHERE seller_id = ? AND sale_date BETWEEN ? AND ?");
        $stmt->execute([$seller_id, $week_start, $week_end]);
        $actual = $stmt->fetch()['actual'];
        
        $stmt = $pdo->prepare("SELECT target_amount FROM seller_targets WHERE seller_id = ? AND period_type = 'weekly' AND period_start <= ? AND period_end >= ?");
        $stmt->execute([$seller_id, $today, $today]);
        $target = $stmt->fetch();
        $target_amount = $target ? $target['target_amount'] : 2500;
    }
    
    $percentage = $target_amount > 0 ? round(($actual / $target_amount) * 100) : 0;
    
    return [
        'actual' => $actual,
        'target' => $target_amount,
        'percentage' => $percentage,
        'status' => $percentage >= 100 ? 'achieved' : ($percentage >= 75 ? 'on_track' : 'behind')
    ];
}

function getPaymentStatus($paid, $payment_date, $due_date) {
    $today = date('Y-m-d');
    
    if ($paid) {
        if (strtotime($payment_date) <= strtotime($due_date)) {
            return ['status' => 'paid', 'label' => 'ተከፍሏል', 'color' => '#28a745', 'bg' => '#d4edda'];
        } else {
            return ['status' => 'late', 'label' => 'ዘግይቷል', 'color' => '#856404', 'bg' => '#fff3cd'];
        }
    }
    
    if (strtotime($today) > strtotime($due_date)) {
        return ['status' => 'missing', 'label' => 'አልተከፈለም', 'color' => '#721c24', 'bg' => '#f8d7da'];
    }
    
    return ['status' => 'pending', 'label' => 'በመጠባበቅ', 'color' => '#6c757d', 'bg' => '#e2e3e5'];
}

function calculateApprovalWeight($pdo, $expense_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(weight_value),0) as total FROM expense_approvals WHERE expense_id = ? AND status = 'approved'");
    $stmt->execute([$expense_id]);
    $total = $stmt->fetch()['total'];
    
    $pdo->prepare("UPDATE expenses SET approval_weight_total = ? WHERE id = ?")->execute([$total, $expense_id]);
    
    if ($total >= 100) {
        $pdo->prepare("UPDATE expenses SET status = 'FULLY_APPROVED', fully_approved_at = NOW() WHERE id = ?")->execute([$expense_id]);
        
        // Add to income as negative
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        $exp = $stmt->fetch();
        
        $stmt = $pdo->prepare("INSERT INTO income (source, reference_id, amount, description, recorded_by, recorded_date) 
                               VALUES ('expense', ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$expense_id, -$exp['amount'], "ወጪ: " . $exp['category'] . " - " . $exp['description'], $exp['requested_by']]);
        
        createAlert($pdo, 'approval_delay', 'info', 'ወጪ ሙሉ በሙሉ ጸድቋል', 
            "ወጪ ቁጥር #{$expense_id} በሙሉ ጸድቋል። መጠን: ETB " . number_format($exp['amount'], 2),
            $expense_id, 'expense', 'nibret');
    }
    
    return $total;
}

function updateStudentPaymentScore($pdo, $student_id) {
    // Get all payments for student
    $stmt = $pdo->prepare("
        SELECT c.*, 
               DATEDIFF(c.payment_date, c.due_date) as days_late
        FROM contributions c 
        WHERE c.student_id = ? 
        ORDER BY c.year, c.month
    ");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
    
    $total_paid = count($payments);
    $on_time = 0;
    $late = 0;
    $last_payment = null;
    $longest_gap = 0;
    $current_streak = 0;
    
    $today = new DateTime();
    $expected_start = new DateTime('2026-01-01');
    $interval = new DateInterval('P1M');
    $period = new DatePeriod($expected_start, $interval, $today);
    $total_expected = iterator_count($period);
    
    foreach ($payments as $p) {
        if ($p['days_late'] <= 0) $on_time++;
        else $late++;
        
        if ($last_payment) {
            $gap = (strtotime($p['payment_date']) - strtotime($last_payment)) / (60*60*24);
            if ($gap > $longest_gap) $longest_gap = $gap;
            
            if ($gap <= 35) $current_streak++;
            else $current_streak = 0;
        } else {
            $current_streak = 1;
        }
        $last_payment = $p['payment_date'];
    }
    
    $missed = $total_expected - $total_paid;
    $consistency = $total_expected > 0 ? round(($on_time / $total_expected) * 100) : 0;
    
    // Determine risk level
    if ($missed >= 3 || $longest_gap > 90) $risk = 'critical';
    elseif ($missed >= 2 || $longest_gap > 60) $risk = 'high';
    elseif ($missed >= 1 || $longest_gap > 30) $risk = 'medium';
    else $risk = 'low';
    
    $stmt = $pdo->prepare("
        INSERT INTO student_payment_scores 
        (student_id, consistency_score, total_months_paid, total_months_expected, on_time_payments, late_payments, missed_payments, last_payment_date, longest_gap_days, current_streak, risk_level) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        consistency_score = ?, total_months_paid = ?, total_months_expected = ?, on_time_payments = ?, late_payments = ?, missed_payments = ?, last_payment_date = ?, longest_gap_days = ?, current_streak = ?, risk_level = ?, updated_at = NOW()
    ");
    $stmt->execute([
        $student_id, $consistency, $total_paid, $total_expected, $on_time, $late, $missed, $last_payment, $longest_gap, $current_streak, $risk,
        $consistency, $total_paid, $total_expected, $on_time, $late, $missed, $last_payment, $longest_gap, $current_streak, $risk
    ]);
    
    // Create alert for critical risk
    if ($risk == 'critical') {
        $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        createAlert($pdo, 'payment_gap', 'critical', 'ከፍተኛ ስጋት ያለው ተማሪ', 
            "{$student['name']} ለ {$missed} ወራት አልከፈለም። አፋጣኝ ክትትል ያስፈልጋል።",
            $student_id, 'student', 'nibret');
    }
}

function getActiveCycle($pdo) {
    $stmt = $pdo->query("SELECT * FROM sales_cycles WHERE status = 'active' ORDER BY start_date DESC LIMIT 1");
    $cycle = $stmt->fetch();
    
    if (!$cycle) {
        // Create new cycle
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime('+2 days'));
        $name = 'ዑደት ' . date('W');
        
        $stmt = $pdo->prepare("INSERT INTO sales_cycles (cycle_name, start_date, end_date, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$name, $start, $end]);
        $cycle = ['id' => $pdo->lastInsertId(), 'cycle_name' => $name, 'start_date' => $start, 'end_date' => $end];
    }
    
    return $cycle;
}

function takeDailySnapshot($pdo) {
    $today = date('Y-m-d');
    
    // Check if already taken
    $stmt = $pdo->prepare("SELECT id FROM daily_snapshots WHERE snapshot_date = ?");
    $stmt->execute([$today]);
    if ($stmt->fetch()) return;
    
    // Get totals
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM income");
    $total_income = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM income WHERE source = 'sale'");
    $lmat_sales = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM income WHERE source = 'contribution'");
    $total_contributions = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM income WHERE source = 'manual'");
    $manual_income = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status = 'FULLY_APPROVED'");
    $total_expense = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM withdrawals");
    $withdrawals = $stmt->fetch()['total'];
    
    $balance = $total_income - $total_expense - $withdrawals;
    
    $stmt = $pdo->prepare("
        INSERT INTO daily_snapshots (snapshot_date, total_income, total_expense, total_contributions, lmat_sales, manual_income, withdrawals, balance) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$today, $total_income, $total_expense, $total_contributions, $lmat_sales, $manual_income, $withdrawals, $balance]);
}

function checkAndCreateAlerts($pdo) {
    // 1. No activity in 24 hours (LMAT sellers)
    $stmt = $pdo->query("
        SELECT u.id, u.name, MAX(s.created_at) as last_sale
        FROM users u
        LEFT JOIN sales s ON u.id = s.seller_id
        WHERE u.department = 'lmat' AND u.role_id = 2 AND u.is_active = 1
        GROUP BY u.id
        HAVING last_sale < DATE_SUB(NOW(), INTERVAL 24 HOUR) OR last_sale IS NULL
    ");
    foreach ($stmt->fetchAll() as $seller) {
        $last = $seller['last_sale'] ? date('Y-m-d H:i', strtotime($seller['last_sale'])) : 'መቼም';
        createAlert($pdo, 'no_activity', 'warning', '24 ሰዓት ውስጥ እንቅስቃሴ የሌለው ሻጭ', 
            "{$seller['name']} ባለፉት 24 ሰዓታት ውስጥ ሽያጭ አልመዘገበም። የመጨረሻ ሽያጭ: {$last}",
            $seller['id'], 'seller', 'lmat');
    }
    
    // 2. Approval delays (> 2 days)
    $stmt = $pdo->query("
        SELECT e.*, u.name as requester 
        FROM expenses e
        JOIN users u ON e.requested_by = u.id
        WHERE e.status NOT IN ('FULLY_APPROVED', 'REJECTED')
        AND e.requested_at < DATE_SUB(NOW(), INTERVAL 2 DAY)
        AND e.delay_notified = 0
    ");
    foreach ($stmt->fetchAll() as $exp) {
        createAlert($pdo, 'approval_delay', 'warning', 'የወጪ ማፅደቂያ ዘግይቷል', 
            "ወጪ ቁጥር #{$exp['id']} ለ2 ቀናት በመጠባበቅ ላይ ነው። አቅራቢ: {$exp['requester']}",
            $exp['id'], 'expense', 'nibret');
        $pdo->prepare("UPDATE expenses SET delay_notified = 1 WHERE id = ?")->execute([$exp['id']]);
    }
    
    // 3. Low stock products
    $stmt = $pdo->query("SELECT id, name, stock FROM products WHERE stock <= 5 AND stock > 0");
    foreach ($stmt->fetchAll() as $prod) {
        createAlert($pdo, 'low_stock', 'info', 'ዝቅተኛ ክምችት', 
            "{$prod['name']} ክምችት ዝቅተኛ ነው። ያለው: " . number_format($prod['stock'], 1),
            $prod['id'], 'product', 'lmat');
    }
    
    // 4. High expense alert (> 5000)
    $stmt = $pdo->query("
        SELECT e.*, u.name as requester 
        FROM expenses e
        JOIN users u ON e.requested_by = u.id
        WHERE e.amount > 5000 AND e.requested_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    foreach ($stmt->fetchAll() as $exp) {
        createAlert($pdo, 'high_expense', 'warning', 'ከፍተኛ የወጪ ጥያቄ', 
            "ከፍተኛ መጠን ያለው ወጪ ቀርቧል። መጠን: ETB " . number_format($exp['amount'], 2) . " - {$exp['requester']}",
            $exp['id'], 'expense', 'nibret');
    }
}

function getEventProfitLoss($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as income FROM income WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $income = $stmt->fetch()['income'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as expense FROM expenses WHERE event_id = ? AND status = 'FULLY_APPROVED'");
    $stmt->execute([$event_id]);
    $expense = $stmt->fetch()['expense'];
    
    return [
        'income' => $income,
        'expense' => $expense,
        'profit' => $income - $expense,
        'profit_percentage' => $income > 0 ? round(($income - $expense) / $income * 100, 1) : 0
    ];
}

function getWeekOverWeekGrowth($pdo, $type = 'sales') {
    $this_week_start = date('Y-m-d', strtotime('monday this week'));
    $this_week_end = date('Y-m-d', strtotime('sunday this week'));
    $last_week_start = date('Y-m-d', strtotime('monday last week'));
    $last_week_end = date('Y-m-d', strtotime('sunday last week'));
    
    if ($type == 'sales') {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE sale_date BETWEEN ? AND ?");
        $stmt->execute([$this_week_start, $this_week_end]);
        $this_week = $stmt->fetch()['total'];
        
        $stmt->execute([$last_week_start, $last_week_end]);
        $last_week = $stmt->fetch()['total'];
    } else {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM contributions WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$this_week_start, $this_week_end]);
        $this_week = $stmt->fetch()['total'];
        
        $stmt->execute([$last_week_start, $last_week_end]);
        $last_week = $stmt->fetch()['total'];
    }
    
    $growth = $last_week > 0 ? round(($this_week - $last_week) / $last_week * 100, 1) : 0;
    
    return [
        'this_week' => $this_week,
        'last_week' => $last_week,
        'growth' => $growth,
        'trend' => $growth >= 0 ? 'up' : 'down'
    ];
}

function undoTransaction($pdo, $type, $id, $reason, $user_id) {
    $pdo->beginTransaction();
    try {
        switch ($type) {
            case 'sale':
                $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
                $stmt->execute([$id]);
                $sale = $stmt->fetch();
                if (!$sale) throw new Exception("Sale not found");
                
                // Restore stock
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
                    ->execute([$sale['quantity'], $sale['product_id']]);
                
                // Delete from income
                $pdo->prepare("DELETE FROM income WHERE source = 'sale' AND reference_id = ?")->execute([$id]);
                
                // Log undo
                $pdo->prepare("INSERT INTO transaction_undo_log (original_transaction_type, original_id, undo_reason, original_data, undone_by) 
                               VALUES ('sale', ?, ?, ?, ?)")
                    ->execute([$id, $reason, json_encode($sale), $user_id]);
                
                // Delete sale
                $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$id]);
                break;
                
            case 'expense':
                $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
                $stmt->execute([$id]);
                $expense = $stmt->fetch();
                if (!$expense) throw new Exception("Expense not found");
                if ($expense['status'] == 'FULLY_APPROVED') {
                    $pdo->prepare("DELETE FROM income WHERE source = 'expense' AND reference_id = ?")->execute([$id]);
                }
                $pdo->prepare("INSERT INTO transaction_undo_log (original_transaction_type, original_id, undo_reason, original_data, undone_by) 
                               VALUES ('expense', ?, ?, ?, ?)")
                    ->execute([$id, $reason, json_encode($expense), $user_id]);
                $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
                break;
        }
        
        logAudit($pdo, $user_id, 'DELETE', $type . 's', $id, null, ['undo_reason' => $reason]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function getApprovalProgress($pdo, $expense_id) {
    $stmt = $pdo->prepare("SELECT approver_role, status, weight_value FROM expense_approvals WHERE expense_id = ?");
    $stmt->execute([$expense_id]);
    $approvals = $stmt->fetchAll();
    
    $progress = [
        'Collector' => ['status' => 'pending', 'weight' => 50],
        'Deputy' => ['status' => 'pending', 'weight' => 30],
        'Secretary' => ['status' => 'pending', 'weight' => 20],
        'total_approved' => 0
    ];
    
    foreach ($approvals as $a) {
        $progress[$a['approver_role']]['status'] = $a['status'];
        if ($a['status'] == 'approved') {
            $progress['total_approved'] += $a['weight_value'];
        }
    }
    
    return $progress;
}

function simulateExpense($pdo, $amount, $category) {
    $balance = getBalance($pdo);
    $new_balance = $balance - $amount;
    
    $risk_level = 'low';
    if ($new_balance < 0) $risk_level = 'critical';
    elseif ($new_balance < 5000) $risk_level = 'high';
    elseif ($new_balance < 20000) $risk_level = 'medium';
    
    $balance_percentage = $balance > 0 ? round(($amount / $balance) * 100) : 100;
    
    return [
        'current_balance' => $balance,
        'expense_amount' => $amount,
        'new_balance' => $new_balance,
        'balance_percentage' => $balance_percentage,
        'risk_level' => $risk_level,
        'message' => $risk_level == 'critical' ? '⚠️ ቀሪ ሂሳብ ከዜሮ በታች ይሆናል!' : 
                     ($risk_level == 'high' ? '⚠️ ቀሪ ሂሳብ በእጅጉ ይቀንሳል' : '✅ በቂ ቀሪ ሂሳብ አለ')
    ];
}

function getTransparencyData($pdo) {
    $stmt = $pdo->query("SELECT * FROM transparency_settings LIMIT 1");
    $settings = $stmt->fetch();
    
    if (!$settings || !$settings['public_view_enabled']) {
        return ['enabled' => false];
    }
    
    $data = ['enabled' => true];
    
    if ($settings['show_income_total']) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM income");
        $data['total_income'] = $stmt->fetch()['total'];
    }
    
    if ($settings['show_expense_total']) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE status = 'FULLY_APPROVED'");
        $data['total_expense'] = $stmt->fetch()['total'];
    }
    
    if ($settings['show_balance']) {
        $data['balance'] = getBalance($pdo);
    }
    
    if ($settings['show_monthly_comparison']) {
        $this_month = date('m');
        $last_month = date('m', strtotime('-1 month'));
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM income WHERE MONTH(recorded_date) = ? AND YEAR(recorded_date) = YEAR(CURDATE())");
        $stmt->execute([$this_month]);
        $data['this_month_income'] = $stmt->fetch()['total'];
        
        $stmt->execute([$last_month]);
        $data['last_month_income'] = $stmt->fetch()['total'];
        
        $data['monthly_growth'] = $data['last_month_income'] > 0 ? 
            round(($data['this_month_income'] - $data['last_month_income']) / $data['last_month_income'] * 100, 1) : 0;
    }
    
    return $data;
}
?>