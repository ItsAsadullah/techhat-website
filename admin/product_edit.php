<?php
require_once '../core/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: products.php");
    exit;
}

// Fetch Product Data
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php?msg=not_found");
    exit;
}

// Fetch Variants
$stmtVar = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$stmtVar->execute([$id]);
$variants = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

// Fetch Images
$stmtImg = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$stmtImg->execute([$id]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

// Fetch Flash Sale
$stmtFS = $pdo->prepare("
    SELECT fs.*, fsi.discount_percentage 
    FROM flash_sales fs 
    JOIN flash_sale_items fsi ON fs.id = fsi.flash_sale_id 
    WHERE fsi.product_id = ? AND fs.is_active = 1 
    LIMIT 1
");
$stmtFS->execute([$id]);
$flashSale = $stmtFS->fetch(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update Basic Info
            $title = trim($_POST['title']);
            $description = $_POST['description'];
            $specifications = $_POST['specifications'];
            $video_url = trim($_POST['video_url']);
            $badge_text = trim($_POST['badge_text']);
            $warranty_type = trim($_POST['warranty_type']);
            $warranty_period = trim($_POST['warranty_period']);
            $category_id = !empty($_POST['sub_category_id']) ? $_POST['sub_category_id'] : $_POST['category_id'];
            $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE products SET title = ?, category_id = ?, description = ?, specifications = ?, video_url = ?, badge_text = ?, warranty_type = ?, warranty_period = ?, is_flash_sale = ? WHERE id = ?");
            $stmt->execute([$title, $category_id, $description, $specifications, $video_url, $badge_text, $warranty_type, $warranty_period, $is_flash_sale, $id]);

            // 2. Update Flash Sale
            // First, deactivate existing for this product (simplification)
            if ($flashSale) {
                $pdo->prepare("UPDATE flash_sales SET is_active = 0 WHERE id = ?")->execute([$flashSale['id']]);
            }

            if ($is_flash_sale) {
                $fs_discount = $_POST['fs_discount'];
                $fs_start = $_POST['fs_start'] ?: null;
                $fs_end = $_POST['fs_end'] ?: null;
                
                $stmtFS = $pdo->prepare("INSERT INTO flash_sales (title, discount_percentage, start_at, end_at, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmtFS->execute(["Flash Sale: $title", $fs_discount, $fs_start, $fs_end]);
                $fs_id = $pdo->lastInsertId();

                $stmtFSI = $pdo->prepare("INSERT INTO flash_sale_items (flash_sale_id, product_id, discount_percentage) VALUES (?, ?, ?)");
                $stmtFSI->execute([$fs_id, $id, $fs_discount]);
            }

            // 3. Update Variants
            $uploadDir = '../uploads/products/';
            
            // Get existing variant IDs to detect deletions
            $existingVarIds = array_column($variants, 'id');
            $keptVarIds = [];

            if (isset($_POST['variants'])) {
                foreach ($_POST['variants'] as $index => $v) {
                    $vid = $v['id'] ?? null;
                    $color = trim($v['color']);
                    $color_code = trim($v['color_code'] ?? '');
                    if (!empty($v['color_code_text'])) {
                        $color_code = trim($v['color_code_text']);
                    }
                    
                    $size = trim($v['size']);
                    $storage = trim($v['storage'] ?? '');
                    $sim_type = trim($v['sim_type'] ?? '');
                    
                    // Generate Name
                    $parts = [];
                    if ($color) $parts[] = $color;
                    if ($size) $parts[] = $size;
                    if ($storage) $parts[] = $storage;
                    if ($sim_type) $parts[] = $sim_type;
                    
                    $name = implode(' - ', $parts);
                    if (empty($name)) $name = "Default";

                    $price = $v['price'];
                    $offer = $v['offer_price'] ?: null;
                    $stock = $v['stock'];
                    $sku = $v['sku'];
                    
                    // Image Handling
                    $variantImgPath = $v['existing_image'] ?? null;
                    if (!empty($_FILES['variant_images']['name'][$index])) {
                        $tmp = $_FILES['variant_images']['tmp_name'][$index];
                        $ext = pathinfo($_FILES['variant_images']['name'][$index], PATHINFO_EXTENSION);
                        $fname = "v_{$id}_{$index}_" . time() . ".$ext";
                        move_uploaded_file($tmp, $uploadDir . $fname);
                        $variantImgPath = 'uploads/products/' . $fname;
                    }

                    if ($vid && in_array($vid, $existingVarIds)) {
                        // Update
                        $stmtVar = $pdo->prepare("UPDATE product_variants SET name = ?, color = ?, color_code = ?, size = ?, storage = ?, sim_type = ?, price = ?, offer_price = ?, stock_quantity = ?, sku = ?, image_path = ? WHERE id = ?");
                        $stmtVar->execute([$name, $color, $color_code, $size, $storage, $sim_type, $price, $offer, $stock, $sku, $variantImgPath, $vid]);
                        $keptVarIds[] = $vid;
                    } else {
                        // Insert New
                        $stmtVar = $pdo->prepare("INSERT INTO product_variants (product_id, name, color, color_code, size, storage, sim_type, price, offer_price, stock_quantity, sku, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtVar->execute([$id, $name, $color, $color_code, $size, $storage, $sim_type, $price, $offer, $stock, $sku, $variantImgPath]);
                        
                        // Initial Stock
                        $newVarId = $pdo->lastInsertId();
                        $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, quantity, movement_type, source) VALUES (?, ?, ?, 'in', 'adjustment')")->execute([$id, $newVarId, $stock]);
                    }
                }
            }

            // Delete removed variants
            $varsToDelete = array_diff($existingVarIds, $keptVarIds);
            if (!empty($varsToDelete)) {
                $inQuery = implode(',', array_fill(0, count($varsToDelete), '?'));
                $pdo->prepare("DELETE FROM product_variants WHERE id IN ($inQuery)")->execute(array_values($varsToDelete));
            }

            // 4. Gallery Images
            // Handle Deletions
            if (isset($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $imgId) {
                    $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);
                    // Optional: Unlink file
                }
            }

            // Handle New Uploads
            if (!empty($_FILES['gallery']['name'][0])) {
                foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                    if (empty($tmp)) continue;
                    $ext = pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION);
                    $fname = "p_{$id}_new_{$i}_" . time() . ".$ext";
                    move_uploaded_file($tmp, $uploadDir . $fname);
                    
                    $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 0)");
                    $stmtImg->execute([$id, 'uploads/products/' . $fname]);
                }
            }

            $pdo->commit();
            header("Location: products.php?msg=updated");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/partials/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - TechHat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .admin-content { margin-left: 260px; padding: 2rem; background: #f3f4f6; min-height: 100vh; }
        @media (max-width: 768px) { .admin-content { margin-left: 0; padding: 1rem; } }
        
        .editor-toolbar button { padding: 5px 10px; border-radius: 4px; margin-right: 2px; }
        .editor-toolbar button:hover { background: #e5e7eb; }
        #editor-content { min-height: 300px; outline: none; }
        #editor-content img { max-width: 100%; height: auto; }
        .drop-zone { border: 2px dashed #cbd5e1; transition: all 0.2s; }
        .drop-zone.dragover { border-color: #3b82f6; background: #eff6ff; }
    </style>
</head>
<body class="font-sans text-gray-800">

<div class="admin-content">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Edit Product</h1>
            <a href="products.php" class="text-gray-600 hover:text-gray-900"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Basic Details -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-semibold mb-4">Basic Information</h2>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Title</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($product['title']); ?>" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Badge / Label (e.g. Summer Sale)</label>
                            <input type="text" name="badge_text" value="<?php echo htmlspecialchars($product['badge_text'] ?? ''); ?>" placeholder="Optional" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Warranty Type</label>
                                <select name="warranty_type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                    <option value="No Warranty" <?php echo ($product['warranty_type'] ?? '') === 'No Warranty' ? 'selected' : ''; ?>>No Warranty</option>
                                    <option value="Brand Warranty" <?php echo ($product['warranty_type'] ?? '') === 'Brand Warranty' ? 'selected' : ''; ?>>Brand Warranty</option>
                                    <option value="Service Warranty" <?php echo ($product['warranty_type'] ?? '') === 'Service Warranty' ? 'selected' : ''; ?>>Service Warranty</option>
                                    <option value="Parts Warranty" <?php echo ($product['warranty_type'] ?? '') === 'Parts Warranty' ? 'selected' : ''; ?>>Parts Warranty</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Warranty Period</label>
                                <input type="text" name="warranty_period" value="<?php echo htmlspecialchars($product['warranty_period'] ?? ''); ?>" placeholder="e.g. 1 Year" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <div class="border rounded-lg overflow-hidden">
                                <div class="bg-gray-50 border-b p-2 flex flex-wrap gap-1 editor-toolbar">
                                    <button type="button" onclick="execCmd('bold')" title="Bold"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" onclick="execCmd('italic')" title="Italic"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" onclick="execCmd('underline')" title="Underline"><i class="bi bi-type-underline"></i></button>
                                    <span class="w-px h-6 bg-gray-300 mx-1"></span>
                                    <button type="button" onclick="execCmd('justifyLeft')" title="Align Left"><i class="bi bi-text-left"></i></button>
                                    <button type="button" onclick="execCmd('justifyCenter')" title="Align Center"><i class="bi bi-text-center"></i></button>
                                    <button type="button" onclick="execCmd('justifyRight')" title="Align Right"><i class="bi bi-text-right"></i></button>
                                    <span class="w-px h-6 bg-gray-300 mx-1"></span>
                                    <button type="button" onclick="execCmd('insertUnorderedList')" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" onclick="execCmd('insertOrderedList')" title="Numbered List"><i class="bi bi-list-ol"></i></button>
                                    <span class="w-px h-6 bg-gray-300 mx-1"></span>
                                    <button type="button" onclick="promptImage()" title="Insert Image"><i class="bi bi-image"></i></button>
                                    <button type="button" onclick="execCmd('createLink', prompt('Enter URL:', 'http://'))" title="Link"><i class="bi bi-link"></i></button>
                                </div>
                                <div id="editor-content" contenteditable="true" class="p-4 bg-white"><?php echo $product['description']; ?></div>
                                <textarea name="description" id="hidden-description" class="hidden"></textarea>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Video URL</label>
                            <input type="text" name="video_url" value="<?php echo htmlspecialchars($product['video_url']); ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                    </div>

                    <!-- Variants -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Product Variants</h2>
                            <button type="button" onclick="addVariant()" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded hover:bg-blue-100 font-medium">+ Add Variant</button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="p-3">Image</th>
                                        <th class="p-3">Color & Code</th>
                                        <th class="p-3">Size</th>
                                        <th class="p-3">Storage</th>
                                        <th class="p-3">Sim</th>
                                        <th class="p-3">Price</th>
                                        <th class="p-3">Offer</th>
                                        <th class="p-3">Stock</th>
                                        <th class="p-3">SKU</th>
                                        <th class="p-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="variants-body">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Specifications -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-semibold mb-4">Specifications</h2>
                        <textarea name="specifications" rows="4" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none font-mono text-sm"><?php echo htmlspecialchars($product['specifications']); ?></textarea>
                    </div>

                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-all shadow-lg shadow-blue-200">
                            Update Product
                        </button>
                    </div>

                    <!-- Category -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-semibold mb-4">Category</h2>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Main Category</label>
                                <select name="category_id" id="category_id" class="w-full px-3 py-2 border rounded-lg outline-none" onchange="loadSubCategories(this.value)">
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Sub Category</label>
                                <select name="sub_category_id" id="sub_category_id" class="w-full px-3 py-2 border rounded-lg outline-none" disabled>
                                    <option value="">Select Sub Category</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Gallery -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-semibold mb-4">Product Gallery</h2>
                        
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <?php foreach ($images as $img): ?>
                            <div class="relative aspect-square bg-gray-100 rounded overflow-hidden border group">
                                <img src="../<?php echo $img['image_path']; ?>" class="w-full h-full object-cover">
                                <label class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity">
                                    <input type="checkbox" name="delete_images[]" value="<?php echo $img['id']; ?>" class="hidden" onchange="this.parentElement.parentElement.style.opacity = this.checked ? '0.3' : '1'">
                                    ×
                                </label>
                                <?php if ($img['is_primary']): ?>
                                    <span class="absolute bottom-0 left-0 right-0 bg-blue-600 text-white text-[10px] text-center py-0.5">Primary</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="drop-zone p-6 rounded-lg text-center cursor-pointer mb-4" id="gallery-drop">
                            <i class="bi bi-cloud-upload text-3xl text-gray-400"></i>
                            <p class="text-sm text-gray-500 mt-2">Add More Images</p>
                            <input type="file" name="gallery[]" id="gallery-input" multiple accept="image/*" class="hidden">
                        </div>
                        <div id="gallery-preview" class="grid grid-cols-3 gap-2"></div>
                    </div>

                    <!-- Flash Sale -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Flash Sale</h2>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_flash_sale" id="is_flash_sale" class="sr-only peer" onchange="toggleFlashSale()" <?php echo $product['is_flash_sale'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                            </label>
                        </div>
                        
                        <div id="flash-sale-options" class="<?php echo $product['is_flash_sale'] ? '' : 'hidden'; ?> space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Discount (%)</label>
                                <input type="number" name="fs_discount" value="<?php echo $flashSale['discount_percentage'] ?? ''; ?>" class="w-full px-3 py-2 border rounded-lg outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                                <input type="datetime-local" name="fs_start" value="<?php echo isset($flashSale['start_at']) ? date('Y-m-d\TH:i', strtotime($flashSale['start_at'])) : ''; ?>" class="w-full px-3 py-2 border rounded-lg outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                                <input type="datetime-local" name="fs_end" value="<?php echo isset($flashSale['end_at']) ? date('Y-m-d\TH:i', strtotime($flashSale['end_at'])) : ''; ?>" class="w-full px-3 py-2 border rounded-lg outline-none">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // --- Rich Text Editor ---
    function execCmd(command, value = null) { document.execCommand(command, false, value); }
    function promptImage() { const url = prompt('Enter Image URL:', 'http://'); if (url) execCmd('insertImage', url); }
    document.getElementById('productForm').onsubmit = function() { document.getElementById('hidden-description').value = document.getElementById('editor-content').innerHTML; };

    // --- Category Loading ---
    const categories = <?php 
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    ?>;
    const currentCatId = <?php echo $product['category_id']; ?>;
    
    const catSelect = document.getElementById('category_id');
    const subCatSelect = document.getElementById('sub_category_id');

    // Init Categories
    categories.filter(c => !c.parent_id).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        catSelect.appendChild(opt);
    });

    // Set Selected Category
    // Check if currentCatId is a subcategory
    const currentCat = categories.find(c => c.id == currentCatId);
    if (currentCat) {
        if (currentCat.parent_id) {
            catSelect.value = currentCat.parent_id;
            loadSubCategories(currentCat.parent_id);
            subCatSelect.value = currentCatId;
        } else {
            catSelect.value = currentCatId;
            loadSubCategories(currentCatId);
        }
    }

    function loadSubCategories(parentId) {
        subCatSelect.innerHTML = '<option value="">Select Sub Category</option>';
        subCatSelect.disabled = true;
        if (!parentId) return;
        const subs = categories.filter(c => c.parent_id == parentId);
        if (subs.length > 0) {
            subCatSelect.disabled = false;
            subs.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                subCatSelect.appendChild(opt);
            });
        }
    }

    // --- Variants ---
    let variantCount = 0;
    const existingVariants = <?php echo json_encode($variants); ?>;

    function addVariant(data = null) {
        const tbody = document.getElementById('variants-body');
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-50 hover:bg-gray-50';
        
        // Parse Name to Color/Size if possible (Simple heuristic)
        let color = '', size = '';
        if (data) {
            color = data.color || '';
            size = data.size || '';
        }

        const imgPreview = data && data.image_path ? `../${data.image_path}` : '';
        const imgClass = imgPreview ? '' : 'hidden';
        const iconClass = imgPreview ? 'hidden' : '';
        const colorCode = data ? (data.color_code || '') : '';

        tr.innerHTML = `
            <td class="p-2 align-top">
                <input type="hidden" name="variants[${variantCount}][id]" value="${data ? data.id : ''}">
                <input type="hidden" name="variants[${variantCount}][existing_image]" value="${data ? data.image_path : ''}">
                <div class="w-12 h-12 bg-gray-100 rounded border flex items-center justify-center cursor-pointer overflow-hidden relative" onclick="document.getElementById('v_img_${variantCount}').click()">
                    <img id="v_preview_${variantCount}" src="${imgPreview}" class="w-full h-full object-cover ${imgClass}">
                    <i class="bi bi-camera text-gray-400 ${iconClass}" id="v_icon_${variantCount}"></i>
                </div>
                <input type="file" name="variant_images[${variantCount}]" id="v_img_${variantCount}" class="hidden" accept="image/*" onchange="previewVariantImage(this, ${variantCount})">
            </td>
            <td class="p-2 align-top">
                <input type="text" name="variants[${variantCount}][color]" value="${color}" placeholder="Name" class="w-full px-2 py-1 border rounded text-sm mb-1">
                <div class="flex items-center gap-1">
                    <input type="color" name="variants[${variantCount}][color_code]" value="${colorCode}" class="w-8 h-8 p-0 border rounded cursor-pointer" title="Color Code" onchange="this.nextElementSibling.value = this.value">
                    <input type="text" name="variants[${variantCount}][color_code_text]" value="${colorCode}" placeholder="#000000" class="w-20 px-2 py-1 border rounded text-xs" onchange="this.previousElementSibling.value = this.value; this.previousElementSibling.dispatchEvent(new Event('change'));">
                </div>
            </td>
            <td class="p-2 align-top"><input type="text" name="variants[${variantCount}][size]" value="${size}" placeholder="Size" class="w-full px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top"><input type="text" name="variants[${variantCount}][storage]" value="${data ? (data.storage || '') : ''}" placeholder="Storage" class="w-full px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top"><input type="text" name="variants[${variantCount}][sim_type]" value="${data ? (data.sim_type || '') : ''}" placeholder="Sim" class="w-full px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top"><input type="number" name="variants[${variantCount}][price]" value="${data ? data.price : ''}" placeholder="0.00" required class="w-20 px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top"><input type="number" name="variants[${variantCount}][offer_price]" value="${data ? data.offer_price : ''}" placeholder="0.00" class="w-20 px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top"><input type="number" name="variants[${variantCount}][stock]" value="${data ? data.stock_quantity : ''}" placeholder="0" required class="w-16 px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top"><input type="text" name="variants[${variantCount}][sku]" value="${data ? data.sku : ''}" placeholder="SKU" class="w-20 px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(tr);
        variantCount++;
    }

    function previewVariantImage(input, id) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(`v_preview_${id}`).src = e.target.result;
                document.getElementById(`v_preview_${id}`).classList.remove('hidden');
                document.getElementById(`v_icon_${id}`).classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Load existing variants
    if (existingVariants.length > 0) {
        existingVariants.forEach(v => addVariant(v));
    } else {
        addVariant();
    }

    // --- Gallery Drag & Drop ---
    const dropZone = document.getElementById('gallery-drop');
    const galleryInput = document.getElementById('gallery-input');
    const galleryPreview = document.getElementById('gallery-preview');

    dropZone.addEventListener('click', () => galleryInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('dragover'); });
    dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); handleFiles(e.dataTransfer.files); });
    galleryInput.addEventListener('change', (e) => { handleFiles(e.target.files); });

    function handleFiles(files) {
        if (files !== galleryInput.files) { try { galleryInput.files = files; } catch(e) {} }
        galleryPreview.innerHTML = '';
        Array.from(files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'relative aspect-square bg-gray-100 rounded overflow-hidden border group';
                div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover"><button type="button" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">×</button>`;
                galleryPreview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }

    function toggleFlashSale() {
        const checked = document.getElementById('is_flash_sale').checked;
        const options = document.getElementById('flash-sale-options');
        if (checked) options.classList.remove('hidden'); else options.classList.add('hidden');
    }
</script>
</body>
</html>
