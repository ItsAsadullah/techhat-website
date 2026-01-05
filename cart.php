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
        $buy_now = ($_POST['buy_now'] ?? '0') === '1';
        
        if ($variant_id > 0) {
            $_SESSION['cart'][$variant_id] = ($_SESSION['cart'][$variant_id] ?? 0) + $qty;
        }
        
        // If "Buy Now", redirect to checkout directly
        if ($buy_now) {
            header('Location: checkout.php');
            exit;
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
    <title>Shopping Cart | TechHat</title>
    <meta name="description" content="View your cart items and proceed to checkout at TechHat.">
    <link rel="canonical" href="<?php echo BASE_URL; ?>cart.php">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f8f9fa;
        }
        
        .accent-primary {
            background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%);
        }
        
        .accent-text {
            color: #D4145A;
        }
        
        .cart-item {
            background: white;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .cart-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .qty-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .qty-btn:hover {
            background: #f8f9fa;
            border-color: #D4145A;
        }
        
        .qty-input {
            width: 60px;
            text-align: center;
            border: 1px solid #dee2e6;
            padding: 4px;
        }
        
        .summary-card {
            background: white;
            border: 1px solid #e9ecef;
            position: sticky;
            top: 100px;
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include 'includes/header.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-600 mb-6">
            <a href="index.php" class="hover:text-pink-600 transition">
                <i class="bi bi-house-door-fill"></i> Home
            </a>
            <i class="bi bi-chevron-right text-xs"></i>
            <span class="accent-text font-semibold">Shopping Cart</span>
        </div>

        <!-- Page Title -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                <i class="bi bi-cart-check-fill accent-text"></i>
                Shopping Cart
            </h1>
            <p class="text-gray-600 mt-2">Review your items and proceed to checkout</p>
        </div>

        <?php if (empty($variants)): ?>
            <!-- Empty Cart State -->
            <div class="bg-white rounded-xl p-12 text-center">
                <i class="bi bi-cart-x text-8xl text-gray-300 mb-4 inline-block"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Your cart is empty</h2>
                <p class="text-gray-600 mb-6">Looks like you haven't added anything to your cart yet</p>
                <a href="category.php" class="inline-flex items-center gap-2 accent-primary text-white px-8 py-3 rounded-full font-semibold hover:opacity-90 transition">
                    <i class="bi bi-shop"></i>
                    Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Cart with Items -->
            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Cart Items (Left Column) -->
                <div class="lg:col-span-2 space-y-4">
                    <form method="POST" id="cartForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="action" value="update">
                        
                        <!-- Cart Header -->
                        <div class="bg-white rounded-xl p-4 mb-4 border border-gray-200">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-700">
                                    <i class="bi bi-bag-check-fill accent-text"></i>
                                    <?php echo count($variants); ?> Item(s) in Cart
                                </span>
                                <a href="category.php" class="text-sm accent-text hover:underline flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> Add More Items
                                </a>
                            </div>
                        </div>

                        <!-- Cart Items List -->
                        <?php foreach ($variants as $item): 
                            $currentPrice = ($item['offer_price'] && $item['offer_price'] > 0) ? $item['offer_price'] : $item['price'];
                            $hasDiscount = $item['offer_price'] && $item['offer_price'] > 0 && $item['offer_price'] < $item['price'];
                        ?>
                        <div class="cart-item rounded-xl p-4 mb-3">
                            <div class="flex gap-4">
                                <!-- Product Image -->
                                <div class="w-24 h-24 bg-gray-50 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    <?php if($item['image']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             class="max-w-full max-h-full object-contain">
                                    <?php else: ?>
                                        <i class="bi bi-image text-4xl text-gray-300"></i>
                                    <?php endif; ?>
                                </div>

                                <!-- Product Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1 pr-4">
                                            <a href="product.php?slug=<?php echo htmlspecialchars($item['slug']); ?>" 
                                               class="font-semibold text-gray-800 hover:text-pink-600 transition block mb-1">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                            <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded inline-block">
                                                <i class="bi bi-tag text-xs"></i> <?php echo htmlspecialchars($item['variant_name']); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Remove Button (Mobile) -->
                                        <button type="button" 
                                                onclick="removeItem(<?php echo $item['variant_id']; ?>)"
                                                class="lg:hidden text-gray-400 hover:text-red-500 transition">
                                            <i class="bi bi-trash text-xl"></i>
                                        </button>
                                    </div>

                                    <!-- Price and Quantity Row -->
                                    <div class="flex flex-wrap items-center justify-between gap-4 mt-3">
                                        <!-- Price -->
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xl font-bold accent-text">
                                                    ৳<?php echo number_format($currentPrice); ?>
                                                </span>
                                                <?php if($hasDiscount): ?>
                                                    <span class="text-sm text-gray-400 line-through">
                                                        ৳<?php echo number_format($item['price']); ?>
                                                    </span>
                                                    <span class="text-xs bg-red-100 text-red-600 px-2 py-1 rounded-full font-semibold">
                                                        <?php echo round((($item['price'] - $currentPrice) / $item['price']) * 100); ?>% OFF
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Quantity Controls -->
                                        <div class="flex items-center gap-3">
                                            <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                                                <button type="button" 
                                                        onclick="decreaseQty(<?php echo $item['variant_id']; ?>)"
                                                        class="qty-btn">
                                                    <i class="bi bi-dash"></i>
                                                </button>
                                                <input type="number" 
                                                       name="qty[<?php echo $item['variant_id']; ?>]" 
                                                       id="qty_<?php echo $item['variant_id']; ?>"
                                                       value="<?php echo $item['qty']; ?>" 
                                                       min="1" 
                                                       max="<?php echo $item['stock_quantity']; ?>"
                                                       class="qty-input border-0 focus:outline-none"
                                                       onchange="updateLineTotal(<?php echo $item['variant_id']; ?>, <?php echo $currentPrice; ?>)">
                                                <button type="button" 
                                                        onclick="increaseQty(<?php echo $item['variant_id']; ?>, <?php echo $item['stock_quantity']; ?>)"
                                                        class="qty-btn">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Subtotal -->
                                            <div class="hidden md:block">
                                                <span class="text-sm text-gray-500">Subtotal:</span>
                                                <span class="font-bold text-gray-800 ml-1" id="line_total_<?php echo $item['variant_id']; ?>">
                                                    ৳<?php echo number_format($item['line_total']); ?>
                                                </span>
                                            </div>

                                            <!-- Remove Button (Desktop) -->
                                            <button type="button" 
                                                    onclick="removeItem(<?php echo $item['variant_id']; ?>)"
                                                    class="hidden lg:block text-gray-400 hover:text-red-500 transition ml-2">
                                                <i class="bi bi-trash text-xl"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Stock Warning -->
                                    <?php if($item['stock_quantity'] < 5): ?>
                                        <div class="mt-2 text-xs text-orange-600 flex items-center gap-1">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            Only <?php echo $item['stock_quantity']; ?> left in stock
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap gap-3 mt-6">
                            <button type="submit" 
                                    class="flex-1 md:flex-none bg-gray-800 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-700 transition flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-clockwise"></i>
                                Update Cart
                            </button>
                            <a href="category.php" 
                               class="flex-1 md:flex-none border border-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-50 transition flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-left"></i>
                                Continue Shopping
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Order Summary (Right Column) -->
                <div class="lg:col-span-1">
                    <div class="summary-card rounded-xl p-6 sticky top-32">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <i class="bi bi-receipt accent-text"></i>
                            Order Summary
                        </h2>

                        <!-- Items Breakdown -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6 max-h-64 overflow-y-auto">
                            <div class="space-y-3">
                                <?php foreach ($variants as $item): 
                                    $currentPrice = ($item['offer_price'] && $item['offer_price'] > 0) ? $item['offer_price'] : $item['price'];
                                ?>
                                <div class="flex items-start justify-between text-sm pb-3 border-b border-gray-200">
                                    <div class="flex-1 pr-2">
                                        <div class="font-medium text-gray-800 mb-1">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($item['variant_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            ৳<?php echo number_format($currentPrice); ?> × <span class="font-semibold"><?php echo $item['qty']; ?></span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-gray-900">
                                            ৳<?php echo number_format($item['line_total']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Pricing Breakdown -->
                        <div class="space-y-3 mb-6 pb-4 border-b-2 border-gray-200">
                            <div class="flex justify-between text-gray-600">
                                <span class="text-sm">Subtotal (<?php echo count($variants); ?> items)</span>
                                <span class="font-semibold text-gray-800">৳<?php echo number_format($total); ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span class="text-sm">Discount</span>
                                <span class="font-semibold text-green-600">-৳0</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span class="text-sm">Shipping</span>
                                <span class="font-semibold text-green-600">FREE</span>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="flex justify-between items-center mb-6">
                            <span class="text-lg font-bold text-gray-800">Total Amount</span>
                            <span class="text-3xl font-bold accent-text">৳<?php echo number_format($total); ?></span>
                        </div>

                        <a href="checkout.php" 
                           class="w-full accent-primary text-white py-4 rounded-lg font-bold hover:opacity-90 transition flex items-center justify-center gap-2 mb-3 shadow-lg">
                            <i class="bi bi-lock-fill"></i>
                            Proceed to Checkout
                        </a>

                        <!-- Trust Badges -->
                        <div class="mt-6 pt-6 border-t border-gray-200 space-y-3">
                            <div class="flex items-start gap-3 text-sm text-gray-600">
                                <i class="bi bi-shield-check text-green-600 text-xl"></i>
                                <div>
                                    <div class="font-semibold text-gray-800">Secure Checkout</div>
                                    <div class="text-xs">SSL encrypted payment</div>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-sm text-gray-600">
                                <i class="bi bi-truck text-blue-600 text-xl"></i>
                                <div>
                                    <div class="font-semibold text-gray-800">Free Shipping</div>
                                    <div class="text-xs">On all orders</div>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-sm text-gray-600">
                                <i class="bi bi-arrow-repeat text-purple-600 text-xl"></i>
                                <div>
                                    <div class="font-semibold text-gray-800">Easy Returns</div>
                                    <div class="text-xs">7 days return policy</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function decreaseQty(variantId) {
            const input = document.getElementById('qty_' + variantId);
            const currentVal = parseInt(input.value) || 1;
            if (currentVal > 1) {
                input.value = currentVal - 1;
                input.dispatchEvent(new Event('change'));
            }
        }

        function increaseQty(variantId, maxStock) {
            const input = document.getElementById('qty_' + variantId);
            const currentVal = parseInt(input.value) || 1;
            if (currentVal < maxStock) {
                input.value = currentVal + 1;
                input.dispatchEvent(new Event('change'));
            }
        }

        function updateLineTotal(variantId, unitPrice) {
            const input = document.getElementById('qty_' + variantId);
            const qty = parseInt(input.value) || 0;
            const lineTotal = qty * unitPrice;
            const lineTotalEl = document.getElementById('line_total_' + variantId);
            if (lineTotalEl) {
                lineTotalEl.textContent = '৳' + lineTotal.toLocaleString();
            }
            
            // Auto-update cart via AJAX
            const formData = new FormData(document.getElementById('cartForm'));
            fetch('cart.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                // Just update the summary without reloading
                location.reload();
            }).catch(err => console.error('Cart update error:', err));
        }

        function removeItem(variantId) {
            if (confirm('Are you sure you want to remove this item from cart?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="variant_id" value="${variantId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
