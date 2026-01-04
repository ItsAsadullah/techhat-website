<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

// Dashboard stats
$totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

$todaySalesOrder = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE (status IS NULL OR status NOT IN ('cancelled','Cancelled')) AND DATE(created_at) = CURDATE()")
    ->fetchColumn();
$todaySalesPos = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM pos_sales WHERE DATE(created_at) = CURDATE()")
    ->fetchColumn();
$todaySales = $todaySalesOrder + $todaySalesPos;

$monthStart = date('Y-m-01');
$stmtMonthOrder = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE (status IS NULL OR status NOT IN ('cancelled','Cancelled')) AND DATE(created_at) >= ?");
$stmtMonthOrder->execute([$monthStart]);
$monthSalesOrder = (float) $stmtMonthOrder->fetchColumn();
$stmtMonthPos = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM pos_sales WHERE DATE(created_at) >= ?");
$stmtMonthPos->execute([$monthStart]);
$monthSalesPos = (float) $stmtMonthPos->fetchColumn();
$monthSales = $monthSalesOrder + $monthSalesPos;

$todayIncome = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM accounts_income WHERE DATE(created_at) = CURDATE()")
    ->fetchColumn();
$todayExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM accounts_expense WHERE DATE(created_at) = CURDATE()")
    ->fetchColumn();
$monthIncomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM accounts_income WHERE DATE(created_at) >= ?");
$monthIncomeStmt->execute([$monthStart]);
$monthIncome = (float) $monthIncomeStmt->fetchColumn();
$monthExpenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM accounts_expense WHERE DATE(created_at) >= ?");
$monthExpenseStmt->execute([$monthStart]);
$monthExpense = (float) $monthExpenseStmt->fetchColumn();

$todayNet = $todaySales + $todayIncome - $todayExpense;
$monthNet = $monthSales + $monthIncome - $monthExpense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TechHat</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            margin: 0;
        }
        .content { 
            padding: 30px;
        }
        .dashboard-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .quick-action { background: #27ae60; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 16px; }
        .quick-action:hover { background: #229954; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { 
            background: #fff; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
            border-left: 4px solid #3498db;
        }
        .stat-card h3 { 
            font-size: 14px; 
            color: #7f8c8d; 
            margin-bottom: 10px; 
            font-weight: normal;
            text-transform: uppercase;
        }
        .stat-card p { 
            font-size: 32px; 
            font-weight: bold; 
            color: #2c3e50; 
            margin: 0;
        }
        .stat-card.positive { border-left-color: #27ae60; }
        .stat-card.negative { border-left-color: #e74c3c; }
        .stat-card.warning { border-left-color: #f39c12; }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <div class="dashboard-header">
                <h1>Dashboard</h1>
                <a href="pos.php" class="quick-action">🛒 Open POS System</a>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <p><?php echo number_format($totalOrders); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Products</h3>
                    <p><?php echo number_format($totalProducts); ?></p>
                </div>
                <div class="stat-card positive">
                    <h3>Today's Sales</h3>
                    <p>৳<?php echo number_format($todaySales, 2); ?></p>
                </div>
                <div class="stat-card positive">
                    <h3>This Month Sales</h3>
                    <p>৳<?php echo number_format($monthSales, 2); ?></p>
                </div>
                <div class="stat-card <?php echo $todayNet >= 0 ? 'positive' : 'negative'; ?>">
                    <h3>Today's Net Profit</h3>
                    <p>৳<?php echo number_format($todayNet, 2); ?></p>
                </div>
                <div class="stat-card <?php echo $monthNet >= 0 ? 'positive' : 'negative'; ?>">
                    <h3>This Month Net Profit</h3>
                    <p>৳<?php echo number_format($monthNet, 2); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>