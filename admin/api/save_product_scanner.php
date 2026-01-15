<?php
/**
 * ================================================================
 * TechHat Shop - Save Product API
 * Handles product creation with variations and serial numbers
 * Uses PDO Transactions for data integrity
 * ================================================================
 */

header('Content-Type: application/json');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../../core/db.php';
require_once '../../core/auth.php';

// Check authentication
if (!is_admin()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized access'], 401);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Verify CSRF token
$csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // ============================================
    // 1. COLLECT AND VALIDATE INPUT DATA
    // ============================================
    
    // Required fields
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        throw new Exception('Product name is required');
    }
    
    // Generate slug
    $slug = generateSlug($pdo, $name);
    
    // Optional fields
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pc');
    $warrantyMonths = (int)($_POST['warranty_months'] ?? 0);
    $warrantyType = trim($_POST['warranty_type'] ?? '');
    $productType = in_array($_POST['product_type'] ?? '', ['simple', 'variable']) ? $_POST['product_type'] : 'simple';
    $hasSerial = isset($_POST['has_serial']) && $_POST['has_serial'] == '1' ? 1 : 0;
    $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
    $isFlashSale = isset($_POST['is_flash_sale']) && $_POST['is_flash_sale'] == '1' ? 1 : 0;
    
    // ============================================
    // 2. INSERT PRODUCT
    // ============================================
    
    $productStmt = $pdo->prepare("
        INSERT INTO products (
            category_id, brand_id, title, slug, description, video_url,
            product_type, warranty_months, warranty_type, has_serial, unit,
            is_flash_sale, is_active, created_at, updated_at
        ) VALUES (
            :category_id, :brand_id, :title, :slug, :description, :video_url,
            :product_type, :warranty_months, :warranty_type, :has_serial, :unit,
            :is_flash_sale, :is_active, NOW(), NOW()
        )
    ");
    
    $productStmt->execute([
        ':category_id' => $categoryId,
        ':brand_id' => $brandId,
        ':title' => $name,
        ':slug' => $slug,
        ':description' => $description,
        ':video_url' => $videoUrl,
        ':product_type' => $productType,
        ':warranty_months' => $warrantyMonths,
        ':warranty_type' => $warrantyType ?: null,
        ':has_serial' => $hasSerial,
        ':unit' => $unit,
        ':is_flash_sale' => $isFlashSale,
        ':is_active' => $isActive
    ]);
    
    $productId = $pdo->lastInsertId();
    
    // ============================================
    // 3. HANDLE PRODUCT VARIANTS
    // ============================================
    
    $variantIds = [];
    
    if ($productType === 'simple') {
        // Simple product - create single default variant
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $expense = (float)($_POST['expense'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $offerPrice = !empty($_POST['offer_price']) ? (float)$_POST['offer_price'] : null;
        $stockQuantity = (int)($_POST['stock_quantity'] ?? 0);
        $sku = trim($_POST['sku'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        
        // Auto-generate SKU if empty
        if (empty($sku)) {
            $sku = generateSku($productId);
        }
        
        $variantStmt = $pdo->prepare("
            INSERT INTO product_variants (
                product_id, name, sku, barcode, price, offer_price,
                cost_price, expense, stock_quantity, is_default, status, created_at
            ) VALUES (
                :product_id, :name, :sku, :barcode, :price, :offer_price,
                :cost_price, :expense, :stock_quantity, 1, 1, NOW()
            )
        ");
        
        $variantStmt->execute([
            ':product_id' => $productId,
            ':name' => 'Default',
            ':sku' => $sku,
            ':barcode' => $barcode ?: null,
            ':price' => $price,
            ':offer_price' => $offerPrice,
            ':cost_price' => $costPrice,
            ':expense' => $expense,
            ':stock_quantity' => $stockQuantity
        ]);
        
        $variantIds[] = [
            'id' => $pdo->lastInsertId(),
            'stock' => $stockQuantity,
            'name' => 'Default'
        ];
        
    } else {
        // Variable product - create multiple variants
        $variations = $_POST['variations'] ?? [];
        
        if (empty($variations)) {
            throw new Exception('Variable products require at least one variation');
        }
        
        $variantStmt = $pdo->prepare("
            INSERT INTO product_variants (
                product_id, name, sku, barcode, price, offer_price,
                cost_price, expense, stock_quantity, variant_image, is_default, status, created_at
            ) VALUES (
                :product_id, :name, :sku, :barcode, :price, :offer_price,
                :cost_price, :expense, :stock_quantity, :variant_image, :is_default, 1, NOW()
            )
        ");
        
        foreach ($variations as $index => $variation) {
            $varName = trim($variation['name'] ?? '');
            if (empty($varName)) continue;
            
            $varSku = trim($variation['sku'] ?? '');
            if (empty($varSku)) {
                $varSku = generateSku($productId, $index + 1);
            }
            
            $varCost = (float)($variation['cost'] ?? 0);
            $varExpense = (float)($variation['expense'] ?? 0);
            $varPrice = (float)($variation['price'] ?? 0);
            $varOfferPrice = !empty($variation['offer_price']) ? (float)$variation['offer_price'] : null;
            $varStock = (int)($variation['stock'] ?? 0);
            $isDefault = $index === 0 ? 1 : 0;
            
            // Handle variant image upload
            $variantImage = null;
            if (isset($_FILES['variation_images']['name'][$index]) && !empty($_FILES['variation_images']['name'][$index])) {
                $variantImage = uploadVariantImage($_FILES['variation_images'], $index, $productId);
            }
            
            $variantStmt->execute([
                ':product_id' => $productId,
                ':name' => $varName,
                ':sku' => $varSku,
                ':barcode' => null,
                ':price' => $varPrice,
                ':offer_price' => $varOfferPrice,
                ':cost_price' => $varCost,
                ':expense' => $varExpense,
                ':stock_quantity' => $varStock,
                ':variant_image' => $variantImage,
                ':is_default' => $isDefault
            ]);
            
            $variantId = $pdo->lastInsertId();
            
            $variantIds[] = [
                'id' => $variantId,
                'stock' => $varStock,
                'name' => $varName
            ];
            
            // Handle variant attributes
            if (!empty($variation['attributes'])) {
                $attributes = json_decode($variation['attributes'], true);
                if (is_array($attributes)) {
                    saveVariantAttributes($pdo, $variantId, $attributes);
                }
            }
        }
    }
    
    // ============================================
    // 4. HANDLE SERIAL NUMBERS
    // ============================================
    
    $serialsInserted = 0;
    
    if ($hasSerial && isset($_POST['serials']) && is_array($_POST['serials'])) {
        $serials = array_filter(array_map('trim', $_POST['serials']));
        
        if (!empty($serials)) {
            $serialStmt = $pdo->prepare("
                INSERT INTO product_serials (
                    product_id, variant_id, serial_number, status,
                    warranty_start, warranty_end, created_at
                ) VALUES (
                    :product_id, :variant_id, :serial_number, 'available',
                    :warranty_start, :warranty_end, NOW()
                )
            ");
            
            // For simple products, use the first (only) variant
            $defaultVariantId = $variantIds[0]['id'] ?? null;
            
            // Calculate warranty dates
            $warrantyStart = date('Y-m-d');
            $warrantyEnd = $warrantyMonths > 0 ? date('Y-m-d', strtotime("+{$warrantyMonths} months")) : null;
            
            foreach ($serials as $serial) {
                if (empty($serial)) continue;
                
                // Check for duplicate serial
                $checkStmt = $pdo->prepare("SELECT id FROM product_serials WHERE serial_number = :serial");
                $checkStmt->execute([':serial' => $serial]);
                
                if ($checkStmt->fetch()) {
                    // Log duplicate but continue
                    error_log("Duplicate serial number skipped: {$serial}");
                    continue;
                }
                
                $serialStmt->execute([
                    ':product_id' => $productId,
                    ':variant_id' => $defaultVariantId,
                    ':serial_number' => $serial,
                    ':warranty_start' => $warrantyStart,
                    ':warranty_end' => $warrantyEnd
                ]);
                
                $serialsInserted++;
            }
        }
    }
    
    // ============================================
    // 5. HANDLE PRODUCT IMAGES
    // ============================================
    
    $imagesUploaded = 0;
    $primaryImageIndex = (int)($_POST['primary_image_index'] ?? 0);
    
    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $imageStmt = $pdo->prepare("
            INSERT INTO product_images (product_id, image_path, is_primary, is_thumbnail, sort_order, created_at)
            VALUES (:product_id, :image_path, :is_primary, :is_thumbnail, :sort_order, NOW())
        ");
        
        $uploadDir = '../../uploads/products/' . $productId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($_FILES['product_images']['name'] as $index => $fileName) {
            if (empty($fileName)) continue;
            
            $tmpName = $_FILES['product_images']['tmp_name'][$index];
            $fileType = $_FILES['product_images']['type'][$index];
            $fileSize = $_FILES['product_images']['size'][$index];
            
            // Validate file
            if (!validateImageFile($fileType, $fileSize)) {
                continue;
            }
            
            // Generate unique filename
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = uniqid('img_') . '.' . strtolower($extension);
            $targetPath = $uploadDir . $newFileName;
            $dbPath = 'uploads/products/' . $productId . '/' . $newFileName;
            
            if (move_uploaded_file($tmpName, $targetPath)) {
                $isPrimary = ($index === $primaryImageIndex) ? 1 : 0;
                
                $imageStmt->execute([
                    ':product_id' => $productId,
                    ':image_path' => $dbPath,
                    ':is_primary' => $isPrimary,
                    ':is_thumbnail' => $isPrimary, // Primary is also thumbnail
                    ':sort_order' => $index
                ]);
                
                $imagesUploaded++;
            }
        }
    }
    
    // ============================================
    // 6. RECORD STOCK MOVEMENT
    // ============================================
    
    foreach ($variantIds as $variant) {
        if ($variant['stock'] > 0) {
            $stockStmt = $pdo->prepare("
                INSERT INTO stock_movements (
                    product_id, variant_id, quantity, movement_type, source, note, created_at
                ) VALUES (
                    :product_id, :variant_id, :quantity, 'in', 'adjustment', :note, NOW()
                )
            ");
            
            $stockStmt->execute([
                ':product_id' => $productId,
                ':variant_id' => $variant['id'],
                ':quantity' => $variant['stock'],
                ':note' => 'Initial stock on product creation'
            ]);
        }
    }
    
    // ============================================
    // 7. COMMIT TRANSACTION
    // ============================================
    
    $pdo->commit();
    
    // Success response
    jsonResponse([
        'success' => true,
        'message' => 'Product created successfully',
        'product_id' => $productId,
        'slug' => $slug,
        'variants_created' => count($variantIds),
        'serials_inserted' => $serialsInserted,
        'images_uploaded' => $imagesUploaded
    ]);
    
} catch (Exception $e) {
    // Rollback on any error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Product Save Error: ' . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Generate URL-friendly slug
 */
function generateSlug($pdo, $name) {
    // Convert to lowercase, replace spaces with hyphens
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Ensure uniqueness
    $baseSlug = $slug;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        
        if (!$stmt->fetch()) {
            break;
        }
        
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Generate SKU
 */
function generateSku($productId, $variantIndex = null) {
    $prefix = 'TH';
    $sku = $prefix . str_pad($productId, 6, '0', STR_PAD_LEFT);
    
    if ($variantIndex !== null) {
        $sku .= '-V' . $variantIndex;
    }
    
    return $sku;
}

/**
 * Validate image file
 */
function validateImageFile($mimeType, $fileSize) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    return in_array($mimeType, $allowedTypes) && $fileSize <= $maxSize;
}

/**
 * Upload variant image
 */
function uploadVariantImage($files, $index, $productId) {
    $fileName = $files['name'][$index];
    $tmpName = $files['tmp_name'][$index];
    $fileType = $files['type'][$index];
    $fileSize = $files['size'][$index];
    
    if (!validateImageFile($fileType, $fileSize)) {
        return null;
    }
    
    $uploadDir = '../../uploads/products/' . $productId . '/variants/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = 'var_' . uniqid() . '.' . strtolower($extension);
    $targetPath = $uploadDir . $newFileName;
    $dbPath = 'uploads/products/' . $productId . '/variants/' . $newFileName;
    
    if (move_uploaded_file($tmpName, $targetPath)) {
        return $dbPath;
    }
    
    return null;
}

/**
 * Save variant attributes to junction table
 */
function saveVariantAttributes($pdo, $variantId, $attributes) {
    $stmt = $pdo->prepare("
        INSERT INTO product_variant_attributes (variant_id, attribute_id, attribute_value_id, created_at)
        VALUES (:variant_id, :attribute_id, :attribute_value_id, NOW())
    ");
    
    foreach ($attributes as $attr) {
        if (isset($attr['attrId']) && isset($attr['value'])) {
            // Find or create attribute value
            $valueId = findOrCreateAttributeValue($pdo, $attr['attrId'], $attr['value']);
            
            if ($valueId) {
                $stmt->execute([
                    ':variant_id' => $variantId,
                    ':attribute_id' => $attr['attrId'],
                    ':attribute_value_id' => $valueId
                ]);
            }
        }
    }
}

/**
 * Find existing attribute value or create new one
 */
function findOrCreateAttributeValue($pdo, $attributeId, $value) {
    $slug = strtolower(preg_replace('/[^a-z0-9]/', '-', $value));
    
    // Check if exists
    $stmt = $pdo->prepare("
        SELECT id FROM attribute_values WHERE attribute_id = :attr_id AND (value = :value OR slug = :slug)
    ");
    $stmt->execute([
        ':attr_id' => $attributeId,
        ':value' => $value,
        ':slug' => $slug
    ]);
    
    $existing = $stmt->fetch();
    if ($existing) {
        return $existing['id'];
    }
    
    // Create new
    $insertStmt = $pdo->prepare("
        INSERT INTO attribute_values (attribute_id, value, slug, created_at)
        VALUES (:attr_id, :value, :slug, NOW())
    ");
    
    $insertStmt->execute([
        ':attr_id' => $attributeId,
        ':value' => $value,
        ':slug' => $slug
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
