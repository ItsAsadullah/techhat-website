<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/core/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Parameters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 500000;
$selected_brands = isset($_GET['brands']) ? $_GET['brands'] : [];
$selected_cats = isset($_GET['categories']) ? $_GET['categories'] : [];
$in_stock_only = isset($_GET['in_stock']) ? (int)$_GET['in_stock'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Redirect if no search
if (empty($search)) {
    header('Location: index.php');
    exit;
}

// Build WHERE clause
$params = [];
$searchTerm = '%' . $search . '%';
$whereConditions = ["(p.title LIKE ? OR p.description LIKE ?)", "p.is_active = 1"];
$params[] = $searchTerm;
$params[] = $searchTerm;

// Brand filter
if (!empty($selected_brands)) {
    $placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
    $whereConditions[] = "p.brand_id IN ($placeholders)";
    $params = array_merge($params, $selected_brands);
}

// Category filter
if (!empty($selected_cats)) {
    $placeholders = implode(',', array_fill(0, count($selected_cats), '?'));
    $whereConditions[] = "p.category_id IN ($placeholders)";
    $params = array_merge($params, $selected_cats);
}

// Price filter
$whereConditions[] = "LEAST(COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 999999)) >= ?";
$params[] = $min_price;
$whereConditions[] = "GREATEST(COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 0), COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 0)) <= ?";
$params[] = $max_price;

// Stock filter
if ($in_stock_only) {
    $whereConditions[] = "(COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) > 0";
}

$sqlWhere = "WHERE " . implode(' AND ', $whereConditions);

// Sorting
$orderBy = "ORDER BY p.id DESC";
switch ($sort) {
    case 'price_low': $orderBy = "ORDER BY min_effective ASC"; break;
    case 'price_high': $orderBy = "ORDER BY min_effective DESC"; break;
    case 'oldest': $orderBy = "ORDER BY p.id ASC"; break;
    case 'name_az': $orderBy = "ORDER BY p.title ASC"; break;
    case 'name_za': $orderBy = "ORDER BY p.title DESC"; break;
    default: $orderBy = "ORDER BY p.id DESC";
}

// Count total
$sqlCount = "SELECT COUNT(DISTINCT p.id) FROM products p $sqlWhere";
try {
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_products = $stmtCount->fetchColumn();
} catch (PDOException $e) {
    die("<div style='background:red;color:white;padding:20px;margin:20px;'>SQL Error in Count: " . $e->getMessage() . "<br>SQL: " . htmlspecialchars($sqlCount) . "</div>");
}
$total_pages = ceil($total_products / $per_page);

// Main query
$sql = "SELECT p.*, 
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_thumbnail = 1 LIMIT 1) as thumb,
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
    LEAST(COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 999999)) as min_effective,
    GREATEST(COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variations WHERE product_id = p.id), 0), COALESCE((SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants_legacy WHERE product_id = p.id), 0)) as max_effective,
    LEAST(COALESCE((SELECT MIN(price) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(price) FROM product_variants_legacy WHERE product_id = p.id), 999999)) as min_regular,
    (COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) as total_stock,
    b.name as brand_name,
    c.name as category_name
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    $sqlWhere
    $orderBy
    LIMIT $per_page OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='background:red;color:white;padding:20px;margin:20px;'>SQL Error: " . $e->getMessage() . "<br>SQL: " . htmlspecialchars($sql) . "</div>");
}

// Sidebar filters
$metaParams = ["%$search%", "%$search%"];
$metaWhere = "WHERE (p.title LIKE ? OR p.description LIKE ?) AND p.is_active = 1";

try {
    $stmtBrands = $pdo->prepare("SELECT DISTINCT b.id, b.name FROM products p JOIN brands b ON p.brand_id = b.id $metaWhere ORDER BY b.name");
    $stmtBrands->execute($metaParams);
    $filterBrands = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div style='background:orange;color:white;padding:20px;'>Brand Filter Error: " . $e->getMessage() . "</div>";
    $filterBrands = [];
}

