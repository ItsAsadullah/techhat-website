<?php
require_once 'core/db.php';
require_once 'core/auth.php';

// BASE_URL define check (Safety check if not in core/db.php)
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/');
}

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header("Location: index.php");
    exit;
}

// Fetch Product
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.slug = ? AND p.is_active = 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found");
}

// Handle Review Submission
$review_msg = '';
$review_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_logged_in()) {
        $review_error = "Please login to submit a review.";
    } else {
        $rating = (int) $_POST['rating'];
        $comment = trim($_POST['comment']);
        $user_id = current_user_id();
        
        if ($rating < 1 || $rating > 5) {
            $review_error = "Invalid rating.";
        } else {
            // Check if already reviewed
            $stmtCheck = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
            $stmtCheck->execute([$product['id'], $user_id]);
            if ($stmtCheck->fetch()) {
                $review_error = "You have already reviewed this product.";
            } else {
                $stmtReview = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
                if ($stmtReview->execute([$product['id'], $user_id, $rating, $comment])) {
                    $review_msg = "Review submitted successfully!";
                } else {
                    $review_error = "Failed to submit review.";
                }
            }
        }
    }
}

// Fetch Images
$stmtImg = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$stmtImg->execute([$product['id']]);
$imagesRaw = $stmtImg->fetchAll();
$images = [];
foreach ($imagesRaw as $img) {
    $base = strtolower(basename($img['image_path']));
    if (in_array($base, ['default.png', 'no-image.png', 'placeholder.png', 'default.jpg', 'no-image.jpg', 'placeholder.jpg', 'default.webp'])) {
        continue; 
    }
    $images[] = $img;
}
if (empty($images) && !empty($imagesRaw)) {
    $images = [$imagesRaw[0]];
}
// Fallback image if absolutely nothing
if (empty($images)) {
    $images[] = ['image_path' => 'assets/images/placeholder.png'];
}

// Fetch Variants - from new system first, fallback to legacy
$stmtVar = $pdo->prepare("
    SELECT pv.* FROM product_variations pv WHERE pv.product_id = ? AND pv.status = 1
    UNION ALL
    SELECT pvl.* FROM product_variants_legacy pvl WHERE pvl.product_id = ? AND pvl.status = 1
");
$stmtVar->execute([$product['id'], $product['id']]);
$variants = $stmtVar->fetchAll();

// Flash Sale Logic
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

// Process Variants with Pricing & Grouping
$variants_enhanced = [];
$unique_colors = [];
$unique_sizes = [];
$unique_storages = [];
$unique_sims = [];

foreach ($variants as $vv) {
    $base_price = (float) $vv['price'];
    $offer_price = ($vv['offer_price'] && $vv['offer_price'] > 0) ? (float) $vv['offer_price'] : null;
    $effective = $offer_price ?? $base_price;

    $color = isset($vv['color']) ? trim((string) $vv['color']) : '';
    $color_code = isset($vv['color_code']) ? trim((string) $vv['color_code']) : '';
    
    // Fallback: If color code is empty but color name exists, use color name as code (for CSS)
    if (empty($color_code) && !empty($color)) {
        $color_code = $color;
    }
    
    // For matching: Use color_code as identifier if color name is empty
    $color_identifier = !empty($color) ? $color : $color_code;

    $size = isset($vv['size']) ? trim((string) $vv['size']) : '';
    $storage = isset($vv['storage']) ? trim((string) $vv['storage']) : '';
    $sim_type = isset($vv['sim_type']) ? trim((string) $vv['sim_type']) : '';
    
    // Apply Flash Discount
    if ($flash_discount_live && $flash_discount_live > 0) {
        $effective = round($effective * (1 - ($flash_discount_live / 100)), 2);
    }

    $v_data = [
        'id' => $vv['id'],
        'color' => $color_identifier !== '' ? $color_identifier : null,
        'color_display' => $color !== '' ? $color : $color_code,
        'color_code' => $color_code !== '' ? $color_code : null,
        'size' => $size !== '' ? $size : null,
        'storage' => $storage !== '' ? $storage : null,
        'sim_type' => $sim_type !== '' ? $sim_type : null,
        'price' => $base_price,
        'offer_price' => $offer_price,
        'effective_price' => $effective,
        'stock' => (int)$vv['stock_quantity'],
        'image' => $vv['image_path'],
        'sku' => $vv['sku']
    ];
    $variants_enhanced[] = $v_data;

    // Collect Unique Attributes
    // Show color option if either color name or color_code exists
    if ($v_data['color']) {
        $unique_colors[$v_data['color']] = [
            'name' => $v_data['color_display'],
            'code' => $v_data['color_code'],
            'image' => $v_data['image']
        ];
    }
    if ($v_data['size']) $unique_sizes[$v_data['size']] = $v_data['size'];
    if ($v_data['storage']) $unique_storages[$v_data['storage']] = $v_data['storage'];
    if ($v_data['sim_type']) $unique_sims[$v_data['sim_type']] = $v_data['sim_type'];
}

// Pricing Aggregates - Use first variant price to avoid flickering
$effective_prices = array_column($variants_enhanced, 'effective_price');
$min_price = $effective_prices ? min($effective_prices) : 0;
$max_price = $effective_prices ? max($effective_prices) : 0;

// Get first variant data for initial display (prevents price flickering)
$first_variant = $variants_enhanced[0] ?? null;
$display_price = $first_variant ? number_format($first_variant['effective_price']) : '0';
$original_price = null;
$discount_percent = 0;
if ($first_variant) {
    // Calculate discount from original price
    if ($first_variant['offer_price'] && $first_variant['offer_price'] < $first_variant['price']) {
        $original_price = $first_variant['price'];
        $discount_percent = round((($original_price - $first_variant['offer_price']) / $original_price) * 100);
    } elseif ($flash_discount_live && $flash_discount_live > 0) {
        // Flash sale discount
        $pre_flash_price = $first_variant['offer_price'] ?? $first_variant['price'];
        $original_price = $pre_flash_price;
        $discount_percent = $flash_discount_live;
    }
}

// YouTube Video ID Extraction
$videoId = null;
if (!empty($product['video_url'])) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $product['video_url'], $match);
    $videoId = $match[1] ?? null;
}
$stock_left = $variants ? array_sum(array_column($variants, 'stock_quantity')) : 0;

// Fetch Reviews
$stmtReviews = $pdo->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.created_at DESC
");
$stmtReviews->execute([$product['id']]);
$reviews = $stmtReviews->fetchAll();
$avg_rating = 0;
$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $avg_rating = array_sum(array_column($reviews, 'rating')) / $total_reviews;
}

