<?php
require_once '../core/auth.php';
require_admin();
require_once '../core/db.php';

$order_id = (int) ($_GET['order_id'] ?? 0);

if (!$order_id) {
    die('Invalid order ID');
}

// Fetch order details
$stmt = $pdo->prepare("
    SELECT o.id, o.user_id, o.status, o.payment_method, o.payment_status, 
           o.transaction_id, o.total_amount, o.shipping_address, o.created_at,
           u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found');
}

// Fetch order items
$stmt = $pdo->prepare("
    SELECT oi.*, pv.name AS variant_name, p.title AS product_title
    FROM order_items oi
    JOIN product_variants pv ON pv.id = oi.variant_id
    JOIN products p ON p.id = pv.product_id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Generate HTML for invoice
$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #' . $order['id'] . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Courier New", monospace; padding: 20px; background: #f5f5f5; }
        .invoice-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .invoice-header { text-align: center; border-bottom: 3px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .header-content { display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 10px; }
        .header-logo img { height: 60px; object-fit: contain; }
        .header-title { text-align: left; }
        .header-title .company-name { font-size: 28px; font-weight: bold; color: #1e3c72; }
        .header-title .company-tagline { font-size: 12px; color: #db2777; font-weight: 600; }
        .company-info { font-size: 13px; color: #333; margin-top: 8px; line-height: 1.5; }
        .invoice-title { text-align: center; font-size: 20px; font-weight: bold; margin: 20px 0 5px 0; }
        .invoice-id { text-align: center; font-size: 12px; color: #666; margin-bottom: 20px; }
        .invoice-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; font-size: 13px; line-height: 1.8; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background: #000; color: white; font-weight: bold; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .totals { display: flex; justify-content: flex-end; margin-bottom: 20px; }
        .totals-box { width: 250px; font-size: 13px; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #ddd; }
        .totals-row.total { font-weight: bold; font-size: 15px; border-top: 2px solid #000; border-bottom: 2px double #000; padding: 10px 0; }
        .shipping-box { background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px; }
        .shipping-box h4 { font-weight: bold; margin-bottom: 5px; margin-top: 0; }
        .footer { text-align: center; border-top: 1px dashed #999; padding-top: 15px; font-size: 12px; color: #666; }
        .footer p { margin: 3px 0; }
        .print-btn { background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; display: block; margin: 0 auto 20px; }
        .print-btn:hover { background: #229954; }
        @media print {
            body { background: white; padding: 0; }
            .invoice-container { box-shadow: none; padding: 20px; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print Invoice</button>

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="header-content">
                <div class="header-logo">
                    <img src="../assets/images/techhat.png" alt="TechHat Logo">
                </div>
                <div class="header-title">
                    <div class="company-name">TECHHAT</div>
                    <div class="company-tagline">ডিজিটাল বাংলাদেশের জন্য প্রযুক্তির সেতুবন্ধন</div>
                </div>
            </div>
            <div class="company-info">
                <div>Holidhani Bazar, Jhenaidah Sadar, Jhenaidah</div>
                <div>Mobile: <strong>01911414022</strong></div>
            </div>
        </div>

        <div class="invoice-title">INVOICE</div>
        <div class="invoice-id">Order #' . $order['id'] . '</div>

        <div class="invoice-info">
            <div>
                <strong>Invoice Date:</strong> ' . date('d M Y') . '<br>
                <strong>Order Date:</strong> ' . date('M d, Y - H:i A', strtotime($order['created_at'])) . '
            </div>
            <div>
                <strong>Customer:</strong> ' . htmlspecialchars($order['customer_name'] ?? 'Guest') . '<br>
                <strong>Phone:</strong> ' . htmlspecialchars($order['customer_phone'] ?? '-') . '<br>
                <strong>Email:</strong> ' . htmlspecialchars($order['customer_email'] ?? '-') . '
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; font-size: 13px; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; line-height: 1.8;">
            <div>
                <strong>Payment Method:</strong> ' . htmlspecialchars($order['payment_method']) . '
            </div>
            <div style="text-align: right;">
                <strong>Payment Status:</strong> <span style="display: inline-block; padding: 4px 10px; border-radius: 4px; background: ' . ($order['payment_status'] === 'paid' ? '#dcfce7' : '#fef3c7') . '; color: ' . ($order['payment_status'] === 'paid' ? '#166534' : '#92400e') . '; font-weight: 600;">' . ($order['payment_status'] === 'paid' ? '✅ Paid' : '⏳ Pending') . '</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 25px;">#</th>
                    <th>Product</th>
                    <th>Variant</th>
                    <th class="text-right" style="width: 80px;">Price</th>
                    <th class="text-center" style="width: 50px;">Qty</th>
                    <th class="text-right" style="width: 80px;">Total</th>
                </tr>
            </thead>
            <tbody>
';

foreach ($items as $item) {
    $html .= '
                <tr>
                    <td style="width: 25px;">' . (array_search($item, $items) + 1) . '</td>
                    <td><strong>' . htmlspecialchars($item['product_title']) . '</strong></td>
                    <td>' . htmlspecialchars($item['variant_name']) . '</td>
                    <td class="text-right">৳' . number_format($item['unit_price'], 2) . '</td>
                    <td class="text-center">' . $item['quantity'] . '</td>
                    <td class="text-right">৳' . number_format($item['line_total'], 2) . '</td>
                </tr>
    ';
}

$html .= '
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="totals-row">
                    <span>Subtotal:</span>
                    <span>৳' . number_format($order['total_amount'], 2) . '</span>
                </div>
                <div class="totals-row">
                    <span>Tax (0%):</span>
                    <span>৳0.00</span>
                </div>
                <div class="totals-row total">
                    <span>TOTAL:</span>
                    <span>৳' . number_format($order['total_amount'], 2) . '</span>
                </div>
            </div>
        </div>

        <div class="shipping-box">
            <h4>SHIPPING ADDRESS:</h4>
';

foreach (explode("\n", $order['shipping_address']) as $line) {
    if (!empty(trim($line))) {
        $html .= '<p>' . htmlspecialchars(trim($line)) . '</p>';
    }
}

$html .= '
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for your order!</strong></p>
            <p>আপনার ক্রয়ের জন্য ধন্যবাদ!</p>
            <p>For inquiries: 01911414022</p>
            <p>Powered by TechHat | ' . date('Y') . '</p>
        </div>
    </div>
</body>
</html>
';

// Output as HTML (browser will handle print to PDF)
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="Invoice-Order-' . $order['id'] . '.html"');
echo $html;
?>