try {
    $stmtCats = $pdo->prepare("SELECT DISTINCT c.id, c.name, COUNT(p.id) as count FROM products p JOIN categories c ON p.category_id = c.id $metaWhere GROUP BY c.id ORDER BY c.name");
    $stmtCats->execute($metaParams);
    $filterCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div style='background:orange;color:white;padding:20px;'>Category Filter Error: " . $e->getMessage() . "</div>";
    $filterCats = [];
}

echo "<!-- Debug: Products found: " . count($products) . ", Brands: " . count($filterBrands) . ", Categories: " . count($filterCats) . " -->";

$metaTitle = 'Search: ' . htmlspecialchars($search) . ' | TechHat';

// Get header data manually
if (!isset($pdo)) { require_once 'core/db.php'; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
    $stmtCatHeader = $pdo->query("SELECT * FROM categories LIMIT 10");
    $categoriesHeader = $stmtCatHeader->fetchAll();
} catch (Exception $e) { $categoriesHeader = []; }

try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
    $settingsData = $settingsStmt->fetchAll();
    $settings = [];
    foreach($settingsData as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) { $settings = []; }

$cartCount = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? array_sum($_SESSION['cart']) : 0;
$wishlistCount = 0;
if(isset($_SESSION['user_id'])) {
    try {
        $wishlistStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $wishlistStmt->execute([$_SESSION['user_id']]);
        $wishlistCount = $wishlistStmt->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $metaTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .accent-primary { background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .filter-checkbox:checked + label { background: #fce7f3; border-color: #ec4899; }
        .price-input:focus { border-color: #ec4899; box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1); }
        .product-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .badge-flash { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .badge-discount { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .sidebar-filter { max-height: calc(100vh - 180px); overflow-y: auto; }
        .sidebar-filter::-webkit-scrollbar { width: 6px; }
        .sidebar-filter::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .sidebar-filter::-webkit-scrollbar-thumb { background: #ec4899; border-radius: 10px; }
        .mobile-drawer { transform: translateX(-100%); transition: transform 0.3s ease; }
        .mobile-drawer.open { transform: translateX(0); }
    </style>
</head>
<body class="bg-gray-50">

<!-- Top Bar -->
<div class="bg-gradient-to-r from-pink-700 to-pink-600 text-white py-2 text-sm">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <span><i class="bi bi-house-fill"></i> Welcome to TechHat</span>
            <span><i class="bi bi-telephone-fill"></i> 09678-300400</span>
        </div>
        <div class="flex items-center gap-3">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="logout.php" class="hover:underline"><i class="bi bi-box-arrow-right"></i> Logout</a>
            <?php else: ?>
                <a href="login.php" class="hover:underline"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                <span>|</span>
                <a href="register.php" class="hover:underline"><i class="bi bi-person-plus-fill"></i> Register</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="bg-white shadow-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="flex items-center justify-between gap-4">
            <a href="index.php" class="flex items-center gap-2">
                <img src="assets/images/logo.png" alt="TechHat" class="h-12" onerror="this.style.display='none'">
                <span class="text-2xl font-bold text-pink-600">TechHat</span>
            </a>
            
            <form action="search.php" method="GET" class="flex-1 max-w-2xl">
                <div class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search for products..." 
                           class="w-full px-4 py-3 pr-12 border-2 border-gray-200 rounded-full focus:outline-none focus:border-pink-500">
                    <button type="submit" class="absolute right-1 top-1 bottom-1 px-6 bg-pink-600 text-white rounded-full hover:bg-pink-700">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            
            <div class="flex items-center gap-4">
                <a href="wishlist.php" class="relative flex items-center gap-2 hover:text-pink-600">
                    <i class="bi bi-heart text-2xl"></i>
                    <?php if($wishlistCount > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-pink-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">
                            <?php echo $wishlistCount; ?>
                        </span>
                    <?php endif; ?>
                    <span class="hidden lg:inline">Wishlist</span>
                </a>
                <a href="cart.php" class="relative flex items-center gap-2 bg-pink-600 text-white px-4 py-2 rounded-full hover:bg-pink-700">
                    <i class="bi bi-cart3 text-xl"></i>
                    <?php if($cartCount > 0): ?>
                        <span class="bg-white text-pink-600 text-xs px-2 py-0.5 rounded-full font-bold">
                            <?php echo $cartCount; ?>
                        </span>
                    <?php endif; ?>
                    <span class="hidden lg:inline">Cart</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center gap-2 py-2 overflow-x-auto scrollbar-hide">
                <a href="categories.php" class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700 whitespace-nowrap">
                    <i class="bi bi-grid-3x3-gap-fill"></i> All Categories
                </a>
                <?php foreach($categoriesHeader as $cat): ?>
                    <a href="category.php?id=<?php echo $cat['id']; ?>" class="px-4 py-2 hover:text-pink-600 whitespace-nowrap">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
                <a href="category.php?offers=1" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 whitespace-nowrap">
                    <i class="bi bi-lightning-fill"></i> Special Offers
                </a>
            </div>
        </div>
    </nav>
</header>

<div class="bg-gradient-to-r from-pink-50 to-purple-50 py-4">
    <div class="max-w-7xl mx-auto px-4">
        <nav class="flex items-center gap-2 text-sm text-gray-600">
            <a href="index.php" class="hover:text-pink-600 transition"><i class="bi bi-house-door-fill"></i> Home</a>
            <i class="bi bi-chevron-right text-xs"></i>
            <span class="text-gray-900 font-medium">Search Results</span>
        </nav>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-6 lg:py-8">
    
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 flex items-center gap-3">
                <i class="bi bi-search text-pink-600"></i>
                Search: "<span class="text-pink-600"><?php echo htmlspecialchars($search); ?></span>"
            </h1>
            <p class="text-gray-600 mt-1"><?php echo number_format($total_products); ?> products found</p>
        </div>
        
        <div class="flex items-center gap-3">
            <!-- Mobile Filter Toggle -->
            <button onclick="toggleMobileFilter()" class="lg:hidden flex items-center gap-2 px-4 py-2.5 bg-white border-2 border-pink-600 text-pink-600 rounded-xl font-semibold hover:bg-pink-50 transition shadow-sm">
                <i class="bi bi-funnel-fill"></i> Filters
            </button>

            <!-- Sort Dropdown -->
            <select onchange="updateSort(this.value)" class="appearance-none bg-white border-2 border-gray-200 text-gray-700 py-2.5 pl-4 pr-10 rounded-xl font-medium focus:outline-none focus:border-pink-500 cursor-pointer shadow-sm hover:border-gray-300 transition">
                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="name_az" <?php echo $sort == 'name_az' ? 'selected' : ''; ?>>Name: A-Z</option>
                <option value="name_za" <?php echo $sort == 'name_za' ? 'selected' : ''; ?>>Name: Z-A</option>
                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest</option>
            </select>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
        
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:block w-72 flex-shrink-0">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 sticky top-24 sidebar-filter">
                <form action="search.php" method="GET" class="space-y-6">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                    <!-- Price Range -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="bi bi-currency-dollar text-pink-600"></i>
                            Price Range
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>Min: ৳<?php echo number_format($min_price); ?></span>
                                <span>Max: ৳<?php echo number_format($max_price); ?></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" name="min_price" value="<?php echo $min_price; ?>" 
                                       placeholder="Min" min="0" step="100"
                                       class="price-input w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:outline-none transition">
                                <input type="number" name="max_price" value="<?php echo $max_price; ?>" 
                                       placeholder="Max" min="0" step="100"
                                       class="price-input w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:outline-none transition">
                            </div>
                            <button type="submit" class="w-full bg-gradient-to-r from-pink-600 to-purple-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:from-pink-700 hover:to-purple-700 transition shadow-md">
                                Apply
                            </button>
                        </div>
                    </div>

                    <!-- Stock -->
                    <div class="border-b border-gray-200 pb-6">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" name="in_stock" value="1" 
                                   onchange="this.form.submit()"
                                   <?php echo $in_stock_only ? 'checked' : ''; ?>
                                   class="w-5 h-5 text-pink-600 border-gray-300 rounded focus:ring-pink-500">
                            <span class="text-sm text-gray-700 group-hover:text-pink-600 transition font-medium">
                                In Stock Only
                            </span>
                        </label>
                    </div>

                    <!-- Categories -->
                    <?php if(!empty($filterCats)): ?>
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="bi bi-grid-3x3-gap-fill text-pink-600"></i>
                            Categories
                        </h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
                            <?php foreach($filterCats as $cat): ?>
                            <label class="flex items-center gap-3 cursor-pointer group hover:bg-pink-50 p-2 rounded-lg transition">
                                <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" 
                                       onchange="this.form.submit()"
                                       <?php echo in_array($cat['id'], $selected_cats) ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-pink-600 border-gray-300 rounded focus:ring-pink-500">
                                <span class="text-sm text-gray-700 flex-1 font-medium">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </span>
                                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                    <?php echo $cat['count']; ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Brands -->
                    <?php if(!empty($filterBrands)): ?>
                    <div class="pb-2">
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="bi bi-award-fill text-pink-600"></i>
                            Brands
                        </h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
                            <?php foreach($filterBrands as $brand): ?>
                            <label class="flex items-center gap-3 cursor-pointer group hover:bg-pink-50 p-2 rounded-lg transition">
                                <input type="checkbox" name="brands[]" value="<?php echo $brand['id']; ?>" 
                                       onchange="this.form.submit()"
                                       <?php echo in_array($brand['id'], $selected_brands) ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-pink-600 border-gray-300 rounded focus:ring-pink-500">
                                <span class="text-sm text-gray-700 font-medium">
                                    <?php echo htmlspecialchars($brand['name']); ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 min-w-0">
            <?php if (!empty($products)): ?>
                <!-- Product Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-4 lg:gap-6">
                    <?php foreach($products as $p): 
                        $minEffective = (float) $p['min_effective'];
                        $maxEffective = (float) $p['max_effective'];
                        $minRegular = (float) $p['min_regular'];
                        
                        $savePercent = 0;
                        if($minRegular > $minEffective && $minRegular > 0) {
                            $savePercent = round((($minRegular - $minEffective) / $minRegular) * 100);
                        }
                        
                        $thumbSrc = $p['thumb'] ?: ($p['image'] ?: 'assets/images/no-image.png');
                        $isOutOfStock = ($p['total_stock'] ?? 0) <= 0;
                    ?>
                    
                    <div class="product-card bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden flex flex-col group">
                        <!-- Image Section -->
                        <a href="product.php?slug=<?php echo $p['slug']; ?>" class="relative block bg-gray-50 aspect-square overflow-hidden">
                            <img src="<?php echo htmlspecialchars($thumbSrc); ?>" 
                                 alt="<?php echo htmlspecialchars($p['title']); ?>" 
                                 class="w-full h-full object-contain p-4 group-hover:scale-110 transition-transform duration-500">
                            
                            <!-- Badges -->
                            <div class="absolute top-3 left-3 flex flex-col gap-1.5 z-10">
                                <?php if($savePercent > 0): ?>
                                    <span class="badge-discount text-white text-xs font-bold px-2.5 py-1 rounded-lg shadow-lg">
                                        -<?php echo $savePercent; ?>%
                                    </span>
                                <?php endif; ?>
                                <?php if($isOutOfStock): ?>
                                    <span class="bg-gray-800 text-white text-xs font-bold px-2.5 py-1 rounded-lg shadow-lg">
                                        Out of Stock
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Wishlist Button -->
                            <button class="absolute top-3 right-3 w-9 h-9 bg-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-lg hover:bg-pink-50 hover:text-pink-600">
                                <i class="bi bi-heart text-lg"></i>
                            </button>
                        </a>

                        <!-- Product Info -->
                        <div class="p-4 flex flex-col flex-1">
                            <?php if(!empty($p['brand_name'])): ?>
                                <div class="text-xs text-gray-500 mb-1 font-medium uppercase tracking-wide">
                                    <?php echo htmlspecialchars($p['brand_name']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <a href="product.php?slug=<?php echo $p['slug']; ?>" class="text-sm font-semibold text-gray-800 hover:text-pink-600 line-clamp-2 mb-2 transition" title="<?php echo htmlspecialchars($p['title']); ?>">
                                <?php echo htmlspecialchars($p['title']); ?>
                            </a>

                            <div class="mt-auto pt-2">
                                <div class="flex items-baseline gap-2 mb-2">
                                    <span class="text-lg font-bold text-pink-600">
                                        ৳<?php echo number_format($minEffective); ?>
                                    </span>
                                    <?php if($minRegular > $minEffective): ?>
                                        <span class="text-xs text-gray-400 line-through">
                                            ৳<?php echo number_format($minRegular); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if(!$isOutOfStock): ?>
                                    <button class="w-full bg-gradient-to-r from-pink-600 to-purple-600 text-white py-2 rounded-lg font-semibold text-sm hover:from-pink-700 hover:to-purple-700 transition shadow-md hover:shadow-lg">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-300 text-gray-500 py-2 rounded-lg font-semibold text-sm cursor-not-allowed">
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="flex items-center gap-1">
                        <?php if($page > 1): ?>
                            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>&sort=<?php echo $sort; ?><?php echo !empty($selected_brands) ? '&brands[]=' . implode('&brands[]=', $selected_brands) : ''; ?><?php echo !empty($selected_cats) ? '&categories[]=' . implode('&categories[]=', $selected_cats) : ''; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&in_stock=<?php echo $in_stock_only; ?>" 
                               class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-pink-50 hover:border-pink-500 transition">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for($i = $start; $i <= $end; $i++): 
                        ?>
                            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?><?php echo !empty($selected_brands) ? '&brands[]=' . implode('&brands[]=', $selected_brands) : ''; ?><?php echo !empty($selected_cats) ? '&categories[]=' . implode('&categories[]=', $selected_cats) : ''; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&in_stock=<?php echo $in_stock_only; ?>" 
                               class="px-4 py-2 <?php echo $i == $page ? 'bg-pink-600 text-white' : 'bg-white text-gray-700 hover:bg-pink-50'; ?> border border-gray-300 rounded-lg transition">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if($page < $total_pages): ?>
                            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>&sort=<?php echo $sort; ?><?php echo !empty($selected_brands) ? '&brands[]=' . implode('&brands[]=', $selected_brands) : ''; ?><?php echo !empty($selected_cats) ? '&categories[]=' . implode('&categories[]=', $selected_cats) : ''; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&in_stock=<?php echo $in_stock_only; ?>" 
                               class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-pink-50 hover:border-pink-500 transition">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-2xl p-12 text-center shadow-lg border border-gray-100">
                    <div class="w-24 h-24 bg-gradient-to-br from-pink-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="bi bi-search text-4xl text-pink-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-3">No Products Found</h2>
                    <p class="text-gray-600 max-w-md mx-auto mb-6">
                        We couldn't find any products matching "<span class="font-semibold"><?php echo htmlspecialchars($search); ?></span>". 
                        Try adjusting your filters or search for something else.
                    </p>
                    <div class="flex gap-3 justify-center">
                        <a href="?search=<?php echo urlencode($search); ?>" class="inline-flex items-center gap-2 bg-white border-2 border-pink-600 text-pink-600 px-6 py-2.5 rounded-full font-semibold hover:bg-pink-50 transition">
                            <i class="bi bi-arrow-clockwise"></i> Clear Filters
                        </a>
                        <a href="index.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-pink-600 to-purple-600 text-white px-6 py-2.5 rounded-full font-semibold hover:from-pink-700 hover:to-purple-700 transition shadow-lg">
                            <i class="bi bi-house-door-fill"></i> Back to Home
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Mobile Filter Drawer -->
<div id="mobileFilterOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleMobileFilter()"></div>
<div id="mobileFilterDrawer" class="mobile-drawer fixed inset-y-0 left-0 w-80 max-w-[85vw] bg-white z-50 shadow-2xl overflow-y-auto">
    <div class="sticky top-0 bg-gradient-to-r from-pink-600 to-purple-600 text-white p-4 flex items-center justify-between z-10">
        <h3 class="font-bold text-lg flex items-center gap-2">
            <i class="bi bi-funnel-fill"></i> Filters
        </h3>
        <button onclick="toggleMobileFilter()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition">
            <i class="bi bi-x-lg text-xl"></i>
        </button>
    </div>
    <div class="p-4">
        <form action="search.php" method="GET" class="space-y-6">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

            <!-- Price Range -->
            <div class="border-b border-gray-200 pb-6">
                <h3 class="font-bold text-gray-800 mb-4">Price Range</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm text-gray-600">
                        <span>Min: ৳<?php echo number_format($min_price); ?></span>
                        <span>Max: ৳<?php echo number_format($max_price); ?></span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" name="min_price" value="<?php echo $min_price; ?>" 
                               placeholder="Min" min="0" step="100"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:outline-none">
                        <input type="number" name="max_price" value="<?php echo $max_price; ?>" 
                               placeholder="Max" min="0" step="100"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:outline-none">
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-pink-600 to-purple-600 text-white py-2.5 rounded-lg text-sm font-semibold">
                        Apply
                    </button>
                </div>
            </div>

            <!-- Stock -->
            <div class="border-b border-gray-200 pb-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="in_stock" value="1" 
                           onchange="this.form.submit()"
                           <?php echo $in_stock_only ? 'checked' : ''; ?>
                           class="w-5 h-5 text-pink-600 border-gray-300 rounded">
                    <span class="text-sm text-gray-700 font-medium">In Stock Only</span>
                </label>
            </div>

            <!-- Categories -->
            <?php if(!empty($filterCats)): ?>
            <div class="border-b border-gray-200 pb-6">
                <h3 class="font-bold text-gray-800 mb-4">Categories</h3>
                <div class="space-y-2">
                    <?php foreach($filterCats as $cat): ?>
                    <label class="flex items-center gap-3 cursor-pointer hover:bg-pink-50 p-2 rounded-lg">
                        <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" 
                               onchange="this.form.submit()"
                               <?php echo in_array($cat['id'], $selected_cats) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-pink-600 border-gray-300 rounded">
                        <span class="text-sm text-gray-700 flex-1">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </span>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                            <?php echo $cat['count']; ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Brands -->
            <?php if(!empty($filterBrands)): ?>
            <div class="pb-2">
                <h3 class="font-bold text-gray-800 mb-4">Brands</h3>
                <div class="space-y-2">
                    <?php foreach($filterBrands as $brand): ?>
                    <label class="flex items-center gap-3 cursor-pointer hover:bg-pink-50 p-2 rounded-lg">
                        <input type="checkbox" name="brands[]" value="<?php echo $brand['id']; ?>" 
                               onchange="this.form.submit()"
                               <?php echo in_array($brand['id'], $selected_brands) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-pink-600 border-gray-300 rounded">
                        <span class="text-sm text-gray-700">
                            <?php echo htmlspecialchars($brand['name']); ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
function updateSort(value) {
    const url = new URL(window.location);
    url.searchParams.set('sort', value);
    window.location = url.toString();
}

function toggleMobileFilter() {
    const drawer = document.getElementById('mobileFilterDrawer');
    const overlay = document.getElementById('mobileFilterOverlay');
    
    if(drawer.classList.contains('open')) {
        drawer.classList.remove('open');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    } else {
        drawer.classList.add('open');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
