<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;

        $category_id = !empty($_POST['sub_category_id']) ? (int) $_POST['sub_category_id'] : (int) ($_POST['category_id'] ?? 0);
        $catSlug = null;
        if ($category_id) {
            $stmtCat = $pdo->prepare("SELECT slug FROM categories WHERE id = ? LIMIT 1");
            $stmtCat->execute([$category_id]);
            $catSlug = $stmtCat->fetchColumn() ?: null;
        }

        $titleSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slugParts = array_filter([$catSlug, $titleSlug]);
        $slug = implode('-', $slugParts);
        if ($slug === '') {
            throw new Exception('Title is required to generate slug.');
        }

        $variantNames = $_POST['variant_name'] ?? [];
        $variantPrices = $_POST['variant_price'] ?? [];
        $variantOffers = $_POST['variant_offer'] ?? [];
        $variantStocks = $_POST['variant_stock'] ?? [];
        $variantSkus = $_POST['variant_sku'] ?? [];
        $variantCosts = $_POST['variant_cost'] ?? [];
        $variantExpenses = $_POST['variant_expense'] ?? [];
        $variantCount = count($variantNames);
        if ($variantCount === 0 || $variantCount !== count($variantPrices) || $variantCount !== count($variantStocks)) {
            throw new Exception('Variants are required with price and stock.');
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!empty($_FILES['thumb_image']['name'])) {
            if ($_FILES['thumb_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Thumbnail upload failed.');
            }
            if (!is_uploaded_file($_FILES['thumb_image']['tmp_name'])) {
                throw new Exception('Invalid thumbnail upload attempt.');
            }
            $mime = finfo_file($finfo, $_FILES['thumb_image']['tmp_name']);
            if (!in_array($mime, $allowedMime, true)) {
                throw new Exception('Unsupported thumbnail type. Use JPG, PNG, or WEBP.');
            }
        }
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $idx => $tmp_name) {
                if ($_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) {
                    throw new Exception('Image upload failed for one of the files.');
                }
                if (!is_uploaded_file($tmp_name)) {
                    throw new Exception('Invalid image upload attempt.');
                }
                $mime = finfo_file($finfo, $tmp_name);
                if (!in_array($mime, $allowedMime, true)) {
                    throw new Exception('Unsupported image type. Use JPG, PNG, or WEBP.');
                }
            }
        }
        if ($finfo) {
            finfo_close($finfo);
        }
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO products (title, slug, category_id, description, video_url, is_flash_sale) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $slug, $category_id ?: null, $description, $video_url, $is_flash_sale]);
        $product_id = $pdo->lastInsertId();

        $stmtVar = $pdo->prepare("INSERT INTO product_variants (product_id, name, price, offer_price, cost_price, expense, stock_quantity, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtStock = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, quantity, movement_type, source, created_at) VALUES (?, ?, ?, 'in', 'adjustment', NOW())");
        for ($i = 0; $i < $variantCount; $i++) {
            $v_name = trim($variantNames[$i]);
            $v_price = $variantPrices[$i] === '' ? 0 : (float) $variantPrices[$i];
            $v_offer = $variantOffers[$i] !== '' ? (float) $variantOffers[$i] : null;
            $v_cost = $variantCosts[$i] === '' ? 0 : (float) $variantCosts[$i];
            $v_expense = $variantExpenses[$i] === '' ? 0 : (float) $variantExpenses[$i];
            $v_stock = $variantStocks[$i] === '' ? 0 : (int) $variantStocks[$i];
            $v_sku = trim($variantSkus[$i]);

            if ($v_name === '' || $v_price < 0 || $v_stock < 0) {
                throw new Exception('Variant name, non-negative price, and stock are required.');
            }

            $stmtVar->execute([$product_id, $v_name, $v_price, $v_offer, $v_cost, $v_expense, $v_stock, $v_sku]);
            $variant_id = $pdo->lastInsertId();
            $stmtStock->execute([$product_id, $variant_id, $v_stock]);
        }

        $uploadDir = realpath(__DIR__ . '/../uploads/products');
        if (!$uploadDir) {
            mkdir(__DIR__ . '/../uploads/products', 0775, true);
            $uploadDir = realpath(__DIR__ . '/../uploads/products');
        }

        $thumbnailUploaded = !empty($_FILES['thumb_image']['name']);
        $thumbPath = null;
        $primarySet = false;
        $thumbSet = false;

        $stmtImg = $pdo->prepare('INSERT INTO product_images (product_id, image_path, is_primary, is_thumbnail) VALUES (?, ?, ?, ?)');

        if ($thumbnailUploaded) {
            $mime = mime_content_type($_FILES['thumb_image']['tmp_name']);
            $thumbName = uniqid('thumb_', true) . '.jpg';
            $thumbTarget = $uploadDir . DIRECTORY_SEPARATOR . $thumbName;
            compress_image($_FILES['thumb_image']['tmp_name'], $thumbTarget, $mime);
            if (!file_exists($thumbTarget)) {
                move_uploaded_file($_FILES['thumb_image']['tmp_name'], $thumbTarget);
            }
            if (file_exists($thumbTarget)) {
                $thumbPath = 'uploads/products/' . $thumbName;
                $stmtImg->execute([$product_id, $thumbPath, 0, 1]);
                $thumbSet = true;
            }
        }

        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $mime = mime_content_type($tmp_name);
                $file_name = uniqid('p_', true) . '.jpg';
                $target = $uploadDir . DIRECTORY_SEPARATOR . $file_name;
                compress_image($tmp_name, $target, $mime);
                if (!file_exists($target)) {
                    move_uploaded_file($tmp_name, $target);
                }
                if (file_exists($target)) {
                    $is_primary = $primarySet ? 0 : 1;
                    $is_thumbnail = $thumbSet ? 0 : 1;
                    $stmtImg->execute([$product_id, 'uploads/products/' . $file_name, $is_primary, $is_thumbnail]);
                    if (!$primarySet) {
                        $primarySet = true;
                    }
                    if (!$thumbSet) {
                        $thumbSet = true;
                    }
                }
            }
        }

        if (!$primarySet && $thumbSet && $thumbPath) {
            // If only thumbnail uploaded, also mark it as primary
            $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE product_id = ? AND image_path = ?')
                ->execute([$product_id, $thumbPath]);
            $primarySet = true;
        }

        $pdo->commit();
        header("Location: products.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

function compress_image(string $source, string $destination, string $mime, int $quality = 78): void
{
    if (!is_file($source)) {
        return;
    }
    if (!function_exists('imagecreatefromjpeg')) {
        return;
    }
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $png = imagecreatefrompng($source);
            // Convert PNG to truecolor to allow jpeg encoding
            $image = imagecreatetruecolor(imagesx($png), imagesy($png));
            imagecopy($image, $png, 0, 0, 0, 0, imagesx($png), imagesy($png));
            imagedestroy($png);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return;
    }
    if ($image) {
        imagejpeg($image, $destination, $quality);
        imagedestroy($image);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product - TechHat Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            margin: 0;
        }
        .content { padding: 30px; }
        .form-group { margin-bottom: 15px; position: relative; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; box-sizing: border-box; color: #111; background: #fff; }
        .variant-row { display: flex; gap: 10px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .variant-row input { flex: 1; }
        .btn { padding: 10px 20px; background: #333; color: #fff; border: none; cursor: pointer; }
        
        /* Dropdown Styles */
        .dropdown-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .dropdown-item {
            padding: 8px;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background: #f4f4f4;
        }
        .dropdown-add {
            padding: 8px;
            color: #007bff;
            cursor: pointer;
            border-top: 1px solid #eee;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <h1>Add New Product</h1>
            <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="form-group">
                    <label>Product Title</label>
                    <input type="text" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                </div>

                <!-- Category Searchable Dropdown -->
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" id="cat-search" placeholder="Search or Add Category..." autocomplete="off">
                    <input type="hidden" name="category_id" id="category_id" value="<?php echo isset($_POST['category_id']) ? (int)$_POST['category_id'] : ''; ?>">
                    <div id="cat-results" class="dropdown-results"></div>
                </div>

                <!-- Sub Category Searchable Dropdown -->
                <div class="form-group">
                    <label>Sub Category (Optional)</label>
                    <input type="text" id="sub-cat-search" placeholder="Search or Add Sub Category..." autocomplete="off" disabled>
                    <input type="hidden" name="sub_category_id" id="sub_category_id" value="<?php echo isset($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : ''; ?>">
                    <div id="sub-cat-results" class="dropdown-results"></div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Video URL (YouTube Embed)</label>
                    <input type="text" name="video_url" value="<?php echo isset($_POST['video_url']) ? htmlspecialchars($_POST['video_url']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Thumbnail Image (optional)</label>
                    <input type="file" name="thumb_image" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Product Images (Select Multiple)</label>
                    <input type="file" name="images[]" multiple accept="image/*">
                </div>

                <h3>Variants & Stock</h3>
                <div id="variants-container">
                    <?php
                    $postedVariants = $_POST['variant_name'] ?? [];
                    $rows = count($postedVariants) > 0 ? count($postedVariants) : 1;
                    for ($i = 0; $i < $rows; $i++):
                        $vName = $postedVariants[$i] ?? '';
                        $vPrice = $_POST['variant_price'][$i] ?? '';
                        $vOffer = $_POST['variant_offer'][$i] ?? '';
                        $vStock = $_POST['variant_stock'][$i] ?? '';
                        $vSku = $_POST['variant_sku'][$i] ?? '';
                        $vCost = $_POST['variant_cost'][$i] ?? '';
                        $vExpense = $_POST['variant_expense'][$i] ?? '';
                    ?>
                    <div class="variant-row">
                        <input type="text" name="variant_name[]" placeholder="Name (e.g. Red-XL)" value="<?php echo htmlspecialchars($vName); ?>" required>
                        <input type="number" name="variant_price[]" placeholder="Selling Price" step="0.01" value="<?php echo htmlspecialchars($vPrice); ?>" required>
                        <input type="number" name="variant_offer[]" placeholder="Offer Price" step="0.01" value="<?php echo htmlspecialchars($vOffer); ?>">
                        <input type="number" name="variant_cost[]" placeholder="Purchase Price" step="0.01" value="<?php echo htmlspecialchars($vCost); ?>">
                        <input type="number" name="variant_expense[]" placeholder="Expense" step="0.01" value="<?php echo htmlspecialchars($vExpense); ?>">
                        <input type="number" name="variant_stock[]" placeholder="Stock Qty" step="1" value="<?php echo htmlspecialchars($vStock); ?>">
                        <input type="text" name="variant_sku[]" placeholder="SKU" value="<?php echo htmlspecialchars($vSku); ?>">
                        <span class="variant-profit" style="min-width:120px; align-self:center; font-weight:600;"></span>
                        <?php if ($rows > 1): ?><button type="button" onclick="this.parentElement.remove()" style="background:red; color:white; border:none; cursor:pointer;">X</button><?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="button" id="add-variant-btn" style="margin-bottom: 20px;">+ Add Another Variant</button>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_flash_sale" <?php echo isset($_POST['is_flash_sale']) ? 'checked' : ''; ?>> Add to Flash Sale
                    </label>
                </div>

                <button type="submit" class="btn">Save Product</button>
            </form>
        </div>
    </div>

    <script>
        // Variant Logic
        function attachProfitCalc(row) {
            const inputs = row.querySelectorAll('input');
            const profitLabel = row.querySelector('.variant-profit');
            function recalc() {
                const price = parseFloat(row.querySelector('[name="variant_price[]"]').value) || 0;
                const offer = parseFloat(row.querySelector('[name="variant_offer[]"]').value) || price;
                const cost = parseFloat(row.querySelector('[name="variant_cost[]"]').value) || 0;
                const expense = parseFloat(row.querySelector('[name="variant_expense[]"]').value) || 0;
                const sale = offer > 0 ? offer : price;
                const profit = sale - cost - expense;
                if (profitLabel) {
                    profitLabel.textContent = 'Margin: ' + profit.toFixed(2);
                    profitLabel.style.color = profit >= 0 ? 'green' : 'red';
                }
            }
            inputs.forEach(inp => inp.addEventListener('input', recalc));
            recalc();
        }

        document.querySelectorAll('#variants-container .variant-row').forEach(attachProfitCalc);

        document.getElementById('add-variant-btn').addEventListener('click', function() {
            const container = document.getElementById('variants-container');
            const row = document.createElement('div');
            row.className = 'variant-row';
            row.innerHTML = `
                <input type="text" name="variant_name[]" placeholder="Name (e.g. Red-XL)" required>
                <input type="number" name="variant_price[]" placeholder="Selling Price" step="0.01" required>
                <input type="number" name="variant_offer[]" placeholder="Offer Price" step="0.01">
                <input type="number" name="variant_cost[]" placeholder="Purchase Price" step="0.01">
                <input type="number" name="variant_expense[]" placeholder="Expense" step="0.01">
                <input type="number" name="variant_stock[]" placeholder="Stock Qty" step="1">
                <input type="text" name="variant_sku[]" placeholder="SKU">
                <span class="variant-profit" style="min-width:120px; align-self:center; font-weight:600;"></span>
                <button type="button" onclick="this.parentElement.remove()" style="background:red; color:white; border:none; cursor:pointer;">X</button>
            `;
            container.appendChild(row);
            attachProfitCalc(row);
        });

        // Category Logic
        const catSearch = document.getElementById('cat-search');
        const catId = document.getElementById('category_id');
        const catResults = document.getElementById('cat-results');
        
        const subCatSearch = document.getElementById('sub-cat-search');
        const subCatId = document.getElementById('sub_category_id');
        const subCatResults = document.getElementById('sub-cat-results');

        function setupDropdown(input, hiddenInput, resultsDiv, parentIdInput = null) {
            input.addEventListener('focus', function() {
                if (this.value.trim() === '') {
                    this.dispatchEvent(new Event('input'));
                } else {
                    resultsDiv.style.display = 'block';
                }
            });
            input.addEventListener('input', function() {
                const term = this.value;
                const parentId = parentIdInput ? parentIdInput.value : '';
                
                fetch(`api/category_ajax.php?action=search&term=${term}&parent_id=${parentId}`)
                    .then(res => res.json())
                    .then(data => {
                        resultsDiv.innerHTML = '';
                        resultsDiv.style.display = 'block';
                        
                        if (data.success && data.results.length > 0) {
                            data.results.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'dropdown-item';
                                div.textContent = item.name;
                                div.onclick = () => {
                                    input.value = item.name;
                                    hiddenInput.value = item.id;
                                    resultsDiv.style.display = 'none';
                                    if (!parentIdInput) { // If this is main category
                                        subCatSearch.disabled = false;
                                        subCatSearch.value = '';
                                        subCatId.value = '';
                                    }
                                };
                                resultsDiv.appendChild(div);
                            });
                        }

                        // Add New Option
                        const addDiv = document.createElement('div');
                        addDiv.className = 'dropdown-add';
                        addDiv.textContent = `+ Add "${term}"`;
                        addDiv.onclick = () => {
                            const formData = new FormData();
                            formData.append('name', term);
                            if (parentId) formData.append('parent_id', parentId);

                            fetch('api/category_ajax.php?action=add', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(res => {
                                if (res.success) {
                                    input.value = res.name;
                                    hiddenInput.value = res.id;
                                    resultsDiv.style.display = 'none';
                                    if (!parentIdInput) {
                                        subCatSearch.disabled = false;
                                        subCatSearch.value = '';
                                        subCatId.value = '';
                                    }
                                } else {
                                    alert('Error adding category');
                                }
                            });
                        };
                        resultsDiv.appendChild(addDiv);
                    });
            });

            // Hide on click outside
            document.addEventListener('click', function(e) {
                if (e.target !== input && e.target !== resultsDiv) {
                    resultsDiv.style.display = 'none';
                }
            });
        }

        setupDropdown(catSearch, catId, catResults);
        setupDropdown(subCatSearch, subCatId, subCatResults, catId);

    </script>
</body>
</html>