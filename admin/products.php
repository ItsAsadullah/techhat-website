<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

// Handle Delete via POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $id = (int) $_POST['delete_id'];
    
    // Delete images first (optional cleanup)
    $stmtImg = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $stmtImg->execute([$id]);
    $images = $stmtImg->fetchAll();
    foreach ($images as $img) {
        $path = __DIR__ . '/../' . $img['image_path'];
        if (file_exists($path)) @unlink($path);
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: products.php?msg=deleted");
    exit;
}

// Fetch Categories for Filter
$categories_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $categories_stmt->fetchAll();

// Build Query with Filters
$where = ["1=1"];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%" . $_GET['search'] . "%";
    $params[] = "%" . $_GET['search'] . "%";
}

if (!empty($_GET['category'])) {
    $where[] = "p.category_id = ?";
    $params[] = $_GET['category'];
}

if (!empty($_GET['stock_status'])) {
    if ($_GET['stock_status'] === 'low') {
        // Check stock from BOTH product_variations (new) and product_variants_legacy (old)
        $where[] = "(COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) < 5";
    } elseif ($_GET['stock_status'] === 'out') {
        $where[] = "(COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) <= 0";
    } elseif ($_GET['stock_status'] === 'in') {
        $where[] = "(COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) > 0";
    }
}

$whereSQL = implode(" AND ", $where);

// Fetch Products
$sql = "SELECT p.*, c.name as category_name, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) as image_path,
        (COALESCE((SELECT COUNT(*) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT COUNT(*) FROM product_variants_legacy WHERE product_id = p.id), 0)) as variant_count,
        (COALESCE((SELECT SUM(stock_quantity) FROM product_variations WHERE product_id = p.id), 0) + COALESCE((SELECT SUM(stock_quantity) FROM product_variants_legacy WHERE product_id = p.id), 0)) as total_stock,
        (LEAST(COALESCE((SELECT MIN(price) FROM product_variations WHERE product_id = p.id), 999999), COALESCE((SELECT MIN(price) FROM product_variants_legacy WHERE product_id = p.id), 999999))) as min_price,
        (GREATEST(COALESCE((SELECT MAX(price) FROM product_variations WHERE product_id = p.id), 0), COALESCE((SELECT MAX(price) FROM product_variants_legacy WHERE product_id = p.id), 0))) as max_price
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $whereSQL
        ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Stats
$total_products = count($products); // This is just filtered count, but good enough for now
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products - TechHat Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Override/Fix for Sidebar conflict if any */
        .admin-content {
            margin-left: 260px; /* Adjust based on sidebar width */
            padding: 2rem;
            min-height: 100vh;
            background-color: #f3f4f6;
        }
        @media (max-width: 768px) {
            .admin-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <div class="admin-content">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Products</h1>
                <p class="text-gray-500 mt-1">Manage your product catalog</p>
            </div>
            <a href="product_add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-medium shadow-sm transition-all flex items-center gap-2">
                <i class="bi bi-plus-lg"></i> Add New Product
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-5 mb-6 border border-gray-100">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2 relative">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           placeholder="Search by name, description..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                </div>
                
                <div>
                    <select name="category" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex gap-2">
                    <select name="stock_status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        <option value="">All Stock Status</option>
                        <option value="in" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] === 'in') ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] === 'low') ? 'selected' : ''; ?>>Low Stock (< 5)</option>
                        <option value="out" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                    <button type="submit" class="bg-gray-800 text-white px-4 rounded-lg hover:bg-gray-700 transition-colors">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase text-gray-500 font-semibold tracking-wider">
                            <th class="px-6 py-4">Product</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4">Price</th>
                            <th class="px-6 py-4">Stock</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="bi bi-box-seam text-4xl mb-3 text-gray-300"></i>
                                        <p>No products found matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr class="hover:bg-gray-50 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="h-16 w-16 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                                <?php if ($p['image_path']): ?>
                                                    <img src="../<?php echo htmlspecialchars($p['image_path']); ?>" alt="" class="h-full w-full object-cover">
                                                <?php else: ?>
                                                    <div class="h-full w-full flex items-center justify-center text-gray-400">
                                                        <i class="bi bi-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                                                    <?php echo htmlspecialchars($p['title']); ?>
                                                </h3>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?php echo $p['variant_count']; ?> Variants
                                                    <?php if($p['is_flash_sale']): ?>
                                                        <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                            <i class="bi bi-lightning-fill mr-1"></i> Flash Sale
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($p['category_name']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                                <?php echo htmlspecialchars($p['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php 
                                                if ($p['min_price'] == $p['max_price']) {
                                                    echo '৳' . number_format($p['min_price']);
                                                } else {
                                                    echo '৳' . number_format($p['min_price']) . ' - ৳' . number_format($p['max_price']);
                                                }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                            $stock = (int)$p['total_stock'];
                                            $stockClass = 'bg-green-100 text-green-800';
                                            $stockText = 'In Stock';
                                            if ($stock <= 0) {
                                                $stockClass = 'bg-red-100 text-red-800';
                                                $stockText = 'Out of Stock';
                                            } elseif ($stock < 5) {
                                                $stockClass = 'bg-yellow-100 text-yellow-800';
                                                $stockText = 'Low Stock';
                                            }
                                        ?>
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $stockClass; ?>">
                                                <?php echo $stockText; ?>
                                            </span>
                                            <span class="text-xs text-gray-500"><?php echo $stock; ?> units</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($p['is_active']): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="../product.php?slug=<?php echo $p['slug']; ?>" target="_blank" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View on Site">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                                <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination (Placeholder for now) -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <span class="text-sm text-gray-500">Showing <?php echo count($products); ?> products</span>
                <!-- Add pagination links here if needed -->
            </div>
        </div>

    </div>

</body>
</html>
