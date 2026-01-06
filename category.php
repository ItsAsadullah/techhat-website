<?php
require_once 'core/auth.php';

$slug = $_GET['slug'] ?? '';
$catId = $_GET['id'] ?? '';
$isOffers = isset($_GET['offers']) && $_GET['offers'] == 1;

// Get filter parameters
$sort = $_GET['sort'] ?? 'newest';
$priceMin = isset($_GET['price_min']) ? (int)$_GET['price_min'] : null;
$priceMax = isset($_GET['price_max']) ? (int)$_GET['price_max'] : null;
$inStock = isset($_GET['in_stock']) ? (int)$_GET['in_stock'] : 0;
$brandFilter = isset($_GET['brand']) ? (int)$_GET['brand'] : null;

if (!$slug && !$catId && !$isOffers) {
    header('Location: index.php');
    exit;
}

if ($isOffers) {
    $category = ['id' => 0, 'name' => 'Special Offers', 'slug' => 'offers'];
    $sql = "SELECT p.*, b.name as brand_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_thumbnail = 1 LIMIT 1) as thumb,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
        LEAST(COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 999999)) as min_effective,
        GREATEST(COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 0), COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 0)) as max_effective,
        LEAST(COALESCE((SELECT MIN(price) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(price) FROM product_variants_legacy WHERE product_id = p.id), 999999)) as min_regular,
        (COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) as total_stock,
        (SELECT COALESCE(fsi.discount_percentage, fs.discount_percentage) FROM flash_sale_items fsi JOIN flash_sales fs ON fs.id = fsi.flash_sale_id WHERE fsi.product_id = p.id AND fs.is_active = 1 AND (fs.start_at IS NULL OR fs.start_at <= NOW()) AND (fs.end_at IS NULL OR fs.end_at >= NOW()) ORDER BY fs.end_at ASC LIMIT 1) as flash_discount
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE ((SELECT COUNT(*) FROM product_variations WHERE product_id = p.id AND offer_price > 0) > 0 OR (SELECT COUNT(*) FROM product_variants_legacy WHERE product_id = p.id AND offer_price > 0) > 0)";
    
    // Apply filters
    if ($brandFilter) {
        $sql .= " AND p.brand_id = :brand_id";
    }
    if ($inStock) {
        $sql .= " AND (COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) > 0";
    }
    
    // Apply sorting
    switch($sort) {
        case 'price_low':
            $sql .= " ORDER BY min_effective ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY min_effective DESC";
            break;
        case 'name':
            $sql .= " ORDER BY p.title ASC";
            break;
        default:
            $sql .= " ORDER BY p.id DESC";
    }
    
    $stmtProd = $pdo->prepare($sql);
    if ($brandFilter) {
        $stmtProd->bindParam(':brand_id', $brandFilter);
    }
    $stmtProd->execute();
} else {
    // Fetch category and optional children
    if ($slug) {
        $stmtCat = $pdo->prepare('SELECT id, name, slug, description FROM categories WHERE slug = ? LIMIT 1');
        $stmtCat->execute([$slug]);
    } else {
        $stmtCat = $pdo->prepare('SELECT id, name, slug, description FROM categories WHERE id = ? LIMIT 1');
        $stmtCat->execute([$catId]);
    }
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

    $sql = "SELECT p.*, b.name as brand_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_thumbnail = 1 LIMIT 1) as thumb,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
        LEAST(COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 999999)) as min_effective,
        GREATEST(COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 0), COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 0)) as max_effective,
        LEAST(COALESCE((SELECT MIN(price) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(price) FROM product_variants_legacy WHERE product_id = p.id), 999999)) as min_regular,
        (COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) as total_stock,
        (SELECT COALESCE(fsi.discount_percentage, fs.discount_percentage) FROM flash_sale_items fsi JOIN flash_sales fs ON fs.id = fsi.flash_sale_id WHERE fsi.product_id = p.id AND fs.is_active = 1 AND (fs.start_at IS NULL OR fs.start_at <= NOW()) AND (fs.end_at IS NULL OR fs.end_at >= NOW()) ORDER BY fs.end_at ASC LIMIT 1) as flash_discount
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.category_id IN ($placeholders) AND p.is_active = 1";
    
    // Apply filters
    if ($brandFilter) {
        $sql .= " AND p.brand_id = :brand_id";
    }
    if ($inStock) {
        $sql .= " AND (COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) > 0";
    }
    
    // Apply sorting
    switch($sort) {
        case 'price_low':
            $sql .= " ORDER BY min_effective ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY min_effective DESC";
            break;
        case 'name':
            $sql .= " ORDER BY p.title ASC";
            break;
        default:
            $sql .= " ORDER BY p.id DESC";
    }
    
    $stmtProd = $pdo->prepare($sql);
    
    // Bind parameters
    foreach ($catIds as $idx => $cid) {
        $stmtProd->bindValue($idx + 1, $cid);
    }
    if ($brandFilter) {
        $stmtProd->bindParam(':brand_id', $brandFilter);
    }
    $stmtProd->execute();
}

