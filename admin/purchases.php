<?php
require_once '../core/auth.php';
require_admin();

// Set charset for proper UTF-8 handling
header('Content-Type: text/html; charset=utf-8');
$pdo->exec("SET NAMES utf8mb4");

// Create uploads directory if not exists
$upload_dir = '../uploads/memos/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle Add Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $name = $_POST['supplier_name'];
    $company = $_POST['company_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    
    $stmt = $pdo->prepare("INSERT INTO suppliers (name, company_name, phone, email, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $company, $phone, $email, $address]);
    header("Location: purchases.php?tab=suppliers&success=1");
    exit;
}

// Handle Edit Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier'])) {
    $id = (int)$_POST['supplier_id'];
    $name = $_POST['supplier_name'];
    $company = $_POST['company_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    
    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, company_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
    $stmt->execute([$name, $company, $phone, $email, $address, $id]);
    header("Location: purchases.php?tab=suppliers&success=3");
    exit;
}

// Handle Delete Supplier
if (isset($_GET['delete_supplier'])) {
    $id = (int)$_GET['delete_supplier'];
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: purchases.php?tab=suppliers&success=4");
    exit;
}

// Handle Add Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_purchase'])) {
    try {
        $pdo->beginTransaction();
        
        $supplier_id = $_POST['supplier_id'];
        $purchase_date = $_POST['purchase_date'];
        $payment_status = $_POST['payment_status'];
        $paid_amount = (float)$_POST['paid_amount'];
        $payment_method = $_POST['payment_method'];
        $note = $_POST['note'];
        
        // Handle file upload
        $memo_file = null;
        if (isset($_FILES['memo_file']) && $_FILES['memo_file']['error'] === 0) {
            $file_ext = pathinfo($_FILES['memo_file']['name'], PATHINFO_EXTENSION);
            $memo_file = 'memo_' . time() . '_' . uniqid() . '.' . $file_ext;
            move_uploaded_file($_FILES['memo_file']['tmp_name'], $upload_dir . $memo_file);
        }
        
        // Calculate total amount
        $total_amount = 0;
        $product_ids = $_POST['product_id'];
        $variant_ids = $_POST['variant_id'];
        $quantities = $_POST['quantity'];
        $unit_prices = $_POST['unit_price'];
        
        for ($i = 0; $i < count($product_ids); $i++) {
            $total_amount += $quantities[$i] * $unit_prices[$i];
        }
        
        // Insert purchase
        $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, purchase_date, total_amount, paid_amount, payment_status, payment_method, memo_file, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$supplier_id, $purchase_date, $total_amount, $paid_amount, $payment_status, $payment_method, $memo_file, $note]);
        $purchase_id = $pdo->lastInsertId();
        
        // Insert purchase items and update stock
        for ($i = 0; $i < count($product_ids); $i++) {
            $product_id = $product_ids[$i];
            $variant_id = $variant_ids[$i] ?: null;
            $quantity = (int)$quantities[$i];
            $unit_price = (float)$unit_prices[$i];
            $total_price = $quantity * $unit_price;
            
            // Insert purchase item
            $stmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, variant_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$purchase_id, $product_id, $variant_id, $quantity, $unit_price, $total_price]);
            
            // Update variant stock
            if ($variant_id) {
                $stmt = $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $stmt->execute([$quantity, $variant_id]);
            }
        }
        
        // Add to expenses
        $expense_desc = "Purchase from Supplier ID: $supplier_id (Purchase #$purchase_id)";
        $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, category, expense_date) VALUES (?, ?, 'Inventory', ?)");
        $stmt->execute([$expense_desc, $total_amount, $purchase_date]);
        
        // Update supplier balance if not fully paid
        if ($payment_status !== 'paid') {
            $due_amount = $total_amount - $paid_amount;
            $stmt = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$due_amount, $supplier_id]);
        }
        
        $pdo->commit();
        header("Location: purchases.php?success=2");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Delete Purchase
if (isset($_GET['delete_purchase'])) {
    $id = (int)$_GET['delete_purchase'];
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: purchases.php");
    exit;
}

// Fetch Suppliers
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();

// Get supplier for editing if edit parameter exists
$edit_supplier = null;
if (isset($_GET['edit_supplier'])) {
    $edit_id = (int)$_GET['edit_supplier'];
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_supplier = $stmt->fetch();
}

