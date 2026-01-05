<?php
require_once '../core/db.php';

$order_id = (int) ($_GET['id'] ?? 0);

if (!$order_id) {
    echo '<div class="p-6 text-center text-red-600">Invalid order ID</div>';
    exit;
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
    echo '<div class="p-6 text-center text-red-600">Order not found</div>';
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

// Parse shipping address
$address_lines = explode("\n", $order['shipping_address']);
?>

<div class="bg-white">
    <!-- Header -->
    <div class="bg-gradient-to-r from-pink-600 to-pink-700 p-6 text-white flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold">Order #<?php echo $order['id']; ?></h2>
            <p class="text-pink-100 mt-1"><?php echo date('M d, Y - H:i A', strtotime($order['created_at'])); ?></p>
        </div>
        <button onclick="closeOrderModal()" class="text-pink-100 hover:text-white text-2xl">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Content -->
    <div class="p-6 grid grid-cols-2 gap-6">
        <!-- Left: Order Items -->
        <div>
            <h3 class="font-bold text-lg mb-4 text-gray-900">Order Items</h3>
            <div class="space-y-3 border-b pb-4">
                <?php foreach ($items as $item): ?>
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($item['product_title']); ?>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($item['variant_name']); ?>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Qty: <span class="font-semibold"><?php echo $item['quantity']; ?></span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-gray-900">
                            ৳<?php echo number_format($item['line_total'], 2); ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            @৳<?php echo number_format($item['unit_price'], 2); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Total -->
            <div class="mt-4 pt-4 border-t-2">
                <div class="flex justify-between items-center text-lg">
                    <span class="font-bold text-gray-900">Total Amount:</span>
                    <span class="font-bold text-pink-600 text-2xl">
                        ৳<?php echo number_format($order['total_amount'], 2); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Right: Customer & Status Info -->
        <div>
            <!-- Customer Info -->
            <div class="mb-6">
                <h3 class="font-bold text-lg mb-3 text-gray-900">Customer Information</h3>
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <div>
                        <span class="text-gray-600 text-sm">Name:</span>
                        <div class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">Email:</span>
                        <div class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($order['customer_email'] ?? '-'); ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">Phone:</span>
                        <div class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($order['customer_phone'] ?? '-'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="mb-6">
                <h3 class="font-bold text-lg mb-3 text-gray-900">Shipping Address</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                    <?php foreach ($address_lines as $line): ?>
                        <div class="text-gray-900 font-semibold text-sm">
                            <?php echo htmlspecialchars(trim($line)); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <h3 class="font-bold text-lg mb-3 text-gray-900">Order Status</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="mb-3">
                        <span class="text-gray-600 text-sm">Delivery Status:</span>
                        <div class="mt-1">
                            <span class="px-3 py-1 rounded-full text-sm font-semibold
                                <?php 
                                    if ($order['status'] === 'pending') echo 'bg-yellow-100 text-yellow-800';
                                    elseif ($order['status'] === 'processing') echo 'bg-blue-100 text-blue-800';
                                    elseif ($order['status'] === 'shipped') echo 'bg-purple-100 text-purple-800';
                                    elseif ($order['status'] === 'delivered') echo 'bg-green-100 text-green-800';
                                    elseif ($order['status'] === 'cancelled') echo 'bg-red-100 text-red-800';
                                ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">Payment Status:</span>
                        <div class="mt-1">
                            <span class="px-3 py-1 rounded-full text-sm font-semibold
                                <?php echo $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t">
                        <span class="text-gray-600 text-sm">Payment Method:</span>
                        <div class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($order['payment_method']); ?>
                        </div>
                        <?php if (!empty($order['transaction_id'])): ?>
                            <div class="text-xs text-gray-600 mt-1">
                                <strong>TX ID:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
