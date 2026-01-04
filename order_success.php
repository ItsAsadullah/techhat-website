<?php
require_once __DIR__ . '/core/auth.php';
require_login();

$order_id = (int)($_GET['order_id'] ?? 0);
?>
<h2>Order Successful 🎉</h2>
<p>Your order #<?= htmlspecialchars($order_id) ?> has been placed.</p>
