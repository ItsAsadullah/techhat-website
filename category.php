<?php
require_once 'core/auth.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: index.php');
    exit;
}

// Fetch category and optional children
$stmtCat = $pdo->prepare('SELECT id, name, slug FROM categories WHERE slug = ? LIMIT 1');
$stmtCat->execute([$slug]);
$category = $stmtCat->fetch();
if (!$category) {
    die('Category not found');
}

// Collect category ids (self + children)
$catIds = [$category['id']];
$stmtChildren = $pdo->prepare('SELECT id FROM categories WHERE parent_id = ?');
$stmtChildren->execute([$category['id']]);
foreach ($stmtChildren->fetchAll() as $row) {
    $catIds[] = $row['id'];
}
$placeholders = implode(',', array_fill(0, count($catIds), '?'));

$sql = "SELECT p.*, 
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_thumbnail = 1 LIMIT 1) as thumb,
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
    (SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants WHERE product_id = p.id) as min_effective,
    (SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants WHERE product_id = p.id) as max_effective,
    (SELECT MIN(price) FROM product_variants WHERE product_id = p.id) as min_regular,
    (SELECT COALESCE(fsi.discount_percentage, fs.discount_percentage) FROM flash_sale_items fsi JOIN flash_sales fs ON fs.id = fsi.flash_sale_id WHERE fsi.product_id = p.id AND fs.is_active = 1 AND (fs.start_at IS NULL OR fs.start_at <= NOW()) AND (fs.end_at IS NULL OR fs.end_at >= NOW()) ORDER BY fs.end_at ASC LIMIT 1) as flash_discount
    FROM products p
    WHERE p.category_id IN ($placeholders)
    ORDER BY p.id DESC";
$stmtProd = $pdo->prepare($sql);
$stmtProd->execute($catIds);
$products = $stmtProd->fetchAll();

$metaTitle = htmlspecialchars($category['name']) . ' | Buy Online at TechHat';
$metaDesc  = 'Shop ' . htmlspecialchars($category['name']) . ' products at TechHat with flash deals and POS-synced stock.';
$canonical = BASE_URL . 'category.php?slug=' . urlencode($category['slug']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $metaTitle; ?></title>
    <meta name="description" content="<?php echo $metaDesc; ?>">
    <link rel="canonical" href="<?php echo $canonical; ?>">
    <meta property="og:title" content="<?php echo $metaTitle; ?>">
    <meta property="og:description" content="<?php echo $metaDesc; ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f5f5f5; }
        .container { width: 95%; max-width: 1200px; margin: auto; }
        .section-title { font-size: 22px; margin: 20px 0 10px; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
        .product-card { background: #fff; border-radius: 4px; overflow: hidden; transition: transform 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); position: relative; }
        .product-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .product-card .badge-wrap { position: absolute; top: 8px; left: 8px; display: flex; flex-wrap: wrap; gap: 6px; max-width: 140px; }
        .deal-badge { background: #f85606; color: #fff; padding: 4px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; box-shadow: 0 1px 4px rgba(0,0,0,0.12); }
        .flash-badge { background: #111; color: #ffd7c2; padding: 4px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; box-shadow: 0 1px 4px rgba(0,0,0,0.12); }
        .ship-badge { background: #0c7dd9; color: #fff; padding: 4px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; box-shadow: 0 1px 4px rgba(0,0,0,0.12); }
        .product-img { height: 180px; background: #f9f9f9; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-img img { max-width: 100%; max-height: 100%; }
        .product-info { padding: 10px; }
        .product-title { font-size: 14px; height: 36px; overflow: hidden; margin-bottom: 5px; line-height: 1.3; }
        .product-price { color: #f85606; font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <h1 class="section-title"><?php echo htmlspecialchars($category['name']); ?></h1>
        <div class="product-grid">
            <?php foreach($products as $p): ?>
            <?php 
            $flashDiscount = $p['flash_discount'] ?? null;
            $minEffective = (float) $p['min_effective'];
            $maxEffective = (float) $p['max_effective'];
            if ($flashDiscount && $flashDiscount > 0) {
                $minEffective = round($minEffective * (1 - ($flashDiscount / 100)), 2);
                $maxEffective = round($maxEffective * (1 - ($flashDiscount / 100)), 2);
            }
            $savePercent = ($p['min_regular'] && $p['min_regular'] > $minEffective) ? round((($p['min_regular'] - $minEffective) / $p['min_regular']) * 100) : 0;
            $isFreeShip = ($minEffective !== null && $minEffective >= FREE_SHIP_THRESHOLD);
            ?>
            <a href="product.php?slug=<?php echo $p['slug']; ?>" class="product-card">
                <div class="product-img">
                    <?php if($savePercent > 0 || !empty($p['is_flash_sale']) || $isFreeShip): ?>
                    <div class="badge-wrap">
                        <?php if($savePercent > 0): ?><span class="deal-badge">Save <?php echo $savePercent; ?>%</span><?php endif; ?>
                        <?php if(!empty($p['is_flash_sale'])): ?><span class="flash-badge">Flash</span><?php endif; ?>
                        <?php if($isFreeShip): ?><span class="ship-badge">Free Ship</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php $thumbSrc = $p['thumb'] ?: $p['image']; ?>
                    <?php if($thumbSrc): ?>
                        <img src="<?php echo $thumbSrc; ?>" alt="<?php echo htmlspecialchars($p['title']); ?>">
                    <?php else: ?>
                        <span>No Image</span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-title"><?php echo htmlspecialchars($p['title']); ?></div>
                    <div class="product-price">
                        ৳<?php echo number_format($minEffective); ?>
                        <?php if($minEffective != $maxEffective): ?>
                            - ৳<?php echo number_format($maxEffective); ?>
                        <?php endif; ?>
                        <?php if($p['min_regular'] && $p['min_regular'] > $minEffective): ?>
                            <span class="product-old-price">৳<?php echo number_format($p['min_regular']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if(!empty($p['is_flash_sale'])): ?>
                        <div style="color:#f85606; font-size:12px; font-weight:700;">Flash Sale</div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <p>No products found in this category.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
