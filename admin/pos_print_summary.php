<?php
require_once '../core/auth.php';
require_admin();

// Get parameters
$reportType = $_GET['type'] ?? 'day';
$date = $_GET['date'] ?? date('Y-m-d');

// Calculate date range based on report type
switch ($reportType) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $endDate = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        $title = 'Weekly Sales Report';
        $period = date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));
        break;
    
    case 'month':
        $startDate = date('Y-m-01', strtotime($date));
        $endDate = date('Y-m-t', strtotime($date));
        $title = 'Monthly Sales Report';
        $period = date('F Y', strtotime($date));
        break;
    
    case 'year':
        $startDate = date('Y-01-01', strtotime($date));
        $endDate = date('Y-12-31', strtotime($date));
        $title = 'Yearly Sales Report';
        $period = date('Y', strtotime($date));
        break;
    
    case 'day':
    default:
        $startDate = $endDate = $date;
        $title = 'Daily Sales Report';
        $period = date('d F Y', strtotime($date));
        break;
}

// Get sales data
$sql = "
    SELECT 
        ps.*,
        u.name as staff_name,
        COUNT(psi.id) as total_items,
        'sale' as transaction_type
    FROM pos_sales ps
    LEFT JOIN users u ON ps.staff_user_id = u.id
    LEFT JOIN pos_sale_items psi ON ps.id = psi.pos_sale_id
    WHERE DATE(ps.created_at) BETWEEN ? AND ?
    GROUP BY ps.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$sales = $stmt->fetchAll();

// Get returns data
$returnsSQL = "
    SELECT 
        pr.*,
        ps.id as sale_id,
        ps.customer_name,
        ps.customer_phone,
        ps.payment_method,
        u.name as staff_name,
        COUNT(pri.id) as total_items,
        'return' as transaction_type
    FROM pos_returns pr
    INNER JOIN pos_sales ps ON pr.pos_sale_id = ps.id
    LEFT JOIN users u ON pr.returned_by = u.id
    LEFT JOIN pos_return_items pri ON pr.id = pri.pos_return_id
    WHERE DATE(pr.created_at) BETWEEN ? AND ?
    GROUP BY pr.id
";
$stmt = $pdo->prepare($returnsSQL);
$stmt->execute([$startDate, $endDate]);
$returns = $stmt->fetchAll();

// Merge and sort all transactions
$allTransactions = array_merge($sales, $returns);
usort($allTransactions, function($a, $b) {
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});

// Calculate totals
$totalSales = count($sales);
$totalReturns = count($returns);
$totalAmount = array_sum(array_column($sales, 'total_amount'));
$totalReturnAmount = array_sum(array_column($returns, 'return_amount'));
$netAmount = $totalAmount - $totalReturnAmount;

$cashSales = array_sum(array_map(fn($s) => $s['payment_method'] === 'cash' ? $s['total_amount'] : 0, $sales));
$cardSales = array_sum(array_map(fn($s) => $s['payment_method'] === 'card' ? $s['total_amount'] : 0, $sales));
$mobileSales = array_sum(array_map(fn($s) => $s['payment_method'] === 'mobile' ? $s['total_amount'] : 0, $sales));

$cashReturns = array_sum(array_map(fn($r) => $r['payment_method'] === 'cash' ? $r['return_amount'] : 0, $returns));
$cardReturns = array_sum(array_map(fn($r) => $r['payment_method'] === 'card' ? $r['return_amount'] : 0, $returns));
$mobileReturns = array_sum(array_map(fn($r) => $r['payment_method'] === 'mobile' ? $r['return_amount'] : 0, $returns));

// Get detailed items for the period
$itemsSQL = "
    SELECT 
        p.title as product_name,
        pv.name as variant_name,
        SUM(psi.quantity) as total_qty,
        psi.price as unit_price,
        SUM(psi.quantity * psi.price) as total_amount
    FROM pos_sale_items psi
    INNER JOIN product_variants pv ON psi.variant_id = pv.id
    INNER JOIN products p ON pv.product_id = p.id
    INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id
    WHERE DATE(ps.created_at) BETWEEN ? AND ?
    GROUP BY psi.variant_id, psi.price
    ORDER BY total_amount DESC
