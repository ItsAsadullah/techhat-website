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
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: products.php");
    exit;
}

// Fetch Categories for Filter
$categories_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $categories_stmt->fetchAll();

// Fetch Products with Category Name
$sql = "SELECT p.*, c.name as category_name, 
        (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count,
        (SELECT SUM(stock_quantity) FROM product_variants WHERE product_id = p.id) as total_stock
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products - TechHat Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            margin: 0;
        }
        .content { padding: 30px; }
        
        /* Header Section */
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Search and Filter Bar */
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 45px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 18px;
        }
        
        .clear-search {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            padding: 5px;
            font-size: 18px;
            display: none;
        }
        
        .clear-search:hover {
            color: #e74c3c;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .filter-group select {
            padding: 10px 35px 10px 12px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%237f8c8d' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }
        
        .results-count {
            color: #7f8c8d;
            font-size: 14px;
            margin-left: auto;
            font-weight: 600;
        }
        
        /* Button Styles */
        .btn { 
            padding: 12px 24px;
            text-decoration: none;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        .btn-danger { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        .btn-danger:hover {
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            transform: scaleY(0);
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .product-card:hover::before {
            transform: scaleY(1);
        }
        
        .product-id {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .product-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
            padding-right: 60px;
        }
        
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .info-row i {
            width: 20px;
            color: #3498db;
        }
        
        .info-label {
            font-weight: 600;
            color: #34495e;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .stock-high {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .stock-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid #ecf0f1;
        }
        
        .product-actions .btn {
            flex: 1;
            justify-content: center;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .empty-state i {
            font-size: 80px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 24px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .no-results i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 15px;
        }
        
        .no-results h3 {
            font-size: 20px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <div class="page-header">
                <h1>
                    <i class="bi bi-box-seam"></i>
                    Products
                </h1>
                <a href="product_add.php" class="btn">
                    <i class="bi bi-plus-circle"></i>
                    Add New Product
                </a>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No Products Yet</h3>
                    <p>Start adding products to your inventory</p>
                </div>
            <?php else: ?>
                <!-- Search and Filter Bar -->
                <div class="filter-bar">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" placeholder="Search products by name..." autocomplete="off">
                        <button class="clear-search" id="clearSearch">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="bi bi-funnel"></i> Category:</label>
                        <select id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="bi bi-stack"></i> Stock:</label>
                        <select id="stockFilter">
                            <option value="">All Stock</option>
                            <option value="high">High Stock (>50)</option>
                            <option value="medium">Medium Stock (11-50)</option>
                            <option value="low">Low Stock (≤10)</option>
                        </select>
                    </div>
                    
                    <span class="results-count" id="resultsCount"><?php echo count($products); ?> products</span>
                </div>

                <div class="no-results" id="noResults" style="display: none;">
                    <i class="bi bi-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search or filters</p>
                </div>

                <div class="products-grid" id="productsGrid">
                    <?php foreach ($products as $p): 
                        $stock = (int)($p['total_stock'] ?? 0);
                        $stockClass = $stock > 50 ? 'stock-high' : ($stock > 10 ? 'stock-medium' : 'stock-low');
                        $stockLevel = $stock > 50 ? 'high' : ($stock > 10 ? 'medium' : 'low');
                    ?>
                        <div class="product-card" 
                             data-title="<?php echo htmlspecialchars(strtolower($p['title'])); ?>"
                             data-category="<?php echo htmlspecialchars($p['category_name'] ?? ''); ?>"
                             data-stock="<?php echo $stockLevel; ?>">
                            <span class="product-id">#<?php echo $p['id']; ?></span>
                            
                            <h3 class="product-title"><?php echo htmlspecialchars($p['title']); ?></h3>
                            
                            <div class="product-info">
                                <div class="info-row">
                                    <i class="bi bi-tag"></i>
                                    <span class="info-label">Category:</span>
                                    <span><?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <i class="bi bi-boxes"></i>
                                    <span class="info-label">Variants:</span>
                                    <span><?php echo $p['variant_count']; ?> variant(s)</span>
                                </div>
                                
                                <div class="info-row">
                                    <i class="bi bi-stack"></i>
                                    <span class="info-label">Stock:</span>
                                    <span class="stock-badge <?php echo $stockClass; ?>">
                                        <?php echo $stock; ?> units
                                    </span>
                                </div>
                            </div>
                            
                            <div class="product-actions">
                                <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm">
                                    <i class="bi bi-pencil-square"></i>
                                    Edit
                                </a>
                                <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('Are you sure?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                    <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" style="width: 100%;">
                                        <i class="bi bi-trash"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search and Filter Functionality
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const stockFilter = document.getElementById('stockFilter');
        const clearSearch = document.getElementById('clearSearch');
        const productsGrid = document.getElementById('productsGrid');
        const resultsCount = document.getElementById('resultsCount');
        const noResults = document.getElementById('noResults');
        
        function filterProducts() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategory = categoryFilter.value;
            const selectedStock = stockFilter.value;
            
            const productCards = productsGrid.querySelectorAll('.product-card');
            let visibleCount = 0;
            
            productCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const category = card.getAttribute('data-category');
                const stock = card.getAttribute('data-stock');
                
                const matchesSearch = !searchTerm || title.includes(searchTerm);
                const matchesCategory = !selectedCategory || category === selectedCategory;
                const matchesStock = !selectedStock || stock === selectedStock;
                
                if (matchesSearch && matchesCategory && matchesStock) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update results count
            resultsCount.textContent = visibleCount + ' product' + (visibleCount !== 1 ? 's' : '');
            
            // Show/hide no results message
            if (visibleCount === 0) {
                productsGrid.style.display = 'none';
                noResults.style.display = 'block';
            } else {
                productsGrid.style.display = 'grid';
                noResults.style.display = 'none';
            }
            
            // Show/hide clear button
            clearSearch.style.display = searchTerm ? 'block' : 'none';
        }
        
        // Event listeners
        searchInput.addEventListener('input', filterProducts);
        categoryFilter.addEventListener('change', filterProducts);
        stockFilter.addEventListener('change', filterProducts);
        
        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            filterProducts();
            searchInput.focus();
        });
        
        // Add animation to filtered cards
        searchInput.addEventListener('keyup', () => {
            const visibleCards = productsGrid.querySelectorAll('.product-card[style="display: block;"]');
            visibleCards.forEach((card, index) => {
                card.style.animation = 'none';
                setTimeout(() => {
                    card.style.animation = `fadeIn 0.3s ease ${index * 0.05}s both`;
                }, 10);
            });
        });
        
        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>