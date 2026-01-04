<?php
require_once 'core/db.php';
require_once 'core/auth.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header("Location: index.php");
    exit;
}

// Fetch Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE slug = ?");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found");
}

// Fetch Images
$stmtImg = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
$stmtImg->execute([$product['id']]);
$imagesRaw = $stmtImg->fetchAll();
$images = [];
foreach ($imagesRaw as $img) {
    $base = strtolower(basename($img['image_path']));
    if (in_array($base, ['default.png', 'no-image.png', 'placeholder.png', 'default.jpg', 'no-image.jpg', 'placeholder.jpg', 'default.webp'])) {
        continue; // drop placeholder when real images exist
    }
    $images[] = $img;
}
if (empty($images) && !empty($imagesRaw)) {
    // if only placeholder exists, keep one to display
    $images = [$imagesRaw[0]];
}

// Fetch Variants
$stmtVar = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$stmtVar->execute([$product['id']]);
$variants = $stmtVar->fetchAll();

// Flash discount fetch happens later; we need it for pricing adjustments

$variants_enhanced = [];
foreach ($variants as $vv) {
    $base_price = (float) $vv['price'];
    $offer_price = ($vv['offer_price'] && $vv['offer_price'] > 0) ? (float) $vv['offer_price'] : null;
    $effective = $offer_price ?? $base_price;
    $variants_enhanced[] = [
        'row' => $vv,
        'base' => $offer_price ?? $base_price,
        'price' => $base_price,
        'offer' => $offer_price,
        'effective' => $effective,
    ];
}

