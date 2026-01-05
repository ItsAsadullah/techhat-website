<?php
require_once '../core/auth.php';
require_admin();
require_once '../core/db.php';
require_once __DIR__ . '/partials/sidebar.php';

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_status' && !empty($_POST['order_id'])) {
    header('Content-Type: application/json');
    $order_id = (int) $_POST['order_id'];
    $status = $_POST['status'] ?? '';
    
    if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        echo json_encode(['success' => true, 'status' => $status]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// Fetch orders with filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR o.transaction_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $where[] = "o.payment_method = ?";
    $params[] = $payment_filter;
}

$sql = "SELECT o.id, o.user_id, o.status, o.payment_method, o.payment_status, o.transaction_id, 
               o.total_amount, o.shipping_address, o.created_at,
               u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
               COUNT(oi.id) AS item_count,
               GROUP_CONCAT(CONCAT(p.title, ' (', oi.quantity, ')') SEPARATOR ', ') AS products
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN product_variants pv ON pv.id = oi.variant_id
        LEFT JOIN products p ON p.id = pv.product_id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get unique payment methods for filter
$stmt = $pdo->query("SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL ORDER BY payment_method");
$payment_methods = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - TechHat Admin</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .status-pending { @apply bg-yellow-100 text-yellow-800; }
        .status-processing { @apply bg-blue-100 text-blue-800; }
        .status-shipped { @apply bg-purple-100 text-purple-800; }
        .status-delivered { @apply bg-green-100 text-green-800; }
        .status-cancelled { @apply bg-red-100 text-red-800; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen" style="margin-left: 280px;">
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200">
                <div class="px-8 py-6">
                    <h1 class="text-3xl font-bold text-gray-900">Orders Management</h1>
                    <p class="text-gray-600 mt-1">Manage and track all customer orders</p>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-auto p-8">
                <!-- Filter Section -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" placeholder="Order ID, Customer, Email..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <!-- Payment Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                            <select name="payment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                                <option value="">All Methods</option>
                                <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method); ?>" 
                                            <?php echo $payment_filter === $method ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($method); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Search Button -->
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-pink-600 hover:bg-pink-700 text-white font-semibold py-2 rounded-lg transition">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Orders Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Order ID</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Customer</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Products</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Payment</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Total</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Date</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                            <i class="bi bi-inbox text-4xl mb-2 block opacity-50"></i>
                                            No orders found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4">
                                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="font-bold text-pink-600 hover:text-pink-700 transition">
                                                #<?php echo $order['id']; ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <?php echo htmlspecialchars($order['customer_email'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-700 max-w-xs truncate" title="<?php echo htmlspecialchars($order['products']); ?>">
                                                <?php echo htmlspecialchars(substr($order['products'] ?? '', 0, 50)); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <select class="px-3 py-1 rounded-full text-xs font-semibold status-<?php echo $order['status']; ?>" 
                                                    data-order-id="<?php echo $order['id']; ?>" 
                                                    onchange="updateStatus(this)">
                                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($order['payment_method']); ?>
                                            </div>
                                            <span class="text-xs px-2 py-1 rounded-full 
                                                    <?php echo $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-gray-900">
                                            ৳<?php echo number_format($order['total_amount'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" 
                                                    class="text-pink-600 hover:text-pink-700 font-semibold transition text-sm">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateStatus(select) {
            const orderId = select.dataset.orderId;
            const status = select.value;
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('order_id', orderId);
            formData.append('status', status);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    select.className = 'px-3 py-1 rounded-full text-xs font-semibold status-' + status;
                    showNotification('Order status updated successfully!', 'success');
                } else {
                    showNotification('Failed to update status', 'error');
                    // Revert on error
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred', 'error');
                location.reload();
            });
        }

        function showNotification(message, type = 'info') {
            const notif = document.createElement('div');
            notif.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white text-sm font-semibold z-50 
                ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            notif.textContent = message;
            document.body.appendChild(notif);
            
            setTimeout(() => notif.remove(), 3000);
        }
    </script>
</body>
</html>
