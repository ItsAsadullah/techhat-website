<?php
require_once '../core/auth.php';
require_admin();

// Always in iframe mode when accessed from POS tabs
$isIframe = true;

// Get unique customers
$customersSQL = "
    SELECT DISTINCT customer_name, customer_phone
    FROM pos_sales
    WHERE customer_name IS NOT NULL AND customer_name != ''
    ORDER BY customer_name ASC
";
$customers = $pdo->query($customersSQL)->fetchAll();

// Selected customer
$selectedCustomer = $_GET['customer'] ?? '';

// Get customer ledger
$ledger = [];
$totalSales = 0;
$totalAmount = 0;

if ($selectedCustomer) {
    $ledgerSQL = "
        SELECT 
            ps.*,
            u.name as staff_name,
            COUNT(psi.id) as total_items,
            COALESCE(SUM(pr.return_amount), 0) as total_returned
        FROM pos_sales ps
        LEFT JOIN users u ON ps.staff_user_id = u.id
        LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
        LEFT JOIN pos_returns pr ON ps.id = pr.pos_sale_id
        WHERE (ps.customer_name = ? OR ps.customer_phone = ?)
        GROUP BY ps.id
        ORDER BY ps.created_at DESC
    ";
    $stmt = $pdo->prepare($ledgerSQL);
    $stmt->execute([$selectedCustomer, $selectedCustomer]);
    $ledger = $stmt->fetchAll();
    
    $totalSales = count($ledger);
    $totalAmount = array_sum(array_column($ledger, 'total_amount'));
    $totalReturns = array_sum(array_column($ledger, 'total_returned'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger - TechHat Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; padding: 0; background: #f5f7fa; font-family: Arial, sans-serif; }
        
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #3498db; }
        .summary-card h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; }
        .summary-card .value { font-size: 28px; font-weight: bold; color: #2c3e50; }
        
        .filter-section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #2c3e50; }
        .filter-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        
        .customer-info { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .customer-info h2 { margin-bottom: 10px; color: #2c3e50; }
        .customer-info p { color: #7f8c8d; margin: 5px 0; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #34495e; color: white; font-weight: 600; font-size: 14px; }
        tr:hover { background: #f8f9fa; }
        
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-warning { background: #fff3cd; color: #856404; }
        
        .no-data { text-align: center; padding: 60px 20px; color: #95a5a6; }
        .no-data-icon { font-size: 48px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div style="padding: 20px; background: #f5f7fa;">
    
            <h1>Customer Ledger</h1>

            <!-- Customer Selection -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-group">
                        <label>Select Customer</label>
                        <select name="customer" onchange="this.form.submit()">
                            <option value="">-- Select a customer --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo htmlspecialchars($customer['customer_name']); ?>" 
                                        <?php echo $selectedCustomer === $customer['customer_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['customer_name']); ?> 
                                    (<?php echo htmlspecialchars($customer['customer_phone']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($selectedCustomer && !empty($ledger)): ?>
                <!-- Customer Info -->
                <div class="customer-info">
                    <h2><?php echo htmlspecialchars($ledger[0]['customer_name']); ?></h2>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($ledger[0]['customer_phone']); ?></p>
                </div>

                <!-- Summary -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3>Total Purchases</h3>
                        <div class="value"><?php echo $totalSales; ?></div>
                    </div>
                    <div class="summary-card" style="border-left-color: #27ae60;">
                        <h3>Total Amount</h3>
                        <div class="value">৳<?php echo number_format($totalAmount, 2); ?></div>
                    </div>
                    <?php if ($totalReturns > 0): ?>
                    <div class="summary-card" style="border-left-color: #e74c3c;">
                        <h3>Total Returns</h3>
                        <div class="value" style="color: #e74c3c;">৳<?php echo number_format($totalReturns, 2); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="summary-card" style="border-left-color: #3498db;">
                        <h3><?php echo $totalReturns > 0 ? 'Net Amount' : 'Average Purchase'; ?></h3>
                        <div class="value">৳<?php echo number_format($totalReturns > 0 ? ($totalAmount - $totalReturns) : ($totalAmount / $totalSales), 2); ?></div>
                    </div>
                </div>

                <!-- Ledger Table -->
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Staff</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledger as $sale): 
                            $isPartialReturn = $sale['total_returned'] > 0 && $sale['total_returned'] < $sale['total_amount'];
                            $isFullReturn = $sale['total_returned'] >= $sale['total_amount'];
                        ?>
                        <tr>
                            <td>
                                <strong>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                <?php if ($sale['total_returned'] > 0): ?>
                                    <br><span class="badge" style="background: #e74c3c; color: white; font-size: 10px;">
                                        <?php echo $isFullReturn ? 'FULL RETURN' : 'PARTIAL'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y, h:i A', strtotime($sale['created_at'])); ?></td>
                            <td><?php echo $sale['total_items']; ?> item(s)</td>
                            <td>
                                <strong>৳<?php echo number_format($sale['total_amount'], 2); ?></strong>
                                <?php if ($sale['total_returned'] > 0): ?>
                                    <br><small style="color: #e74c3c;">- ৳<?php echo number_format($sale['total_returned'], 2); ?></small>
                                    <br><small style="color: #27ae60; font-weight: 600;">Net: ৳<?php echo number_format($sale['total_amount'] - $sale['total_returned'], 2); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $sale['payment_method'] === 'cash' ? 'success' : ($sale['payment_method'] === 'card' ? 'info' : 'warning'); ?>">
                                    <?php echo strtoupper($sale['payment_method']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($sale['staff_name'] ?? 'N/A'); ?></td>
                            <td>
                                <a href="pos_invoice.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn btn-success btn-sm">
                                    🖨️ Invoice
                                </a>
                                <?php if ($sale['total_returned'] > 0): ?>
                                    <br>
                                    <a href="pos_return_invoice.php?sale_id=<?php echo $sale['id']; ?>" target="_blank" class="btn btn-sm" style="background: #9b59b6; color: white; margin-top: 5px;">
                                        📄 Return Voucher
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="3" style="text-align: right;">TOTAL:</td>
                            <td>
                                ৳<?php echo number_format($totalAmount, 2); ?>
                                <?php if ($totalReturns > 0): ?>
                                    <br><small style="color: #e74c3c;">Returns: ৳<?php echo number_format($totalReturns, 2); ?></small>
                                    <br><small style="color: #27ae60;">Net: ৳<?php echo number_format($totalAmount - $totalReturns, 2); ?></small>
                                <?php endif; ?>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                        <tr style="background: #ecf0f1; font-weight: bold;">
                            <td colspan="3" style="text-align: right;">TOTAL:</td>
                            <td colspan="4"><strong>৳<?php echo number_format($totalAmount, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>

            <?php elseif ($selectedCustomer): ?>
                <div class="no-data">
                    <div class="no-data-icon">📋</div>
                    <p>No transactions found for this customer</p>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">👤</div>
                    <p>Please select a customer to view their ledger</p>
                </div>
            <?php endif; ?>
    </div>
</body>
</html>
