<?php
require_once '../core/auth.php';
require_admin();

header('Content-Type: application/json');

try {
    // Get form data
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand_id = (int)($_POST['brand_id'] ?? 0);
    $short_description = trim($_POST['short_description'] ?? '');
    $long_description = trim($_POST['long_description'] ?? '');
    $product_type = $_POST['product_type'] ?? 'simple';
    $tags = trim($_POST['tags'] ?? '');
    $is_draft = isset($_POST['is_draft']) && $_POST['is_draft'] == '1';
    
    // Validation
    if (empty($product_name)) {
        throw new Exception('Product name is required');
    }
    
    if ($category_id <= 0) {
        throw new Exception('Please select a category');
    }
    
    // Create slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_name)));
    $slug = $slug . '-' . time(); // Ensure unique
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert product
    $stmt = $pdo->prepare("
        INSERT INTO products (title, slug, category_id, brand_id, description, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $product_name,
        $slug,
        $category_id,
        $brand_id ?: null,
        $long_description ?: $short_description,
        $is_draft ? 0 : 1
    ]);
    $product_id = $pdo->lastInsertId();
    
    // Handle thumbnail upload
    if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . $product_id . '_thumb_' . time() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $filepath)) {
            $image_path = 'uploads/products/' . $filename;
            
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (?, ?, 1, 0)");
            $stmt->execute([$product_id, $image_path]);
        }
    }
    
    // Handle gallery images
    if (!empty($_FILES['gallery'])) {
        $upload_dir = __DIR__ . '/../uploads/products/';
        $order = 1;
        
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                $filename = 'product_' . $product_id . '_gallery_' . $order . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $filepath)) {
                    $image_path = 'uploads/products/' . $filename;
                    
                    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (?, ?, 0, ?)");
                    $stmt->execute([$product_id, $image_path, $order]);
                    $order++;
                }
            }
        }
    }
    
    // Handle variants/variations
    if ($product_type === 'simple') {
        // Simple product - single variant
        $purchase_price = floatval($_POST['simple_purchase_price'] ?? 0);
        $extra_cost = floatval($_POST['simple_extra_cost'] ?? 0);
        $selling_price = floatval($_POST['simple_selling_price'] ?? 0);
        $old_price = floatval($_POST['simple_old_price'] ?? 0);
        $stock = intval($_POST['simple_stock'] ?? 0);
        
        $sku = 'SKU-' . strtoupper(substr(md5($product_name . time()), 0, 8));
        
        $stmt = $pdo->prepare("
            INSERT INTO product_variants (product_id, name, sku, price, offer_price, purchase_price, stock_quantity, status)
            VALUES (?, 'Default', ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $product_id,
            $sku,
            $selling_price,
            $old_price > 0 ? $old_price : null,
            $purchase_price + $extra_cost,
            $stock
        ]);
        
    } else {
        // Variable product - multiple variations
        $variations = json_decode($_POST['variations'] ?? '[]', true);
        
        if (!empty($variations)) {
            foreach ($variations as $idx => $var) {
                $sku = 'SKU-' . strtoupper(substr(md5($product_name . json_encode($var['attributes'] ?? []) . time()), 0, 8)) . '-' . ($idx + 1);
                
                $stmt = $pdo->prepare("
                    INSERT INTO product_variants (product_id, name, sku, price, offer_price, purchase_price, stock_quantity, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                // Create variant name from attributes
                $variant_name = implode(' / ', array_values($var['attributes'] ?? ['Default']));
                
                $stmt->execute([
                    $product_id,
                    $variant_name ?: 'Default',
                    $sku,
                    floatval($var['selling_price'] ?? 0),
                    floatval($var['old_price'] ?? 0) > 0 ? floatval($var['old_price']) : null,
                    floatval($var['purchase_price'] ?? 0) + floatval($var['extra_cost'] ?? 0),
                    intval($var['stock'] ?? 0)
                ]);
                
                // Also save to product_variations table for new system
                $stmt2 = $pdo->prepare("
                    INSERT INTO product_variations (product_id, sku, variation_data, purchase_price, price, offer_price, stock_quantity, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt2->execute([
                    $product_id,
                    $sku,
                    json_encode($var['attributes'] ?? []),
                    floatval($var['purchase_price'] ?? 0) + floatval($var['extra_cost'] ?? 0),
                    floatval($var['selling_price'] ?? 0),
                    floatval($var['old_price'] ?? 0) > 0 ? floatval($var['old_price']) : null,
                    intval($var['stock'] ?? 0)
                ]);
            }
        } else {
            // No variations provided, create default
            $stmt = $pdo->prepare("
                INSERT INTO product_variants (product_id, name, sku, price, stock_quantity, status)
                VALUES (?, 'Default', ?, 0, 0, 1)
            ");
            $stmt->execute([$product_id, 'SKU-' . $product_id]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => $is_draft ? 'Product saved as draft!' : 'Product published successfully!',
        'product_id' => $product_id,
        'redirect' => 'products.php?msg=added'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
