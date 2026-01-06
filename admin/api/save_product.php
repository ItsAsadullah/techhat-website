<?php
require_once '../../core/auth.php';
require_once '../../core/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get form data
    $productName = $_POST['product_name'] ?? '';
    $brandId = $_POST['brand_id'] ?? null;
    $tags = $_POST['tags'] ?? '';
    $shortDescription = $_POST['short_description'] ?? '';
    $longDescription = $_POST['long_description'] ?? '';
    $productType = $_POST['product_type'] ?? 'simple';
    $status = $_POST['status'] ?? 'draft'; // draft or published
    
    // SEO data
    $metaTitle = $_POST['meta_title'] ?? $productName;
    $metaKeywords = $_POST['meta_keywords'] ?? '';
    $metaDescription = $_POST['meta_description'] ?? '';
    
    // Shipping data
    $weight = $_POST['weight'] ?? 0;
    $length = $_POST['length'] ?? 0;
    $width = $_POST['width'] ?? 0;
    $height = $_POST['height'] ?? 0;
    
    // Warranty data
    $warrantyType = $_POST['warranty_type'] ?? 'none';
    $warrantyPeriod = $_POST['warranty_period'] ?? '';
    $returnPolicy = $_POST['return_policy'] ?? 'no_return';
    
    // Video URL
    $videoUrl = $_POST['video_url'] ?? '';
    
    // Validate required fields
    if (empty($productName)) {
        throw new Exception('Product name is required');
    }
    
    // Get category ID (from last selected category)
    $categoryId = null;
    for ($i = 1; $i <= 10; $i++) {
        if (isset($_POST["category_level_$i"])) {
            $categoryId = $_POST["category_level_$i"];
        }
    }
    
    if (!$categoryId) {
        throw new Exception('Category is required');
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert product
    $stmt = $pdo->prepare("
        INSERT INTO products (
            name, category_id, brand_id, description, short_description, 
            tags, meta_title, meta_keywords, meta_description,
            weight, length, width, height,
            warranty_type, warranty_period, return_policy,
            video_url, vendor_id, status, created_at
        ) VALUES (
            :name, :category_id, :brand_id, :description, :short_description,
            :tags, :meta_title, :meta_keywords, :meta_description,
            :weight, :length, :width, :height,
            :warranty_type, :warranty_period, :return_policy,
            :video_url, :vendor_id, :status, NOW()
        )
    ");
    
    $stmt->execute([
        ':name' => $productName,
        ':category_id' => $categoryId,
        ':brand_id' => $brandId,
        ':description' => $longDescription,
        ':short_description' => $shortDescription,
        ':tags' => $tags,
        ':meta_title' => $metaTitle,
        ':meta_keywords' => $metaKeywords,
        ':meta_description' => $metaDescription,
        ':weight' => $weight,
        ':length' => $length,
        ':width' => $width,
        ':height' => $height,
        ':warranty_type' => $warrantyType,
        ':warranty_period' => $warrantyPeriod,
        ':return_policy' => $returnPolicy,
        ':video_url' => $videoUrl,
        ':vendor_id' => $_SESSION['user_id'],
        ':status' => $status
    ]);
    
    $productId = $pdo->lastInsertId();
    
    // Handle thumbnail upload
    $thumbnailPath = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $thumbnailPath = uploadImage($_FILES['thumbnail'], 'products');
        
        // Update product with thumbnail
        $stmt = $pdo->prepare("UPDATE products SET image = :image WHERE id = :id");
        $stmt->execute([':image' => $thumbnailPath, ':id' => $productId]);
    }
    
    // Handle gallery images
    if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
        $galleryImages = [];
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                $imagePath = uploadImage([
                    'tmp_name' => $tmpName,
                    'name' => $_FILES['gallery']['name'][$key],
                    'type' => $_FILES['gallery']['type'][$key],
                    'error' => $_FILES['gallery']['error'][$key],
                    'size' => $_FILES['gallery']['size'][$key]
                ], 'products');
                $galleryImages[] = $imagePath;
            }
        }
        
        if (!empty($galleryImages)) {
            // Store gallery images in a separate table or as JSON
            $stmt = $pdo->prepare("UPDATE products SET gallery_images = :gallery WHERE id = :id");
            $stmt->execute([
                ':gallery' => json_encode($galleryImages),
                ':id' => $productId
            ]);
        }
    }
    
    // Handle product variations based on type
    if ($productType === 'simple') {
        // Simple product - single variation
        $purchasePrice = $_POST['simple_purchase_price'] ?? 0;
        $extraCost = $_POST['simple_extra_cost'] ?? 0;
        $sellingPrice = $_POST['simple_selling_price'] ?? 0;
        $oldPrice = $_POST['simple_old_price'] ?? null;
        $stock = $_POST['simple_stock'] ?? 0;
        
        $sku = generateSKU($productId, 'SIMPLE');
        
        $stmt = $pdo->prepare("
            INSERT INTO product_variations (
                product_id, sku, variation_json, 
                purchase_price, extra_cost, selling_price, old_price, 
                stock_qty, is_active, created_at
            ) VALUES (
                :product_id, :sku, :variation_json,
                :purchase_price, :extra_cost, :selling_price, :old_price,
                :stock_qty, 1, NOW()
            )
        ");
        
        $stmt->execute([
            ':product_id' => $productId,
            ':sku' => $sku,
            ':variation_json' => json_encode(['type' => 'simple']),
            ':purchase_price' => $purchasePrice,
            ':extra_cost' => $extraCost,
            ':selling_price' => $sellingPrice,
            ':old_price' => $oldPrice,
            ':stock_qty' => $stock
        ]);
        
    } else {
        // Variable product - multiple variations
        if (isset($_POST['variations']) && is_array($_POST['variations'])) {
            foreach ($_POST['variations'] as $index => $variation) {
                $attributes = $variation['attributes'] ?? '';
                $purchasePrice = $variation['purchase_price'] ?? 0;
                $extraCost = $variation['extra_cost'] ?? 0;
                $sellingPrice = $variation['selling_price'] ?? 0;
                $oldPrice = $variation['old_price'] ?? null;
                $stock = $variation['stock'] ?? 0;
                
                // Generate SKU
                $sku = generateSKU($productId, $attributes);
                
                // Parse attributes (e.g., "Black, 8GB, 128GB")
                $attrArray = array_map('trim', explode(',', $attributes));
                $variationJson = [];
                
                // Try to match with common attribute names
                $attributeNames = ['Color', 'RAM', 'Storage', 'Size'];
                foreach ($attrArray as $key => $value) {
                    $attributeName = $attributeNames[$key] ?? "Attribute_" . ($key + 1);
                    $variationJson[$attributeName] = $value;
                }
                
                // Handle variation image upload
                $variationImage = null;
                if (isset($_FILES['variations']['tmp_name'][$index]['image'])) {
                    $imageFile = [
                        'tmp_name' => $_FILES['variations']['tmp_name'][$index]['image'],
                        'name' => $_FILES['variations']['name'][$index]['image'],
                        'type' => $_FILES['variations']['type'][$index]['image'],
                        'error' => $_FILES['variations']['error'][$index]['image'],
                        'size' => $_FILES['variations']['size'][$index]['image']
                    ];
                    
                    if ($imageFile['error'] === UPLOAD_ERR_OK) {
                        $variationImage = uploadImage($imageFile, 'products');
                    }
                }
                
                // Insert variation
                $stmt = $pdo->prepare("
                    INSERT INTO product_variations (
                        product_id, sku, variation_json,
                        purchase_price, extra_cost, selling_price, old_price,
                        stock_qty, image, is_active, created_at
                    ) VALUES (
                        :product_id, :sku, :variation_json,
                        :purchase_price, :extra_cost, :selling_price, :old_price,
                        :stock_qty, :image, 1, NOW()
                    )
                ");
                
                $stmt->execute([
                    ':product_id' => $productId,
                    ':sku' => $sku,
                    ':variation_json' => json_encode($variationJson),
                    ':purchase_price' => $purchasePrice,
                    ':extra_cost' => $extraCost,
                    ':selling_price' => $sellingPrice,
                    ':old_price' => $oldPrice,
                    ':stock_qty' => $stock,
                    ':image' => $variationImage
                ]);
            }
        }
    }
    
    // Handle category attributes (if selected)
    if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
        foreach ($_POST['attributes'] as $attributeId => $values) {
            if (is_array($values)) {
                foreach ($values as $valueId) {
                    // Link product with attribute values
                    $stmt = $pdo->prepare("
                        INSERT INTO product_attribute_values (product_id, attribute_id, value_id)
                        VALUES (:product_id, :attribute_id, :value_id)
                        ON DUPLICATE KEY UPDATE value_id = :value_id
                    ");
                    $stmt->execute([
                        ':product_id' => $productId,
                        ':attribute_id' => $attributeId,
                        ':value_id' => $valueId
                    ]);
                }
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Product saved successfully',
        'product_id' => $productId,
        'redirect' => $status === 'published' ? 'products.php' : 'product_edit.php?id=' . $productId
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Upload image to server
 */
function uploadImage($file, $folder) {
    $uploadDir = "../../uploads/$folder/";
    
    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }
    
    throw new Exception('Failed to upload image');
}

/**
 * Generate unique SKU
 */
function generateSKU($productId, $suffix) {
    $suffix = preg_replace('/[^A-Z0-9]/i', '', $suffix);
    $suffix = strtoupper(substr($suffix, 0, 10));
    return 'SKU-' . str_pad($productId, 6, '0', STR_PAD_LEFT) . '-' . $suffix . '-' . time();
}
