<?php
require_once '../core/auth.php';
require_admin();

$sale_id = (int)($_GET['sale_id'] ?? 0);

if (!$sale_id) {
    die('Invalid sale ID');
}

// Fetch sale details
$stmt = $pdo->prepare("
    SELECT 
        ps.*,
        u.name as staff_name
    FROM pos_sales ps
    LEFT JOIN users u ON ps.staff_user_id = u.id
    WHERE ps.id = ?
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die('Sale not found');
}

// Fetch all returns for this sale
$stmt = $pdo->prepare("
    SELECT 
        pr.*,
        u.name as returned_by_name
    FROM pos_returns pr
    LEFT JOIN users u ON pr.returned_by = u.id
    WHERE pr.pos_sale_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->execute([$sale_id]);
$returns = $stmt->fetchAll();

if (empty($returns)) {
    die('No returns found for this sale');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Voucher - Invoice #<?php echo $sale_id; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            padding: 20px; 
            background: white; 
            color: #000;
        }
        
        .voucher-container { max-width: 800px; margin: 0 auto; }
        
        .voucher-header { 
            text-align: center; 
            border-bottom: 3px double #000; 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
        }
        .voucher-header h1 { 
            font-size: 24px; 
            margin-bottom: 5px;
            color: #e74c3c;
        }
        .company-name { 
            font-size: 18px; 
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .original-sale-info { 
            background: #f8f9fa; 
            padding: 15px; 
            border: 2px solid #dee2e6; 
            border-radius: 5px;
            margin-bottom: 20px; 
        }
        .original-sale-info h3 { 
            margin-bottom: 10px; 
            color: #495057;
            border-bottom: 1px solid #adb5bd;
            padding-bottom: 5px;
        }
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 5px 0; 
        }
        
        .return-section { 
            margin-bottom: 30px; 
            border: 2px solid #e74c3c;
            border-radius: 5px;
            overflow: hidden;
        }
        .return-header { 
            background: #e74c3c; 
            color: white; 
            padding: 10px 15px; 
            font-weight: bold;
        }
        .return-body { padding: 15px; }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
        }
        th, td { 
            padding: 8px; 
            text-align: left; 
            border: 1px solid #000; 
        }
        th { 
            background: #495057; 
            color: white; 
            font-weight: bold; 
            font-size: 12px;
        }
        td { font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .return-total { 
            background: #fff3cd; 
            padding: 15px; 
            border: 2px solid #ffc107; 
            border-radius: 5px;
            margin: 15px 0;
        }
        .return-total-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 8px;
            font-size: 14px;
        }
        .return-total-row.grand { 
            font-size: 20px; 
            font-weight: bold; 
            border-top: 2px solid #e74c3c;
            padding-top: 10px;
            margin-top: 10px;
            color: #e74c3c;
        }
        
        .footer { 
            margin-top: 30px; 
            padding-top: 15px; 
            border-top: 2px dashed #000; 
            text-align: center; 
            font-size: 11px; 
        }
        
        .print-btn { 
            background: #e74c3c; 
            color: white; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px; 
            cursor: pointer; 
            margin: 20px auto; 
            display: block; 
        }
        .print-btn:hover { background: #c0392b; }
        
        @media print {
            body { padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print Return Voucher</button>

    <div class="voucher-container">
        <div class="voucher-header">
            <div class="company-name">TECHHAT</div>
            <h1>↩️ RETURN VOUCHER</h1>
            <div style="font-size: 12px; margin-top: 5px;">
                Original Invoice: #<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?>
            </div>
        </div>

        <!-- Original Sale Information -->
        <div class="original-sale-info">
            <h3>Original Sale Information</h3>
            <div class="info-row">
                <span><strong>Invoice #:</strong></span>
                <span>#<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span><strong>Sale Date:</strong></span>
                <span><?php echo date('d M Y, h:i A', strtotime($sale['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span><strong>Customer:</strong></span>
                <span><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer'); ?></span>
            </div>
            <?php if ($sale['customer_phone']): ?>
            <div class="info-row">
                <span><strong>Phone:</strong></span>
                <span><?php echo htmlspecialchars($sale['customer_phone']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span><strong>Original Amount:</strong></span>
                <span><strong>৳<?php echo number_format($sale['total_amount'], 2); ?></strong></span>
            </div>
            <div class="info-row">
                <span><strong>Payment Method:</strong></span>
                <span><?php echo strtoupper($sale['payment_method']); ?></span>
            </div>
        </div>

        <?php 
        $total_all_returns = 0;
        $return_count = 1;
        foreach ($returns as $return): 
            $total_all_returns += $return['return_amount'];
            
            // Fetch return items
            $stmt = $pdo->prepare("
                SELECT 
                    pri.*,
                    p.title as product_name,
                    pv.name as variant_name
                FROM pos_return_items pri
                INNER JOIN product_variants pv ON pri.variant_id = pv.id
                INNER JOIN products p ON pv.product_id = p.id
                WHERE pri.pos_return_id = ?
            ");
            $stmt->execute([$return['id']]);
            $return_items = $stmt->fetchAll();
        ?>
        
        <!-- Return Details -->
        <div class="return-section">
            <div class="return-header">
                Return #<?php echo $return_count; ?> - ID: <?php echo $return['id']; ?> 
                (<?php echo date('d M Y, h:i A', strtotime($return['created_at'])); ?>)
            </div>
            <div class="return-body">
                <p style="margin-bottom: 10px;">
                    <strong>Returned By:</strong> <?php echo htmlspecialchars($return['returned_by_name'] ?? 'N/A'); ?><br>
                    <strong>Reason:</strong> <?php echo htmlspecialchars($return['return_reason']); ?>
                </p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Product / Variant</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($return_items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($item['variant_name']); ?></small>
                            </td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-right">৳<?php echo number_format($item['price'], 2); ?></td>
                            <td class="text-right">৳<?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="3" class="text-right">Return Amount:</td>
                            <td class="text-right" style="color: #e74c3c;">৳<?php echo number_format($return['return_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php 
        $return_count++;
        endforeach; 
        ?>

        <!-- Total Returns Summary -->
        <div class="return-total">
            <div class="return-total-row">
                <span>Original Invoice Amount:</span>
                <span>৳<?php echo number_format($sale['total_amount'], 2); ?></span>
            </div>
            <div class="return-total-row">
                <span>Total Returns (<?php echo count($returns); ?> transaction<?php echo count($returns) > 1 ? 's' : ''; ?>):</span>
                <span style="color: #e74c3c;">- ৳<?php echo number_format($total_all_returns, 2); ?></span>
            </div>
            <div class="return-total-row grand">
                <span>NET AMOUNT:</span>
                <span>৳<?php echo number_format($sale['total_amount'] - $total_all_returns, 2); ?></span>
            </div>
        </div>

        <div class="footer">
            <p><strong>*** RETURN VOUCHER ***</strong></p>
            <p>Stock has been restored to inventory</p>
            <p>Amount has been adjusted in accounts</p>
            <p style="margin-top: 10px;">TechHat POS System | Printed: <?php echo date('d M Y, h:i A'); ?></p>
        </div>
    </div>
</body>
</html>