$allProducts = $stmtProd->fetchAll();

// Set fixed price range (0 to 1,000,000)
$actualMinPrice = 0;
$actualMaxPrice = 1000000;

// Set filter defaults if not provided
if ($priceMin === null) $priceMin = $actualMinPrice;
if ($priceMax === null) $priceMax = $actualMaxPrice;

// Apply price range filter in PHP (after fetching)
$products = [];
foreach ($allProducts as $p) {
    $price = (float)$p['min_effective'];
    if ($price < $priceMin) continue;
    if ($price > $priceMax) continue;
    $products[] = $p;
}

// Fetch brands for filter
$stmtBrands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC");
$brands = $stmtBrands->fetchAll();

$metaTitle = htmlspecialchars($category['name']) . ' | Buy Online at TechHat';
$metaDesc  = 'Shop ' . htmlspecialchars($category['name']) . ' products at TechHat with flash deals and POS-synced stock.';
$canonical = BASE_URL . 'category.php?' . ($isOffers ? 'offers=1' : 'slug=' . urlencode($category['slug']));
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.1/nouislider.min.css">
    <style>
        body { 
            background: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        /* Price Range Slider Styles */
        .noUi-connect {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .noUi-horizontal .noUi-handle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            cursor: pointer;
            top: -8px;
        }
        .noUi-horizontal {
            height: 6px;
        }
        .noUi-target {
            background: #e5e7eb;
            border: none;
            box-shadow: none;
        }
        /* Filter Section Styles */
        .filter-section {
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 0;
        }
        .filter-section:last-child {
            border-bottom: none;
        }
        .filter-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }
        .filter-header i {
            transition: transform 0.3s ease;
        }
        .filter-header.collapsed i {
            transform: rotate(-90deg);
        }
        .filter-options {
            max-height: 300px;
            overflow-y: auto;
        }
        .filter-options::-webkit-scrollbar {
            width: 4px;
        }
        .filter-options::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .filter-options::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .filter-option {
            padding: 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .filter-option:hover {
            color: #667eea;
        }
        .product-card { 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .product-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
        .product-card img {
            transition: transform 0.4s ease;
        }
        .product-card:hover img {
            transform: scale(1.05);
        }
        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .badge-deal { background: linear-gradient(135deg, #f85606 0%, #ff7700 100%); color: #fff; }
        .badge-flash { background: linear-gradient(135deg, #111 0%, #333 100%); color: #ffd7c2; }
        .badge-ship { background: linear-gradient(135deg, #0c7dd9 0%, #0e63b8 100%); color: #fff; }
        .filter-btn {
            transition: all 0.3s ease;
        }
        .filter-btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .subcategory-pill {
            transition: all 0.3s ease;
            background: white;
            border: 2px solid #e5e7eb;
        }
        .subcategory-pill:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        /* Mobile Filter Drawer */
        .filter-drawer {
            position: fixed;
            top: 0;
            left: -100%;
            width: 85%;
            max-width: 400px;
            height: 100vh;
            background: white;
            z-index: 9999;
            transition: left 0.3s ease;
            overflow-y: auto;
        }
        .filter-drawer.open {
            left: 0;
        }
        .filter-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
            display: none;
        }
        .filter-overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Mobile Filter Overlay -->
    <div class="filter-overlay" id="filterOverlay" onclick="toggleMobileFilters()"></div>
    
    <!-- Mobile Filter Drawer -->
    <div class="filter-drawer" id="filterDrawer">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="bi bi-funnel-fill text-purple-600"></i> Filters
                </h3>
                <button onclick="toggleMobileFilters()" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>
            
            <!-- Filter Content (same as desktop) -->
            <div id="mobileFilterContent"></div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-8" style="margin-top: 100px;">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2 flex items-center gap-3">
                <i class="bi bi-grid-fill text-purple-600"></i>
                <?php echo htmlspecialchars($category['name']); ?>
            </h1>
            <?php if (!empty($category['description'])): ?>
                <p class="text-gray-600"><?php echo htmlspecialchars($category['description']); ?></p>
            <?php endif; ?>
            <p class="text-gray-500 mt-2">
                <span class="font-semibold"><?php echo count($products); ?></span> products found
            </p>
        </div>

        <!-- Subcategories -->
        <?php
        $subcategories = [];
        if (!$isOffers) {
            $stmtSub = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name ASC");
            $stmtSub->execute([$category['id']]);
            $subcategories = $stmtSub->fetchAll();
        }
        ?>

        <?php if (!empty($subcategories)): ?>
        <div class="mb-8 overflow-x-auto pb-4">
            <div class="flex gap-3 flex-nowrap">
                <?php foreach ($subcategories as $sub): ?>
                    <a href="category.php?id=<?php echo $sub['id']; ?>" 
                       class="subcategory-pill px-5 py-2 rounded-full font-semibold text-gray-700 whitespace-nowrap inline-flex items-center gap-2">
                        <i class="bi bi-tag-fill text-sm"></i>
                        <?php echo htmlspecialchars($sub['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Sidebar Filters (Desktop) -->
            <aside class="hidden lg:block w-full lg:w-72 flex-shrink-0">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                    <!-- Filter Header -->
                    <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-6 py-4">
                        <h3 class="text-lg font-bold flex items-center gap-2">
                            <i class="bi bi-funnel-fill"></i>
                            Filters
                        </h3>
                    </div>

                    <div class="p-6">
                        <form method="GET" id="filterForm">
                            <input type="hidden" name="id" value="<?php echo $catId; ?>">
                            <input type="hidden" name="slug" value="<?php echo $slug; ?>">
                            <?php if ($isOffers): ?>
                                <input type="hidden" name="offers" value="1">
                            <?php endif; ?>
                            <input type="hidden" name="price_min" id="hiddenPriceMin" value="<?php echo $priceMin; ?>">
                            <input type="hidden" name="price_max" id="hiddenPriceMax" value="<?php echo $priceMax; ?>">

                            <!-- Price Range Filter -->
                            <div class="filter-section">
                                <div class="filter-header">
                                    <span><i class="bi bi-currency-exchange text-purple-600"></i> Price Range</span>
                                    <i class="bi bi-chevron-down text-sm"></i>
                                </div>
                                <div class="filter-content">
                                    <div id="priceRangeSlider" class="mb-4"></div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <div class="flex-1">
                                            <input type="number" id="minPriceInput" readonly
                                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-center font-semibold"
                                                   value="<?php echo $priceMin; ?>">
                                            <label class="text-xs text-gray-500 block mt-1 text-center">Min Price (৳)</label>
                                        </div>
                                        <span class="text-gray-400">-</span>
                                        <div class="flex-1">
                                            <input type="number" id="maxPriceInput" readonly
                                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-center font-semibold"
                                                   value="<?php echo $priceMax; ?>">
                                            <label class="text-xs text-gray-500 block mt-1 text-center">Max Price (৳)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Availability Filter -->
                            <div class="filter-section">
                                <div class="filter-header">
                                    <span><i class="bi bi-box-seam text-purple-600"></i> Availability</span>
                                    <i class="bi bi-chevron-down text-sm"></i>
                                </div>
                                <div class="filter-content">
                                    <label class="filter-option">
                                        <input type="checkbox" name="in_stock" value="1" <?php echo $inStock ? 'checked' : ''; ?>
                                               class="w-4 h-4 text-purple-600 rounded focus:ring-2 focus:ring-purple-500" onchange="this.form.submit()">
                                        <span class="text-sm text-gray-700">In Stock Only</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Brand Filter -->
                            <?php if (!empty($brands)): ?>
                            <div class="filter-section">
                                <div class="filter-header">
                                    <span><i class="bi bi-award text-purple-600"></i> Brand</span>
                                    <i class="bi bi-chevron-down text-sm"></i>
                                </div>
                                <div class="filter-content filter-options">
                                    <?php foreach ($brands as $brand): ?>
                                        <label class="filter-option">
                                            <input type="radio" name="brand" value="<?php echo $brand['id']; ?>" 
                                                   <?php echo $brandFilter == $brand['id'] ? 'checked' : ''; ?>
                                                   class="w-4 h-4 text-purple-600 focus:ring-2 focus:ring-purple-500" onchange="this.form.submit()">
                                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars($brand['name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    <?php if ($brandFilter): ?>
                                        <label class="filter-option">
                                            <input type="radio" name="brand" value="" checked class="w-4 h-4 text-purple-600" onchange="this.form.submit()">
                                            <span class="text-sm text-purple-600 font-semibold">All Brands</span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Sort By -->
                            <div class="filter-section">
                                <div class="filter-header">
                                    <span><i class="bi bi-sort-down text-purple-600"></i> Sort By</span>
                                    <i class="bi bi-chevron-down text-sm"></i>
                                </div>
                                <div class="filter-content filter-options">
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="newest" <?php echo $sort == 'newest' ? 'checked' : ''; ?>
                                               class="w-4 h-4 text-purple-600" onchange="this.form.submit()">
                                        <span class="text-sm text-gray-700">Newest First</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="price_low" <?php echo $sort == 'price_low' ? 'checked' : ''; ?>
                                               class="w-4 h-4 text-purple-600" onchange="this.form.submit()">
                                        <span class="text-sm text-gray-700">Price: Low to High</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="price_high" <?php echo $sort == 'price_high' ? 'checked' : ''; ?>
                                               class="w-4 h-4 text-purple-600" onchange="this.form.submit()">
                                        <span class="text-sm text-gray-700">Price: High to Low</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="sort" value="name" <?php echo $sort == 'name' ? 'checked' : ''; ?>
                                               class="w-4 h-4 text-purple-600" onchange="this.form.submit()">
                                        <span class="text-sm text-gray-700">Name (A-Z)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Clear Filters -->
                            <?php if ($sort != 'newest' || $priceMin != $actualMinPrice || $priceMax != $actualMaxPrice || $inStock || $brandFilter): ?>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <a href="?<?php echo $isOffers ? 'offers=1' : ($catId ? 'id='.$catId : 'slug='.$slug); ?>" 
                                   class="block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg transition-all">
                                    <i class="bi bi-x-circle"></i> Clear All Filters
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1">
                <!-- Mobile Filter Button -->
                <div class="lg:hidden mb-4 flex gap-3">
                    <button onclick="toggleMobileFilters()" class="flex-1 bg-white border-2 border-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl hover:border-purple-600 hover:text-purple-600 transition-all">
                        <i class="bi bi-funnel-fill"></i> Filters
                        <?php if ($sort != 'newest' || $priceMin || $priceMax || $inStock || $brandFilter): ?>
                            <span class="ml-2 px-2 py-1 bg-purple-600 text-white text-xs rounded-full">Active</span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-4">
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
                    $isFreeShip = ($minEffective !== null && $minEffective >= 5000);
                    $isOutOfStock = (int)$p['total_stock'] <= 0;
                    
                    // Parse specifications
                    $specs = [];
                    if (!empty($p['specifications'])) {
                        $specLines = explode("\n", $p['specifications']);
                        foreach ($specLines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            if (strpos($line, ':') !== false) {
                                list($key, $value) = explode(':', $line, 2);
                                $specs[trim($key)] = trim($value);
                            }
                        }
                    }
                    ?>
                    <a href="product.php?slug=<?php echo $p['slug']; ?>" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden product-card group">
                        <div class="relative bg-gray-50 aspect-square flex items-center justify-center overflow-hidden">
                            <?php if($savePercent > 0 || !empty($p['is_flash_sale']) || $isFreeShip || $isOutOfStock): ?>
                            <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                                <?php if($isOutOfStock): ?>
                                    <span class="badge bg-gray-600 text-white">Out of Stock</span>
                                <?php else: ?>
                                    <?php if($savePercent > 0): ?><span class="badge badge-deal">-<?php echo $savePercent; ?>%</span><?php endif; ?>
                                    <?php if(!empty($p['is_flash_sale'])): ?><span class="badge badge-flash">Flash</span><?php endif; ?>
                                    <?php if($isFreeShip): ?><span class="badge badge-ship">Free Ship</span><?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php $thumbSrc = $p['thumb'] ?: $p['image']; ?>
                            <?php if($thumbSrc): ?>
                                <img src="<?php echo $thumbSrc; ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" class="w-full h-full object-contain p-4">
                            <?php else: ?>
                                <div class="text-gray-300 text-center">
                                    <i class="bi bi-image text-4xl"></i>
                                    <p class="text-xs mt-2">No Image</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3 line-clamp-2 leading-tight">
                                <?php echo htmlspecialchars($p['title']); ?>
                            </h3>
                            
                            <!-- Key Specifications -->
                            <?php if (!empty($specs)): ?>
                            <div class="mb-3 pb-3 border-b border-gray-100 space-y-1">
                                <?php
                                // Define key specs to show based on common fields
                                $keySpecs = ['Processor', 'RAM', 'Storage', 'Display', 'Battery', 'Graphics', 'Camera', 'OS', 'Warranty'];
                                $displayCount = 0;
                                foreach ($keySpecs as $key):
                                    if (isset($specs[$key]) && $displayCount < 3):
                                        $displayCount++;
                                ?>
                                    <div class="flex items-start gap-2 text-xs">
                                        <i class="bi bi-check2 text-green-600 mt-0.5 flex-shrink-0"></i>
                                        <span class="text-gray-600 line-clamp-1">
                                            <span class="font-semibold text-gray-700"><?php echo $key; ?>:</span> 
                                            <?php echo htmlspecialchars($specs[$key]); ?>
                                        </span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($p['brand_name']) && empty($specs)): ?>
                                <p class="text-xs text-gray-500 mb-3 pb-3 border-b border-gray-100">
                                    <i class="bi bi-award"></i> <?php echo htmlspecialchars($p['brand_name']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <span class="text-lg font-bold text-purple-600 block">
                                        ৳<?php echo number_format($minEffective); ?>
                                    </span>
                                    <?php if($minEffective != $maxEffective): ?>
                                        <span class="text-xs text-gray-500">- ৳<?php echo number_format($maxEffective); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if($p['min_regular'] && $p['min_regular'] > $minEffective): ?>
                                        <div class="text-xs text-gray-400 line-through">৳<?php echo number_format($p['min_regular']); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-purple-600 group-hover:scale-110 transition-transform">
                                    <i class="bi bi-arrow-right-circle-fill text-2xl"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-20 bg-white rounded-2xl shadow-sm border border-gray-100">
                        <i class="bi bi-inbox text-gray-300 text-6xl mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">No Products Found</h3>
                        <p class="text-gray-600 mb-4">Try adjusting your filters or browse other categories</p>
                        <a href="?<?php echo $isOffers ? 'offers=1' : ($catId ? 'id='.$catId : 'slug='.$slug); ?>" 
                           class="inline-block bg-purple-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-purple-700 transition-all">
                            <i class="bi bi-arrow-clockwise"></i> Reset Filters
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- noUiSlider JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.1/nouislider.min.js"></script>

    <script>
        // Initialize Price Range Slider
        const priceSlider = document.getElementById('priceRangeSlider');
        const minPriceInput = document.getElementById('minPriceInput');
        const maxPriceInput = document.getElementById('maxPriceInput');
        const hiddenMinInput = document.getElementById('hiddenPriceMin');
        const hiddenMaxInput = document.getElementById('hiddenPriceMax');
        const filterForm = document.getElementById('filterForm');

        if (priceSlider) {
            const actualMin = <?php echo $actualMinPrice; ?>;
            const actualMax = <?php echo $actualMaxPrice; ?>;
            const currentMin = <?php echo $priceMin; ?>;
            const currentMax = <?php echo $priceMax; ?>;

            noUiSlider.create(priceSlider, {
                start: [currentMin, currentMax],
                connect: true,
                range: {
                    'min': actualMin,
                    'max': actualMax
                },
                step: 100,
                tooltips: false,
                format: {
                    to: function (value) {
                        return Math.round(value);
                    },
                    from: function (value) {
                        return Number(value);
                    }
                }
            });

            // Update inputs and hidden fields when slider changes
            priceSlider.noUiSlider.on('update', function (values, handle) {
                const minVal = values[0];
                const maxVal = values[1];
                
                minPriceInput.value = minVal;
                maxPriceInput.value = maxVal;
                hiddenMinInput.value = minVal;
                hiddenMaxInput.value = maxVal;
            });

            // Submit form when slider is released
            priceSlider.noUiSlider.on('change', function (values, handle) {
                filterForm.submit();
            });
        }

        // Toggle filter sections
        document.querySelectorAll('.filter-header').forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling;
                if (content && content.classList.contains('filter-content')) {
                    this.classList.toggle('collapsed');
                    if (content.style.display === 'none') {
                        content.style.display = 'block';
                    } else {
                        content.style.display = 'none';
                    }
                }
            });
        });

        function toggleMobileFilters() {
            const drawer = document.getElementById('filterDrawer');
            const overlay = document.getElementById('filterOverlay');
            drawer.classList.toggle('open');
            overlay.classList.toggle('show');
            
            // Copy desktop filter content to mobile on first open
            if (drawer.classList.contains('open')) {
                const desktopFilters = document.querySelector('aside form').cloneNode(true);
                document.getElementById('mobileFilterContent').innerHTML = '';
                document.getElementById('mobileFilterContent').appendChild(desktopFilters);
                
                // Re-initialize slider in mobile drawer
                const mobileSlider = document.getElementById('mobileFilterContent').querySelector('#priceRangeSlider');
                if (mobileSlider && !mobileSlider.noUiSlider) {
                    const actualMin = <?php echo $actualMinPrice; ?>;
                    const actualMax = <?php echo $actualMaxPrice; ?>;
                    const currentMin = <?php echo $priceMin; ?>;
                    const currentMax = <?php echo $priceMax; ?>;

                    noUiSlider.create(mobileSlider, {
                        start: [currentMin, currentMax],
                        connect: true,
                        range: {
                            'min': actualMin,
                            'max': actualMax
                        },
                        step: 100
                    });

                    mobileSlider.noUiSlider.on('change', function () {
                        document.getElementById('mobileFilterContent').querySelector('form').submit();
                    });
                }
            }
        }
    </script>
</body>
</html>
