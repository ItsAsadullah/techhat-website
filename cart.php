<?php
require_once 'core/auth.php';
require_once 'core/stock.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function redirect_back() {
    $ref = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header('Location: ' . $ref);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $variant_id = (int) ($_POST['variant_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        if ($variant_id > 0) {
            $_SESSION['cart'][$variant_id] = ($_SESSION['cart'][$variant_id] ?? 0) + $qty;
        }
        redirect_back();
    }

    if ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $variant_id => $qty) {
            $variant_id = (int) $variant_id;
            $qty = max(0, (int) $qty);
            if ($qty === 0) {
                unset($_SESSION['cart'][$variant_id]);
            } else {
                $_SESSION['cart'][$variant_id] = $qty;
            }
        }
        header('Location: cart.php');
        exit;
    }

    if ($action === 'remove') {
        $variant_id = (int) ($_POST['variant_id'] ?? 0);
        unset($_SESSION['cart'][$variant_id]);
        header('Location: cart.php');
        exit;
    }
}

$cartItems = $_SESSION['cart'];
$variants = [];
$total = 0;

if (!empty($cartItems)) {
    $ids = array_keys($cartItems);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT v.id as variant_id, v.name as variant_name, v.price, v.offer_price, v.stock_quantity, v.product_id,
                p.title, p.slug,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
         FROM product_variants v
         JOIN products p ON p.id = v.product_id
         WHERE v.id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $variants = $stmt->fetchAll();

    foreach ($variants as &$v) {
        $qty = $cartItems[$v['variant_id']] ?? 0;
        $unit = $v['offer_price'] !== null && $v['offer_price'] > 0 ? $v['offer_price'] : $v['price'];
        $v['qty'] = $qty;
        $v['line_total'] = $unit * $qty;
        $total += $v['line_total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart | TechHat</title>
    <meta name="description" content="View your cart items and proceed to checkout at TechHat.">
    <link rel="canonical" href="<?php echo BASE_URL; ?>cart.php">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f5f5f5; }
        .container { width: 95%; max-width: 1000px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #eee; padding: 12px; text-align: left; }
        th { background: #fafafa; }
        .actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn { padding: 10px 15px; border: none; background: #f85606; color: #fff; cursor: pointer; }
        .btn-secondary { background: #555; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <h1>Your Cart</h1>
        <?php if (empty($variants)): ?>
            <p>Your cart is empty.</p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="update">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variants as $item): ?>
                        <tr>
                            <td>
                                <a href="product.php?slug=<?php echo $item['slug']; ?>"><?php echo htmlspecialchars($item['title']); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars($item['variant_name']); ?></td>
                            <td>৳<?php echo number_format(($item['offer_price'] && $item['offer_price'] > 0) ? $item['offer_price'] : $item['price']); ?></td>
                            <td>
                                <input type="number" name="qty[<?php echo $item['variant_id']; ?>]" value="<?php echo $item['qty']; ?>" min="0" style="width:70px;">
                            </td>
                            <td>৳<?php echo number_format($item['line_total']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="variant_id" value="<?php echo $item['variant_id']; ?>">
                                    <button class="btn-secondary" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="actions">
                    <button type="submit" class="btn-secondary">Update Cart</button>
                    <a href="checkout.php" class="btn">Proceed to Checkout (৳<?php echo number_format($total); ?>)</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
