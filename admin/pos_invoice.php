<?php
require_once '../core/auth.php';
require_admin();

$sale_id = (int)($_GET['id'] ?? 0);

if ($sale_id <= 0) {
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

// Fetch sale items
$stmt = $pdo->prepare("
    SELECT 
        psi.*,
        p.title as product_name,
        pv.name as variant_name,
        pv.sku
    FROM pos_sale_items psi
    INNER JOIN product_variants pv ON psi.variant_id = pv.id
    INNER JOIN products p ON pv.product_id = p.id
    WHERE psi.pos_sale_id = ?
    ORDER BY psi.id ASC
");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Invoice #<?php echo $sale_id; ?> - TechHat</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        .invoice-container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }
        .invoice-header { 
            text-align: center; 
            border-bottom: 2px solid #000; 
            padding-bottom: 20px; 
            margin-bottom: 20px; 
        }
        .invoice-header h1 { 
            font-size: 32px; 
            margin-bottom: 5px; 
        }
        .invoice-header p { 
            font-size: 14px; 
            color: #666; 
        }
        .invoice-info { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 30px; 
            font-size: 14px; 
        }
        .invoice-info div { 
            line-height: 1.8; 
        }
        .invoice-info strong { 
            display: inline-block; 
            width: 120px; 
        }
        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
        }
        .items-table thead { 
            background: #000; 
            color: white; 
        }
        .items-table th, .items-table td { 
            padding: 12px; 
            text-align: left; 
            border: 1px solid #ddd; 
        }
        .items-table th { 
            font-weight: bold; 
        }
        .items-table tbody tr:nth-child(even) { 
            background: #f9f9f9; 
        }
        .items-table .text-right { 
            text-align: right; 
        }
        .items-table .text-center { 
            text-align: center; 
        }
        .totals { 
            margin-left: auto; 
            width: 300px; 
            font-size: 14px; 
        }
        .totals-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 8px 0; 
            border-bottom: 1px solid #ddd; 
        }
        .totals-row.total { 
            font-size: 18px; 
            font-weight: bold; 
            border-top: 2px solid #000; 
            border-bottom: 2px double #000; 
            padding: 15px 0; 
            margin-top: 10px; 
        }
        .footer { 
            text-align: center; 
            margin-top: 40px; 
            padding-top: 20px; 
            border-top: 1px dashed #999; 
            font-size: 13px; 
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
            margin-bottom: 20px; 
            display: block; 
            margin-left: auto; 
            margin-right: auto; 
        }
        .print-btn:hover { 
            background: #229954; 
        }
        @media print {
            body { 
                background: white; 
                padding: 0; 
            }
            .invoice-container { 
                box-shadow: none; 
                padding: 20px; 
            }
            .print-btn { 
                display: none; 
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print Invoice</button>

    <div class="invoice-container">
        <div class="invoice-header">
            <h1>TECHHAT</h1>
            <p>Custom Ecommerce + POS + Accounting System</p>
            <p>Phone: +880 1234-567890 | Email: info@techhat.com</p>
        </div>

        <div class="invoice-info">
            <div>
                <strong>Invoice #:</strong> <?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?><br>
                <strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($sale['created_at'])); ?><br>
                <strong>Staff:</strong> <?php echo htmlspecialchars($sale['staff_name'] ?? 'N/A'); ?><br>
                <strong>Payment:</strong> <?php echo strtoupper($sale['payment_method']); ?>
            </div>
            <?php if ($sale['customer_name'] || $sale['customer_phone']): ?>
            <div>
                <strong>Customer Info:</strong><br>
                <?php if ($sale['customer_name']): ?>
                    <strong>Name:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?><br>
                <?php endif; ?>
                <?php if ($sale['customer_phone']): ?>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($sale['customer_phone']); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Variant</th>
                    <th class="text-center">SKU</th>
                    <th class="text-right">Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sn = 1;
                $subtotal = 0;
                foreach ($items as $item): 
                    $itemTotal = $item['price'] * $item['quantity'];
                    $subtotal += $itemTotal;
                ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['variant_name']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($item['sku'] ?: '-'); ?></td>
                    <td class="text-right">৳<?php echo number_format($item['price'], 2); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-right">৳<?php echo number_format($itemTotal, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>৳<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <?php if ($sale['commission'] != 0): ?>
            <div class="totals-row" style="color: <?php echo $sale['commission'] > 0 ? '#27ae60' : '#e74c3c'; ?>; font-weight: 600;">
                <span>Commission <?php echo $sale['commission'] > 0 ? '(+)' : '(-)'; ?>:</span>
                <span><?php echo $sale['commission'] > 0 ? '+' : ''; ?>৳<?php echo number_format($sale['commission'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="totals-row">
                <span>Tax (0%):</span>
                <span>৳0.00</span>
            </div>
            <div class="totals-row total">
                <span>TOTAL:</span>
                <span>৳<?php echo number_format($sale['total_amount'], 2); ?></span>
            </div>
        </div>

        <div class="footer">
            <p><strong>Thank you for your purchase!</strong></p>
            <p>This is a computer-generated invoice and does not require a signature.</p>
            <p>Powered by TechHat POS System | <?php echo date('Y'); ?></p>
        </div>
    </div>

    <script>
        // Auto print on load (optional)
        // window.addEventListener('load', function() {
        //     setTimeout(() => window.print(), 500);
        // });
    </script>
</body>
</html>