// Fetch Related Products (Same Category)
$related_products = [];
if ($product['category_id']) {
    $stmtRelated = $pdo->prepare("
        SELECT p.id, p.title, p.slug, p.badge_text
        FROM products p
        WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $stmtRelated->execute([$product['category_id'], $product['id']]);
    $related_products = $stmtRelated->fetchAll();
    
    // Fetch pricing and images for related products
    foreach ($related_products as &$rp) {
        // Get cheapest variant price (no status filter to ensure we get data)
        $stmtPrice = $pdo->prepare("
            SELECT MIN(CASE WHEN offer_price > 0 THEN offer_price ELSE price END) as final_price,
                   MIN(price) as base_price
            FROM product_variations 
            WHERE product_id = ?
            UNION ALL
            SELECT MIN(CASE WHEN offer_price > 0 THEN offer_price ELSE price END) as final_price,
                   MIN(price) as base_price
            FROM product_variants_legacy 
            WHERE product_id = ?
        ");
        $stmtPrice->execute([$rp['id'], $rp['id']]);
        $priceData = $stmtPrice->fetch();
        $rp['price'] = $priceData['base_price'] ?? 0;
        $rp['offer_price'] = null;
        
        // Check if offer price exists and is different
        if ($priceData && $priceData['base_price'] && $priceData['final_price'] < $priceData['base_price']) {
            $rp['offer_price'] = $priceData['final_price'];
        }
        
        // Get primary image
        $stmtImg = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
        $stmtImg->execute([$rp['id']]);
        $img = $stmtImg->fetch();
        $rp['image'] = $img ? $img['image_path'] : 'assets/images/placeholder.png';
    }
}

// Fetch All Settings (Delivery, Return, Warranty, etc.)
$stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings WHERE setting_key IN ('home_district', 'delivery_charge_inside', 'delivery_charge_outside', 'site_name', 'return_days', 'return_policy_text', 'warranty_policy', 'delivery_time_inside', 'delivery_time_outside', 'free_delivery_threshold')");
$siteSettings = [];
while ($row = $stmtSettings->fetch()) {
    $siteSettings[$row['setting_key']] = $row['setting_value'];
}
// Defaults
$homeDistrict = $siteSettings['home_district'] ?? 'Jhenaidah';
$chargeInside = $siteSettings['delivery_charge_inside'] ?? 70;
$chargeOutside = $siteSettings['delivery_charge_outside'] ?? 150;
$siteName = $siteSettings['site_name'] ?? 'TechHat';
$returnDays = $siteSettings['return_days'] ?? 14;
$returnPolicyText = $siteSettings['return_policy_text'] ?? '';
$warrantyPolicyText = $siteSettings['warranty_policy'] ?? '';
$deliveryTimeInside = $siteSettings['delivery_time_inside'] ?? '2-3 business days';
$deliveryTimeOutside = $siteSettings['delivery_time_outside'] ?? '3-5 business days';
$freeDeliveryThreshold = $siteSettings['free_delivery_threshold'] ?? 5000;

// Helper function to format policy text
function formatPolicyText($text) {
    if (empty($text)) return '';
    
    $lines = explode("\n", $text);
    $formatted = '<div class="space-y-2">';
    $currentSectionType = 'positive'; // 'positive' or 'negative'
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check if line is a heading (ends with colon)
        if (preg_match('/^(.+):$/', $line, $matches)) {
            $heading = $matches[1];
            $formatted .= '<p class="font-bold text-gray-900 mt-3 mb-1">' . htmlspecialchars($heading) . ':</p>';
            
            // Determine section type based on heading text
            $lowerHeading = strtolower($heading);
            if (strpos($lowerHeading, 'non-') !== false || 
                strpos($lowerHeading, 'not ') !== false || 
                strpos($lowerHeading, 'exclusion') !== false ||
                strpos($lowerHeading, 'exclude') !== false ||
                strpos($lowerHeading, 'prohibited') !== false ||
                strpos($lowerHeading, 'exception') !== false) {
                $currentSectionType = 'negative';
            } else {
                $currentSectionType = 'positive';
            }
        }
        // Check if line starts with bullet (•), dash (-), asterisk (*), or any special character
        elseif (preg_match('/^[•\-\*\x{2022}\x{2023}\x{2043}\x{204C}\x{204D}\x{2219}\x{25E6}\x{2043}]+\s*(.+)$/u', $line, $matches)) {
            $formatted .= '<div class="flex items-start gap-2 ml-2">';
            
            if ($currentSectionType === 'negative') {
                $formatted .= '<i class="bi bi-x-circle-fill text-red-600 mt-0.5 text-xs flex-shrink-0"></i>';
            } else {
                $formatted .= '<i class="bi bi-check-circle-fill text-green-600 mt-0.5 text-xs flex-shrink-0"></i>';
            }
            
            $formatted .= '<p class="text-sm text-gray-700">' . htmlspecialchars($matches[1]) . '</p>';
            $formatted .= '</div>';
        }
        // Regular line
        else {
            $formatted .= '<p class="text-sm text-gray-700">' . htmlspecialchars($line) . '</p>';
        }
    }
    
    $formatted .= '</div>';
    return $formatted;
}

// SEO
$metaTitle = htmlspecialchars($product['title']) . ' | ' . htmlspecialchars($siteName);
$metaDesc  = substr(strip_tags($product['description']), 0, 155);
$canonical = BASE_URL . 'product.php?slug=' . urlencode($product['slug']);

require_once 'includes/header.php';
?>

<!-- Custom CSS -->
<style>
    .zoom-container {
        overflow: hidden;
        position: relative;
        cursor: crosshair;
    }
    .zoom-container img {
        transition: transform 0.3s ease;
        transform-origin: center center;
    }
    .zoom-container:hover img {
        transform: scale(1.5);
    }
    .star-rating input { display: none; }
    .star-rating label { cursor: pointer; color: #ddd; font-size: 1.5rem; }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label { color: #fbbf24; }
    .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
    
    /* Sticky Nav */
    #product-nav {
        top: var(--header-height, 70px);
        transition: top 0.3s ease-in-out;
    }
    .nav-link {
        display: inline-block;
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: #4b5563;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
    }
    .nav-link:hover, .nav-link.active {
        color: #2563eb;
        border-bottom-color: #2563eb;
    }
    html { scroll-behavior: smooth; }
    
    /* Disable invalid variants */
    .variant-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: #f3f4f6;
        color: #9ca3af;
        border-color: #e5e7eb;
    }
</style>

