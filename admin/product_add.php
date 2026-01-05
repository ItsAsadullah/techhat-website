<?php
require_once '../core/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Basic Info
            $title = trim($_POST['title']);
            $description = $_POST['description']; // HTML content
            $specifications = $_POST['specifications'];
            $video_url = trim($_POST['video_url']);
            $badge_text = trim($_POST['badge_text']);
            $warranty_type = trim($_POST['warranty_type']);
            $warranty_period = trim($_POST['warranty_period']);
            $category_id = !empty($_POST['sub_category_id']) ? $_POST['sub_category_id'] : $_POST['category_id'];
            $brand_id = !empty($_POST['brand_id']) ? $_POST['brand_id'] : null;
            $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;

            // Slug Generation
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
            $stmtCheck->execute([$slug]);
            if ($stmtCheck->fetch()) {
                $slug .= '-' . time();
            }

            // Insert Product
            $stmt = $pdo->prepare("INSERT INTO products (title, slug, category_id, brand_id, description, specifications, video_url, badge_text, warranty_type, warranty_period, is_flash_sale) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $category_id, $brand_id, $description, $specifications, $video_url, $badge_text, $warranty_type, $warranty_period, $is_flash_sale]);
            $product_id = $pdo->lastInsertId();

            // 2. Flash Sale
            if ($is_flash_sale) {
                $fs_discount = $_POST['fs_discount'];
                $fs_start = $_POST['fs_start'] ?: null;
                $fs_end = $_POST['fs_end'] ?: null;
                
                // Create Flash Sale Record
                $stmtFS = $pdo->prepare("INSERT INTO flash_sales (title, discount_percentage, start_at, end_at, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmtFS->execute(["Flash Sale: $title", $fs_discount, $fs_start, $fs_end]);
                $fs_id = $pdo->lastInsertId();

                // Link Item
                $stmtFSI = $pdo->prepare("INSERT INTO flash_sale_items (flash_sale_id, product_id, discount_percentage) VALUES (?, ?, ?)");
                $stmtFSI->execute([$fs_id, $product_id, $fs_discount]);
            }

            // 3. Variants
            $uploadDir = '../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            if (isset($_POST['variants'])) {
                foreach ($_POST['variants'] as $index => $v) {
                    $color = trim($v['color']);
                    $color_code = trim($v['color_code'] ?? '');
                    // If color code text is provided, use it (might be more precise or user typed it)
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
                    
                    // Variant Image Upload
                    $variantImgPath = null;
                    if (!empty($_FILES['variant_images']['name'][$index])) {
                        $tmp = $_FILES['variant_images']['tmp_name'][$index];
                        $ext = pathinfo($_FILES['variant_images']['name'][$index], PATHINFO_EXTENSION);
                        $fname = "v_{$product_id}_{$index}_" . time() . ".$ext";
                        move_uploaded_file($tmp, $uploadDir . $fname);
                        $variantImgPath = 'uploads/products/' . $fname;
                    }

                    $stmtVar = $pdo->prepare("INSERT INTO product_variants (product_id, name, color, color_code, size, storage, sim_type, price, offer_price, stock_quantity, sku, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtVar->execute([$product_id, $name, $color, $color_code, $size, $storage, $sim_type, $price, $offer, $stock, $sku, $variantImgPath]);
                    
                    // Initial Stock Movement
                    $varId = $pdo->lastInsertId();
                    $stmtMove = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, quantity, movement_type, source) VALUES (?, ?, ?, 'in', 'adjustment')");
                    $stmtMove->execute([$product_id, $varId, $stock]);
                }
            }

            // 4. Gallery Images
            if (!empty($_FILES['gallery']['name'][0])) {
                foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                    if (empty($tmp)) continue;
                    $ext = pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION);
                    $fname = "p_{$product_id}_{$i}_" . time() . ".$ext";
                    move_uploaded_file($tmp, $uploadDir . $fname);
                    
                    $is_primary = ($i === 0) ? 1 : 0;
                    $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)");
                    $stmtImg->execute([$product_id, 'uploads/products/' . $fname, $is_primary]);
                }
            }

            $pdo->commit();
            header("Location: products.php?msg=added");
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
    <title>Add Product - TechHat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .admin-content { margin-left: 260px; padding: 2rem; background: #f3f4f6; min-height: 100vh; }
        @media (max-width: 768px) { .admin-content { margin-left: 0; padding: 1rem; } }
        
        /* Select2 Custom Styles */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
            padding-left: 12px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 8px;
        }
        
        /* Custom Editor Styles */
        .editor-toolbar button { padding: 5px 10px; border-radius: 4px; margin-right: 2px; }
        .editor-toolbar button:hover { background: #e5e7eb; }
        .editor-toolbar button.active { background: #d1d5db; }
        #editor-content { min-height: 300px; outline: none; }
        #editor-content img { max-width: 100%; height: auto; }
        
        /* Drag Drop Zone */
        .drop-zone { border: 2px dashed #cbd5e1; transition: all 0.2s; }
        .drop-zone.dragover { border-color: #3b82f6; background: #eff6ff; }
    </style>
</head>
<body class="font-sans text-gray-800">

<div class="admin-content">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Add New Product</h1>
            <a href="products.php" class="text-gray-600 hover:text-gray-900"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column: Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Basic Details -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-semibold mb-4">Basic Information</h2>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Title</label>
                            <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Badge / Label (e.g. Summer Sale)</label>
                            <input type="text" name="badge_text" placeholder="Optional" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Warranty Type</label>
                                <select name="warranty_type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                    <option value="No Warranty">No Warranty</option>
                                    <option value="Brand Warranty">Brand Warranty</option>
                                    <option value="Service Warranty">Service Warranty</option>
                                    <option value="Parts Warranty">Parts Warranty</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Warranty Period</label>
                                <input type="text" name="warranty_period" placeholder="e.g. 1 Year" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <div class="border rounded-lg overflow-hidden">
                                <!-- Custom Toolbar -->
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
                                <!-- Editor Area -->
                                <div id="editor-content" contenteditable="true" class="p-4 bg-white"></div>
                                <textarea name="description" id="hidden-description" class="hidden"></textarea>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Video URL (YouTube)</label>
                            <input type="text" name="video_url" placeholder="https://youtube.com/embed/..." class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                    </div>

                    <!-- Variants & Pricing -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold flex items-center gap-2">
                                <i class="bi bi-boxes text-blue-600"></i>
                                Product Variants
                                <span class="text-xs text-gray-500 font-normal">(Auto-configured based on category)</span>
                            </h2>
                            <button type="button" onclick="addVariant()" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded hover:bg-blue-100 font-medium">+ Add Variant</button>
                        </div>
                        
                        <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs">
                            <strong class="text-blue-800"><i class="bi bi-info-circle"></i> Smart Variants:</strong>
                            <span class="text-blue-700">Variant fields automatically adjust based on your selected category. Select a category first to see relevant options.</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm" id="variants-table">
                                <thead class="bg-gray-50 text-gray-600" id="variant-header">
                                    <tr>
                                        <th class="p-3">Image</th>
                                        <th class="p-3">Color & Code</th>
                                        <th class="p-3">Size</th>
                                        <th class="p-3">Storage</th>
                                        <th class="p-3">Sim</th>
                                        <th class="p-3">Price</th>
                                        <th class="p-3">Offer Price</th>
                                        <th class="p-3">Stock</th>
                                        <th class="p-3">SKU</th>
                                        <th class="p-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="variants-body">
                                    <!-- Dynamic Rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Specifications -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <i class="bi bi-list-ul text-blue-600"></i>
                            Product Specifications
                        </h2>
                        <p class="text-xs text-gray-500 mb-4">Add key specifications. Format: Key: Value (one per line)</p>
                        
                        <!-- Specification Templates by Category -->
                        <div class="mb-4">
                            <button type="button" onclick="loadSpecTemplate('laptop')" class="text-xs bg-gray-100 hover:bg-blue-50 px-3 py-1.5 rounded mr-2 mb-2">
                                📱 Laptop Template
                            </button>
                            <button type="button" onclick="loadSpecTemplate('phone')" class="text-xs bg-gray-100 hover:bg-blue-50 px-3 py-1.5 rounded mr-2 mb-2">
                                📱 Phone Template
                            </button>
                            <button type="button" onclick="loadSpecTemplate('monitor')" class="text-xs bg-gray-100 hover:bg-blue-50 px-3 py-1.5 rounded mr-2 mb-2">
                                🖥️ Monitor Template
                            </button>
                            <button type="button" onclick="loadSpecTemplate('watch')" class="text-xs bg-gray-100 hover:bg-blue-50 px-3 py-1.5 rounded mr-2 mb-2">
                                ⌚ Watch Template
                            </button>
                        </div>
                        
                        <textarea name="specifications" id="specificationsField" rows="12" 
                                  class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none font-mono text-sm"
                                  placeholder="Example:&#10;Processor: Intel Core i5-12th Gen&#10;RAM: 8GB DDR4&#10;Storage: 512GB SSD&#10;Display: 15.6&quot; FHD&#10;Graphics: Intel UHD&#10;Battery: 3-cell 42Wh&#10;OS: Windows 11&#10;Warranty: 2 Years"></textarea>
                        
                        <div class="mt-3 text-xs text-gray-500 bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <strong class="text-blue-800">💡 Tips:</strong>
                            <ul class="list-disc list-inside mt-1 space-y-1">
                                <li>Enter one specification per line</li>
                                <li>Format: <code class="bg-white px-1 rounded">Spec Name: Spec Value</code></li>
                                <li>Common specs will show on product cards</li>
                            </ul>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Organization & Media -->
                <div class="space-y-6">
                    
                    <!-- Publish Action -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-all shadow-lg shadow-blue-200">
                            Publish Product
                        </button>
                    </div>

                    <!-- Category -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Category</h2>
                            <button type="button" onclick="openAddCategoryModal()" class="text-xs bg-blue-500 text-white px-3 py-1.5 rounded hover:bg-blue-600">
                                <i class="bi bi-plus-circle"></i> Add New
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Main Category</label>
                                <select name="category_id" id="category_id" class="w-full searchable-select" onchange="loadSubCategories(this.value)">
                                    <option value="">Select Category</option>
                                    <?php
                                    $stmtCats = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name ASC");
                                    while($cat = $stmtCats->fetch()) {
                                        echo "<option value='{$cat['id']}'>".htmlspecialchars($cat['name'])."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Sub Category</label>
                                <select name="sub_category_id" id="sub_category_id" class="w-full searchable-select" disabled>
                                    <option value="">Select Sub Category</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Brand -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Brand</h2>
                            <button type="button" onclick="openAddBrandModal()" class="text-xs bg-blue-500 text-white px-3 py-1.5 rounded hover:bg-blue-600">
                                <i class="bi bi-plus-circle"></i> Add New
                            </button>
                        </div>
                        <select name="brand_id" id="brand_id" class="w-full searchable-select">
                            <option value="">Select Brand</option>
                            <?php
                            $stmtBrands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC");
                            while($b = $stmtBrands->fetch()) {
                                echo "<option value='{$b['id']}'>".htmlspecialchars($b['name'])."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <!-- Unified Image Gallery -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <i class="bi bi-images text-blue-600"></i>
                            Product Images
                        </h2>
                        <p class="text-xs text-gray-500 mb-3">Upload main gallery images. Variant-specific images can be added in variants section below.</p>
                        
                        <div class="drop-zone p-6 rounded-lg text-center cursor-pointer mb-4 border-2 border-dashed border-gray-300 hover:border-blue-500 transition-colors" id="gallery-drop">
                            <i class="bi bi-cloud-upload text-4xl text-gray-400"></i>
                            <p class="text-sm text-gray-600 mt-2 font-medium">Drag & Drop or Click to Upload</p>
                            <p class="text-xs text-gray-400 mt-1">Supports: JPG, PNG, WebP</p>
                            <input type="file" name="gallery[]" id="gallery-input" multiple accept="image/*" class="hidden">
                        </div>

                        <div id="gallery-preview" class="grid grid-cols-3 gap-3">
                            <!-- Image previews will appear here -->
                        </div>
                        
                        <!-- All Images Gallery (Gallery + Variant Images) -->
                        <div id="all-images-gallery" class="mt-6 pt-6 border-t border-gray-200 hidden">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <i class="bi bi-grid-3x3-gap"></i>
                                All Product Images
                                <span class="text-xs text-gray-500 font-normal">(Gallery + Variants)</span>
                            </h3>
                            <div id="unified-gallery" class="grid grid-cols-4 gap-2">
                                <!-- All images preview -->
                            </div>
                            <div class="mt-3">
                                <label class="text-xs text-gray-600">
                                    <i class="bi bi-star-fill text-yellow-500"></i>
                                    <strong>Primary/Thumbnail Image:</strong> Click on an image to set as product thumbnail
                                </label>
                                <input type="hidden" name="primary_image_index" id="primary-image-index" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Flash Sale -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Flash Sale</h2>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_flash_sale" id="is_flash_sale" class="sr-only peer" onchange="toggleFlashSale()">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                            </label>
                        </div>
                        
                        <div id="flash-sale-options" class="hidden space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Discount (%)</label>
                                <input type="number" name="fs_discount" class="w-full px-3 py-2 border rounded-lg outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                                <input type="datetime-local" name="fs_start" class="w-full px-3 py-2 border rounded-lg outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                                <input type="datetime-local" name="fs_end" class="w-full px-3 py-2 border rounded-lg outline-none">
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
    function execCmd(command, value = null) {
        document.execCommand(command, false, value);
    }
    
    function promptImage() {
        const url = prompt('Enter Image URL:', 'http://');
        if (url) execCmd('insertImage', url);
    }

    document.getElementById('productForm').onsubmit = function() {
        document.getElementById('hidden-description').value = document.getElementById('editor-content').innerHTML;
    };

    // --- Category Loading ---
    // Category-Specific Variant Configuration
    const variantConfig = {
        // Mobile/Smartphone
        'mobile': {fields: ['color', 'storage', 'ram'], labels: {color: 'Color', storage: 'Storage', ram: 'RAM'}},
        'smartphone': {fields: ['color', 'storage', 'ram'], labels: {color: 'Color', storage: 'Storage (GB)', ram: 'RAM (GB)'}},
        'phone': {fields: ['color', 'storage', 'ram'], labels: {color: 'Color', storage: 'Storage', ram: 'RAM'}},
        
        // Electronics
        'laptop': {fields: ['color', 'storage', 'ram', 'processor'], labels: {color: 'Color', storage: 'Storage', ram: 'RAM', processor: 'Processor'}},
        'desktop': {fields: ['processor', 'ram', 'storage', 'graphics'], labels: {processor: 'Processor', ram: 'RAM', storage: 'Storage', graphics: 'Graphics'}},
        'tablet': {fields: ['color', 'storage', 'ram'], labels: {color: 'Color', storage: 'Storage', ram: 'RAM'}},
        'monitor': {fields: ['size', 'resolution', 'refresh_rate'], labels: {size: 'Size', resolution: 'Resolution', refresh_rate: 'Refresh Rate'}},
        
        // Networking
        'router': {fields: ['ports', 'speed', 'frequency'], labels: {ports: 'Ports', speed: 'Speed', frequency: 'Frequency'}},
        'modem': {fields: ['type', 'speed'], labels: {type: 'Type', speed: 'Speed'}},
        
        // Wearables
        'watch': {fields: ['color', 'size', 'strap'], labels: {color: 'Color', size: 'Size', strap: 'Strap Type'}},
        'smartwatch': {fields: ['color', 'size', 'strap'], labels: {color: 'Color', size: 'Size', strap: 'Strap'}},
        
        // Audio
        'headphone': {fields: ['color', 'type', 'connectivity'], labels: {color: 'Color', type: 'Type', connectivity: 'Connectivity'}},
        'earphone': {fields: ['color', 'type'], labels: {color: 'Color', type: 'Type'}},
        'speaker': {fields: ['color', 'power', 'connectivity'], labels: {color: 'Color', power: 'Power (W)', connectivity: 'Connectivity'}},
        'airpod': {fields: ['color', 'generation'], labels: {color: 'Color', generation: 'Generation'}},
        
        // Accessories
        'charger': {fields: ['type', 'power', 'ports'], labels: {type: 'Type', power: 'Power (W)', ports: 'Ports'}},
        'cable': {fields: ['length', 'type'], labels: {length: 'Length', type: 'Type'}},
        'powerbank': {fields: ['capacity', 'ports'], labels: {capacity: 'Capacity (mAh)', ports: 'Ports'}},
        
        // TV & Display
        'tv': {fields: ['size', 'resolution', 'smart'], labels: {size: 'Size (inch)', resolution: 'Resolution', smart: 'Smart TV'}},
        
        // Gaming
        'console': {fields: ['storage', 'edition'], labels: {storage: 'Storage', edition: 'Edition'}},
        'controller': {fields: ['color', 'type'], labels: {color: 'Color', type: 'Type'}},
        
        // Default fallback
        'default': {fields: ['color', 'size', 'storage'], labels: {color: 'Color', size: 'Size', storage: 'Storage'}}
    };

    let currentVariantFields = ['color', 'size', 'storage', 'sim_type']; // Default fields
    let currentVariantLabels = {color: 'Color', size: 'Size', storage: 'Storage', sim_type: 'SIM Type'};

    // Fetch categories on load
    const categories = <?php 
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    ?>;
    
    const catSelect = document.getElementById('category_id');
    const subCatSelect = document.getElementById('sub_category_id');

    // Populate Main Categories
    categories.filter(c => !c.parent_id).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        catSelect.appendChild(opt);
    });

    function loadSubCategories(parentId) {
        subCatSelect.innerHTML = '<option value="">Select Sub Category</option>';
        subCatSelect.disabled = true;
        
        if (!parentId) {
            updateVariantFields('default');
            return;
        }

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

        // Update variant fields based on selected category
        const selectedCat = categories.find(c => c.id == parentId);
        if (selectedCat) {
            updateVariantFields(selectedCat.slug || selectedCat.name.toLowerCase());
        }
    }

    // Update variant fields based on category
    function updateVariantFields(categoryKey) {
        // Find matching configuration (check slug/name in lowercase)
        const key = categoryKey.toLowerCase().replace(/[^a-z0-9]/g, '');
        const config = variantConfig[key] || variantConfig['default'];
        
        currentVariantFields = config.fields;
        currentVariantLabels = config.labels;

        // Update variant table header
        updateVariantTableHeader();
        
        // Clear existing variants
        document.getElementById('variants-body').innerHTML = '';
        variantCount = 0;
        
        // Show info message
        const infoBox = document.querySelector('.variant-info-box');
        if (infoBox) {
            infoBox.querySelector('.variant-fields-list').textContent = 
                Object.values(currentVariantLabels).join(', ');
        }
    }

    // Update variant table header dynamically
    function updateVariantTableHeader() {
        const thead = document.querySelector('#variants-body').closest('table').querySelector('thead tr');
        
        // Keep Image, Actions, Price, Stock columns - only update middle section
        let headerHTML = '<th class="p-2 text-left text-xs font-medium text-gray-600 uppercase">Image</th>';
        
        // Add dynamic variant fields
        currentVariantFields.forEach(field => {
            headerHTML += `<th class="p-2 text-left text-xs font-medium text-gray-600 uppercase">${currentVariantLabels[field]}</th>`;
        });
        
        headerHTML += `
            <th class="p-2 text-left text-xs font-medium text-gray-600 uppercase">Price</th>
            <th class="p-2 text-left text-xs font-medium text-gray-600 uppercase">Stock</th>
            <th class="p-2 text-left text-xs font-medium text-gray-600 uppercase">Actions</th>
        `;
        
        thead.innerHTML = headerHTML;
    }

    // --- Variants ---
    let variantCount = 0;
    function addVariant() {
        const tbody = document.getElementById('variants-body');
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-50 hover:bg-gray-50';
        
        // Start with image column
        let rowHTML = `
            <td class="p-2 align-top">
                <div class="w-12 h-12 bg-gray-100 rounded border flex items-center justify-center cursor-pointer overflow-hidden relative" onclick="document.getElementById('v_img_${variantCount}').click()">
                    <img id="v_preview_${variantCount}" class="w-full h-full object-cover hidden">
                    <i class="bi bi-camera text-gray-400" id="v_icon_${variantCount}"></i>
                </div>
                <input type="file" name="variant_images[${variantCount}]" id="v_img_${variantCount}" class="hidden" accept="image/*" onchange="previewVariantImage(this, ${variantCount})">
            </td>
        `;
        
        // Add dynamic variant fields
        currentVariantFields.forEach(field => {
            const label = currentVariantLabels[field];
            
            // Special handling for color field (with color picker)
            if (field === 'color') {
                rowHTML += `
                    <td class="p-2 align-top">
                        <input type="text" name="variants[${variantCount}][${field}]" placeholder="${label}" class="w-full px-2 py-1 border rounded text-sm mb-1">
                        <div class="flex items-center gap-1">
                            <input type="color" name="variants[${variantCount}][color_code]" class="w-8 h-8 p-0 border rounded cursor-pointer" title="Color Code" onchange="this.nextElementSibling.value = this.value">
                            <input type="text" name="variants[${variantCount}][color_code_text]" placeholder="#000000" class="w-20 px-2 py-1 border rounded text-xs" onchange="this.previousElementSibling.value = this.value; this.previousElementSibling.dispatchEvent(new Event('change'));">
                        </div>
                    </td>
                `;
            } else {
                // Regular text input for other fields
                rowHTML += `
                    <td class="p-2 align-top">
                        <input type="text" name="variants[${variantCount}][${field}]" placeholder="${label}" class="w-full px-2 py-1 border rounded text-sm">
                    </td>
                `;
            }
        });
        
        // Add price and stock columns
        rowHTML += `
            <td class="p-2 align-top"><input type="number" name="variants[${variantCount}][price]" placeholder="0.00" required class="w-20 px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top"><input type="number" name="variants[${variantCount}][stock]" placeholder="0" required class="w-16 px-2 py-1 border rounded text-sm"></td>
            <td class="p-2 align-top text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700"><i class="bi bi-trash"></i></button></td>
        `;
        
        tr.innerHTML = rowHTML;
        tbody.appendChild(tr);
        variantCount++;
    }

    function previewVariantImage(input, id) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById(`v_preview_${id}`);
                const icon = document.getElementById(`v_icon_${id}`);
                
                if (preview && icon) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    icon.classList.add('hidden');
                }
                
                // Update unified gallery
                updateUnifiedGallery();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Update unified gallery with all images (gallery + variants)
    function updateUnifiedGallery() {
        const unifiedGalleryContainer = document.getElementById('unified-gallery');
        const allImagesSection = document.getElementById('all-images-gallery');
        
        if (!unifiedGalleryContainer) return;
        
        unifiedGalleryContainer.innerHTML = '';
        let imageIndex = 0;
        
        // Add gallery images
        const galleryImages = galleryPreview.querySelectorAll('img');
        galleryImages.forEach((img, idx) => {
            const div = createImageThumbnail(img.src, imageIndex, 'Gallery ' + (idx + 1));
            unifiedGalleryContainer.appendChild(div);
            imageIndex++;
        });
        
        // Add variant images
        document.querySelectorAll('[id^="v_preview_"]:not(.hidden)').forEach((img, idx) => {
            const div = createImageThumbnail(img.src, imageIndex, 'Variant ' + (idx + 1));
            unifiedGalleryContainer.appendChild(div);
            imageIndex++;
        });
        
        // Show/hide unified gallery section
        if (imageIndex > 0) {
            allImagesSection.classList.remove('hidden');
        } else {
            allImagesSection.classList.add('hidden');
        }
    }

    function createImageThumbnail(src, index, label) {
        const div = document.createElement('div');
        div.className = 'relative group cursor-pointer border-2 rounded-lg overflow-hidden transition-all hover:border-blue-500';
        div.dataset.index = index;
        
        const isPrimary = index === parseInt(document.getElementById('primary-image-index').value || 0);
        if (isPrimary) {
            div.classList.add('border-yellow-500', 'ring-2', 'ring-yellow-400');
        } else {
            div.classList.add('border-gray-200');
        }
        
        div.innerHTML = `
            <img src="${src}" class="w-full aspect-square object-cover">
            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all flex items-center justify-center">
                ${isPrimary ? '<i class="bi bi-star-fill text-yellow-400 text-2xl"></i>' : '<i class="bi bi-star text-white text-2xl opacity-0 group-hover:opacity-100"></i>'}
            </div>
            <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-70 text-white text-xs px-2 py-1 text-center">
                ${label}
            </div>
        `;
        
        div.addEventListener('click', function() {
            // Set as primary
            document.getElementById('primary-image-index').value = index;
            updateUnifiedGallery();
        });
        
        return div;
    }

    // Add one variant by default
    addVariant();

    // --- Gallery Drag & Drop ---
    const dropZone = document.getElementById('gallery-drop');
    const galleryInput = document.getElementById('gallery-input');
    const galleryPreview = document.getElementById('gallery-preview');

    dropZone.addEventListener('click', () => galleryInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    galleryInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        // Note: This is a visual preview only. 
        // For actual upload, we rely on the input[type=file]. 
        // If using Drag & Drop, we need to manually assign files to the input or use AJAX.
        // For simplicity in this PHP form, we'll just append to the input if possible (modern browsers) 
        // or just show preview if it came from the input change.
        
        if (files !== galleryInput.files) {
            // If dropped, we try to assign to input (works in some browsers)
            try {
                galleryInput.files = files;
            } catch(e) {
                console.log("Cannot programmatically set files");
            }
        }

        galleryPreview.innerHTML = '';
        Array.from(files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'relative aspect-square bg-gray-100 rounded-lg overflow-hidden border-2 border-gray-200 group hover:border-blue-500 transition-all';
                div.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <button type="button" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm opacity-0 group-hover:opacity-100 transition-opacity shadow-lg hover:bg-red-600" onclick="this.parentElement.remove(); updateUnifiedGallery();">×</button>
                    ${index === 0 ? '<span class="absolute bottom-0 left-0 right-0 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-xs text-center py-1 font-semibold"><i class="bi bi-star-fill"></i> Primary</span>' : ''}
                `;
                galleryPreview.appendChild(div);
                
                // Update unified gallery after each image is added
                updateUnifiedGallery();
            };
            reader.readAsDataURL(file);
        });
    }

    // --- Flash Sale Toggle ---
    function toggleFlashSale() {
        const checked = document.getElementById('is_flash_sale').checked;
        const options = document.getElementById('flash-sale-options');
        if (checked) {
            options.classList.remove('hidden');
        } else {
            options.classList.add('hidden');
        }
    }

    // --- Specification Templates ---
    function loadSpecTemplate(type) {
        const specField = document.getElementById('specificationsField');
        const templates = {
            laptop: `Processor: Intel Core i5-12th Gen
RAM: 8GB DDR4
Storage: 512GB SSD
Display: 15.6" FHD (1920x1080)
Graphics: Intel UHD Graphics
Battery: 3-cell 42Wh
OS: Windows 11 Home
Connectivity: Wi-Fi 6, Bluetooth 5.1
Ports: 2x USB 3.2, 1x USB-C, HDMI, Audio Jack
Weight: 1.7kg
Warranty: 2 Years International`,
            phone: `Processor: Snapdragon 888
RAM: 8GB
Storage: 128GB
Display: 6.5" AMOLED FHD+
Camera: 64MP + 8MP + 2MP
Front Camera: 32MP
Battery: 4500mAh Fast Charging
OS: Android 13
Network: 5G
SIM: Dual SIM
Warranty: 1 Year Official`,
            monitor: `Display: 27" IPS Full HD
Resolution: 1920x1080
Refresh Rate: 75Hz
Response Time: 5ms
Brightness: 250 cd/m²
Contrast Ratio: 1000:1
Viewing Angle: 178°/178°
Connectivity: HDMI, VGA, DisplayPort
VESA Mount: 100x100mm
Warranty: 3 Years`,
            watch: `Display: 1.4" AMOLED
Resolution: 454x454
Battery: 300mAh (7 days)
Water Resistance: 5ATM
Connectivity: Bluetooth 5.0
Sensors: Heart Rate, SpO2, Accelerometer
GPS: Built-in
Compatibility: Android & iOS
Strap: Silicone
Warranty: 1 Year`
        };
        
        if (templates[type]) {
            specField.value = templates[type];
        }
    }

    // --- Initialize Select2 ---
    $(document).ready(function() {
        $('.searchable-select').select2({
            placeholder: 'Search...',
            allowClear: true,
            width: '100%'
        });
    });

    // --- Add Category Modal ---
    function openAddCategoryModal() {
        const categoryName = prompt('Enter new category name:');
        if (categoryName && categoryName.trim()) {
            addNewCategory(categoryName.trim());
        }
    }

    function addNewCategory(name) {
        fetch('../api/add_category.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name: name, parent_id: null})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const option = new Option(name, data.id, true, true);
                $('#category_id').append(option).trigger('change');
                alert('Category added successfully!');
            } else {
                alert('Error: ' + (data.message || 'Failed to add category'));
            }
        })
        .catch(err => alert('Error: ' + err));
    }

    // --- Add Brand Modal ---
    function openAddBrandModal() {
        const brandName = prompt('Enter new brand name:');
        if (brandName && brandName.trim()) {
            addNewBrand(brandName.trim());
        }
    }

    function addNewBrand(name) {
        fetch('../api/add_brand.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name: name})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const option = new Option(name, data.id, true, true);
                $('#brand_id').append(option).trigger('change');
                alert('Brand added successfully!');
            } else {
                alert('Error: ' + (data.message || 'Failed to add brand'));
            }
        })
        .catch(err => alert('Error: ' + err));
    }

    // --- Load Subcategories ---
    function loadSubCategories(parentId) {
        const subSelect = document.getElementById('sub_category_id');
        subSelect.innerHTML = '<option value="">Select Sub Category</option>';
        
        if (!parentId) {
            subSelect.disabled = true;
            return;
        }

        fetch(`../api/get_subcategories.php?parent_id=${parentId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.categories) {
                    subSelect.disabled = false;
                    data.categories.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.name;
                        subSelect.appendChild(option);
                    });
                    $('#sub_category_id').select2('destroy').select2({placeholder: 'Search...', allowClear: true, width: '100%'});
                }
            });
    }

    // Initialize variant table header on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateVariantTableHeader();
    });
</script>
</body>
</html>
