<?php
require_once '../core/auth.php';
require_admin();

$sale_id = (int)($_GET['sale_id'] ?? 0);

if (!$sale_id) {
    die('Invalid sale ID');
}

// Fetch sale details
$sql = "
    SELECT 
        ps.*,
        u.name as staff_name,
        COALESCE(SUM(pr.return_amount), 0) as total_returned
    FROM pos_sales ps
    LEFT JOIN users u ON ps.staff_user_id = u.id
    LEFT JOIN pos_returns pr ON ps.id = pr.pos_sale_id
    WHERE ps.id = ?
    GROUP BY ps.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die('Sale not found');
}

// Fetch sale items with already returned quantities
$sql = "
    SELECT 
        psi.*,
        p.title as product_name,
        pv.name as variant_name,
        COALESCE(SUM(pri.quantity), 0) as returned_qty
    FROM pos_sale_items psi
    INNER JOIN product_variants pv ON psi.variant_id = pv.id
    INNER JOIN products p ON pv.product_id = p.id
    LEFT JOIN pos_return_items pri ON psi.id = pri.pos_sale_item_id
    WHERE psi.pos_sale_id = ?
    GROUP BY psi.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

$remaining_amount = $sale['total_amount'] - $sale['total_returned'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Return - Invoice #<?php echo $sale_id; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { margin: 0; padding: 20px; background: #f5f7fa; font-family: Arial, sans-serif; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        .invoice-info { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .invoice-info div { margin-bottom: 8px; }
        .invoice-info strong { color: #34495e; }
        
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .items-table th { background: #34495e; color: white; font-weight: 600; }
        .items-table tr:hover { background: #f8f9fa; }
        
        .return-input { width: 80px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .return-reason { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0; }
        
        .summary { background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .summary div { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .summary .total { font-size: 18px; font-weight: bold; border-top: 2px solid #27ae60; padding-top: 10px; margin-top: 10px; }
        
        .btn { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: #95a5a6; color: white; margin-left: 10px; }
        .btn-secondary:hover { background: #7f8c8d; }
        
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>↩️ Process Return - Invoice #<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?></h1>
        
        <div class="invoice-info">
            <div><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($sale['created_at'])); ?></div>
            <div><strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></div>
            <?php if ($sale['customer_phone']): ?>
                <div><strong>Phone:</strong> <?php echo htmlspecialchars($sale['customer_phone']); ?></div>
            <?php endif; ?>
            <div><strong>Staff:</strong> <?php echo htmlspecialchars($sale['staff_name']); ?></div>
            <div><strong>Payment Method:</strong> <?php echo strtoupper($sale['payment_method']); ?></div>
            <div><strong>Total Amount:</strong> ৳<?php echo number_format($sale['total_amount'], 2); ?></div>
            <?php if ($sale['total_returned'] > 0): ?>
                <div><strong>Previously Returned:</strong> <span style="color: #e74c3c;">৳<?php echo number_format($sale['total_returned'], 2); ?></span></div>
                <div><strong>Remaining:</strong> <span style="color: #27ae60;">৳<?php echo number_format($remaining_amount, 2); ?></span></div>
            <?php endif; ?>
        </div>

        <?php if ($remaining_amount <= 0): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Full Return Processed!</strong><br>
                This invoice has been fully returned. No further returns can be processed.
            </div>
            <a href="pos_sales.php" class="btn btn-secondary">← Back to Sales</a>
        <?php else: ?>
            <div class="alert alert-warning">
                <strong>⚠️ Return Policy:</strong> Select items to return. Stock will be added back and amount will be refunded.
            </div>

            <form method="POST" action="pos_return_submit.php" id="returnForm">
                <input type="hidden" name="sale_id" value="<?php echo $sale_id; ?>">
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product / Variant</th>
                            <th>Sold Qty</th>
                            <th>Unit Price</th>
                            <th>Return Qty</th>
                            <th>Return Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $available_qty = $item['quantity'] - $item['returned_qty'];
                        ?>
                        <tr <?php echo $available_qty <= 0 ? 'style="opacity: 0.5; background: #f8f9fa;"' : ''; ?>>
                            <td>
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                <small style="color: #7f8c8d;"><?php echo htmlspecialchars($item['variant_name']); ?></small>
                                <?php if ($item['returned_qty'] > 0): ?>
                                    <br><span class="badge" style="background: #e74c3c; color: white; font-size: 10px;">Returned: <?php echo $item['returned_qty']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $item['quantity']; ?>
                                <?php if ($item['returned_qty'] > 0): ?>
                                    <br><small style="color: #27ae60;">Available: <?php echo $available_qty; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>৳<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <?php if ($available_qty > 0): ?>
                                <input type="number" 
                                       name="return_qty[<?php echo $item['id']; ?>]" 
                                       class="return-input return-qty-input" 
                                       min="0" 
                                       max="<?php echo $available_qty; ?>" 
                                       value="0"
                                       data-price="<?php echo $item['price']; ?>"
                                       data-item-id="<?php echo $item['id']; ?>"
                                       onchange="calculateReturnAmount()">
                                <?php else: ?>
                                    <span style="color: #e74c3c; font-weight: 600;">Fully Returned</span>
                                <?php endif; ?>
                            </td>
                            <td class="return-amount-display" data-item-id="<?php echo $item['id']; ?>">৳0.00</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <label for="return_reason" style="display: block; margin-bottom: 8px; font-weight: 600;">Return Reason:</label>
                <textarea name="return_reason" id="return_reason" class="return-reason" rows="3" placeholder="Enter reason for return..." required></textarea>

                <div class="summary">
                    <div>
                        <span>Total Return Amount:</span>
                        <span id="totalReturnAmount" style="font-size: 20px; font-weight: bold; color: #e74c3c;">৳0.00</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger" id="submitBtn" disabled>↩️ Process Return</button>
                <a href="pos_sales.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function calculateReturnAmount() {
            let totalReturn = 0;
            const inputs = document.querySelectorAll('.return-qty-input');
            
            inputs.forEach(input => {
                const qty = parseInt(input.value) || 0;
                const price = parseFloat(input.dataset.price);
                const itemId = input.dataset.itemId;
                const returnAmount = qty * price;
                
                totalReturn += returnAmount;
                
                // Update individual item return amount display
                const displayEl = document.querySelector(`.return-amount-display[data-item-id="${itemId}"]`);
                if (displayEl) {
                    displayEl.textContent = '৳' + returnAmount.toFixed(2);
                }
            });
            
            document.getElementById('totalReturnAmount').textContent = '৳' + totalReturn.toFixed(2);
            document.getElementById('submitBtn').disabled = totalReturn === 0;
        }

        // Prevent form submission if no items selected
        document.getElementById('returnForm').addEventListener('submit', function(e) {
            const totalReturn = parseFloat(document.getElementById('totalReturnAmount').textContent.replace('৳', ''));
            if (totalReturn === 0) {
                e.preventDefault();
                alert('Please select at least one item to return!');
                return false;
            }
            
            const reason = document.getElementById('return_reason').value.trim();
            if (!reason) {
                e.preventDefault();
                alert('Please enter a return reason!');
                return false;
            }
            
            return confirm(`Confirm return of ৳${totalReturn.toFixed(2)}?\n\nThis will:\n• Add stock back\n• Record return transaction\n• Update accounts`);
        });
    </script>
</body>
</html>