";
$stmt = $pdo->prepare($itemsSQL);
$stmt->execute([$startDate, $endDate]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - TechHat</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            padding: 20px; 
            background: white; 
            color: #000;
        }
        
        .report-container { max-width: 900px; margin: 0 auto; }
        
        .report-header { 
            text-align: center; 
            border-bottom: 3px double #000; 
            padding-bottom: 20px; 
            margin-bottom: 20px; 
        }
        .report-header h1 { 
            font-size: 28px; 
            margin-bottom: 5px; 
            text-transform: uppercase;
        }
        .report-header .company-name { 
            font-size: 20px; 
            margin-bottom: 10px; 
            font-weight: bold;
        }
        .report-header .period { 
            font-size: 16px; 
            color: #333; 
            margin-top: 10px;
        }
        
        .summary-section { 
            margin-bottom: 30px; 
            padding: 15px; 
            background: #f5f5f5; 
            border: 1px solid #000;
        }
        .summary-section h2 { 
            font-size: 16px; 
            margin-bottom: 10px; 
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .summary-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 5px 0; 
            border-bottom: 1px dotted #999;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row.total { 
            font-size: 18px; 
            font-weight: bold; 
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 10px 0;
            margin-top: 5px;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 30px; 
        }
        table.ledger { 
            border: 1px solid #000; 
        }
        th, td { 
            padding: 8px; 
            text-align: left; 
            border: 1px solid #000; 
        }
        th { 
            background: #e0e0e0; 
            font-weight: bold; 
            text-transform: uppercase;
            font-size: 12px;
        }
        td { font-size: 12px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .return-row { 
            background: #ffe6e6; 
            color: #d00;
        }
        .return-amount { 
            color: #d00; 
            font-weight: bold; 
        }
        .badge-sale { 
            background: #28a745; 
            color: white; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 10px;
            font-weight: bold;
        }
        .badge-return { 
            background: #dc3545; 
            color: white; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 10px;
            font-weight: bold;
        }
        
        .section-title { 
            font-size: 18px; 
            font-weight: bold; 
            margin: 20px 0 10px; 
            padding-bottom: 5px; 
            border-bottom: 2px solid #000;
            text-transform: uppercase;
        }
        
        .footer { 
            margin-top: 40px; 
            padding-top: 20px; 
            border-top: 1px dashed #000; 
            text-align: center; 
            font-size: 11px; 
            color: #666; 
        }
        
        .print-btn { 
            background: #27ae60; 
            color: white; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px; 
            cursor: pointer; 
            margin: 20px auto; 
            display: block; 
        }
        .print-btn:hover { background: #229954; }
        
        @media print {
            body { padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>

    <div class="report-container">
        <div class="report-header">
            <div class="company-name">TECHHAT</div>
            <h1><?php echo $title; ?></h1>
            <div class="period"><?php echo $period; ?></div>
            <div style="font-size: 12px; margin-top: 5px; color: #666;">
                Generated on: <?php echo date('d M Y, h:i A'); ?>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <h2>Summary</h2>
            <div class="summary-row">
                <span>Total Sales Transactions:</span>
                <span><strong><?php echo $totalSales; ?></strong></span>
            </div>
            <?php if ($totalReturns > 0): ?>
            <div class="summary-row">
                <span>Total Returns:</span>
                <span style="color: #e74c3c;"><strong><?php echo $totalReturns; ?></strong></span>
            </div>
            <div class="summary-row">
                <span>Net Transactions:</span>
                <span><strong><?php echo $totalSales + $totalReturns; ?></strong></span>
            </div>
            <?php endif; ?>
            <div class="summary-row" style="border-top: 1px solid #999; margin-top: 5px; padding-top: 5px;">
                <span>Gross Sales:</span>
                <span>৳<?php echo number_format($totalAmount, 2); ?></span>
            </div>
            <?php if ($totalReturnAmount > 0): ?>
            <div class="summary-row">
                <span>Total Returns Amount:</span>
                <span style="color: #e74c3c;">- ৳<?php echo number_format($totalReturnAmount, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <span>Cash Sales:</span>
                <span>৳<?php echo number_format($cashSales, 2); ?></span>
            </div>
            <?php if ($cashReturns > 0): ?>
            <div class="summary-row">
                <span>Cash Returns:</span>
                <span style="color: #e74c3c;">- ৳<?php echo number_format($cashReturns, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <span>Card Sales:</span>
                <span>৳<?php echo number_format($cardSales, 2); ?></span>
            </div>
            <?php if ($cardReturns > 0): ?>
            <div class="summary-row">
                <span>Card Returns:</span>
                <span style="color: #e74c3c;">- ৳<?php echo number_format($cardReturns, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <span>Mobile Payment Sales:</span>
                <span>৳<?php echo number_format($mobileSales, 2); ?></span>
            </div>
            <?php if ($mobileReturns > 0): ?>
            <div class="summary-row">
                <span>Mobile Payment Returns:</span>
                <span style="color: #e74c3c;">- ৳<?php echo number_format($mobileReturns, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row total">
                <span><?php echo $totalReturnAmount > 0 ? 'NET SALES:' : 'TOTAL SALES:'; ?></span>
                <span>৳<?php echo number_format($netAmount, 2); ?></span>
            </div>
        </div>

        <!-- Transaction Ledger -->
        <div class="section-title">Transaction Ledger (Chronological)</div>
        <table class="ledger">
            <thead>
                <tr>
                    <th class="text-center">Date & Time</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Invoice #</th>
                    <th>Customer</th>
                    <th class="text-center">Items</th>
                    <th class="text-center">Payment</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allTransactions)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px; color: #999;">
                            No transactions found for this period
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $runningTotal = 0;
                    foreach ($allTransactions as $txn): 
                        $isSale = $txn['transaction_type'] === 'sale';
                        $amount = $isSale ? $txn['total_amount'] : -$txn['return_amount'];
                        $runningTotal += $amount;
                        $invoiceNum = $isSale ? $txn['id'] : $txn['sale_id'];
                    ?>
                    <tr class="<?php echo !$isSale ? 'return-row' : ''; ?>">
                        <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($txn['created_at'])); ?></td>
                        <td class="text-center">
                            <?php if ($isSale): ?>
                                <span class="badge-sale">SALE</span>
                            <?php else: ?>
                                <span class="badge-return">RETURN</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php echo str_pad($invoiceNum, 6, '0', STR_PAD_LEFT); ?>
                            <?php if (!$isSale): ?>
                                <br><small style="color: #999;">Ret #<?php echo $txn['id']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($txn['customer_name']): ?>
                                <?php echo htmlspecialchars($txn['customer_name']); ?>
                                <?php if ($txn['customer_phone']): ?>
                                    <br><small><?php echo htmlspecialchars($txn['customer_phone']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                Walk-in
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $txn['total_items']; ?></td>
                        <td class="text-center"><?php echo strtoupper($txn['payment_method']); ?></td>
                        <td class="text-right <?php echo !$isSale ? 'return-amount' : ''; ?>">
                            <?php echo !$isSale ? '- ' : ''; ?>৳<?php echo number_format(abs($amount), 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #f0f0f0; font-weight: bold;">
                        <td colspan="6" class="text-right">TOTAL:</td>
                        <td class="text-right">৳<?php echo number_format($runningTotal, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Product Sales Breakdown -->
        <?php if (!empty($items)): ?>
        <div class="section-title">Product Sales Breakdown</div>
        <table class="ledger">
            <thead>
                <tr>
                    <th>Product / Variant</th>
                    <th class="text-center">Quantity Sold</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($item['product_name']); ?>
                        <br><small style="color: #666;"><?php echo htmlspecialchars($item['variant_name']); ?></small>
                    </td>
                    <td class="text-center"><?php echo $item['total_qty']; ?></td>
                    <td class="text-right">৳<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="text-right">৳<?php echo number_format($item['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="footer">
            <p><strong>*** End of Report ***</strong></p>
            <p>This is a computer-generated report from TechHat POS System</p>
            <p>Printed on: <?php echo date('d M Y, h:i:s A'); ?></p>
        </div>
    </div>
</body>
</html>
