<?php
require_once 'core/auth.php';
require_once 'core/db.php';

require_login();

$order_id = (int) ($_GET['id'] ?? 0);
$user_id = current_user_id();

if (!$order_id) {
    header('Location: dashboard.php?view=orders');
    exit;
}

// Fetch order details (Ensure it belongs to the user)
$stmt = $pdo->prepare("
    SELECT o.*
    FROM orders o
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    // Order not found or doesn't belong to user
    header('Location: dashboard.php?view=orders');
    exit;
}

// Fetch order items
$stmt = $pdo->prepare("
    SELECT oi.*, pv.name AS variant_name, p.title AS product_title, p.slug,
    (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as thumb
    FROM order_items oi
    LEFT JOIN product_variants pv ON pv.id = oi.variant_id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="index.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <i class="bi bi-house-door-fill mr-2"></i>
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="bi bi-chevron-right text-gray-400"></i>
                        <a href="dashboard.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">My Account</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="bi bi-chevron-right text-gray-400"></i>
                        <a href="dashboard.php?view=orders" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">Orders</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="bi bi-chevron-right text-gray-400"></i>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Order #<?php echo $order['id']; ?></span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Order Details</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Placed on <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <div>
                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full 
                        <?php 
                            echo match($order['status']) {
                                'delivered' => 'bg-green-100 text-green-800',
                                'processing' => 'bg-blue-100 text-blue-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                default => 'bg-yellow-100 text-yellow-800'
                            };
                        ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
                <dl class="sm:divide-y sm:divide-gray-200">
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo ucfirst($order['payment_method']); ?> (<?php echo ucfirst($order['payment_status']); ?>)</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Shipping Address</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 whitespace-pre-line"><?php echo htmlspecialchars($order['shipping_address']); ?></dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Order Items</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <ul class="border border-gray-200 rounded-md divide-y divide-gray-200">
                                <?php foreach ($items as $item): ?>
                                    <li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">
                                        <div class="w-0 flex-1 flex items-center">
                                            <?php if (!empty($item['thumb'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['thumb']); ?>" alt="" class="h-10 w-10 rounded object-cover mr-3">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded bg-gray-200 mr-3 flex items-center justify-center text-gray-400">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span class="ml-2 flex-1 w-0 truncate">
                                                <?php echo htmlspecialchars($item['product_title']); ?>
                                                <?php if ($item['variant_name']): ?>
                                                    <span class="text-gray-500"> - <?php echo htmlspecialchars($item['variant_name']); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <span class="font-medium"><?php echo $item['quantity']; ?> x $<?php echo number_format($item['unit_price'], 2); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-gray-50">
                        <dt class="text-sm font-medium text-gray-900">Total Amount</dt>
                        <dd class="mt-1 text-sm font-bold text-gray-900 sm:mt-0 sm:col-span-2">$<?php echo number_format($order['total_amount'], 2); ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <p class="text-center text-sm text-gray-500">
            &copy; <?php echo date('Y'); ?> TechHat. All rights reserved.
        </p>
    </div>
</footer>

</body>
</html>
