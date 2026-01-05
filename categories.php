<?php
require_once 'core/db.php';
require_once 'core/auth.php';

// Fetch all parent categories with product counts
$stmt = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count
    FROM categories c 
    WHERE parent_id IS NULL OR parent_id = 0 
    ORDER BY name ASC
");
$parentCategories = $stmt->fetchAll();

// Fetch subcategories for each parent with product counts
$categories = [];
foreach ($parentCategories as $parent) {
    $stmtSub = $pdo->prepare("
        SELECT c.*,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count
        FROM categories c
        WHERE parent_id = ? 
        ORDER BY name ASC
    ");
    $stmtSub->execute([$parent['id']]);
    $parent['subcategories'] = $stmtSub->fetchAll();
    $categories[] = $parent;
}

$metaTitle = 'All Categories | TechHat';
$metaDesc  = 'Browse all product categories at TechHat.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $metaTitle; ?></title>
    <meta name="description" content="<?php echo $metaDesc; ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }
        .category-card { 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .category-card:hover::before {
            left: 100%;
        }
        .category-card:hover { 
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .subcategory-link {
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 8px;
            margin: -8px -12px;
        }
        .subcategory-link:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            transform: translateX(5px);
        }
        .category-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 16px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .product-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-16" style="margin-top: 100px;">
        <!-- Header Section -->
        <div class="text-center mb-12 animate-fade-in">
            <h1 class="text-5xl font-extrabold text-white mb-4 drop-shadow-lg">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                Shop by Category
            </h1>
            <p class="text-white/90 text-lg">Explore our wide range of products</p>
        </div>

        <!-- Categories Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($categories as $index => $cat): ?>
                <div class="bg-white rounded-3xl p-8 shadow-xl border border-white/20 category-card animate-fade-in" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                    <div class="category-icon">
                        <i class="bi bi-<?php 
                            $icons = ['laptop', 'phone', 'watch', 'headphones', 'camera', 'controller', 'tv', 'keyboard', 'mouse'];
                            echo $icons[$index % count($icons)];
                        ?>"></i>
                    </div>
                    
                    <a href="category.php?id=<?php echo $cat['id']; ?>" class="text-2xl font-bold text-gray-800 hover:text-purple-600 transition-colors mb-3 block">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                    
                    <div class="flex items-center gap-2 mb-5">
                        <span class="product-badge">
                            <?php echo $cat['product_count']; ?> Products
                        </span>
                    </div>
                    
                    <?php if (!empty($cat['subcategories'])): ?>
                        <div class="space-y-1">
                            <?php foreach (array_slice($cat['subcategories'], 0, 5) as $sub): ?>
                                <a href="category.php?id=<?php echo $sub['id']; ?>" class="text-gray-600 hover:text-purple-600 flex items-center justify-between gap-2 transition-all subcategory-link">
                                    <span class="flex items-center gap-2">
                                        <i class="bi bi-chevron-right text-xs"></i>
                                        <?php echo htmlspecialchars($sub['name']); ?>
                                    </span>
                                    <span class="text-xs text-gray-400">(<?php echo $sub['product_count']; ?>)</span>
                                </a>
                            <?php endforeach; ?>
                            
                            <?php if (count($cat['subcategories']) > 5): ?>
                                <a href="category.php?id=<?php echo $cat['id']; ?>" class="text-purple-600 hover:text-purple-700 flex items-center gap-2 text-sm font-semibold mt-3">
                                    <i class="bi bi-plus-circle"></i>
                                    View all (<?php echo count($cat['subcategories']); ?>)
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm italic flex items-center gap-2">
                            <i class="bi bi-inbox"></i>
                            No subcategories
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($categories)): ?>
            <div class="text-center py-20">
                <i class="bi bi-inbox text-white/50 text-6xl mb-4"></i>
                <p class="text-white text-xl">No categories found</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
