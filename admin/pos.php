<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

// Fetch all products with variants for POS
$sql = "
    SELECT 
        p.id as product_id,
        p.title as product_name,
        p.slug,
        pv.id as variant_id,
        pv.name as variant_name,
        pv.sku,
        pv.price,
        pv.offer_price,
        pv.stock_quantity,
        c.name as category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM products p
    INNER JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 AND pv.status = 1
    ORDER BY p.title ASC, pv.name ASC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll();

// Fetch all active services
$services_sql = "SELECT * FROM services WHERE is_active = 1 ORDER BY category ASC, name ASC";
$services_stmt = $pdo->query($services_sql);
$services = $services_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - TechHat</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        
        .pos-header { 
            background: white; 
            padding: 20px 30px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .pos-header h1 { 
            font-size: 28px; 
            color: #2c3e50; 
            margin-bottom: 5px;
            font-weight: 700;
        }
        .pos-header .date-time { 
            font-size: 14px; 
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Tabs */
        .pos-tabs { 
            display: flex; 
            gap: 0.5rem; 
            margin-top: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .pos-tab { 
            padding: 14px 30px; 
            background: transparent; 
            border: none; 
            cursor: pointer; 
            font-size: 15px; 
            color: #7f8c8d; 
            border-bottom: 3px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            font-weight: 600;
        }
        .pos-tab:hover { 
            color: #2c3e50; 
            background: rgba(52, 152, 219, 0.05);
        }
        .pos-tab.active { 
            color: #3498db; 
            border-bottom: 3px solid #3498db;
        }
        .pos-tab i {
            margin-right: 0.5rem;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: flex; flex: 1; }
        
        /* Form styling for print tab */
        #printForm button:hover { background: #229954 !important; }
        
        .pos-content { flex: 1; display: flex; overflow: hidden; }
        
        .products-section { 
            flex: 2; 
            padding: 25px; 
            overflow-y: auto; 
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
        }
        .search-box { 
            margin-bottom: 20px;
            position: relative;
        }
        .search-box input { 
            width: 100%; 
            padding: 14px 20px 14px 50px; 
            border: 2px solid #e0e0e0; 
            border-radius: 12px; 
            font-size: 15px;
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
        }
        .search-box input:focus { 
            border-color: #3498db; 
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }
        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 18px;
        }
        
        .products-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
            gap: 18px; 
        }
        .product-card { 
            background: white; 
            border-radius: 12px; 
            padding: 16px; 
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .product-card:hover::before {
            opacity: 1;
        }
        .product-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            border-color: #3498db;
        }
        .product-card.out-of-stock { opacity: 0.5; cursor: not-allowed; }
        .product-card img { 
            width: 100%; 
            height: 140px; 
            object-fit: cover; 
            border-radius: 6px; 
            margin-bottom: 10px; 
            background: #f8f9fa;
        }
        .product-card h4 { font-size: 14px; color: #2c3e50; margin-bottom: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .product-card .variant { font-size: 12px; color: #7f8c8d; margin-bottom: 5px; }
        .product-card .price { font-size: 16px; font-weight: bold; color: #27ae60; margin-bottom: 5px; }
        .product-card .stock { font-size: 12px; color: #95a5a6; }
        .product-card .stock.low { color: #e74c3c; font-weight: bold; }
        
        .cart-section { 
            flex: 1; 
            background: white; 
            border-left: 1px solid #e0e0e0; 
            display: flex; 
            flex-direction: column; 
            min-width: 400px;
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.05);
        }
        .cart-header { 
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white; 
            padding: 20px 25px;
            position: relative;
            overflow: hidden;
        }
        .cart-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 3s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        .cart-header h3 {
            font-size: 20px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }
        .cart-header .items-count {
            font-size: 14px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        .cart-items { 
            flex: 1; 
            overflow-y: auto; 
            padding: 20px;
            background: linear-gradient(to bottom, #f8f9fa 0%, white 100%);
        }
        .cart-items::-webkit-scrollbar {
            width: 8px;
        }
        .cart-items::-webkit-scrollbar-thumb {
            background: rgba(52, 152, 219, 0.3);
            border-radius: 4px;
        }
        .cart-items::-webkit-scrollbar-thumb:hover {
            background: rgba(52, 152, 219, 0.5);
        }
        .cart-item { 
            background: white;
            padding: 15px; 
            margin-bottom: 12px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .cart-item:hover {
            border-color: #3498db;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
            transform: translateX(-4px);
        }
        }
        .cart-item-info { flex: 1; }
        .cart-item-info h5 { font-size: 14px; color: #2c3e50; margin-bottom: 3px; }
        .cart-item-info p { font-size: 12px; color: #7f8c8d; }
        .cart-item-qty { display: flex; align-items: center; gap: 8px; }
        .cart-item-qty button { 
            width: 28px; 
            height: 28px; 
            border: none; 
            background: #3498db; 
            color: white; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: bold;
        }
        .cart-item-qty button:hover { background: #2980b9; }
        .cart-item-qty input { 
            width: 50px; 
            text-align: center; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            padding: 5px;
            font-size: 14px;
        }
        .cart-item-price { font-weight: bold; color: #27ae60; font-size: 15px; min-width: 80px; text-align: right; }
        .cart-item-remove { 
            background: #e74c3c; 
            color: white; 
            border: none; 
            padding: 5px 10px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 12px;
        }
        .cart-item-remove:hover { background: #c0392b; }
        
        .empty-cart { text-align: center; padding: 40px 20px; color: #95a5a6; }
        .empty-cart-icon { font-size: 60px; margin-bottom: 15px; }
        
        .cart-summary { border-top: 2px solid #e0e0e0; padding: 15px 20px; background: #f8f9fa; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 15px; }
        .summary-row.total { font-size: 20px; font-weight: bold; color: #2c3e50; padding-top: 10px; border-top: 2px solid #bdc3c7; margin-top: 10px; }
        
        .customer-info { padding: 15px 20px; border-top: 1px solid #e0e0e0; background: white; }
        .customer-info h4 { margin-bottom: 10px; color: #2c3e50; font-size: 14px; }
        .customer-info input, .customer-info select { 
            width: 100%; 
            padding: 8px 10px; 
            margin-bottom: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 14px;
        }
        
        .checkout-actions { padding: 15px 20px; background: white; border-top: 1px solid #e0e0e0; }
        .btn { 
            width: 100%; 
            padding: 16px; 
            border: none; 
            border-radius: 12px; 
            font-size: 16px; 
            font-weight: 700; 
            cursor: pointer; 
            margin-bottom: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        .btn:hover::before {
            width: 300px;
            height: 300px;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        .btn-primary:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .btn-primary:disabled { 
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-secondary { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        .btn-secondary:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }
        
        .category-badge { display: inline-block; background: #3498db; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; margin-bottom: 5px; }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="content">
            <!-- Header -->
            <div class="pos-header">
                <h1><i class="bi bi-calculator"></i> Point of Sale</h1>
                <div class="date-time">
                    <i class="bi bi-calendar3"></i>
                    <span id="dateTime"></span>
                </div>
                
                <div class="pos-tabs">
                    <button class="pos-tab active" onclick="switchTab('sale')">
                        <i class="bi bi-cart-plus"></i> New Sale
                    </button>
                    <button class="pos-tab" onclick="switchTab('sales')">
                        <i class="bi bi-receipt"></i> Sales History
                    </button>
                    <button class="pos-tab" onclick="switchTab('ledger')">
                        <i class="bi bi-people"></i> Customer Ledger
                    </button>
                    <button class="pos-tab" onclick="switchTab('print')">
                        <i class="bi bi-printer"></i> Print Summary
                    </button>
                </div>
            </div>

            <!-- Tab 1: New Sale -->
            <div class="tab-content active" id="tab-sale">
            <div class="pos-content">
                <!-- Products Section -->
                <div class="products-section">
                    <!-- Product/Service Toggle -->
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <button class="item-type-btn active" id="productsBtn" onclick="switchItemType('products')" style="flex: 1; padding: 12px; border: 2px solid #3498db; background: #3498db; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            <i class="bi bi-box-seam"></i> Products (প্রোডাক্ট)
                        </button>
                        <button class="item-type-btn" id="servicesBtn" onclick="switchItemType('services')" style="flex: 1; padding: 12px; border: 2px solid #e0e6ed; background: white; color: #7f8c8d; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            <i class="bi bi-tools"></i> Services (সেবা)
                        </button>
                    </div>
                    
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" placeholder="Search products, SKU, category..." autofocus>
                    </div>
                    <div class="products-grid" id="productsGrid">
                        <?php foreach ($products as $product): 
                            $finalPrice = $product['offer_price'] > 0 ? $product['offer_price'] : $product['price'];
                            $isOutOfStock = $product['stock_quantity'] <= 0;
                            $isLowStock = $product['stock_quantity'] > 0 && $product['stock_quantity'] <= 5;
                            
                            // Check if image exists
                            $imagePath = '../assets/images/no-image.svg';
                            if ($product['image']) {
                                $fullPath = __DIR__ . '/../' . $product['image'];
                                if (file_exists($fullPath)) {
                                    $imagePath = '../' . $product['image'];
                                }
                            }
                        ?>
                            <div class="product-card <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>" 
                                 data-variant-id="<?php echo $product['variant_id']; ?>"
                                 data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                 data-variant-name="<?php echo htmlspecialchars($product['variant_name']); ?>"
                                 data-price="<?php echo $finalPrice; ?>"
                                 data-stock="<?php echo $product['stock_quantity']; ?>"
                                 data-sku="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>"
                                 data-category="<?php echo htmlspecialchars($product['category_name'] ?? ''); ?>"
                                 onclick="<?php echo !$isOutOfStock ? 'addToCart(this)' : ''; ?>">
                                
                                <?php if ($product['category_name']): ?>
                                    <span class="category-badge"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                <?php endif; ?>
                                
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     onerror="this.src='../assets/images/no-image.svg'">
                                
                                <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                <div class="variant"><?php echo htmlspecialchars($product['variant_name']); ?></div>
                                
                                <?php if ($product['offer_price'] > 0): ?>
                                    <div class="price">
                                        ৳<?php echo number_format($product['offer_price'], 2); ?>
                                        <small style="text-decoration: line-through; color: #95a5a6; font-size: 12px;">
                                            ৳<?php echo number_format($product['price'], 2); ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <div class="price">৳<?php echo number_format($product['price'], 2); ?></div>
                                <?php endif; ?>
                                
                                <div class="stock <?php echo $isLowStock ? 'low' : ''; ?>">
                                    <?php if ($isOutOfStock): ?>
                                        ❌ Out of Stock
                                    <?php elseif ($isLowStock): ?>
                                        ⚠️ Low Stock: <?php echo $product['stock_quantity']; ?>
                                    <?php else: ?>
                                        ✅ In Stock: <?php echo $product['stock_quantity']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Services Grid (Hidden by default) -->
                    <div class="services-grid" id="servicesGrid" style="display: none; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
                        <?php foreach ($services as $service): ?>
                            <div class="product-card service-card" 
                                 data-service-id="<?php echo $service['id']; ?>"
                                 data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                 data-service-desc="<?php echo htmlspecialchars($service['description']); ?>"
                                 data-price="<?php echo $service['price']; ?>"
                                 data-category="<?php echo htmlspecialchars($service['category']); ?>"
                                 data-type="service"
                                 onclick="addServiceToCart(this)">
                                
                                <span class="category-badge" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                                    <?php echo htmlspecialchars($service['category']); ?>
                                </span>
                                
                                <div style="height: 140px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 6px; margin-bottom: 10px;">
                                    <i class="bi bi-tools" style="font-size: 48px; color: white;"></i>
                                </div>
                                
                                <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                                <div class="variant" style="font-size: 11px; color: #7f8c8d; margin-bottom: 8px; height: 32px; overflow: hidden;">
                                    <?php echo htmlspecialchars($service['description']); ?>
                                </div>
                                
                                <div class="price">৳<?php echo number_format($service['price'], 2); ?></div>
                                
                                <div class="stock" style="color: #27ae60; font-weight: 600;">
                                    <i class="bi bi-check-circle-fill"></i> Available
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cart Section -->
                <div class="cart-section">
                    <div class="cart-header">🛒 Shopping Cart</div>
                    
                    <div class="cart-items" id="cartItems">
                        <div class="empty-cart">
                            <div class="empty-cart-icon">🛒</div>
                            <p>Cart is empty<br>Click on products to add</p>
                        </div>
                    </div>

                    <div class="cart-summary" id="cartSummary" style="display: none;">
                        <div class="summary-row">
                            <span>Items:</span>
                            <span id="itemCount">0</span>
                        </div>
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">৳0.00</span>
                        </div>
                        <div class="summary-row" id="commission" style="display: none; font-weight: 600;">
                            <span>Commission:</span>
                            <span>৳0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="total">৳0.00</span>
                        </div>
                    </div>

                    <div class="customer-info" id="customerInfo" style="display: none;">
                        <h4>Customer Information (Optional)</h4>
                        <input type="text" id="customerName" placeholder="Customer Name">
                        <input type="text" id="customerPhone" placeholder="Phone Number">
                        <select id="paymentMethod">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mobile">Mobile Payment</option>
                        </select>
                    </div>

                    <div class="checkout-actions" id="checkoutActions" style="display: none;">
                        <button class="btn btn-primary" id="checkoutBtn" onclick="checkout()">
                            💳 Complete Sale
                        </button>
                        <button class="btn btn-secondary" onclick="clearCart()">
                            🗑️ Clear Cart
                        </button>
                    </div>
                </div>
            </div>
            </div>
            <!-- End Tab 1 -->

            <!-- Tab 2: Sales History -->
            <div class="tab-content" id="tab-sales">
                <iframe src="pos_sales.php" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>

            <!-- Tab 3: Customer Ledger -->
            <div class="tab-content" id="tab-ledger">
                <iframe src="customer_ledger.php" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>

            <!-- Tab 4: Print Summary -->
            <div class="tab-content" id="tab-print" style="background: white; padding: 30px; overflow-y: auto;">
                <div style="max-width: 600px; margin: 0 auto;">
                    <h2 style="margin-bottom: 20px; color: #2c3e50;">📊 Print Sales Summary</h2>
                    <p style="color: #7f8c8d; margin-bottom: 30px;">Select a date range to generate and print a detailed sales report</p>
                    
                    <form id="printForm" style="background: #f8f9fa; padding: 25px; border-radius: 8px; border: 1px solid #dee2e6;">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Report Type:</label>
                            <select id="reportType" style="width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 15px;">
                                <option value="day">Daily Report</option>
                                <option value="week">Weekly Report</option>
                                <option value="month" selected>Monthly Report</option>
                                <option value="year">Yearly Report</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 25px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Select Date:</label>
                            <input type="date" id="reportDate" value="<?php echo date('Y-m-d'); ?>" 
                                   style="width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 15px;">
                            <small style="color: #6c757d; display: block; margin-top: 5px;">
                                Based on report type, corresponding period will be calculated
                            </small>
                        </div>

                        <button type="button" onclick="generateReport()" 
                                style="width: 100%; padding: 15px; background: #27ae60; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s;">
                            🖨️ Generate & Print Report
                        </button>
                    </form>

                    <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196f3; border-radius: 4px;">
                        <strong style="color: #1976d2;">💡 Quick Guide:</strong>
                        <ul style="margin-top: 10px; color: #555; line-height: 1.8;">
                            <li><strong>Daily:</strong> Full report for the selected day</li>
                            <li><strong>Weekly:</strong> Monday to Sunday of the selected week</li>
                            <li><strong>Monthly:</strong> Full month of the selected date</li>
                            <li><strong>Yearly:</strong> Entire year (Jan-Dec)</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.pos-tab').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });
            const activeTab = document.getElementById('tab-' + tab);
            activeTab.classList.add('active');
            activeTab.style.display = tab === 'sale' ? 'flex' : 'block';
        }

        // Generate print report
        function generateReport() {
            const type = document.getElementById('reportType').value;
            const date = document.getElementById('reportDate').value;
            const url = `pos_print_summary.php?type=${type}&date=${date}`;
            window.open(url, '_blank');
        }

        let cart = [];

        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('dateTime').textContent = now.toLocaleDateString('en-US', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Toggle between Products and Services
        function switchItemType(type) {
            const productsGrid = document.getElementById('productsGrid');
            const servicesGrid = document.getElementById('servicesGrid');
            const productsBtn = document.getElementById('productsBtn');
            const servicesBtn = document.getElementById('servicesBtn');
            const searchInput = document.getElementById('searchInput');
            
            if (type === 'products') {
                productsGrid.style.display = 'grid';
                servicesGrid.style.display = 'none';
                productsBtn.classList.add('active');
                servicesBtn.classList.remove('active');
                productsBtn.style.background = '#3498db';
                productsBtn.style.color = 'white';
                productsBtn.style.borderColor = '#3498db';
                servicesBtn.style.background = 'white';
                servicesBtn.style.color = '#7f8c8d';
                servicesBtn.style.borderColor = '#e0e6ed';
                searchInput.placeholder = 'Search products, SKU, category...';
            } else {
                productsGrid.style.display = 'none';
                servicesGrid.style.display = 'grid';
                servicesBtn.classList.add('active');
                productsBtn.classList.remove('active');
                servicesBtn.style.background = '#3498db';
                servicesBtn.style.color = 'white';
                servicesBtn.style.borderColor = '#3498db';
                productsBtn.style.background = 'white';
                productsBtn.style.color = '#7f8c8d';
                productsBtn.style.borderColor = '#e0e6ed';
                searchInput.placeholder = 'Search services...';
            }
            searchInput.value = '';
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const productCards = document.querySelectorAll('#productsGrid .product-card');
            const serviceCards = document.querySelectorAll('#servicesGrid .product-card');
            
            // Search in products
            productCards.forEach(card => {
                const productName = (card.dataset.productName || '').toLowerCase();
                const variantName = (card.dataset.variantName || '').toLowerCase();
                const sku = (card.dataset.sku || '').toLowerCase();
                const category = (card.dataset.category || '').toLowerCase();
                
                const matches = productName.includes(searchTerm) || 
                              variantName.includes(searchTerm) || 
                              sku.includes(searchTerm) ||
                              category.includes(searchTerm);
                
                card.style.display = matches ? 'block' : 'none';
            });
            
            // Search in services
            serviceCards.forEach(card => {
                const serviceName = (card.dataset.serviceName || '').toLowerCase();
                const serviceDesc = (card.dataset.serviceDesc || '').toLowerCase();
                const category = (card.dataset.category || '').toLowerCase();
                
                const matches = serviceName.includes(searchTerm) || 
                              serviceDesc.includes(searchTerm) ||
                              category.includes(searchTerm);
                
                card.style.display = matches ? 'block' : 'none';
            });
        });

        // Add service to cart
        function addServiceToCart(element) {
            const serviceId = element.dataset.serviceId;
            const serviceName = element.dataset.serviceName;
            const serviceDesc = element.dataset.serviceDesc;
            const price = parseFloat(element.dataset.price);

            const existingItem = cart.find(item => item.serviceId === serviceId);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    serviceId,
                    serviceName,
                    serviceDesc,
                    price,
                    originalPrice: price,
                    quantity: 1,
                    isService: true
                });
            }

            renderCart();
        }

        // Add to cart (products)
        function addToCart(element) {
            const variantId = element.dataset.variantId;
            const productName = element.dataset.productName;
            const variantName = element.dataset.variantName;
            const price = parseFloat(element.dataset.price);
            const maxStock = parseInt(element.dataset.stock);

            if (maxStock <= 0) {
                alert('Product is out of stock!');
                return;
            }

            const existingItem = cart.find(item => item.variantId === variantId);
            
            if (existingItem) {
                if (existingItem.quantity < maxStock) {
                    existingItem.quantity++;
                } else {
                    alert('Cannot add more. Only ' + maxStock + ' items available in stock.');
                    return;
                }
            } else {
                cart.push({
                    variantId,
                    productName,
                    variantName,
                    price,
                    originalPrice: price, // Store original price
                    quantity: 1,
                    maxStock
                });
            }

            renderCart();
        }

        // Update quantity
        function updateQuantity(variantId, newQty) {
            const item = cart.find(i => i.variantId === variantId);
            if (!item) return;

            const qty = parseInt(newQty);
            if (qty <= 0) {
                removeFromCart(variantId);
                return;
            }

            if (qty > item.maxStock) {
                alert('Only ' + item.maxStock + ' items available in stock.');
                renderCart();
                return;
            }

            item.quantity = qty;
            renderCart();
        }
        
        // Update service quantity
        function updateServiceQuantity(serviceId, newQty) {
            const item = cart.find(i => i.serviceId === serviceId);
            if (!item) return;

            const qty = parseInt(newQty);
            if (qty <= 0) {
                removeServiceFromCart(serviceId);
                return;
            }

            item.quantity = qty;
            renderCart();
        }

        // Update price (custom pricing)
        function updatePrice(variantId, newPrice) {
            const item = cart.find(i => i.variantId === variantId);
            if (!item) return;

            const price = parseFloat(newPrice);
            if (isNaN(price) || price < 0) {
                alert('Invalid price!');
                renderCart();
                return;
            }

            item.price = price;
            renderCart();
        }
        
        // Update service price
        function updateServicePrice(serviceId, newPrice) {
            const item = cart.find(i => i.serviceId === serviceId);
            if (!item) return;

            const price = parseFloat(newPrice);
            if (isNaN(price) || price < 0) {
                alert('Invalid price!');
                renderCart();
                return;
            }

            item.price = price;
            renderCart();
        }

        // Remove from cart
        function removeFromCart(variantId) {
            cart = cart.filter(item => item.variantId !== variantId);
            renderCart();
        }
        
        // Remove service from cart
        function removeServiceFromCart(serviceId) {
            cart = cart.filter(item => item.serviceId !== serviceId);
            renderCart();
        }

        // Clear cart
        function clearCart() {
            if (cart.length === 0) return;
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                renderCart();
            }
        }

        // Render cart
        function renderCart() {
            const cartItemsDiv = document.getElementById('cartItems');
            const cartSummary = document.getElementById('cartSummary');
            const customerInfo = document.getElementById('customerInfo');
            const checkoutActions = document.getElementById('checkoutActions');

            if (cart.length === 0) {
                cartItemsDiv.innerHTML = `
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <p>Cart is empty<br>Click on products to add</p>
                    </div>
                `;
                cartSummary.style.display = 'none';
                customerInfo.style.display = 'none';
                checkoutActions.style.display = 'none';
                return;
            }

            let html = '';
            let totalItems = 0;
            let subtotal = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                const originalPrice = item.originalPrice || item.price;
                const priceVariance = item.price - originalPrice;
                totalItems += item.quantity;
                subtotal += itemTotal;
                
                const itemId = item.isService ? item.serviceId : item.variantId;
                const itemName = item.isService ? item.serviceName : item.productName;
                const itemSubName = item.isService ? item.serviceDesc : item.variantName;
                const itemIcon = item.isService ? '<i class="bi bi-tools" style="color: #9b59b6; margin-right: 5px;"></i>' : '';

                html += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <h5>${itemIcon}${itemName}</h5>
                            <p style="font-size: 11px;">${itemSubName}</p>
                            <div style="display: flex; align-items: center; gap: 5px; margin-top: 5px;">
                                <span style="font-size: 12px; color: #7f8c8d;">Price:</span>
                                <input type="number" 
                                       value="${item.price.toFixed(2)}" 
                                       min="0" 
                                       step="0.01"
                                       onchange="${item.isService ? 'updateServicePrice' : 'updatePrice'}('${itemId}', this.value)"
                                       style="width: 70px; padding: 3px 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">
                                ${priceVariance !== 0 ? `<span style="font-size: 11px; color: ${priceVariance > 0 ? '#27ae60' : '#e74c3c'}; font-weight: 600;">(${priceVariance > 0 ? '+' : ''}৳${priceVariance.toFixed(2)})</span>` : ''}
                            </div>
                        </div>
                        <div class="cart-item-qty">
                            <button onclick="${item.isService ? 'updateServiceQuantity' : 'updateQuantity'}('${itemId}', ${item.quantity - 1})">-</button>
                            <input type="number" 
                                   value="${item.quantity}" 
                                   min="1" 
                                   ${item.isService ? '' : `max="${item.maxStock}"`}
                                   onchange="${item.isService ? 'updateServiceQuantity' : 'updateQuantity'}('${itemId}', this.value)">
                            <button onclick="${item.isService ? 'updateServiceQuantity' : 'updateQuantity'}('${itemId}', ${item.quantity + 1})">+</button>
                        </div>
                        <div class="cart-item-price">৳${itemTotal.toFixed(2)}</div>
                        <button class="cart-item-remove" onclick="${item.isService ? 'removeServiceFromCart' : 'removeFromCart'}('${itemId}')">×</button>
                    </div>
                `;
            });

            cartItemsDiv.innerHTML = html;
            
            // Calculate total commission
            const totalCommission = cart.reduce((sum, item) => {
                const originalPrice = item.originalPrice || item.price;
                return sum + ((item.price - originalPrice) * item.quantity);
            }, 0);
            
            document.getElementById('itemCount').textContent = totalItems;
            document.getElementById('subtotal').textContent = '৳' + subtotal.toFixed(2);
            
            // Update commission display
            const commissionEl = document.getElementById('commission');
            if (totalCommission !== 0) {
                commissionEl.style.display = 'flex';
                commissionEl.querySelector('span:last-child').textContent = (totalCommission > 0 ? '+' : '') + '৳' + totalCommission.toFixed(2);
                commissionEl.querySelector('span:last-child').style.color = totalCommission > 0 ? '#27ae60' : '#e74c3c';
            } else {
                commissionEl.style.display = 'none';
            }
            
            document.getElementById('total').textContent = '৳' + subtotal.toFixed(2);

            cartSummary.style.display = 'block';
            customerInfo.style.display = 'block';
            checkoutActions.style.display = 'block';
        }

        // Checkout
        function checkout() {
            console.log('Checkout function called');
            
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }

            const customerName = document.getElementById('customerName').value.trim();
            const customerPhone = document.getElementById('customerPhone').value.trim();
            const paymentMethod = document.getElementById('paymentMethod').value;

            const confirmMsg = `Complete sale?\n\nItems: ${cart.length}\nTotal: ৳${cart.reduce((sum, item) => sum + (item.price * item.quantity), 0).toFixed(2)}\nPayment: ${paymentMethod.toUpperCase()}`;
            
            if (!confirm(confirmMsg)) return;

            // Prepare form data
            const formData = new FormData();
            formData.append('customer_name', customerName);
            formData.append('customer_phone', customerPhone);
            formData.append('payment_method', paymentMethod);
            
            // Calculate total commission
            const totalCommission = cart.reduce((sum, item) => {
                const originalPrice = item.originalPrice || item.price;
                return sum + ((item.price - originalPrice) * item.quantity);
            }, 0);
            formData.append('commission', totalCommission.toFixed(2));
            
            cart.forEach(item => {
                formData.append('items[]', JSON.stringify({
                    variant_id: item.variantId,
                    quantity: item.quantity,
                    price: item.price,
                    original_price: item.originalPrice || item.price
                }));
            });

            // Submit
            document.getElementById('checkoutBtn').disabled = true;
            document.getElementById('checkoutBtn').textContent = '⏳ Processing...';

            fetch('pos_submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                console.log('Response:', text);
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response Text:', text);
                    alert('JSON Parse Error: ' + e.message + '\nResponse: ' + text.substring(0, 200));
                    throw new Error('Invalid JSON response');
                }
                
                if (data.success) {
                    alert('✅ Sale completed successfully!\nInvoice #' + data.sale_id);
                    
                    // Open invoice in new tab immediately (before user interaction)
                    const invoiceWindow = window.open('pos_invoice.php?id=' + data.sale_id, '_blank');
                    
                    if (!invoiceWindow || invoiceWindow.closed || typeof invoiceWindow.closed == 'undefined') {
                        // Popup blocked
                        if (confirm('Invoice popup was blocked!\n\nClick OK to open invoice.')) {
                            window.open('pos_invoice.php?id=' + data.sale_id, '_blank');
                        }
                    }
                    
                    cart = [];
                    renderCart();
                    document.getElementById('customerName').value = '';
                    document.getElementById('customerPhone').value = '';
                    document.getElementById('paymentMethod').value = 'cash';
                    
                    // Reload page to update stock displays
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('❌ Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Connection error. Please try again.');
            })
            .finally(() => {
                document.getElementById('checkoutBtn').disabled = false;
                document.getElementById('checkoutBtn').textContent = '💳 Complete Sale';
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // F9 = Complete Sale
            if (e.key === 'F9') {
                e.preventDefault();
                if (cart.length > 0) checkout();
            }
            // Esc = Clear search or clear cart
            if (e.key === 'Escape') {
                const searchInput = document.getElementById('searchInput');
                if (searchInput.value) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input'));
                } else if (cart.length > 0) {
                    clearCart();
                }
            }
        });
    </script>
        </div>
    </div>
</body>
</html>