<div class="bg-gray-50 min-h-screen pb-12">
    
    <!-- Breadcrumb -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <nav class="flex text-sm text-gray-500" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="index.php" class="hover:text-blue-600">Home</a>
                    </li>
                    <?php if ($product['category_name']): ?>
                    <li>
                        <div class="flex items-center">
                            <i class="bi bi-chevron-right text-gray-400 mx-2"></i>
                            <a href="category.php?slug=<?php echo $product['category_slug']; ?>" class="hover:text-blue-600"><?php echo htmlspecialchars($product['category_name']); ?></a>
                        </div>
                    </li>
                    <?php endif; ?>
                    <li>
                        <div class="flex items-center">
                            <i class="bi bi-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-900 font-medium truncate max-w-xs"><?php echo htmlspecialchars($product['title']); ?></span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Top Section: Image + Info + Sidebar -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Main Content (Image + Info) -->
            <div class="lg:col-span-9">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        
                        <!-- Image Gallery -->
                        <div class="space-y-4">
                            <!-- Main View Area -->
                            <div id="main-media-container" class="h-96 md:h-[500px] w-full bg-white rounded-lg overflow-hidden border border-gray-200 relative flex items-center justify-center zoom-container group">
                                <img id="main-image" src="<?php echo htmlspecialchars($images[0]['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['title']); ?>" 
                                     class="w-full h-full object-contain object-center">
                                
                                <!-- Carousel Controls -->
                                <button onclick="prevMedia()" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 p-2 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <button onclick="nextMedia()" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 p-2 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>

                            <!-- Thumbnails -->
                            <div class="grid grid-cols-5 gap-2">
                                <?php foreach ($images as $idx => $img): ?>
                                <button onclick="showImage(<?php echo htmlspecialchars(json_encode($img['image_path'])); ?>, <?php echo $idx; ?>)" 
                                        class="h-20 w-full rounded-md overflow-hidden border-2 border-transparent hover:border-blue-500 focus:outline-none focus:border-blue-500 transition-colors bg-white thumbnail-btn flex items-center justify-center"
                                        data-index="<?php echo $idx; ?>">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="max-w-full max-h-full object-contain p-1">
                                </button>
                                <?php endforeach; ?>
                                
                                <?php if ($videoId): ?>
                                <button onclick="showVideo(<?php echo htmlspecialchars(json_encode($videoId)); ?>, <?php echo count($images); ?>)" 
                                        class="h-20 w-full rounded-md overflow-hidden border-2 border-transparent hover:border-blue-500 focus:outline-none focus:border-blue-500 transition-colors bg-gray-900 relative group thumbnail-btn flex items-center justify-center"
                                        data-index="<?php echo count($images); ?>">
                                    <img src="https://img.youtube.com/vi/<?php echo $videoId; ?>/0.jpg" class="w-full h-full object-cover opacity-75 group-hover:opacity-100 transition-opacity">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i class="bi bi-play-circle-fill text-white text-2xl shadow-lg rounded-full bg-black bg-opacity-50"></i>
                                    </div>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="flex flex-col">
                            <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['title']); ?></h1>
                            
                            <!-- Badges -->
                            <?php if (!empty($product['badge_text'])): ?>
                                <div class="mb-3">
                                    <span class="inline-block bg-gradient-to-r from-purple-600 to-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">
                                        <?php echo htmlspecialchars($product['badge_text']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Rating -->
                            <div class="flex items-center mb-4">
                                <div class="flex text-yellow-400 text-sm">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <a href="#reviews" class="ml-2 text-sm text-blue-600 hover:underline">(<?php echo $total_reviews; ?> Reviews)</a>
                                <span class="mx-2 text-gray-300">|</span>
                                <span class="text-sm text-green-600 font-medium" id="stock-status-text"><?php echo $stock_left > 0 ? 'In Stock' : 'Out of Stock'; ?></span>
                            </div>

                            <!-- Price -->
                            <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                                <div class="flex flex-wrap items-baseline gap-3">
                                    <span class="text-3xl font-bold text-blue-600">৳<span id="display-price"><?php echo $display_price; ?></span></span>
                                    <?php if ($original_price): ?>
                                        <span class="text-xl text-gray-400 line-through ml-2" id="static-old-price">৳<?php echo number_format($original_price); ?></span>
                                        <span class="ml-2 px-2 py-1 bg-red-500 text-white text-sm font-semibold rounded" id="static-discount">-<?php echo $discount_percent; ?>%</span>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-2">
                                        <span id="display-old-price" class="text-lg text-gray-500 line-through hidden"></span>
                                        <span id="display-discount" class="text-xs font-semibold text-white bg-red-500 rounded-full px-2 py-0.5 hidden"></span>
                                    </div>
                                    <?php if ($flash_discount_live && $flash_discount_live > 0): ?>
                                        <span class="bg-red-100 text-red-600 text-xs font-bold px-2 py-1 rounded-full">
                                            -<?php echo $flash_discount_live; ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div id="you-save" class="mt-2 text-sm font-medium text-green-600 <?php echo $original_price ? '' : 'hidden'; ?>">
                                    <i class="bi bi-tag-fill"></i> You Save: ৳<span id="save-amount"><?php echo $original_price ? number_format($original_price - $first_variant['effective_price']) : '0'; ?></span> (<span id="save-percent"><?php echo $discount_percent; ?></span>%)
                                </div>
                                <?php if ($flash_end_ts): ?>
                                    <div class="mt-2 inline-flex items-center gap-2 text-orange-700 text-sm font-medium">
                                        <i class="bi bi-lightning-fill"></i>
                                        Flash Sale Ends in: <span id="flash-timer" class="font-mono font-bold"></span>
                                    </div>
                                <?php endif; ?>
                            </div>

            <!-- Add to Cart Form -->
            <form method="POST" action="cart.php" id="cartForm" class="mt-auto" onsubmit="return handleCartSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="variant_id" id="variant_id" value="">
                <input type="hidden" name="buy_now" id="buy_now" value="0">

                <!-- Variants -->
                <?php if (count($variants_enhanced) > 0): ?>
                <div class="mb-6 space-y-4" id="variant-selectors">
                    
                    <!-- Color Selection -->
                    <?php if (!empty($unique_colors)): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color: <span id="selected-color-name" class="font-bold text-gray-900"></span></label>
                        <div class="flex flex-wrap gap-3" id="color-options">
                            <?php foreach($unique_colors as $cName => $cData): ?>
                                <button type="button" 
                                        class="variant-btn color-btn w-10 h-10 rounded-full border-2 border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 relative transition-all"
                                        data-type="color"
                                        data-value="<?php echo htmlspecialchars($cName); ?>"
                                        data-image="<?php echo htmlspecialchars($cData['image'] ?? ''); ?>"
                                        title="<?php echo htmlspecialchars($cName); ?>"
                                        style="background-color: <?php echo htmlspecialchars($cData['code'] ?? '#eee'); ?>;">
                                    <!-- Checkmark for selected state -->
                                    <i class="bi bi-check text-white absolute inset-0 flex items-center justify-center opacity-0 check-icon" style="text-shadow: 0 1px 2px rgba(0,0,0,0.5);"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                                    <!-- Size Selection -->
                                    <?php if (!empty($unique_sizes)): ?>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Size:</label>
                                        <div class="flex flex-wrap gap-2" id="size-options">
                                            <?php foreach($unique_sizes as $s): ?>
                                                <button type="button" class="variant-btn px-4 py-2 border border-gray-200 rounded-md text-sm font-medium hover:border-blue-500 hover:text-blue-600 transition-all" data-type="size" data-value="<?php echo htmlspecialchars($s); ?>">
                                                    <?php echo htmlspecialchars($s); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Storage Selection -->
                                    <?php if (!empty($unique_storages)): ?>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Storage:</label>
                                        <div class="flex flex-wrap gap-2" id="storage-options">
                                            <?php foreach($unique_storages as $st): ?>
                                                <button type="button" class="variant-btn px-4 py-2 border border-gray-200 rounded-md text-sm font-medium hover:border-blue-500 hover:text-blue-600 transition-all" data-type="storage" data-value="<?php echo htmlspecialchars($st); ?>">
                                                    <?php echo htmlspecialchars($st); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Sim Selection -->
                                    <?php if (!empty($unique_sims)): ?>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Sim Type:</label>
                                        <div class="flex flex-wrap gap-2" id="sim-options">
                                            <?php foreach($unique_sims as $sim): ?>
                                                <button type="button" class="variant-btn px-4 py-2 border border-gray-200 rounded-md text-sm font-medium hover:border-blue-500 hover:text-blue-600 transition-all" data-type="sim_type" data-value="<?php echo htmlspecialchars($sim); ?>">
                                                    <?php echo htmlspecialchars($sim); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div>
                                <?php endif; ?>

                                <!-- Quantity & Actions -->
                                <div class="flex flex-col gap-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-32">
                                            <label class="sr-only">Quantity</label>
                                            <div class="flex items-center border border-gray-300 rounded-md">
                                                <button type="button" onclick="updateQty(-1)" class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-l-md">-</button>
                                                <input type="number" name="qty" id="qty" value="1" min="1" max="<?php echo $stock_left; ?>" class="w-full text-center border-none focus:ring-0 p-0 text-gray-900 font-medium">
                                                <button type="button" onclick="updateQty(1)" class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-r-md">+</button>
                                            </div>
                                        </div>
                                        <span class="text-sm text-gray-500" id="items-available"><?php echo $stock_left; ?> items available</span>
                                    </div>
                                    
                                    <div class="flex gap-3">
                                        <button type="submit" id="addToCartBtn" class="flex-1 bg-blue-600 text-white px-4 py-3 rounded-md font-semibold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2 whitespace-nowrap">
                                            <i class="bi bi-cart-plus" id="cartBtnIcon"></i> <span id="cartBtnText">Add to Cart</span>
                                        </button>
                                        <button type="button" onclick="toggleWishlist(<?php echo $product['id']; ?>)" id="wishlistBtn" class="bg-white border-2 border-gray-300 text-gray-700 px-4 py-3 rounded-md font-semibold hover:border-pink-500 hover:text-pink-500 transition-all hover:scale-110 flex items-center justify-center">
                                            <i class="bi bi-heart text-xl animate-heartbeat" id="wishlistIcon"></i>
                                        </button>
                                        <button type="button" onclick="buyNow()" class="flex-1 bg-gray-900 text-white px-4 py-3 rounded-md font-semibold hover:bg-gray-800 transition-all hover:scale-105 hover:shadow-2xl flex items-center justify-center gap-2 animate-glow">
                                            <i class="bi bi-bag-check animate-bounce-subtle"></i> Buy Now
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- WhatsApp, Call & Share Buttons -->
                            <div class="flex flex-col gap-3 mt-4">
                                <div class="flex gap-3">
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $siteSettings['contact_whatsapp'] ?? '8801712345678'); ?>" target="_blank" class="flex-1 bg-green-600 text-white px-4 py-2.5 rounded-md font-semibold hover:bg-green-700 transition-colors flex items-center justify-center gap-2 text-sm">
                                        <i class="bi bi-whatsapp text-lg"></i> Order on WhatsApp
                                    </a>
                                    <a href="tel:<?php echo htmlspecialchars($siteSettings['contact_phone'] ?? '09678-300400'); ?>" class="flex-1 bg-blue-600 text-white px-4 py-2.5 rounded-md font-semibold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2 text-sm">
                                        <i class="bi bi-telephone-fill"></i> Call for Order
                                    </a>
                                </div>
                                
                                <!-- Share -->
                                <div class="flex items-center gap-3 pt-2 border-t border-gray-200">
                                    <span class="text-sm font-medium text-gray-700">Share:</span>
                                    <div class="flex gap-2">
                                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($canonical); ?>" target="_blank" class="w-9 h-9 rounded-full bg-blue-600 hover:bg-blue-700 flex items-center justify-center text-white transition-colors" title="Share on Facebook">
                                            <i class="bi bi-facebook"></i>
                                        </a>
                                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($canonical); ?>&text=<?php echo urlencode($product['title']); ?>" target="_blank" class="w-9 h-9 rounded-full bg-sky-500 hover:bg-sky-600 flex items-center justify-center text-white transition-colors" title="Share on Twitter">
                                            <i class="bi bi-twitter"></i>
                                        </a>
                                        <a href="https://wa.me/?text=<?php echo urlencode($product['title'] . ' - ' . $canonical); ?>" target="_blank" class="w-9 h-9 rounded-full bg-green-600 hover:bg-green-700 flex items-center justify-center text-white transition-colors" title="Share on WhatsApp">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                        <button onclick="copyLink(event)" class="w-9 h-9 rounded-full bg-gray-600 hover:bg-gray-700 flex items-center justify-center text-white transition-colors" title="Copy Link">
                                            <i class="bi bi-link-45deg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar (Delivery & Services) -->
            <div class="lg:col-span-3 space-y-4">
                
                <!-- Delivery Options -->
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Delivery Options</h3>
                        <i class="bi bi-info-circle text-gray-400 cursor-pointer hover:text-blue-600 transition-colors" title="Delivery info" onclick="openDeliveryModal()"></i>
                    </div>

                    <!-- Location -->
                    <div class="flex items-start gap-3 mb-6">
                        <i class="bi bi-geo-alt text-xl text-gray-500 mt-1"></i>
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <span class="text-sm text-gray-800 font-medium" id="user-location-display">Select Location</span>
                                <button onclick="openLocationModal()" class="text-blue-600 text-xs font-bold uppercase hover:underline">Change</button>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100 mb-4">

                    <!-- Standard Delivery -->
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-8 flex justify-center"><i class="bi bi-truck text-xl text-gray-500"></i></div>
                        <div class="flex-1">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-900">Standard Delivery</span>
                                <span class="text-sm font-bold text-gray-900">৳ <span id="delivery-cost">--</span></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Guaranteed by <span id="delivery-date">...</span></p>
                        </div>
                    </div>

                    <!-- COD -->
                    <div class="flex items-start gap-3 mb-6">
                        <div class="w-8 flex justify-center"><i class="bi bi-cash text-xl text-gray-500"></i></div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">Cash on Delivery Available</p>
                        </div>
                    </div>

                    <hr class="border-gray-100 mb-4">

                    <!-- Free Delivery -->
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-8 flex justify-center"><i class="bi bi-truck text-xl text-blue-600"></i></div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">Free Delivery</p>
                            <p class="text-xs text-gray-500 mt-1">On orders over ৳<?php echo number_format($freeDeliveryThreshold); ?></p>
                        </div>
                    </div>

                    <!-- Warranty Badge -->
                    <div class="flex items-start gap-3">
                        <div class="w-8 flex justify-center"><i class="bi bi-shield-check text-xl text-green-600"></i></div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">1 Year Warranty</p>
                            <p class="text-xs text-gray-500 mt-1">100% Authentic</p>
                        </div>
                    </div>
                </div>

                <!-- Return & Warranty -->
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Return & Warranty</h3>
                        <i class="bi bi-info-circle text-gray-400 cursor-pointer hover:text-blue-600 transition-colors" title="Policy info" onclick="openPolicyModal()"></i>
                    </div>

                    <!-- Return -->
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-8 flex justify-center"><i class="bi bi-arrow-counterclockwise text-xl text-gray-500"></i></div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900"><?php echo $returnDays; ?> days easy return</p>
                            <p class="text-xs text-gray-400 mt-1">Change of mind is not applicable</p>
                        </div>
                    </div>

                    <!-- Warranty -->
                    <div class="flex items-start gap-3">
                        <div class="w-8 flex justify-center"><i class="bi bi-shield-check text-xl text-gray-500"></i></div>
                        <div class="flex-1">
                            <?php if (!empty($product['warranty_type']) && $product['warranty_type'] !== 'No Warranty'): ?>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['warranty_period'] ?? ''); ?> <?php echo htmlspecialchars($product['warranty_type']); ?></p>
                            <?php else: ?>
                                <p class="text-sm font-medium text-gray-900">Warranty not available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sold By -->
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Sold By</h3>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500">
                            <i class="bi bi-shop text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($siteName); ?></p>
                            <div class="flex items-center gap-1 text-xs text-gray-500">
                                <i class="bi bi-patch-check-fill text-green-500"></i>
                                <span>100% Trusted Seller</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Location Modal -->
        <div id="locationModal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/50" onclick="closeLocationModal()"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Select Location</h3>
                    <button onclick="closeLocationModal()" class="text-gray-400 hover:text-gray-600"><i class="bi bi-x-lg"></i></button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Division</label>
                        <select id="loc-division" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="loadDistricts()">
                            <option value="">Select Division</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <select id="loc-district" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="loadUpazilas()" disabled>
                            <option value="">Select District</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upazila / Area</label>
                        <select id="loc-upazila" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" disabled>
                            <option value="">Select Area</option>
                        </select>
                    </div>
                    
                    <button onclick="saveLocation()" class="w-full bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors mt-4">
                        Save Location
                    </button>
                </div>
            </div>
        </div>

        <!-- Policy Modal -->
        <div id="policyModal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/50" onclick="closePolicyModal()"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden">
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900">Return & Warranty Policy</h3>
                    <button onclick="closePolicyModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="bi bi-x-lg text-2xl"></i>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto max-h-[calc(80vh-88px)]">
                    <!-- Return Policy Section -->
                    <div class="mb-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-arrow-counterclockwise text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Return Policy</h4>
                                <p class="text-sm text-green-600 font-medium"><?php echo $returnDays; ?> days easy return</p>
                            </div>
                        </div>
                        
                        <?php if (!empty($returnPolicyText)): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <?php echo formatPolicyText($returnPolicyText); ?>
                        </div>
                        <?php else: ?>
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">Product must be unused and in original packaging</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">All accessories and tags must be included</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-x-circle-fill text-red-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">Change of mind is not applicable as a return reason</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                            <p class="text-xs text-orange-800">
                                <i class="bi bi-exclamation-triangle-fill mr-1"></i>
                                <strong>Note:</strong> Please check the product carefully before accepting delivery. Once accepted, returns may not be applicable for certain categories.
                            </p>
                        </div>
                    </div>
                    
                    <hr class="border-gray-200 my-6">
                    
                    <!-- Warranty Section -->
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-shield-check text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Warranty</h4>
                                <?php if (!empty($product['warranty_type']) && $product['warranty_type'] !== 'No Warranty'): ?>
                                    <p class="text-sm text-green-600 font-medium"><?php echo htmlspecialchars($product['warranty_period'] ?? ''); ?> <?php echo htmlspecialchars($product['warranty_type']); ?></p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-600 font-medium">No warranty available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($warrantyPolicyText)): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <?php echo formatPolicyText($warrantyPolicyText); ?>
                        </div>
                        <?php else: ?>
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">Warranty as per manufacturer terms and conditions</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">Original invoice required for warranty claims</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">100% authentic products guaranteed</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="bi bi-info-circle-fill mr-1"></i>
                            For more details or assistance, please contact our customer support team.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Info Modal -->
        <div id="deliveryModal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/50" onclick="closeDeliveryModal()"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden">
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900">Delivery Information</h3>
                    <button onclick="closeDeliveryModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="bi bi-x-lg text-2xl"></i>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto max-h-[calc(80vh-88px)]">
                    <!-- Home Delivery -->
                    <div class="mb-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-truck text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Home Delivery</h4>
                                <p class="text-sm text-green-600 font-medium">Nationwide delivery available</p>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Inside <?php echo htmlspecialchars($homeDistrict); ?>: ৳<?php echo $chargeInside; ?></p>
                                    <p class="text-xs text-gray-500">Delivery within <?php echo htmlspecialchars($deliveryTimeInside); ?></p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Outside <?php echo htmlspecialchars($homeDistrict); ?>: ৳<?php echo $chargeOutside; ?></p>
                                    <p class="text-xs text-gray-500">Delivery within <?php echo htmlspecialchars($deliveryTimeOutside); ?></p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Free Delivery on orders over ৳<?php echo number_format($freeDeliveryThreshold); ?></p>
                                    <p class="text-xs text-gray-500">Applicable for all areas</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="border-gray-200 my-6">
                    
                    <!-- Cash on Delivery -->
                    <div class="mb-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-cash text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Cash on Delivery</h4>
                                <p class="text-sm text-green-600 font-medium">Pay when you receive</p>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">Available for all delivery areas</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">Pay in cash when receiving your order</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bi bi-check-circle-fill text-green-600 mt-0.5"></i>
                                <p class="text-sm text-gray-700">Check products before payment</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="bi bi-info-circle-fill mr-1"></i>
                            <strong>Note:</strong> Delivery times may vary during peak seasons or for remote areas. Track your order status in My Account.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Navigation -->
        <div class="mt-8 sticky z-40 bg-white/100 shadow-md border-b border-gray-200" id="product-nav">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="#specification" class="nav-link whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-colors">Specification</a>
                    <a href="#description" class="nav-link whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-colors">Description</a>
                    <a href="#reviews" class="nav-link whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-colors">Reviews (<?php echo $total_reviews; ?>)</a>
                    <a href="#related-products" class="nav-link whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-colors">Related Products</a>
                </nav>
            </div>
        </div>

        <!-- Content Sections -->
        <div class="bg-white shadow-sm rounded-b-xl p-6 md:p-8 space-y-12 relative z-0">
            
            <!-- Specification Section -->
            <section id="specification" class="scroll-mt-24">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Specification</h2>
                <div class="border rounded-lg overflow-hidden">
                    <?php if (!empty($product['specifications'])): ?>
                        <!-- Assuming specifications is stored as JSON or HTML table -->
                        <!-- For now, displaying raw if HTML, or parsing if JSON -->
                        <?php 
                            $specs = json_decode($product['specifications'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($specs)) {
                                echo '<table class="min-w-full divide-y divide-gray-200">';
                                echo '<tbody class="bg-white divide-y divide-gray-200">';
                                foreach ($specs as $key => $value) {
                                    echo '<tr>';
                                    echo '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 w-1/3 bg-gray-50">' . htmlspecialchars($key) . '</td>';
                                    
                                    $display_val = is_array($value) ? implode(', ', array_map(function($v) { return is_array($v) ? json_encode($v) : $v; }, $value)) : $value;
                                    
                                    echo '<td class="px-6 py-4 text-sm text-gray-500">' . htmlspecialchars((string)$display_val) . '</td>';
                                    echo '</tr>';
                                }
                                echo '</tbody></table>';
                            } else {
                                // Fallback if it's just text or HTML
                                echo '<div class="prose max-w-none p-4">' . $product['specifications'] . '</div>';
                            }
                        ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <p>No detailed specifications available for this product.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <hr class="border-gray-200">

            <!-- Description Section -->
            <section id="description" class="scroll-mt-24">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Description</h2>
                <div class="prose max-w-none text-gray-700">
                    <?php echo $product['description']; ?>
                </div>
            </section>

            <hr class="border-gray-200">

            <!-- Reviews Section -->
            <section id="reviews" class="scroll-mt-24">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Reviews & Ratings</h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Review List -->
                    <div class="lg:col-span-2 space-y-6">
                        <?php if (empty($reviews)): ?>
                            <div class="text-center py-10 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                <i class="bi bi-chat-square-text text-4xl mb-3 block text-gray-400"></i>
                                No reviews yet. Be the first to review!
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="border-b border-gray-100 pb-6 last:border-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                                <?php echo strtoupper(substr($review['user_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="font-medium text-gray-900 block"><?php echo htmlspecialchars($review['user_name']); ?></span>
                                                <div class="flex text-yellow-400 text-xs">
                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                        <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <p class="text-gray-600 text-sm mt-2"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Write Review -->
                    <div class="lg:col-span-1">
                        <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 sticky top-24">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Write a Review</h3>
                            
                            <?php if ($review_msg): ?>
                                <div class="mb-4 text-green-600 text-sm bg-green-50 p-2 rounded border border-green-200"><?php echo $review_msg; ?></div>
                            <?php endif; ?>
                            <?php if ($review_error): ?>
                                <div class="mb-4 text-red-600 text-sm bg-red-50 p-2 rounded border border-red-200"><?php echo $review_error; ?></div>
                            <?php endif; ?>

                            <?php if (is_logged_in()): ?>
                                <form method="POST">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                                        <div class="star-rating">
                                            <input type="radio" id="star5" name="rating" value="5" /><label for="star5" title="5 stars"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star"><i class="bi bi-star-fill"></i></label>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Your Review</label>
                                        <textarea name="comment" rows="4" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2 text-sm" placeholder="What did you like or dislike?" required></textarea>
                                    </div>
                                    <button type="submit" name="submit_review" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium">Submit Review</button>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-sm text-gray-600 mb-3">Please login to write a review.</p>
                                    <a href="login.php?redirect=product.php?slug=<?php echo $product['slug']; ?>" class="inline-block bg-white border border-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-50 text-sm font-medium">Login Now</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <hr class="border-gray-200">

            <!-- Related Products Section -->
            <?php if (!empty($related_products)): ?>
            <section id="related-products" class="scroll-mt-24">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($related_products as $rp): ?>
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-300">
                            <!-- Image -->
                            <div class="relative h-48 bg-gray-100 overflow-hidden group">
                                <img src="<?php echo htmlspecialchars($rp['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($rp['title']); ?>"
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                
                                <?php if (!empty($rp['badge_text'])): ?>
                                    <span class="absolute top-2 right-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full uppercase">
                                        <?php echo htmlspecialchars($rp['badge_text']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <!-- Quick View Overlay -->
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors duration-300 flex items-center justify-center">
                                    <a href="product.php?slug=<?php echo htmlspecialchars($rp['slug']); ?>" 
                                       class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-white text-gray-900 px-4 py-2 rounded-full font-semibold hover:bg-gray-100 flex items-center gap-2">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 text-sm line-clamp-2 mb-3">
                                    <a href="product.php?slug=<?php echo htmlspecialchars($rp['slug']); ?>" 
                                       class="hover:text-blue-600 transition-colors">
                                        <?php echo htmlspecialchars($rp['title']); ?>
                                    </a>
                                </h3>

                                <!-- Price -->
                                <div class="mb-3">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-lg font-bold text-blue-600">
                                            ৳<?php echo number_format($rp['offer_price'] && $rp['offer_price'] > 0 ? $rp['offer_price'] : $rp['price']); ?>
                                        </span>
                                        <?php if ($rp['offer_price'] && $rp['offer_price'] > 0 && $rp['offer_price'] < $rp['price']): ?>
                                            <span class="text-sm text-gray-400 line-through">
                                                ৳<?php echo number_format($rp['price']); ?>
                                            </span>
                                            <span class="text-xs font-semibold text-white bg-red-500 rounded px-1.5 py-0.5">
                                                -<?php echo round(((($rp['price'] - $rp['offer_price']) / $rp['price']) * 100)); ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Add to Cart Button -->
                                <a href="product.php?slug=<?php echo htmlspecialchars($rp['slug']); ?>" 
                                   class="w-full block text-center bg-blue-600 text-white py-2 rounded-md font-medium hover:bg-blue-700 transition-colors text-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php else: ?>
            <!-- No Related Products Message -->
            <section id="related-products" class="scroll-mt-24">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 p-8 text-center">
                    <i class="bi bi-inbox text-4xl text-gray-400 mb-3 block"></i>
                    <p class="text-gray-600 font-medium">No related products available in this category.</p>
                    <p class="text-sm text-gray-500 mt-2">Check out our other categories for more products.</p>
                </div>
            </section>
            <?php endif; ?>

        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/bd-locations.js"></script>
<script>
    // --- Delivery & Location Logic ---
    const homeDistrict = "<?php echo htmlspecialchars($homeDistrict); ?>";
    const chargeInside = <?php echo (int)$chargeInside; ?>;
    const chargeOutside = <?php echo (int)$chargeOutside; ?>;

    function initLocation() {
        // Load saved location
        const savedLoc = JSON.parse(localStorage.getItem('userLocation'));
        if(savedLoc) {
            updateLocationDisplay(savedLoc);
        } else {
            // Default
            updateLocationDisplay({
                division: 'Dhaka',
                district: 'Dhaka',
                upazila: 'Dhaka'
            });
        }
        
        // Populate Divisions
        const divSelect = document.getElementById('loc-division');
        if(divSelect && typeof bdLocations !== 'undefined') {
            Object.keys(bdLocations).sort().forEach(div => {
                const opt = document.createElement('option');
                opt.value = div;
                opt.textContent = div;
                divSelect.appendChild(opt);
            });
        }
    }

    function loadDistricts() {
        const div = document.getElementById('loc-division').value;
        const distSelect = document.getElementById('loc-district');
        const upaSelect = document.getElementById('loc-upazila');
        
        distSelect.innerHTML = '<option value="">Select District</option>';
        upaSelect.innerHTML = '<option value="">Select Area</option>';
        distSelect.disabled = true;
        upaSelect.disabled = true;

        if(div && bdLocations[div]) {
            Object.keys(bdLocations[div]).sort().forEach(dist => {
                const opt = document.createElement('option');
                opt.value = dist;
                opt.textContent = dist;
                distSelect.appendChild(opt);
            });
            distSelect.disabled = false;
        }
    }

    function loadUpazilas() {
        const div = document.getElementById('loc-division').value;
        const dist = document.getElementById('loc-district').value;
        const upaSelect = document.getElementById('loc-upazila');
        
        upaSelect.innerHTML = '<option value="">Select Area</option>';
        upaSelect.disabled = true;

        if(div && dist && bdLocations[div][dist]) {
            bdLocations[div][dist].sort().forEach(upa => {
                const opt = document.createElement('option');
                opt.value = upa;
                opt.textContent = upa;
                upaSelect.appendChild(opt);
            });
            upaSelect.disabled = false;
        }
    }

    function saveLocation() {
        const div = document.getElementById('loc-division').value;
        const dist = document.getElementById('loc-district').value;
        const upa = document.getElementById('loc-upazila').value;

        if(!div || !dist || !upa) {
            alert("Please select all fields");
            return;
        }

        const loc = { division: div, district: dist, upazila: upa };
        localStorage.setItem('userLocation', JSON.stringify(loc));
        updateLocationDisplay(loc);
        closeLocationModal();
    }

    function updateLocationDisplay(loc) {
        const displayEl = document.getElementById('user-location-display');
        const costEl = document.getElementById('delivery-cost');
        const dateEl = document.getElementById('delivery-date');

        if(displayEl) {
            displayEl.textContent = `${loc.district}, ${loc.division}`; // Simplified display
        }

        // Calculate Cost
        let cost = chargeOutside;
        if(loc.district === homeDistrict) {
            cost = chargeInside;
        }
        if(costEl) costEl.textContent = cost;

        // Calculate Date (Today + 3 to 5 days)
        const today = new Date();
        const start = new Date(today); start.setDate(today.getDate() + 3);
        const end = new Date(today); end.setDate(today.getDate() + 5); // +2 more days
        
        const options = { day: 'numeric', month: 'short' };
        if(dateEl) dateEl.textContent = `${start.toLocaleDateString('en-US', options)} - ${end.toLocaleDateString('en-US', options)}`;
    }

    function openLocationModal() {
        document.getElementById('locationModal').classList.remove('hidden');
        // Pre-select if saved
        const savedLoc = JSON.parse(localStorage.getItem('userLocation'));
        if(savedLoc && typeof bdLocations !== 'undefined') {
            document.getElementById('loc-division').value = savedLoc.division;
            loadDistricts();
            document.getElementById('loc-district').value = savedLoc.district;
            loadUpazilas();
            document.getElementById('loc-upazila').value = savedLoc.upazila;
        }
    }

    function closeLocationModal() {
        document.getElementById('locationModal').classList.add('hidden');
    }

    function openPolicyModal() {
        document.getElementById('policyModal').classList.remove('hidden');
    }

    function closePolicyModal() {
        document.getElementById('policyModal').classList.add('hidden');
    }

    function openDeliveryModal() {
        document.getElementById('deliveryModal').classList.remove('hidden');
    }

    function closeDeliveryModal() {
        document.getElementById('deliveryModal').classList.add('hidden');
    }

    // Init
    window.addEventListener('DOMContentLoaded', initLocation);

    // --- Media Gallery Logic ---
    let currentMediaIndex = 0;
    const totalMedia = <?php echo count($images) + ($videoId ? 1 : 0); ?>;
    
    function updateActiveThumbnail(index) {
        document.querySelectorAll('.thumbnail-btn').forEach(btn => {
            btn.classList.remove('ring-2', 'ring-blue-500');
            if(parseInt(btn.dataset.index) === index) {
                btn.classList.add('ring-2', 'ring-blue-500');
            }
        });
        currentMediaIndex = index;
    }

    function showImage(src, index) {
        const container = document.getElementById('main-media-container');
        container.classList.add('zoom-container');
        container.innerHTML = `<img id="main-image" src="${src}" class="w-full h-full object-contain object-center">`;
        
        // Re-add buttons
        addCarouselButtons(container);
        attachZoomListeners();
        updateActiveThumbnail(index);
    }

    function showVideo(videoId, index) {
        const container = document.getElementById('main-media-container');
        container.classList.remove('zoom-container');
        container.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?rel=0&fs=0&modestbranding=1" class="w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>`;
        
        // Re-add buttons
        addCarouselButtons(container);
        updateActiveThumbnail(index);
    }

    function addCarouselButtons(container) {
        const prevBtn = document.createElement('button');
        prevBtn.className = "absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 p-2 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition-opacity z-10";
        prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i>';
        prevBtn.onclick = prevMedia;
        
        const nextBtn = document.createElement('button');
        nextBtn.className = "absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 p-2 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition-opacity z-10";
        nextBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
        nextBtn.onclick = nextMedia;
        
        container.appendChild(prevBtn);
        container.appendChild(nextBtn);
    }

    function nextMedia() {
        let nextIndex = currentMediaIndex + 1;
        if (nextIndex >= totalMedia) nextIndex = 0;
        triggerThumbnail(nextIndex);
    }

    function prevMedia() {
        let prevIndex = currentMediaIndex - 1;
        if (prevIndex < 0) prevIndex = totalMedia - 1;
        triggerThumbnail(prevIndex);
    }

    function triggerThumbnail(index) {
        const btn = document.querySelector(`.thumbnail-btn[data-index="${index}"]`);
        if(btn) btn.click();
    }

    // Zoom Effect
    function attachZoomListeners() {
        const zoomContainer = document.getElementById('main-media-container');
        if(!zoomContainer) return;
        
        zoomContainer.onmousemove = function(e) {
            const img = this.querySelector('img');
            if(!img) return;
            
            const { left, top, width, height } = this.getBoundingClientRect();
            const x = (e.clientX - left) / width * 100;
            const y = (e.clientY - top) / height * 100;
            
            img.style.transformOrigin = `${x}% ${y}%`;
            img.style.transform = 'scale(2)';
        };
        
        zoomContainer.onmouseleave = function() {
            const img = this.querySelector('img');
            if(img) {
                img.style.transform = 'scale(1)';
                setTimeout(() => { img.style.transformOrigin = 'center center'; }, 300);
            }
        };
    }
    
    // Init Zoom
    attachZoomListeners();

    // --- Variant Logic ---
    // Enhanced Variant Logic
    const allVariants = <?php echo json_encode($variants_enhanced); ?>;
    const currentSelection = {
        color: null,
        size: null,
        storage: null,
        sim_type: null
    };

    function initVariants() {
        // Auto-select first available options to show a valid price immediately
        // Priority: Color -> Storage -> Sim -> Size
        
        // 1. Select first color
        const firstColorBtn = document.querySelector('.variant-btn[data-type="color"]');
        if (firstColorBtn) {
            selectVariantOption('color', firstColorBtn.dataset.value, firstColorBtn);
        } else {
            // If no color, try others
            const firstStorage = document.querySelector('.variant-btn[data-type="storage"]');
            if(firstStorage) selectVariantOption('storage', firstStorage.dataset.value, firstStorage);
            else {
                 const firstSize = document.querySelector('.variant-btn[data-type="size"]');
                 if(firstSize) selectVariantOption('size', firstSize.dataset.value, firstSize);
                 else {
                     // No variant options - product has only one default variant
                     // Automatically select the first (and only) variant
                     if (allVariants && allVariants.length > 0) {
                         const defaultVariant = allVariants[0];
                         document.getElementById('variant_id').value = defaultVariant.id;
                         
                         // Update price display
                         const priceValue = defaultVariant.effective_price ?? defaultVariant.offer_price ?? defaultVariant.price ?? 0;
                         const displayPriceEl = document.getElementById('display-price');
                         if (displayPriceEl) {
                             displayPriceEl.innerText = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(priceValue);
                         }
                         
                         // Update stock
                         const stockEl = document.getElementById('items-available');
                         const currentStock = defaultVariant.stock || 0;
                         if (stockEl) {
                             stockEl.textContent = `${currentStock} items available`;
                         }
                     }
                 }
            }
        }
    }

    function selectVariantOption(type, value, btnElement) {
        // Update selection state
        currentSelection[type] = value;

        // Update UI for this type
        const container = btnElement.closest('div').parentElement; // The specific attribute container
        // Remove active class from siblings
        const siblings = document.querySelectorAll(`.variant-btn[data-type="${type}"]`);
        siblings.forEach(btn => {
            btn.classList.remove('ring-2', 'ring-offset-2', 'ring-blue-500', 'border-blue-500', 'text-blue-600', 'bg-blue-50');
            
            // Specific styles for Color vs Text buttons
            if(type === 'color') {
                btn.classList.remove('ring-2', 'ring-offset-2', 'ring-blue-500');
                if(btn.querySelector('.check-icon')) {
                    btn.querySelector('.check-icon').classList.remove('opacity-100');
                    btn.querySelector('.check-icon').classList.add('opacity-0');
                }
            } else {
                btn.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
                btn.classList.add('border-gray-200', 'text-gray-700');
            }
        });

        // Add active class to clicked
        if(type === 'color') {
            btnElement.classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
            if(btnElement.querySelector('.check-icon')) {
                btnElement.querySelector('.check-icon').classList.remove('opacity-0');
                btnElement.querySelector('.check-icon').classList.add('opacity-100');
            }
            
            // Update Main Image if color has one
            const imgPath = btnElement.dataset.image;
            if(imgPath) {
                showImage(imgPath, 0);
            }
        } else {
            btnElement.classList.remove('border-gray-200', 'text-gray-700');
            btnElement.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        }

        // Resolve dependencies and update other options
        updateAvailableOptions(type);
    }

    function updateAvailableOptions(justChangedType) {
        // Filter variants based on current selection
        // Strategy: Always keep all options enabled, but auto-select compatible options
        
        ['color', 'storage', 'sim_type', 'size'].forEach(targetType => {
            if (targetType === justChangedType) return;

            const buttons = document.querySelectorAll(`.variant-btn[data-type="${targetType}"]`);
            if (buttons.length === 0) return;

            // Always enable all buttons - don't disable any variant options
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('disabled', 'opacity-50', 'cursor-not-allowed');
            });

            // Find compatible options based on what is currently selected
            let validOptionsForType = new Set();
            
            allVariants.forEach(v => {
                let match = true;
                for (const [key, val] of Object.entries(currentSelection)) {
                    if (key === targetType) continue;
                    if (val !== null && v[key] !== val) {
                        match = false;
                        break;
                    }
                }
                if (match) {
                    validOptionsForType.add(v[targetType]);
                }
            });

            // Auto-select first valid option if current selection is incompatible
            let currentlySelectedIsValid = validOptionsForType.has(currentSelection[targetType]);
            
            if (!currentlySelectedIsValid && validOptionsForType.size > 0) {
                // Deselect current invalid option
                buttons.forEach(btn => {
                    if (currentSelection[targetType] === btn.dataset.value) {
                        btn.classList.remove('ring-2', 'ring-offset-2', 'ring-blue-500', 'border-blue-500', 'text-blue-600', 'bg-blue-50');
                        if(targetType === 'color') {
                             if(btn.querySelector('.check-icon')) btn.querySelector('.check-icon').classList.add('opacity-0');
                        } else {
                             btn.classList.add('border-gray-200', 'text-gray-700');
                        }
                    }
                });
                
                // Select first valid option
                for (const btn of buttons) {
                    if (validOptionsForType.has(btn.dataset.value)) {
                        selectVariantOption(targetType, btn.dataset.value, btn);
                        break;
                    }
                }
            }
        });

        finalizeSelection();
    }

    function finalizeSelection() {
        // Check if we have a complete match
        const match = allVariants.find(v => {
            return (!currentSelection.color || v.color === currentSelection.color) &&
                   (!currentSelection.size || v.size === currentSelection.size) &&
                   (!currentSelection.storage || v.storage === currentSelection.storage) &&
                   (!currentSelection.sim_type || v.sim_type === currentSelection.sim_type);
        });

        if (!match) {
            return;
        }

        const displayPriceEl = document.getElementById('display-price');
        const priceValue = match.effective_price ?? match.offer_price ?? match.price ?? 0;
        if (displayPriceEl) {
            displayPriceEl.innerText = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(priceValue);
        }

        const basePrice = match.price ?? priceValue;
        const oldPriceEl = document.getElementById('display-old-price');
        const youSaveEl = document.getElementById('you-save');
        const saveAmountEl = document.getElementById('save-amount');
        const savePercentEl = document.getElementById('save-percent');
        
        if (oldPriceEl) {
            if (basePrice > priceValue + 0.001) {
                oldPriceEl.textContent = '৳' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(basePrice);
                oldPriceEl.classList.remove('hidden');
                
                // Show You Save section
                if (youSaveEl && saveAmountEl && savePercentEl) {
                    const saveAmount = basePrice - priceValue;
                    const savePercent = Math.round((saveAmount / basePrice) * 100);
                    saveAmountEl.textContent = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(saveAmount);
                    savePercentEl.textContent = savePercent;
                    youSaveEl.classList.remove('hidden');
                }
            } else {
                oldPriceEl.classList.add('hidden');
                if (youSaveEl) youSaveEl.classList.add('hidden');
            }
        }

        const discountEl = document.getElementById('display-discount');
        if (discountEl) {
            if (basePrice > priceValue + 0.001) {
                const discountPercent = basePrice > 0 ? Math.round(((basePrice - priceValue) / basePrice) * 100) : 0;
                discountEl.textContent = `-${discountPercent}%`;
                discountEl.classList.remove('hidden');
            } else {
                discountEl.classList.add('hidden');
            }
        }

        const selectedColorEl = document.getElementById('selected-color-name');
        if (selectedColorEl) {
            selectedColorEl.textContent = match.color_display ?? match.color ?? '';
        }

        const variantInput = document.getElementById('variant_id');
        if (variantInput) {
            variantInput.value = match.id;
        }

        // UPDATE STOCK LOGIC
        const stockEl = document.getElementById('items-available');
        const stockStatusEl = document.getElementById('stock-status-text');
        const qtyInput = document.getElementById('qty');
        const currentStock = match.stock || 0;

        if (stockEl) {
            stockEl.textContent = `${currentStock} items available`;
        }
        
        if (stockStatusEl) {
             if(currentStock > 0) {
                 stockStatusEl.textContent = 'In Stock';
                 stockStatusEl.className = 'text-sm text-green-600 font-medium';
             } else {
                 stockStatusEl.textContent = 'Out of Stock';
                 stockStatusEl.className = 'text-sm text-red-600 font-medium';
             }
        }

        if (qtyInput) {
            qtyInput.max = currentStock;
            if (parseInt(qtyInput.value) > currentStock) {
                qtyInput.value = currentStock > 0 ? currentStock : 1;
            }
            if(currentStock === 0) {
                 qtyInput.value = 0; // Or disable it
            }
        }
    }
    
    // Form Validation before submit
    function validateForm() {
        const variantInput = document.getElementById('variant_id');
        const qtyInput = document.getElementById('qty');
        const maxStock = parseInt(qtyInput.max) || 0;
        
        if (!variantInput.value) {
            alert("Please select all variant options (Color, Size, etc.)");
            return false;
        }
        
        if(maxStock <= 0) {
             alert("This product variant is out of stock.");
             return false;
        }
        
        return true;
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', () => {
        // Attach click handlers
        document.querySelectorAll('.variant-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if(!this.disabled) {
                    selectVariantOption(this.dataset.type, this.dataset.value, this);
                }
            });
        });

        initVariants();
        
        // Color hover to preview image
        const colorBtns = document.querySelectorAll('.color-btn');
        const mainImage = document.getElementById('main-image');
        let currentMainImage = mainImage ? mainImage.src : '';
        
        colorBtns.forEach(btn => {
            // Store original image on page load
            if (!currentMainImage && mainImage) {
                currentMainImage = mainImage.src;
            }
            
            // Hover to preview variant image
            btn.addEventListener('mouseenter', function() {
                const variantImage = this.dataset.image;
                if (variantImage && mainImage && variantImage.trim() !== '') {
                    mainImage.src = variantImage;
                }
            });
            
            // Restore on mouse leave if not selected
            btn.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected') && mainImage) {
                    // Find currently selected color button
                    const selectedColorBtn = document.querySelector('.color-btn.selected');
                    if (selectedColorBtn && selectedColorBtn.dataset.image) {
                        mainImage.src = selectedColorBtn.dataset.image;
                    } else if (currentMainImage) {
                        mainImage.src = currentMainImage;
                    }
                }
            });
        });
        
        // Auto-select first available variant IMMEDIATELY (before DOM fully renders)
        if (colorBtns.length > 0) {
            colorBtns[0].click();
            
            // Chain select size/storage immediately
            setTimeout(() => {
                const sizeBtns = document.querySelectorAll('[data-type="size"]:not([disabled])');
                if (sizeBtns.length > 0) sizeBtns[0].click();
                
                const storageBtns = document.querySelectorAll('[data-type="storage"]:not([disabled])');
                if (storageBtns.length > 0) storageBtns[0].click();
            }, 50);
        } else {
            const sizeBtns = document.querySelectorAll('[data-type="size"]:not([disabled])');
            if (sizeBtns.length > 0) {
                sizeBtns[0].click();
                setTimeout(() => {
                    const storageBtns = document.querySelectorAll('[data-type="storage"]:not([disabled])');
                    if (storageBtns.length > 0) storageBtns[0].click();
                }, 50);
            } else {
                const storageBtns = document.querySelectorAll('[data-type="storage"]:not([disabled])');
                if (storageBtns.length > 0) storageBtns[0].click();
            }
        }
    });

    // Quantity
    function updateQty(change) {
        const input = document.getElementById('qty');
        let val = parseInt(input.value) + change;
        if (val < 1) val = 1;
        
        // Update: Check max stock dynamically
        const max = parseInt(input.max); 
        if (max && val > max) val = max;
        
        input.value = val;
    }

    // Buy Now
    function buyNow() {
        if(validateForm()) {
            document.getElementById('buy_now').value = '1';
            document.getElementById('cartForm').submit();
        }
    }

    // Copy Link Function
    function copyLink(event) {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            // Show success message
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i>';
            btn.classList.add('bg-green-600');
            btn.classList.remove('bg-gray-600');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('bg-green-600');
                btn.classList.add('bg-gray-600');
            }, 2000);
        }).catch(() => {
            alert('Failed to copy link');
        });
    }

    // Flash Sale Timer
    (function() {
        const timerEl = document.getElementById('flash-timer');
        if (!timerEl) return;
        const serverNow = <?php echo time(); ?>;
        const endTs = <?php echo $flash_end_ts ? $flash_end_ts : 'null'; ?>;
        
        if (!endTs || endTs <= serverNow) return;
        
        let remaining = endTs - serverNow;
        
        function tick() {
            if (remaining < 0) {
                timerEl.innerText = "Expired";
                return;
            }
            const h = String(Math.floor(remaining / 3600)).padStart(2,'0');
            const m = String(Math.floor((remaining % 3600) / 60)).padStart(2,'0');
            const s = String(remaining % 60).padStart(2,'0');
            timerEl.textContent = `${h}:${m}:${s}`;
            remaining--;
            setTimeout(tick, 1000);
        }
        tick();
    })();

    // Sticky Nav Active State & Positioning
    function updateStickyNav() {
        // Handled by CSS variable --header-height
    }

    // Initial call and resize listener
    window.addEventListener('load', updateStickyNav);
    window.addEventListener('resize', updateStickyNav);
    // Also update on scroll in case header resizes
    window.addEventListener('scroll', updateStickyNav);

    window.addEventListener('scroll', function() {
        const sections = document.querySelectorAll('section');
        const navLinks = document.querySelectorAll('.nav-link');
        const header = document.getElementById('mainHeader');
        const nav = document.getElementById('product-nav');
        
        let current = '';
        // Calculate the trigger line (bottom of the sticky nav)
        const headerHeight = header ? header.getBoundingClientRect().height : 0;
        const navHeight = nav ? nav.getBoundingClientRect().height : 0;
        const triggerLine = headerHeight + navHeight + 50; // Added buffer
        
        sections.forEach(section => {
            const rect = section.getBoundingClientRect();
            // Check if section is active
            // Top is above trigger line OR Top is close to trigger line
            if (rect.top <= triggerLine && rect.bottom > triggerLine) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            // Reset to inactive style
            link.classList.remove('border-blue-600', 'text-blue-600');
            link.classList.add('border-transparent', 'text-gray-500');
            
            if (current && link.getAttribute('href') === '#' + current) {
                // Set active style
                link.classList.remove('border-transparent', 'text-gray-500');
                link.classList.add('border-blue-600', 'text-blue-600');
            }
        });
    });

    // ============ AJAX CART FUNCTIONALITY ============
    
    // Check if product is in cart on page load
    const currentVariantId = <?php echo isset($_SESSION['cart']) && !empty($_SESSION['cart']) ? json_encode(array_keys($_SESSION['cart'])) : '[]'; ?>;
    let isInCart = false;

    function updateCartButton(inCart) {
        const btn = document.getElementById('addToCartBtn');
        const icon = document.getElementById('cartBtnIcon');
        const text = document.getElementById('cartBtnText');
        
        if (inCart) {
            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            btn.classList.add('bg-red-600', 'hover:bg-red-700');
            icon.className = 'bi bi-cart-x';
            text.textContent = 'Remove Cart';
            isInCart = true;
        } else {
            btn.classList.remove('bg-red-600', 'hover:bg-red-700');
            btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            icon.className = 'bi bi-cart-plus';
            text.textContent = 'Add to Cart';
            isInCart = false;
        }
    }

    function handleCartSubmit(event) {
        event.preventDefault();
        
        const variantId = document.getElementById('variant_id').value;
        const qty = document.getElementById('qty').value;
        
        if (!variantId) {
            alert('Please select product options');
            return false;
        }
        
        const formData = new FormData();
        formData.append('variant_id', variantId);
        formData.append('qty', qty);
        formData.append('action', isInCart ? 'remove' : 'add');
        
        fetch('api/cart_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartButton(data.inCart);
                updateCartCount(data.cartCount);
                updateCartTotal(data.total || 0);
                showCartNotification(data.message);
                
                // Dispatch custom event
                window.dispatchEvent(new CustomEvent('cartUpdated', {
                    detail: {
                        variantId: parseInt(variantId),
                        action: isInCart ? 'remove' : 'add',
                        inCart: data.inCart
                    }
                }));
                
                // Refresh cart sidebar if open
                if (!document.getElementById('cartSidebar').classList.contains('translate-x-full')) {
                    loadCartSidebar();
                }
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to update cart');
        });
        
        return false;
    }

    // Check current variant on page load
    window.addEventListener('DOMContentLoaded', function() {
        const variantId = document.getElementById('variant_id').value;
        if (variantId && currentVariantId.includes(parseInt(variantId))) {
            updateCartButton(true);
        }
        
        // Check wishlist status
        checkWishlistStatus();
    });
    
    // Listen for cart updates from cart widget
    window.addEventListener('cartUpdated', function(e) {
        const currentVariant = parseInt(document.getElementById('variant_id').value);
        const updatedVariant = parseInt(e.detail.variantId);
        
        // If the updated variant matches current product variant, update button
        if (currentVariant === updatedVariant) {
            updateCartButton(e.detail.inCart);
        }
    });

    // Wishlist Functions
    function checkWishlistStatus() {
        <?php if (is_logged_in()): ?>
        fetch('api/wishlist_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=check&product_id=<?php echo $product['id']; ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.inWishlist) {
                updateWishlistButton(true);
            }
        })
        .catch(error => console.error('Error:', error));
        <?php endif; ?>
    }

    function toggleWishlist(productId) {
        <?php if (!is_logged_in()): ?>
        openAuthModal('loginModal');
        return;
        <?php endif; ?>

        const isInWishlist = document.getElementById('wishlistIcon').classList.contains('bi-heart-fill');
        const action = isInWishlist ? 'remove' : 'add';

        fetch('api/wishlist_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=${action}&product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.requireLogin) {
                openAuthModal('loginModal');
                return;
            }
            if (data.success) {
                updateWishlistButton(data.inWishlist);
                showCartNotification(data.message);
                updateWishlistCount(data.wishlistCount);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function updateWishlistButton(inWishlist) {
        const btn = document.getElementById('wishlistBtn');
        const icon = document.getElementById('wishlistIcon');
        
        if (inWishlist) {
            btn.classList.remove('border-gray-300', 'text-gray-700');
            btn.classList.add('border-pink-500', 'text-pink-500', 'bg-pink-50');
            icon.classList.remove('bi-heart');
            icon.classList.add('bi-heart-fill');
        } else {
            btn.classList.remove('border-pink-500', 'text-pink-500', 'bg-pink-50');
            btn.classList.add('border-gray-300', 'text-gray-700');
            icon.classList.remove('bi-heart-fill');
            icon.classList.add('bi-heart');
        }
    }

    function updateWishlistCount(count) {
        const wishlistBadges = document.querySelectorAll('.wishlist-count-badge');
        wishlistBadges.forEach(badge => {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        });
    }
</script>

<style>
.animate-heartbeat {
    animation: heartbeat 1.5s ease-in-out infinite;
}
.animate-glow {
    animation: glow 2s ease-in-out infinite;
}
.animate-bounce-subtle {
    animation: bounceSubtle 2s infinite;
}
@keyframes heartbeat {
    0%, 100% {
        transform: scale(1);
    }
    10%, 30% {
        transform: scale(1.15);
    }
    20%, 40% {
        transform: scale(1);
    }
}
@keyframes glow {
    0%, 100% {
        box-shadow: 0 0 5px rgba(31, 41, 55, 0.5), 0 0 10px rgba(31, 41, 55, 0.3);
    }
    50% {
        box-shadow: 0 0 20px rgba(31, 41, 55, 0.8), 0 0 30px rgba(31, 41, 55, 0.4);
    }
}
@keyframes bounceSubtle {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}
</style>


</body>
</html>
