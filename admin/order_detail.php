<?php
require_once '../core/auth.php';
require_admin();
require_once '../core/db.php';

$order_id = (int) ($_GET['id'] ?? 0);

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Handle status update
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $status = $_POST['status'] ?? '';
    
    if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $order_id])) {
            header('Location: order_detail.php?id=' . $order_id . '&success=1');
            exit;
        }
    }
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
    header('Location: orders.php');
    exit;
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

$success = isset($_GET['success']) && $_GET['success'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order['id']; ?> - TechHat Admin</title>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Admin Stylesheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }
        
        .order-detail-container {
            margin-left: 280px;
            padding: 30px;
            background: #f9fafb;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            border-left: 5px solid #db2777;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #111827;
        }
        
        .page-header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            color: #666;
        }
        
        .header-title {
            flex: 1;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-top: 4px solid #db2777;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .card h2, .card h3 {
            margin: 0 0 20px 0;
            color: #111827;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .item-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .item-details { flex: 1; }
        .item-title { font-weight: 600; color: #111827; font-size: 16px; }
        .item-variant { font-size: 14px; color: #666; margin-top: 5px; }
        .item-qty { font-size: 12px; color: #999; margin-top: 8px; }
        .item-price { font-weight: bold; color: #111827; text-align: right; }
        
        .total-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 18px;
        }
        
        .total-label { font-weight: bold; color: #111827; }
        .total-amount { font-weight: bold; color: #db2777; font-size: 24px; }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            min-width: 120px;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-shipped { background: #e9d5ff; color: #6b21a8; }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-cod { background: #fef3c7; color: #92400e; }
        .status-paid { background: #dcfce7; color: #166534; }
        
        .invoice-html {
            background: white;
            padding: 0;
            font-family: 'Courier New', monospace;
            color: #000;
            line-height: 1.5;
        }
        
        @media print {
            .invoice-html * {
                color: #000 !important;
                background: white !important;
            }
        }
        
        .btn-primary {
            width: 100%;
            background: #db2777;
            color: white;
            font-weight: 600;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
        }
        
        .btn-primary:hover { background: #c21a6b; }
        
        .btn-secondary {
            background: #6366f1;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-secondary:hover { background: #4f46e5; }
        
        .info-group { margin-bottom: 15px; }
        .info-label {
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-weight: 600;
            color: #111827;
            margin-top: 0px;
            font-size: 16px;
            text-align: left;
        }
        
        .success-alert {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            color: #166534;
            font-weight: 600;
        }
        
        .shipping-address-box {
            background: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e5e7eb;
        }
        
        .address-line { color: #111827; font-weight: 600; font-size: 14px; padding: 5px 0; }
        
        .invoice-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            overflow-y: auto;
        }
        
        .invoice-modal.active { display: block; }
        
        .invoice-content {
            background: white;
            max-width: 900px;
            margin: 20px auto;
            border-radius: 10px;
            padding: 40px;
        }
        
        .invoice-header-bar {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .invoice-html {
            background: white;
            padding: 40px;
            font-family: Arial, sans-serif;
        }
        
        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
            .order-detail-container { margin-left: 0; padding: 15px; }
            .button-group { width: 100%; flex-direction: column; }
            .btn-secondary { width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-title { width: 100%; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/partials/sidebar.php'; ?>
    
    <div class="order-detail-container">
        <div class="container">
            <!-- Top Bar -->
            <div class="page-header">
                <a href="orders.php" style="font-size: 24px; color: #666; text-decoration: none; display: flex; align-items: center;">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <div class="header-title">
                    <h1>Order #<?php echo $order['id']; ?></h1>
                    <p><?php echo date('M d, Y - H:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="button-group">
                    <button class="btn-secondary" onclick="printInvoice()" style="background: #0891b2;">
                        <i class="bi bi-printer"></i> Print Invoice
                    </button>
                    <button class="btn-secondary" onclick="downloadInvoice()" style="background: #059669;">
                        <i class="bi bi-download"></i> Download PDF
                    </button>
                </div>
            </div>

            <!-- Success Message -->
            <?php if ($success): ?>
            <div class="success-alert">
                <i class="bi bi-check-circle"></i>
                <p style="margin: 0;">Order status updated successfully!</p>
            </div>
            <?php endif; ?>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Order Items Card -->
                <div class="card">
                    <h2>
                        <i class="bi bi-box-seam" style="margin-right: 8px;"></i>Order Items
                    </h2>
                    <div>
                        <?php foreach ($items as $item): ?>
                        <div class="item-box">
                            <div class="item-details">
                                <div class="item-title"><?php echo htmlspecialchars($item['product_title']); ?></div>
                                <div class="item-variant"><?php echo htmlspecialchars($item['variant_name']); ?></div>
                                <div class="item-qty">
                                    Qty: <span style="font-weight: 600;"><?php echo $item['quantity']; ?></span> × ৳<?php echo number_format($item['unit_price'], 2); ?>
                                </div>
                            </div>
                            <div class="item-price">
                                ৳<?php echo number_format($item['line_total'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Total -->
                    <div class="total-section">
                        <span class="total-label">Total Amount:</span>
                        <span class="total-amount">৳<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>

                <!-- Shipping Address Card -->
                <div class="card">
                    <h2>
                        <i class="bi bi-geo-alt" style="margin-right: 8px;"></i>Shipping Address
                    </h2>
                    <div class="shipping-address-box">
                        <?php foreach (explode("\n", $order['shipping_address']) as $line): ?>
                            <div class="address-line">
                                <?php echo htmlspecialchars(trim($line)); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Customer Info Card -->
                <div class="card">
                    <h3 style="display: flex; align-items: center; gap: 8px;">
                        <i class="bi bi-person-circle" style="font-size: 20px; color: #db2777;"></i>Customer Information
                    </h3>
                    <div style="display: grid; gap: 15px;">
                        <div style="border-left: 3px solid #db2777; padding-left: 15px;">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></div>
                        </div>
                        <div style="border-left: 3px solid #6366f1; padding-left: 15px;">
                            <div class="info-label">Email Address</div>
                            <div class="info-value" style="word-break: break-all;"><?php echo htmlspecialchars($order['customer_email'] ?? '-'); ?></div>
                        </div>
                        <div style="border-left: 3px solid #059669; padding-left: 15px;">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['customer_phone'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Status Update Card -->
                <div class="card">
                    <h3 style="display: flex; align-items: center; gap: 8px;">
                        <i class="bi bi-clock-history" style="font-size: 20px; color: #db2777;"></i>Order Status
                    </h3>
                    <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div>
                            <label style="font-size: 12px; color: #666; display: block; margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Delivery Status</label>
                            <select name="status" style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; background: white; color: #111827;">
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>⚙️ Processing</option>
                                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>🚚 Shipped</option>
                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>✅ Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary">
                            <i class="bi bi-check-lg"></i> Update Status
                        </button>
                    </form>
                </div>

                <!-- Payment Info Card -->
                <div class="card">
                    <h3 style="display: flex; align-items: center; gap: 8px;">
                        <i class="bi bi-credit-card" style="font-size: 20px; color: #db2777;"></i>Payment Information
                    </h3>
                    <div style="display: grid; gap: 15px;">
                        <div style="border-left: 3px solid #db2777; padding-left: 15px;">
                            <div class="info-label">Payment Method</div>
                            <div class="info-value" style="font-size: 16px;"><?php echo htmlspecialchars($order['payment_method']); ?></div>
                        </div>
                        <div style="border-left: 3px solid #6366f1; padding-left: 15px;">
                            <div class="info-label">Payment Status</div>
                            <div style="margin-top: 5px;">
                                <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                    <?php 
                                    $statusIcons = [
                                        'paid' => '✅ Paid',
                                        'pending' => '⏳ Pending',
                                        'cod' => '💵 COD'
                                    ];
                                    echo $statusIcons[$order['payment_status']] ?? ucfirst($order['payment_status']);
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($order['transaction_id'])): ?>
                        <div style="border-left: 3px solid #059669; padding-left: 15px;">
                            <div class="info-label">Transaction ID</div>
                            <div class="info-value" style="word-break: break-all; font-family: 'Courier New', monospace; font-size: 12px;"><?php echo htmlspecialchars($order['transaction_id']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div id="invoiceModal" class="invoice-modal">
        <div class="invoice-content">
            <div class="invoice-header-bar">
                <button onclick="closeInvoiceModal()" style="background: #e5e7eb; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
            
            <div id="invoiceContent" class="invoice-html">
                <!-- Invoice will be generated here -->
            </div>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <button onclick="printInvoiceWindow()" class="btn-secondary" style="width: auto; background: #6366f1;">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <script>
        function generateInvoiceHTML() {
            const orderItems = Array.from(document.querySelectorAll('.item-box')).map(item => {
                const title = item.querySelector('.item-title').textContent;
                const variant = item.querySelector('.item-variant').textContent;
                const qtyMatch = item.querySelector('.item-qty').textContent.match(/Qty: (\d+)/);
                const priceMatch = item.querySelector('.item-qty').textContent.match(/× ৳([\d.]+)/);
                const qty = qtyMatch ? qtyMatch[1] : 0;
                const unitPrice = priceMatch ? priceMatch[1] : 0;
                const total = item.querySelector('.item-price').textContent.replace('৳', '').trim();
                
                return {
                    title, variant, qty, unitPrice, total
                };
            });

            const customerName = document.querySelectorAll('.info-value')[0]?.textContent || 'Guest';
            const customerEmail = document.querySelectorAll('.info-value')[1]?.textContent || '-';
            const customerPhone = document.querySelectorAll('.info-value')[2]?.textContent || '-';
            const orderDate = document.querySelector('.page-header p')?.textContent || '';
            const totalAmount = document.querySelector('.total-amount')?.textContent || '৳0.00';
            const orderId = document.querySelector('.page-header h1')?.textContent?.replace('Order #', '') || '';
            const paymentMethod = document.querySelectorAll('.info-value')[3]?.textContent || 'N/A';
            const paymentStatus = document.querySelector('.status-badge')?.textContent?.trim() || 'Pending';

            const addressLines = Array.from(document.querySelectorAll('.address-line')).map(line => line.textContent).join('<br>');

            // Calculate subtotal
            const subtotal = orderItems.reduce((sum, item) => sum + parseFloat(item.total.replace(/,/g, '')), 0);

            return `
                <div style="font-family: 'Courier New', monospace; padding: 0;">
                    <div style="text-align: center; border-bottom: 3px solid #000; padding-bottom: 15px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 10px;">
                            <img src="../assets/images/techhat.png" alt="TechHat Logo" style="height: 60px; object-fit: contain;">
                            <div style="text-align: left;">
                                <div style="font-size: 28px; font-weight: bold; color: #1e3c72;">TECHHAT</div>
                                <div style="font-size: 12px; color: #db2777; font-weight: 600;">ডিজিটাল বাংলাদেশের জন্য প্রযুক্তির সেতুবন্ধন</div>
                            </div>
                        </div>
                        <div style="font-size: 13px; color: #333; margin-top: 8px;">
                            <div>Holidhani Bazar, Jhenaidah Sadar, Jhenaidah</div>
                            <div>Mobile: <strong>01911414022</strong></div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="font-size: 20px; font-weight: bold;">INVOICE</div>
                        <div style="font-size: 12px; color: #666;">Order #${orderId}</div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; font-size: 13px;">
                        <div style="line-height: 1.8;">
                            <div><strong>Invoice Date:</strong> ${new Date().toLocaleDateString('bn-BD', {year: 'numeric', month: 'long', day: 'numeric'})}</div>
                            <div><strong>Order Date:</strong> ${orderDate}</div>
                        </div>
                        <div style="line-height: 1.8;">
                            <div><strong>Customer:</strong> ${customerName}</div>
                            <div><strong>Phone:</strong> ${customerPhone}</div>
                            <div><strong>Email:</strong> ${customerEmail}</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; font-size: 13px; background: #f9f9f9; padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <div style="line-height: 1.8;">
                            <div><strong>Payment Method:</strong> ${paymentMethod}</div>
                        </div>
                        <div style="line-height: 1.8; text-align: right;">
                            <div><strong>Payment Status:</strong> <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; background: ${paymentStatus.includes('✅') ? '#dcfce7' : '#fef3c7'}; color: ${paymentStatus.includes('✅') ? '#166534' : '#92400e'}; font-weight: 600;">${paymentStatus}</span></div>
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px;">
                        <thead>
                            <tr style="background: #000; color: white;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: left;">#</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left;">Product</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left;">Variant</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: right;">Price</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center;">Qty</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${orderItems.map((item, idx) => `
                                <tr style="background: ${idx % 2 === 0 ? '#fff' : '#f9f9f9'};">
                                    <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${idx + 1}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${item.title}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${item.variant}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">৳${item.unitPrice}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${item.qty}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: 600;">৳${item.total}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>

                    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                        <div style="width: 250px; font-size: 13px;">
                            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #ddd;">
                                <span>Subtotal:</span>
                                <span>৳${Number(subtotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #ddd;">
                                <span>Tax (0%):</span>
                                <span>৳0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-top: 2px solid #000; border-bottom: 2px double #000; font-weight: bold; font-size: 15px;">
                                <span>TOTAL:</span>
                                <span>${totalAmount}</span>
                            </div>
                        </div>
                    </div>

                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px;">
                        <div style="font-weight: bold; margin-bottom: 5px;">SHIPPING ADDRESS:</div>
                        <div style="color: #333; line-height: 1.6;">
                            ${addressLines}
                        </div>
                    </div>

                    <div style="text-align: center; border-top: 1px dashed #999; padding-top: 15px; font-size: 12px; color: #666;">
                        <p style="margin: 5px 0;"><strong>Thank you for your order!</strong></p>
                        <p style="margin: 3px 0;">আপনার ক্রয়ের জন্য ধন্যবাদ!</p>
                        <p style="margin: 3px 0;">For inquiries: 01911414022</p>
                        <p style="margin: 3px 0;">Powered by TechHat | ${new Date().getFullYear()}</p>
                    </div>
                </div>
            `;
        }

        function printInvoice() {
            const invoiceContent = document.getElementById('invoiceContent');
            const html = generateInvoiceHTML();
            invoiceContent.innerHTML = html;
            
            // Open in new window for printing
            const printWindow = window.open('', '', 'height=800,width=900');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Invoice Print</title>
                    <style>
                        body { font-family: 'Courier New', monospace; margin: 20px; padding: 0; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                        th { background: #000; color: white; font-weight: bold; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        img { max-width: 100px; height: auto; }
                    </style>
                </head>
                <body>
                    ${html}
                    <script>
                        window.print();
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        function printInvoiceWindow() {
            window.print();
        }

        function downloadInvoice() {
            const orderId = <?php echo $order_id; ?>;
            const link = document.createElement('a');
            link.href = 'generate_invoice_pdf.php?order_id=' + orderId;
            link.download = 'Invoice-Order-' + orderId + '.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeInvoiceModal() {
            document.getElementById('invoiceModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('invoiceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeInvoiceModal();
            }
        });

        // Print functionality
        window.addEventListener('beforeprint', function() {
            const modal = document.getElementById('invoiceModal');
            if (modal.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            }
        });

        window.addEventListener('afterprint', function() {
            document.body.style.overflow = 'auto';
        });

        // Auto scroll to top on success
        window.addEventListener('load', function() {
            if (window.location.search.includes('success=1')) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    </script>

    <style media="print">
        * {
            display: block !important;
            visibility: visible !important;
        }
        
        body {
            background: white;
            margin: 0;
            padding: 0;
        }
        
        body > *:not(#invoiceModal) {
            display: none !important;
        }
        
        #invoiceModal {
            display: block !important;
            position: static !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background: white !important;
            width: 100% !important;
            height: auto !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            z-index: auto !important;
        }
        
        .invoice-modal {
            display: block !important;
            position: static !important;
            background: white !important;
            width: 100% !important;
        }
        
        .invoice-content {
            display: block !important;
            margin: 0 !important;
            padding: 20px !important;
            box-shadow: none !important;
            max-width: 100% !important;
            background: white !important;
            page-break-after: always !important;
        }
        
        .invoice-header-bar {
            display: none !important;
        }
        
        #invoiceContent,
        #invoiceContent * {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    </style>
</body>
</html>
