<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = $_POST['description'];
    $amount = (float)$_POST['amount'];
    $category = $_POST['category'];
    $date = $_POST['date'];
    
    $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, category, expense_date) VALUES (?, ?, ?, ?)");
    $stmt->execute([$description, $amount, $category, $date]);
    header("Location: accounts.php?success=1");
    exit;
}

// Handle Delete Expense
if (isset($_GET['delete_expense'])) {
    $id = (int)$_GET['delete_expense'];
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: accounts.php");
    exit;
}

// Date Range Filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$filter_supplier = $_GET['supplier_id'] ?? '';
$filter_type = $_GET['transaction_type'] ?? '';

// Fetch Suppliers for filter
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

// Fetch Sales Data (from pos_sales table)
$sales_stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as sale_day,
        SUM(total_amount) as daily_revenue,
        COUNT(*) as order_count
    FROM pos_sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY created_at DESC
");
$sales_stmt->execute([$start_date, $end_date]);
$daily_sales = $sales_stmt->fetchAll();

// Fetch daily expenses
$daily_expenses_stmt = $pdo->prepare("
    SELECT 
        DATE(expense_date) as expense_day,
        SUM(amount) as daily_expense
    FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY DATE(expense_date)
");
$daily_expenses_stmt->execute([$start_date, $end_date]);
$daily_expenses_data = $daily_expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of expenses by date
$expenses_by_date = [];
foreach ($daily_expenses_data as $exp) {
    $expenses_by_date[$exp['expense_day']] = $exp['daily_expense'];
}

// Total Revenue
$revenue_stmt = $pdo->prepare("SELECT SUM(total_amount) as total_revenue FROM pos_sales WHERE DATE(created_at) BETWEEN ? AND ?");
$revenue_stmt->execute([$start_date, $end_date]);
$total_revenue = (float)$revenue_stmt->fetchColumn();

// Fetch Expenses
$expenses_stmt = $pdo->prepare("
    SELECT * FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
    ORDER BY expense_date DESC
");
$expenses_stmt->execute([$start_date, $end_date]);
$expenses = $expenses_stmt->fetchAll();

// Total Expenses
$total_expenses = array_sum(array_column($expenses, 'amount'));

// Net Profit
$net_profit = $total_revenue - $total_expenses;

// Category-wise Expenses
$category_expenses_stmt = $pdo->prepare("
    SELECT category, SUM(amount) as total 
    FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY category
");
$category_expenses_stmt->execute([$start_date, $end_date]);
$category_expenses = $category_expenses_stmt->fetchAll();

// Recent Transactions (Sales + Purchases with filters)
$transaction_query = "
    SELECT 'Sale' as type, total_amount as amount, created_at as date, 'POS Sale' as description, NULL as supplier_id, NULL as supplier_name
    FROM pos_sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 'Purchase' as type, total_amount as amount, purchase_date as date, 
           CONCAT('Purchase from ', s.name, ' (', p.payment_status, ')') as description,
           p.supplier_id, s.name as supplier_name
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.purchase_date BETWEEN ? AND ?
";

$params = [$start_date, $end_date, $start_date, $end_date];

// Add supplier filter
if ($filter_supplier) {
    $transaction_query .= " AND supplier_id = ?";
    $params[] = $filter_supplier;
}

$transaction_query .= " ORDER BY date DESC";

// Add type filter after UNION
if ($filter_type === 'sale') {
    $transaction_query = "SELECT * FROM (
        SELECT 'Sale' as type, total_amount as amount, created_at as date, 'POS Sale' as description, NULL as supplier_id, NULL as supplier_name
        FROM pos_sales 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ) as filtered ORDER BY date DESC";
    $params = [$start_date, $end_date];
} elseif ($filter_type === 'purchase') {
    $transaction_query = "SELECT * FROM (
        SELECT 'Purchase' as type, total_amount as amount, purchase_date as date, 
               CONCAT('Purchase from ', s.name, ' (', p.payment_status, ')') as description,
               p.supplier_id, s.name as supplier_name
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.purchase_date BETWEEN ? AND ?
    ) as filtered ORDER BY date DESC";
    $params = [$start_date, $end_date];
    
    if ($filter_supplier) {
        $transaction_query = str_replace(") as filtered", " AND supplier_id = ?) as filtered", $transaction_query);
        $params[] = $filter_supplier;
    }
}

$recent_stmt = $pdo->prepare($transaction_query);
$recent_stmt->execute($params);
$recent_transactions = $recent_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accounts - TechHat Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }
        .admin-content {
            margin-left: 280px;
            transition: margin-left 0.3s;
        }
        .content { padding: 30px; }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Date Filter */
        .date-filter {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .date-filter input {
            padding: 10px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 14px;
        }
        .date-filter button {
            padding: 10px 20px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .date-filter button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }
        
        .stat-card.revenue::before {
            background: linear-gradient(90deg, #27ae60 0%, #229954 100%);
        }
        
        .stat-card.expenses::before {
            background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .stat-card.profit::before {
            background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
        }
        
        .stat-card.transactions::before {
            background: linear-gradient(90deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stat-card.revenue .stat-icon {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .stat-card.expenses .stat-icon {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .stat-card.profit .stat-icon {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .stat-card.transactions .stat-icon {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        /* Section Tabs */
        .tabs-container {
            background: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 0;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: #7f8c8d;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            border-radius: 8px 8px 0 0;
        }
        
        .tab:hover {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .tab.active {
            color: #3498db;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
            border-radius: 2px 2px 0 0;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
            background: white;
            padding: 25px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Add Expense Form */
        .expense-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .expense-form h3 {
            color: white;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-grid input,
        .form-grid select {
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.3s;
        }
        
        .form-grid input:focus,
        .form-grid select:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2);
        }
        
        .form-grid button {
            padding: 12px 24px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .form-grid button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e6ed;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .amount-positive {
            color: #27ae60;
            font-weight: 600;
        }
        
        .amount-negative {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 80px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        /* Category Chart */
        .category-chart {
            display: grid;
            gap: 15px;
        }
        
        .category-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .category-bar-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .category-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .category-amount {
            font-weight: 700;
            color: #e74c3c;
        }
        
        .category-progress {
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .category-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        /* Print Button */
        .btn-print {
            padding: 10px 20px;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }
        
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .admin-sidebar,
            .page-header,
            .tabs-container,
            .btn-print,
            .sidebar-overlay,
            .stats-grid,
            .expense-form,
            .no-print {
                display: none !important;
            }
            
            .print-section.active-print,
            .print-section.active-print * {
                visibility: visible;
                display: block !important;
            }
            
            .print-section.active-print {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                padding: 20px;
            }
            
            .print-section.active-print table {
                display: table !important;
            }
            
            .print-section.active-print tbody {
                display: table-row-group !important;
            }
            
            .print-section.active-print tr {
                display: table-row !important;
            }
            
            .print-section.active-print td,
            .print-section.active-print th {
                display: table-cell !important;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 3px solid #2c3e50;
            }
            
            .print-header h1 {
                color: #2c3e50;
                font-size: 24px;
                margin-bottom: 10px;
                font-weight: bold;
            }
            
            .print-header .company-name {
                font-size: 18px;
                color: #3498db;
                font-weight: 700;
                margin-bottom: 10px;
            }
            
            .print-header .print-date {
                color: #555;
                font-size: 13px;
                line-height: 1.6;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-size: 12px;
                table-layout: fixed;
            }
            
            .data-table th,
            .data-table td {
                border: 1px solid #333;
                padding: 8px;
                text-align: left;
            }
            
            .data-table th:nth-child(1),
            .data-table td:nth-child(1) {
                width: 20%;
            }
            
            .data-table th:nth-child(2),
            .data-table td:nth-child(2) {
                width: 20%;
            }
            
            .data-table th:nth-child(3),
            .data-table td:nth-child(3) {
                width: 30%;
                text-align: right;
            }
            
            .data-table th:nth-child(4),
            .data-table td:nth-child(4) {
                width: 30%;
                text-align: right;
            }
            
            .data-table th {
                background: #f0f0f0 !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .data-table tr:nth-child(even) {
                background: #f9f9f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .badge {
                border: 1px solid #333;
                padding: 3px 8px;
                font-size: 11px;
            }
            
            .print-footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #333;
                text-align: center;
                font-size: 12px;
            }
            
            .print-footer p {
                margin: 5px 0;
            }
            
            .category-chart {
                page-break-inside: avoid;
                margin-bottom: 20px;
            }
            
            .category-bar {
                page-break-inside: avoid;
                border: 1px solid #ddd;
                margin-bottom: 10px;
            }
            
            .amount-positive {
                color: #27ae60 !important;
            }
            
            .amount-negative {
                color: #e74c3c !important;
            }
        }
        
        /* Print Button */
        .btn-print {
            padding: 10px 20px;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }
        
        @media (max-width: 768px) {
            .admin-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <div class="page-header">
                <h1>
                    <i class="bi bi-currency-dollar"></i>
                    Accounts & Finance
                </h1>
                
                <form method="GET" class="date-filter">
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
                    
                    <select name="transaction_type" style="padding: 10px 15px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 14px;">
                        <option value="">All Types</option>
                        <option value="sale" <?php echo $filter_type === 'sale' ? 'selected' : ''; ?>>Sales Only</option>
                        <option value="purchase" <?php echo $filter_type === 'purchase' ? 'selected' : ''; ?>>Purchases Only</option>
                    </select>
                    
                    <select name="supplier_id" style="padding: 10px 15px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 14px;">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo $sup['id']; ?>" <?php echo $filter_supplier == $sup['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sup['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </form>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card revenue">
                    <div class="stat-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value amount-positive">৳<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                
                <div class="stat-card expenses">
                    <div class="stat-icon">
                        <i class="bi bi-cart-x"></i>
                    </div>
                    <div class="stat-label">Total Expenses</div>
                    <div class="stat-value amount-negative">৳<?php echo number_format($total_expenses, 2); ?></div>
                </div>
                
                <div class="stat-card profit">
                    <div class="stat-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="stat-label">Net Profit</div>
                    <div class="stat-value <?php echo $net_profit >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                        ৳<?php echo number_format($net_profit, 2); ?>
                    </div>
                </div>
                
                <div class="stat-card transactions">
                    <div class="stat-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value"><?php echo array_sum(array_column($daily_sales, 'order_count')); ?></div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab(event, 'sales')">
                        <i class="bi bi-graph-up"></i> Sales Report
                    </button>
                    <button class="tab" onclick="switchTab(event, 'expenses')">
                        <i class="bi bi-wallet2"></i> Expenses
                    </button>
                    <button class="tab" onclick="switchTab(event, 'analysis')">
                        <i class="bi bi-pie-chart"></i> Analysis
                    </button>
                    <button class="tab" onclick="switchTab(event, 'transactions')">
                        <i class="bi bi-list-ul"></i> Transactions
                    </button>
                </div>
            </div>
            
            <!-- Tab Contents -->
            
            <!-- Sales Report Tab -->
            <div id="sales" class="tab-content active">
                <button class="btn-print" onclick="printSection('sales')">
                    <i class="bi bi-printer"></i> Print Sales Report
                </button>
                
                <div class="print-section" id="print-sales">
                    <div class="print-header">
                        <div class="company-name">TechHat</div>
                        <h1>Sales Report</h1>
                        <div class="print-date">
                            Period: <?php echo date('d M, Y', strtotime($start_date)); ?> - <?php echo date('d M, Y', strtotime($end_date)); ?><br>
                            Printed: <?php echo date('d M, Y h:i A'); ?>
                        </div>
                    </div>
                    
                    <?php if (empty($daily_sales)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>No Sales Data</h3>
                        <p>No sales recorded for the selected period</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Date</th>
                                <th style="width: 20%;">Orders</th>
                                <th style="width: 30%; text-align: right;">Revenue (আয়)</th>
                                <th style="width: 30%; text-align: right;">Expenses (ব্যায়)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_sales as $sale): 
                                $day_expense = $expenses_by_date[$sale['sale_day']] ?? 0;
                            ?>
                            <tr>
                                <td style="width: 20%;"><?php echo date('d M, Y', strtotime($sale['sale_day'])); ?></td>
                                <td style="width: 20%;"><?php echo $sale['order_count']; ?> orders</td>
                                <td style="width: 30%; text-align: right;" class="amount-positive">৳<?php echo number_format($sale['daily_revenue'], 2); ?></td>
                                <td style="width: 30%; text-align: right;" class="amount-negative">৳<?php echo number_format($day_expense, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </div>
            
            <!-- Expenses Tab -->
            <div id="expenses" class="tab-content">
                <button class="btn-print" onclick="printSection('expenses')">
                    <i class="bi bi-printer"></i> Print Expenses Report
                </button>
                
                <div class="expense-form">
                    <h3>
                        <i class="bi bi-plus-circle"></i>
                        Add New Expense
                    </h3>
                    <form method="POST">
                        <div class="form-grid">
                            <input type="text" name="description" placeholder="Description" required>
                            <input type="number" name="amount" step="0.01" placeholder="Amount" required>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="Rent">Rent</option>
                                <option value="Salary">Salary</option>
                                <option value="Utilities">Utilities</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Inventory">Inventory</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Other">Other</option>
                            </select>
                            <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                            <button type="submit" name="add_expense">
                                <i class="bi bi-save"></i> Add Expense
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="print-section" id="print-expenses">
                    <div class="print-header">
                        <div class="company-name">TechHat</div>
                        <h1>Expenses Report</h1>
                        <div class="print-date">
                            Period: <?php echo date('d M, Y', strtotime($start_date)); ?> - <?php echo date('d M, Y', strtotime($end_date)); ?><br>
                            Printed: <?php echo date('d M, Y h:i A'); ?>
                        </div>
                    </div>
                    
                <?php if (empty($expenses)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>No Expenses Recorded</h3>
                        <p>Add your first expense above</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td><?php echo date('d M, Y', strtotime($exp['expense_date'])); ?></td>
                                <td><?php echo htmlspecialchars($exp['description']); ?></td>
                                <td><span class="badge badge-danger"><?php echo $exp['category']; ?></span></td>
                                <td class="amount-negative">৳<?php echo number_format($exp['amount'], 2); ?></td>
                                <td class="no-print">
                                    <a href="accounts.php?delete_expense=<?php echo $exp['id']; ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('Delete this expense?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="print-footer">
                        <p><strong>Total Expenses: ৳<?php echo number_format($total_expenses, 2); ?></strong></p>
                        <p>Generated by TechHat Admin System</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
            
            <!-- Analysis Tab -->
            <div id="analysis" class="tab-content">
                <button class="btn-print" onclick="printSection('analysis')">
                    <i class="bi bi-printer"></i> Print Analysis Report
                </button>
                
                <div class="print-section" id="print-analysis">
                    <div class="print-header">
                        <div class="company-name">TechHat</div>
                        <h1>Expense Analysis by Category</h1>
                        <div class="print-date">
                            Period: <?php echo date('d M, Y', strtotime($start_date)); ?> - <?php echo date('d M, Y', strtotime($end_date)); ?><br>
                            Printed: <?php echo date('d M, Y h:i A'); ?>
                        </div>
                    </div>
                    
                <h3 style="margin-bottom: 20px;">Expense Breakdown by Category</h3>
                
                <?php if (empty($category_expenses)): ?>
                    <div class="empty-state">
                        <i class="bi bi-pie-chart"></i>
                        <h3>No Data Available</h3>
                    </div>
                <?php else: ?>
                    <div class="category-chart">
                        <?php 
                        $max_expense = max(array_column($category_expenses, 'total'));
                        foreach ($category_expenses as $cat): 
                            $percentage = ($cat['total'] / $max_expense) * 100;
                        ?>
                            <div class="category-bar">
                                <div class="category-bar-header">
                                    <span class="category-name"><?php echo $cat['category']; ?></span>
                                    <span class="category-amount">৳<?php echo number_format($cat['total'], 2); ?></span>
                                </div>
                                <div class="category-progress">
                                    <div class="category-progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="print-footer">
                        <p><strong>Total Expenses: ৳<?php echo number_format($total_expenses, 2); ?></strong></p>
                        <p>Generated by TechHat Admin System</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
            
            <!-- Transactions Tab -->
            <div id="transactions" class="tab-content">
                <button class="btn-print" onclick="printSection('transactions')">
                    <i class="bi bi-printer"></i> Print Transactions Report
                </button>
                
                <div class="print-section" id="print-transactions">
                    <div class="print-header">
                        <div class="company-name">TechHat</div>
                        <h1>Transactions Report</h1>
                        <div class="print-date">
                            Period: <?php echo date('d M, Y', strtotime($start_date)); ?> - <?php echo date('d M, Y', strtotime($end_date)); ?><br>
                            <?php if ($filter_supplier): ?>
                                Supplier: <?php 
                                    $sup_name = array_filter($suppliers, function($s) use ($filter_supplier) { return $s['id'] == $filter_supplier; });
                                    echo htmlspecialchars(reset($sup_name)['name'] ?? 'All');
                                ?><br>
                            <?php endif; ?>
                            Type: <?php echo $filter_type ? ucfirst($filter_type) : 'All Types'; ?><br>
                            Printed: <?php echo date('d M, Y h:i A'); ?>
                        </div>
                    </div>
                    
                <?php if (empty($recent_transactions)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>No Transactions</h3>
                        <p>No transactions found for the selected filters</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Supplier</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $trans): ?>
                            <tr>
                                <td><?php echo date('d M, Y', strtotime($trans['date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $trans['type'] == 'Sale' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $trans['type'] == 'Sale' ? 'Sale (বিক্রয়)' : 'Purchase (ক্রয়)'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($trans['description']); ?></td>
                                <td>
                                    <?php if ($trans['supplier_name']): ?>
                                        <strong><?php echo htmlspecialchars($trans['supplier_name']); ?></strong>
                                    <?php else: ?>
                                        <span style="color: #95a5a6;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $trans['type'] == 'Sale' ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo $trans['type'] == 'Sale' ? '+' : '-'; ?>৳<?php echo number_format($trans['amount'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="print-footer">
                        <p><strong>Total Transactions: <?php echo count($recent_transactions); ?></strong></p>
                        <p>Generated by TechHat Admin System</p>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(event, tabId) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
        }
        
        function printSection(section) {
            // Get all print sections
            const allPrintSections = document.querySelectorAll('.print-section');
            
            // Hide all print sections
            allPrintSections.forEach(sec => {
                sec.classList.remove('active-print');
            });
            
            // Get the print section to show
            const printContent = document.getElementById('print-' + section);
            
            if (!printContent) {
                alert('Print content not found!');
                return;
            }
            
            // Mark this section as active for printing
            printContent.classList.add('active-print');
            
            // Trigger print
            window.print();
            
            // Clean up after print dialog closes
            setTimeout(() => {
                printContent.classList.remove('active-print');
            }, 100);
        }
    </script>
</body>
</html>
