<?php
require_once '../core/auth.php';
require_admin();

// Check if called from iframe (no sidebar needed)
$isIframe = true; // Always in iframe mode when accessed from POS tabs

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(ps.id = ? OR ps.customer_name LIKE ? OR ps.customer_phone LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($dateFrom) {
    $whereConditions[] = "DATE(ps.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(ps.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSQL = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSQL = "SELECT COUNT(*) FROM pos_sales ps $whereSQL";
$stmt = $pdo->prepare($countSQL);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get sales
$sql = "
    SELECT 
        ps.*,
        u.name as staff_name,
        COUNT(psi.id) as total_items,
        COALESCE(SUM(pr.return_amount), 0) as total_returned,
        COUNT(pr.id) as return_count
    FROM pos_sales ps
    LEFT JOIN users u ON ps.staff_user_id = u.id
    LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
    LEFT JOIN pos_returns pr ON ps.id = pr.pos_sale_id
    $whereSQL
    GROUP BY ps.id
    ORDER BY ps.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Get today's summary
$todaySQL = "
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(total_amount), 0) as total_amount
    FROM pos_sales
    WHERE DATE(created_at) = CURDATE()
";
$todaySummary = $pdo->query($todaySQL)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Sales - TechHat Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; padding: 0; background: #f5f7fa; font-family: Arial, sans-serif; }
        
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #3498db; }
        .summary-card h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; }
        .summary-card .value { font-size: 28px; font-weight: bold; color: #2c3e50; }
        
        .filter-section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #2c3e50; }
        .filter-group input, .filter-group select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #34495e; color: white; font-weight: 600; font-size: 14px; }
        tr:hover { background: #f8f9fa; }
        
        .pagination { display: flex; gap: 10px; justify-content: center; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #2c3e50; }
        .pagination a:hover { background: #3498db; color: white; border-color: #3498db; }
        .pagination .active { background: #3498db; color: white; border-color: #3498db; }
        
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-warning { background: #fff3cd; color: #856404; }
        
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; border: none; cursor: pointer; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div style="padding: 20px; background: #f5f7fa;">
    
            <h1>POS Sales Summary</h1>

            <?php if (isset($_GET['return_success'])): ?>
                <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                    <strong>✅ Return Processed Successfully!</strong><br>
                    Return ID: #<?php echo htmlspecialchars($_GET['return_id'] ?? ''); ?> | 
                    Amount: ৳<?php echo number_format($_GET['amount'] ?? 0, 2); ?>
                </div>
            <?php endif; ?>

            <!-- Today's Summary -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Today's Sales</h3>
                    <div class="value"><?php echo number_format($todaySummary['total_sales']); ?></div>
                </div>
                <div class="summary-card" style="border-left-color: #27ae60;">
                    <h3>Today's Revenue</h3>
                    <div class="value">৳<?php echo number_format($todaySummary['total_amount'], 2); ?></div>
                </div>
                <div class="summary-card" style="border-left-color: #e74c3c;">
                    <h3>Total Records</h3>
                    <div class="value"><?php echo number_format($totalRecords); ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Invoice #, Customer name, Phone" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label>From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="filter-group">
                            <label>To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="pos_sales.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Sales Table -->
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Staff</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #95a5a6;">
                                No sales found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): 
                            $isPartialReturn = $sale['total_returned'] > 0 && $sale['total_returned'] < $sale['total_amount'];
                            $isFullReturn = $sale['total_returned'] >= $sale['total_amount'];
                        ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($sale['created_at'])); ?></td>
                            <td>
                                <?php if ($sale['customer_name']): ?>
                                    <strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">Walk-in Customer</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $sale['total_items']; ?> item(s)</td>
                            <td>
                                <strong>৳<?php echo number_format($sale['total_amount'], 2); ?></strong>
                                <?php if ($sale['total_returned'] > 0): ?>
                                    <br><small style="color: #e74c3c;">Returned: ৳<?php echo number_format($sale['total_returned'], 2); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isFullReturn): ?>
                                    <span class="badge" style="background: #e74c3c; color: white;">FULL RETURN</span>
                                <?php elseif ($isPartialReturn): ?>
                                    <span class="badge" style="background: #f39c12; color: white;">PARTIAL RETURN</span>
                                <?php else: ?>
                                    <span class="badge badge-success">COMPLETED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $sale['payment_method'] === 'cash' ? 'success' : ($sale['payment_method'] === 'card' ? 'info' : 'warning'); ?>">
                                    <?php echo strtoupper($sale['payment_method']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($sale['staff_name'] ?? 'N/A'); ?></td>
                            <td>
                                <a href="pos_invoice.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn btn-success btn-sm" style="margin-right: 5px;">
                                    🖨️ Print
                                </a>
                                <?php if ($sale['return_count'] > 0): ?>
                                    <a href="pos_return_invoice.php?sale_id=<?php echo $sale['id']; ?>" target="_blank" class="btn btn-sm" style="background: #9b59b6; color: white; margin-right: 5px;">
                                        📄 Return Voucher
                                    </a>
                                <?php endif; ?>
                                <?php if (!$isFullReturn): ?>
                                    <a href="pos_return.php?sale_id=<?php echo $sale['id']; ?>" class="btn btn-danger btn-sm">
                                        ↩️ Return
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>">« Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>">Next »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
    </div>
</body>
</html>