// Fetch Purchases with Supplier Info
$purchases = $pdo->query("
    SELECT p.*, s.name as supplier_name, s.company_name 
    FROM purchases p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    ORDER BY p.purchase_date DESC, p.id DESC
")->fetchAll();

// Fetch Products with Variants
$products = $pdo->query("
    SELECT p.id, p.title, 
           GROUP_CONCAT(CONCAT(pv.id, ':', pv.name, ':', pv.price) SEPARATOR '||') as variants
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    GROUP BY p.id
    ORDER BY p.title ASC
")->fetchAll();

$active_tab = $_GET['tab'] ?? 'purchases';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchases - TechHat Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }
        .admin-content {
            margin-left: 280px;
            transition: margin-left 0.3s;
        }
        .content { padding: 30px; }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Success Message */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        /* Tabs */
        .tabs-container {
            background: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 0;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: #7f8c8d;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            border-radius: 8px 8px 0 0;
            text-decoration: none;
            display: inline-block;
        }
        
        .tab:hover {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .tab.active {
            color: #3498db;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
            border-radius: 2px 2px 0 0;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
            background: white;
            padding: 25px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Add Form */
        .add-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .add-form h3 {
            color: white;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2);
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .btn {
            padding: 12px 24px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 15px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        /* Purchase Items */
        .purchase-items {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .purchase-items h4 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 2fr 100px 100px 50px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: end;
        }
        
        .item-row input,
        .item-row select {
            padding: 10px;
            border: 2px solid #e0e6ed;
            border-radius: 6px;
            background: white;
            width: 100%;
        }
        
        .btn-remove {
            padding: 10px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            width: 100%;
        }
        
        .btn-remove:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        .btn-add-item {
            padding: 10px 20px;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-add-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e6ed;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        .btn-view {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 80px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        /* Total Amount Box */
        .total-amount-box {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            padding: 20px;
            border-radius: 8px;
            color: white;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .total-amount-box h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .total-amount-box .amount {
            font-size: 32px;
            font-weight: 700;
        }
        
        .payment-info {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .payment-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .payment-info-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 16px;
            color: #e74c3c;
        }
        
        .hidden {
            display: none !important;
        }
        
        @media (max-width: 768px) {
            .admin-content { margin-left: 0; }
            .item-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <div class="page-header">
                <h1>
                    <i class="bi bi-cart-plus"></i>
                    Purchase Management
                </h1>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <i class="bi bi-check-circle-fill"></i>
                    <?php 
                    if ($_GET['success'] == 1) echo "Supplier added successfully!";
                    if ($_GET['success'] == 2) echo "Purchase added successfully! Stock updated & Expense recorded.";
                    if ($_GET['success'] == 3) echo "Supplier updated successfully!";
                    if ($_GET['success'] == 4) echo "Supplier deleted successfully!";
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="success-message" style="background: #f8d7da; color: #721c24; border-color: #e74c3c;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs">
                    <a href="?tab=purchases" class="tab <?php echo $active_tab === 'purchases' ? 'active' : ''; ?>">
                        <i class="bi bi-cart-plus"></i> Purchases
                    </a>
                    <a href="?tab=suppliers" class="tab <?php echo $active_tab === 'suppliers' ? 'active' : ''; ?>">
                        <i class="bi bi-people"></i> Suppliers
                    </a>
                </div>
            </div>
            
            <!-- Purchases Tab -->
            <div class="tab-content <?php echo $active_tab === 'purchases' ? 'active' : ''; ?>">
                <!-- Add Purchase Form -->
                <div class="add-form">
                    <h3>
                        <i class="bi bi-plus-circle"></i>
                        Add New Purchase
                    </h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Supplier *</label>
                                <select name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?php echo $sup['id']; ?>">
                                            <?php echo htmlspecialchars($sup['name'] . ' - ' . ($sup['company_name'] ?: 'N/A')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Purchase Date *</label>
                                <input type="date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Status *</label>
                                <select name="payment_status" id="paymentStatus" required onchange="updatePaymentFields()">
                                    <option value="paid">Paid (সম্পূর্ণ পরিশোধ)</option>
                                    <option value="partial">Partial (আংশিক পরিশোধ)</option>
                                    <option value="due">Due (বাকি)</option>
                                </select>
                            </div>
                            
                            <div class="form-group hidden" id="paidAmountGroup">
                                <label>Paid Amount (পরিশোধিত টাকা) *</label>
                                <input type="number" name="paid_amount" id="paidAmount" step="0.01" value="0" min="0" oninput="updateDueAmount()">
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="mobile_banking">Mobile Banking</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Upload Memo/Invoice (Optional)</label>
                                <input type="file" name="memo_file" accept="image/*,.pdf">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Note</label>
                                <textarea name="note" rows="2" placeholder="Additional notes..."></textarea>
                            </div>
                            
                            <div class="form-group full-width">
                                <div class="total-amount-box">
                                    <h4><i class="bi bi-calculator"></i> Total Purchase Amount</h4>
                                    <div class="amount">৳<span id="totalAmount">0.00</span></div>
                                    
                                    <div class="payment-info" id="paymentInfo" style="display: none;">
                                        <div class="payment-info-row">
                                            <span>Total Amount:</span>
                                            <span>৳<span id="totalAmountInfo">0.00</span></span>
                                        </div>
                                        <div class="payment-info-row">
                                            <span>Paid Amount:</span>
                                            <span>৳<span id="paidAmountInfo">0.00</span></span>
                                        </div>
                                        <div class="payment-info-row">
                                            <span>Due Amount (বাকি):</span>
                                            <span>৳<span id="dueAmount">0.00</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Purchase Items -->
                        <div class="purchase-items">
                            <h4><i class="bi bi-list-ul"></i> Purchase Items</h4>
                            <div id="itemsContainer">
                                <div class="item-row">
                                    <select name="product_id[]" class="product-select" required onchange="loadVariants(this)">
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $prod): ?>
                                            <option value="<?php echo $prod['id']; ?>" data-variants="<?php echo htmlspecialchars($prod['variants']); ?>">
                                                <?php echo htmlspecialchars($prod['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="variant_id[]" class="variant-select" required>
                                        <option value="">Select Variant</option>
                                    </select>
                                    <input type="number" name="quantity[]" placeholder="Qty" min="1" value="1" required>
                                    <input type="number" name="unit_price[]" placeholder="Price" step="0.01" min="0" required>
                                    <button type="button" class="btn-remove" onclick="removeItem(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn-add-item" onclick="addItem()">
                                <i class="bi bi-plus-circle"></i> Add More Items
                            </button>
                        </div>
                        
                        <button type="submit" name="add_purchase" class="btn">
                            <i class="bi bi-save"></i> Submit Purchase
                        </button>
                    </form>
                </div>
                
                <!-- Purchases List -->
                <?php if (empty($purchases)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>No Purchases Yet</h3>
                        <p>Add your first purchase above</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Total Amount</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th>Memo</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $pur): ?>
                            <tr>
                                <td>#<?php echo $pur['id']; ?></td>
                                <td><?php echo date('d M, Y', strtotime($pur['purchase_date'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pur['supplier_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($pur['company_name'] ?: ''); ?></small>
                                </td>
                                <td><strong>৳<?php echo number_format($pur['total_amount'], 2); ?></strong></td>
                                <td>৳<?php echo number_format($pur['paid_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $badge_class = $pur['payment_status'] === 'paid' ? 'badge-success' : 
                                                  ($pur['payment_status'] === 'partial' ? 'badge-warning' : 'badge-danger');
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($pur['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pur['memo_file']): ?>
                                        <a href="../uploads/memos/<?php echo $pur['memo_file']; ?>" target="_blank" class="btn-view btn-sm">
                                            <i class="bi bi-file-earmark-text"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #7f8c8d;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="purchases.php?delete_purchase=<?php echo $pur['id']; ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('Delete this purchase?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Suppliers Tab -->
            <div class="tab-content <?php echo $active_tab === 'suppliers' ? 'active' : ''; ?>">
                <!-- Add/Edit Supplier Form -->
                <div class="add-form">
                    <h3>
                        <i class="bi <?php echo $edit_supplier ? 'bi-pencil-square' : 'bi-person-plus'; ?>"></i>
                        <?php echo $edit_supplier ? 'Edit Supplier' : 'Add New Supplier'; ?>
                    </h3>
                    <form method="POST">
                        <?php if ($edit_supplier): ?>
                            <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Supplier Name *</label>
                                <input type="text" name="supplier_name" 
                                       value="<?php echo htmlspecialchars($edit_supplier['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" name="company_name" 
                                       value="<?php echo htmlspecialchars($edit_supplier['company_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" 
                                       value="<?php echo htmlspecialchars($edit_supplier['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" 
                                       value="<?php echo htmlspecialchars($edit_supplier['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label>Address</label>
                                <textarea name="address" rows="2"><?php echo htmlspecialchars($edit_supplier['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="<?php echo $edit_supplier ? 'edit_supplier' : 'add_supplier'; ?>" class="btn">
                                <i class="bi bi-save"></i> <?php echo $edit_supplier ? 'Update Supplier' : 'Add Supplier'; ?>
                            </button>
                            
                            <?php if ($edit_supplier): ?>
                                <a href="?tab=suppliers" class="btn" style="background: #95a5a6; color: white;">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Suppliers List -->
                <?php if (empty($suppliers)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>No Suppliers Yet</h3>
                        <p>Add your first supplier above</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Balance (Due)</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td>#<?php echo $sup['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($sup['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($sup['company_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($sup['phone'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($sup['email'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php if ($sup['balance'] > 0): ?>
                                        <span style="color: #e74c3c; font-weight: 600;">৳<?php echo number_format($sup['balance'], 2); ?></span>
                                    <?php else: ?>
                                        <span style="color: #27ae60;">Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($sup['address'] ?: '', 0, 30)); ?></td>
                                <td>
                                    <a href="?tab=suppliers&edit_supplier=<?php echo $sup['id']; ?>" class="btn-view btn-sm">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <a href="?tab=suppliers&delete_supplier=<?php echo $sup['id']; ?>" 
                                       class="btn-delete btn-sm" 
                                       onclick="return confirm('Delete this supplier?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Products data for variant loading
        const productsData = <?php echo json_encode(array_map(function($p) {
            return [
                'id' => $p['id'],
                'variants' => $p['variants']
            ];
        }, $products)); ?>;
        
        function loadVariants(selectElement) {
            const productId = selectElement.value;
            const variantSelect = selectElement.closest('.item-row').querySelector('.variant-select');
            
            variantSelect.innerHTML = '<option value="">Select Variant</option>';
            
            if (!productId) return;
            
            const product = productsData.find(p => p.id == productId);
            if (product && product.variants) {
                const variants = product.variants.split('||');
                variants.forEach(variant => {
                    const [id, name, price] = variant.split(':');
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = `${name} - ৳${price}`;
                    option.dataset.price = price;
                    variantSelect.appendChild(option);
                });
            }
        }
        
        function addItem() {
            const container = document.getElementById('itemsContainer');
            const firstRow = container.querySelector('.item-row');
            const newRow = firstRow.cloneNode(true);
            
            // Reset values
            newRow.querySelectorAll('input').forEach(input => input.value = input.type === 'number' && input.name.includes('quantity') ? '1' : '');
            newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            
            container.appendChild(newRow);
        }
        
        function removeItem(btn) {
            const container = document.getElementById('itemsContainer');
            if (container.querySelectorAll('.item-row').length > 1) {
                btn.closest('.item-row').remove();
            } else {
                alert('At least one item is required!');
            }
        }
        
        // Auto-fill price when variant is selected
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('variant-select')) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const price = selectedOption.dataset.price;
                if (price) {
                    e.target.closest('.item-row').querySelector('input[name="unit_price[]"]').value = price;
                }
                calculateTotal();
            }
        });
        
        // Calculate total amount when quantity or price changes
        document.addEventListener('input', function(e) {
            if (e.target.name === 'quantity[]' || e.target.name === 'unit_price[]') {
                calculateTotal();
            }
        });
        
        function calculateTotal() {
            let total = 0;
            const itemRows = document.querySelectorAll('.item-row');
            
            itemRows.forEach(row => {
                const quantity = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name="unit_price[]"]').value) || 0;
                total += quantity * price;
            });
            
            document.getElementById('totalAmount').textContent = total.toFixed(2);
            document.getElementById('totalAmountText').textContent = total.toFixed(2);
            document.getElementById('totalAmountInfo').textContent = total.toFixed(2);
            
            updateDueAmount();
        }
        
        function updatePaymentFields() {
            const status = document.getElementById('paymentStatus').value;
            const paidAmountInput = document.getElementById('paidAmount');
            const paidAmountGroup = document.getElementById('paidAmountGroup');
            const paymentInfo = document.getElementById('paymentInfo');
            const total = parseFloat(document.getElementById('totalAmount').textContent) || 0;
            
            if (status === 'paid') {
                // Hide paid amount field for Paid status
                paidAmountGroup.classList.add('hidden');
                paidAmountInput.value = total;
                paymentInfo.style.display = 'none';
            } else if (status === 'due') {
                // Show paid amount field, set to 0 for Due
                paidAmountGroup.classList.remove('hidden');
                paidAmountInput.value = '0';
                paymentInfo.style.display = 'block';
            } else {
                // Show paid amount field for Partial
                paidAmountGroup.classList.remove('hidden');
                paymentInfo.style.display = 'block';
            }
            
            updateDueAmount();
        }
        
        function updateDueAmount() {
            const total = parseFloat(document.getElementById('totalAmount').textContent) || 0;
            const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
            const due = total - paid;
            
            document.getElementById('paidAmountInfo').textContent = paid.toFixed(2);
            document.getElementById('dueAmount').textContent = due.toFixed(2);
        }
    </script>
</body>
</html>