// Flash sale timing (from DB)
$flash_end_ts = null;
$flash_discount_live = null;
$stmtFlash = $pdo->prepare("SELECT fs.end_at, fs.start_at, COALESCE(fsi.discount_percentage, fs.discount_percentage) as discount
    FROM flash_sale_items fsi
    JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
    WHERE fsi.product_id = ? AND fs.is_active = 1 AND (fs.start_at IS NULL OR fs.start_at <= NOW()) AND (fs.end_at IS NULL OR fs.end_at >= NOW())
    ORDER BY fs.end_at ASC LIMIT 1");
$stmtFlash->execute([$product['id']]);
if ($row = $stmtFlash->fetch()) {
    $flash_discount_live = $row['discount'];
    if ($row['end_at']) {
        $flash_end_ts = strtotime($row['end_at']);
    }
}

// Apply flash discount to variants
if ($flash_discount_live && $flash_discount_live > 0) {
    foreach ($variants_enhanced as &$ve) {
        $ve['effective'] = round($ve['effective'] * (1 - ($flash_discount_live / 100)), 2);
    }
    unset($ve);
}

// Pricing aggregates
$effective_prices = array_column($variants_enhanced, 'effective');
$min_price = $effective_prices ? min($effective_prices) : 0;
$max_price = $effective_prices ? max($effective_prices) : 0;
$base_min_price = $variants_enhanced ? min(array_column($variants_enhanced, 'base')) : 0;
$save_percent = ($base_min_price && $base_min_price > $min_price) ? round((($base_min_price - $min_price) / $base_min_price) * 100) : 0;
$stock_left = $variants ? min(array_column($variants, 'stock_quantity')) : 0;
$is_free_ship = ($min_price !== null && $min_price >= FREE_SHIP_THRESHOLD);
?>
<?php
$metaTitle = htmlspecialchars($product['title']) . ' | TechHat';
$metaDesc  = substr(strip_tags($product['description']), 0, 155);
$canonical = BASE_URL . 'product.php?slug=' . urlencode($product['slug']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $metaTitle; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <link rel="canonical" href="<?php echo $canonical; ?>">
    <meta property="og:title" content="<?php echo $metaTitle; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <?php if (!empty($images[0]['image_path'])): ?>
    <meta property="og:image" content="<?php echo BASE_URL . $images[0]['image_path']; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Reuse styles from index.php or move to style.css */
        /* ... (Assuming style.css is loaded) ... */
        .product-detail-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; background: #fff; padding: 20px; border-radius: 5px; }
        .gallery img { width: 100%; border: 1px solid #eee; }
        .thumbnails { display: flex; gap: 10px; margin-top: 10px; }
        .thumbnails img { width: 60px; height: 60px; object-fit: cover; cursor: pointer; border: 1px solid #ddd; }
        
        .details h1 { font-size: 24px; margin: 0 0 10px; }
        .price { font-size: 24px; color: #f85606; font-weight: bold; margin-bottom: 20px; }
        .variants { margin-bottom: 20px; }
        .variant-btn { padding: 8px 15px; border: 1px solid #ddd; background: #fff; cursor: pointer; margin-right: 10px; }
        .variant-btn.active { border-color: #f85606; color: #f85606; }
        
        .actions { display: flex; gap: 10px; }
        .btn-buy { flex: 1; padding: 15px; background: #2e2e2e; color: #fff; border: none; cursor: pointer; font-size: 16px; }
        .btn-cart { flex: 1; padding: 15px; background: #f85606; color: #fff; border: none; cursor: pointer; font-size: 16px; }
        .trust-row { display: flex; gap: 10px; margin: 10px 0 20px; flex-wrap: wrap; }
        .trust-pill { background: #e8f3ff; color: #0c7dd9; padding: 8px 12px; border-radius: 4px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .trust-pill.return { background: #eefbf4; color: #1c7c3c; }
        .countdown { margin: 12px 0; padding: 8px 12px; background: #fff4ec; color: #b54500; border: 1px solid #ffd9bf; border-radius: 4px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="product-detail-container">
            <div class="gallery">
                <?php if($images): ?>
                    <img id="main-img" src="<?php echo $images[0]['image_path']; ?>" alt="Product Image">
                    <div class="thumbnails">
                        <?php foreach($images as $img): ?>
                            <img src="<?php echo $img['image_path']; ?>" onclick="document.getElementById('main-img').src=this.src">
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="height:300px; background:#f9f9f9; display:flex; align-items:center; justify-content:center;">No Image</div>
                <?php endif; ?>
            </div>
            <div class="details">
                <h1 style="display:flex; align-items:center; gap:10px;">
                    <?php echo htmlspecialchars($product['title']); ?>
                    <?php if (!empty($product['is_flash_sale'])): ?>
                        <span style="background:#fce7e1; color:#f85606; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:700;">Flash Sale</span>
                    <?php endif; ?>
                </h1>
                <div class="price" style="display:flex; align-items:center; gap:10px;">
                    ৳<span id="display-price"><?php echo number_format($min_price); ?></span>
                    <?php if ($base_min_price > $min_price): ?>
                        <span class="product-old-price" style="font-size:14px; color:#999; text-decoration:line-through;">৳<?php echo number_format($base_min_price); ?></span>
                        <?php if ($save_percent > 0): ?><span style="background:#f85606; color:#fff; padding:3px 6px; border-radius:3px; font-size:12px; font-weight:700;">Save <?php echo $save_percent; ?>%</span><?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($flash_end_ts): ?>
                <div class="countdown" id="flash-countdown">Ends in <span id="flash-timer"></span></div>
                <?php endif; ?>
                <div class="trust-row">
                    <?php if ($is_free_ship): ?>
                        <span class="trust-pill">🚚 Free Shipping</span>
                    <?php else: ?>
                        <span class="trust-pill">🚚 Free Shipping over ৳<?php echo number_format(FREE_SHIP_THRESHOLD); ?></span>
                    <?php endif; ?>
                    <span class="trust-pill return">↩ 7-Day Easy Returns</span>
                </div>
                <?php if ($stock_left > 0 && $stock_left <= 5): ?>
                    <div style="color:#d03801; font-weight:700; margin-bottom:10px;">Only <?php echo $stock_left; ?> left — selling fast!</div>
                <?php endif; ?>
                
                <form method="POST" action="cart.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="variant_id" id="variant_id" value="<?php echo $variants_enhanced[0]['row']['id'] ?? ''; ?>">
                    <div class="variants">
                        <p>Select Variant:</p>
                        <?php foreach($variants_enhanced as $index => $ve): 
                            $v = $ve['row'];
                            $effective = $ve['effective'];
                            $showBase = $ve['offer'] ?? $ve['price'];
                        ?>
                            <button type="button" class="variant-btn<?php echo $index === 0 ? ' active' : ''; ?>" onclick="selectVariant(this, <?php echo $effective; ?>, <?php echo $v['id']; ?>)">
                                <?php echo htmlspecialchars($v['name']); ?>
                                <span style="color:#f85606; font-weight:700; margin-left:6px;">৳<?php echo number_format($effective); ?></span>
                                <?php if ($showBase > $effective): ?>
                                    <span style="text-decoration:line-through; color:#999; margin-left:4px; font-size:12px;">৳<?php echo number_format($showBase); ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin: 10px 0;">
                        <label>Qty</label>
                        <input type="number" name="qty" value="1" min="1" style="width:80px; padding:8px;">
                    </div>
                    <div class="actions">
                        <button class="btn-buy" name="action" value="buy">Buy Now</button>
                        <button class="btn-cart" type="submit">Add to Cart</button>
                    </div>
                </form>

                <div style="margin-top: 30px;">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectVariant(btn, price, id) {
            document.querySelectorAll('.variant-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('display-price').innerText = price;
            document.getElementById('variant_id').value = id;
        }

        // Flash sale countdown based on DB end time
        (function() {
            const timerEl = document.getElementById('flash-timer');
            if (!timerEl) return;
            const serverNow = <?php echo time(); ?>; // seconds
            const endTs = <?php echo $flash_end_ts ? $flash_end_ts : 'null'; ?>; // seconds
            if (!endTs || endTs <= serverNow) return;
            let remaining = endTs - serverNow;
            function tick() {
                if (remaining < 0) return;
                const h = String(Math.floor(remaining / 3600)).padStart(2,'0');
                const m = String(Math.floor((remaining % 3600) / 60)).padStart(2,'0');
                const s = String(remaining % 60).padStart(2,'0');
                timerEl.textContent = `${h}:${m}:${s}`;
                remaining--;
                if (remaining >= 0) setTimeout(tick, 1000);
            }
            tick();
        })();
    </script>
</body>
</html>