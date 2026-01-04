<?php
require_once 'core/auth.php';
require_once 'core/stock.php';

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$cartItems = $_SESSION['cart'];
$ids = array_keys($cartItems);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT v.id as variant_id, v.name as variant_name, v.price, v.offer_price, v.product_id,
            p.title, p.slug
     FROM product_variants v
     JOIN products p ON p.id = v.product_id
     WHERE v.id IN ($placeholders)"
);
$stmt->execute($ids);
$variants = $stmt->fetchAll();

$total = 0;
foreach ($variants as &$v) {
    $qty = $cartItems[$v['variant_id']] ?? 0;
    $unit = ($v['offer_price'] && $v['offer_price'] > 0) ? $v['offer_price'] : $v['price'];
    $v['qty'] = $qty;
    $v['unit'] = $unit;
    $v['line_total'] = $unit * $qty;
    $total += $v['line_total'];
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'COD';
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    if (!$name || !$phone || !$address) {
        $error = 'Please fill all required fields.';
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            $shipping_address = $name . "\n" . $phone . "\n" . $address;
            $payment_status = ($payment_method === 'COD') ? 'cod' : 'pending';
            $user_id = $_SESSION['user_id'] ?? null;

            $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, status, payment_method, payment_status, transaction_id, total_amount, shipping_address) VALUES (?, 'pending', ?, ?, ?, ?, ?)");
            $stmtOrder->execute([$user_id, $payment_method, $payment_status, $transaction_id, $total, $shipping_address]);
            $order_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($variants as $item) {
                $stmtItem->execute([$order_id, $item['product_id'], $item['variant_id'], $item['qty'], $item['unit'], $item['line_total']]);
                adjustStock($item['variant_id'], $item['qty'], 'out', 'online', $order_id, 'checkout');
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $success = 'Order placed successfully. Your Order ID: #' . $order_id;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to place order: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | TechHat</title>
    <meta name="description" content="Complete your TechHat order with secure checkout and POS-synced stock.">
    <link rel="canonical" href="<?php echo BASE_URL; ?>checkout.php">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f5f5f5; }
        .container { width: 95%; max-width: 900px; margin: auto; display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        form { background: #fff; padding: 20px; border-radius: 4px; }
        .summary { background: #fff; padding: 20px; border-radius: 4px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; box-sizing: border-box; }
        .btn { padding: 12px 16px; border: none; background: #f85606; color: #fff; cursor: pointer; width: 100%; }
        .error { color: red; margin-bottom: 10px; }
        .success { color: green; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <form method="POST">
            <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" required>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="COD">Cash on Delivery</option>
                    <option value="bKash">bKash</option>
                    <option value="Nagad">Nagad</option>
                    <option value="Rocket">Rocket</option>
                </select>
            </div>
            <div class="form-group">
                <label>Transaction ID (if paid)</label>
                <input type="text" name="transaction_id" placeholder="Optional">
            </div>
            <button type="submit" class="btn">Place Order</button>
        </form>
        <div class="summary">
            <h3>Order Summary</h3>
            <table>
                <tbody>
                    <?php foreach ($variants as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['title']); ?> (<?php echo htmlspecialchars($item['variant_name']); ?>) × <?php echo $item['qty']; ?></td>
                        <td style="text-align:right;">৳<?php echo number_format($item['line_total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th style="text-align:right;">৳<?php echo number_format($total); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
