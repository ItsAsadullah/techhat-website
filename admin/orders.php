<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

$sql = "SELECT o.id, o.status, o.payment_method, o.payment_status, o.transaction_id, o.total_amount, o.created_at,
               u.name AS customer_name, u.email AS customer_email, COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 200";
$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders - TechHat Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            margin: 0;
        }
        .content { padding: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 12px; color: #fff; display: inline-block; }
        .badge.pending { background: #f0ad4e; }
        .badge.processing { background: #0275d8; }
        .badge.delivered { background: #5cb85c; }
        .badge.cancelled { background: #d9534f; }
        .badge.paid { background: #5cb85c; }
        .badge.failed { background: #d9534f; }
        .badge.cod { background: #292b2c; }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <h1>Orders</h1>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th>Items</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo (int) $order['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?><br>
                                <small><?php echo htmlspecialchars($order['customer_email'] ?? '-'); ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($order['payment_method'] ?: 'N/A'); ?></strong></div>
                                <span class="badge <?php echo htmlspecialchars($order['payment_status']); ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                                <?php if (!empty($order['transaction_id'])): ?>
                                    <div style="font-size:12px; color:#555;">TX: <?php echo htmlspecialchars($order['transaction_id']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>৳<?php echo number_format((float) $order['total_amount'], 2); ?></td>
                            <td><?php echo (int) $order['item_count']; ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
