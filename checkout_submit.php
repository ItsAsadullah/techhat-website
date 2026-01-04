<?php
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/order.php';

require_login();

$user_id = current_user_id();
$payment_method = $_POST['payment_method'] ?? 'cod';

// Example cart from session
$cartItems = $_SESSION['cart'] ?? [];

$order_id = createOrder($user_id, $cartItems, $payment_method);

if ($order_id) {
    // clear cart
    unset($_SESSION['cart']);
    header("Location: order_success.php?order_id={$order_id}");
    exit;
}

header("Location: checkout.php?error=order_failed");
exit;
