<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: products.php");
    exit;
}

// Fetch Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found");
}

// Fetch Category Info
$catName = '';
$subCatName = '';
$catId = '';
$subCatId = '';

if ($product['category_id']) {
    $stmtCat = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmtCat->execute([$product['category_id']]);
    $currentCat = $stmtCat->fetch();
    
    if ($currentCat) {
        if ($currentCat['parent_id']) {
            // It's a subcategory
            $subCatName = $currentCat['name'];
            $subCatId = $currentCat['id'];
            
            // Fetch Parent
            $stmtParent = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmtParent->execute([$currentCat['parent_id']]);
            $parentCat = $stmtParent->fetch();
            if ($parentCat) {
                $catName = $parentCat['name'];
                $catId = $parentCat['id'];
            }
        } else {
            // It's a main category
            $catName = $currentCat['name'];
            $catId = $currentCat['id'];
        }
    }
}

// Fetch Variants
$stmtVar = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$stmtVar->execute([$id]);
$variants = $stmtVar->fetchAll();

// Fetch Images
$stmtImg = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, is_thumbnail DESC, id ASC");
$stmtImg->execute([$id]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

// Fetch Flash Sale (if any)
$flashSale = null;
$stmtFlash = $pdo->prepare("SELECT fs.id as fs_id, fsi.id as fsi_id, fs.start_at, fs.end_at, COALESCE(fsi.discount_percentage, fs.discount_percentage) as discount, fs.is_active
    FROM flash_sale_items fsi
    JOIN flash_sales fs ON fs.id = fsi.flash_sale_id
    WHERE fsi.product_id = ?
    ORDER BY fs.end_at ASC
    LIMIT 1");
$stmtFlash->execute([$id]);
$flashSale = $stmtFlash->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    try {
        $title = trim($_POST['title'] ?? $product['title']);
        $description = trim($_POST['description'] ?? $product['description']);
        $video_url = trim($_POST['video_url'] ?? $product['video_url']);
        $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;
        $flash_start = $_POST['flash_start'] ?? null;
        $flash_end = $_POST['flash_end'] ?? null;
        $flash_discount = $_POST['flash_discount'] ?? null;

        $category_id = !empty($_POST['sub_category_id']) ? (int) $_POST['sub_category_id'] : (int) ($_POST['category_id'] ?? 0);
        $catSlug = null;
        if ($category_id) {
            $stmtCat = $pdo->prepare("SELECT slug FROM categories WHERE id = ? LIMIT 1");
            $stmtCat->execute([$category_id]);
            $catSlug = $stmtCat->fetchColumn() ?: null;
        }
        $titleSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = implode('-', array_filter([$catSlug, $titleSlug]));
        if ($slug === '') {
            throw new Exception('Title is required to generate slug.');
        }

        $variantNames = $_POST['variant_name'] ?? [];
        $variantPrices = $_POST['variant_price'] ?? [];
        $variantOffers = $_POST['variant_offer'] ?? [];
        $variantStocks = $_POST['variant_stock'] ?? [];
        $variantSkus = $_POST['variant_sku'] ?? [];
        $variantIds = $_POST['variant_id'] ?? [];
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

        $existingStock = [];
        foreach ($variants as $v) {
            $existingStock[$v['id']] = (int) $v['stock_quantity'];
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE products SET title=?, slug=?, category_id=?, description=?, video_url=?, is_flash_sale=? WHERE id=?");
        $stmt->execute([$title, $slug, $category_id ?: null, $description, $video_url, $is_flash_sale, $id]);

        if ($is_flash_sale && $flash_end) {
            if ($flashSale) {
                $stmtFS = $pdo->prepare("UPDATE flash_sales SET start_at=?, end_at=?, discount_percentage=?, is_active=1 WHERE id=?");
                $stmtFS->execute([$flash_start ?: null, $flash_end, $flash_discount ?: null, $flashSale['fs_id']]);
                $stmtFSI = $pdo->prepare("UPDATE flash_sale_items SET discount_percentage=? WHERE id=?");
                $stmtFSI->execute([$flash_discount ?: null, $flashSale['fsi_id']]);
            } else {
                $stmtFS = $pdo->prepare("INSERT INTO flash_sales (title, discount_percentage, start_at, end_at, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmtFS->execute(['Product Flash: ' . $title, $flash_discount ?: null, $flash_start ?: null, $flash_end]);
                $newFsId = $pdo->lastInsertId();
                $stmtFSI = $pdo->prepare("INSERT INTO flash_sale_items (flash_sale_id, product_id, discount_percentage) VALUES (?, ?, ?)");
                $stmtFSI->execute([$newFsId, $id, $flash_discount ?: null]);
            }
        } else {
            $pdo->prepare("DELETE FROM flash_sale_items WHERE product_id = ?")->execute([$id]);
        }

        $existingVarIds = array_column($variants, 'id');
        $postedVarIds = $variantIds;

        $toDelete = array_diff($existingVarIds, $postedVarIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $stmtDel = $pdo->prepare("DELETE FROM product_variants WHERE id IN ($placeholders)");
            $stmtDel->execute(array_values($toDelete));
        }

        $stmtInsert = $pdo->prepare("INSERT INTO product_variants (product_id, name, price, offer_price, cost_price, expense, stock_quantity, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtUpdate = $pdo->prepare("UPDATE product_variants SET name=?, price=?, offer_price=?, cost_price=?, expense=?, stock_quantity=?, sku=? WHERE id=?");
        $stmtStock = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, quantity, movement_type, source, created_at) VALUES (?, ?, ?, ?, 'adjustment', NOW())");
        for ($i = 0; $i < $variantCount; $i++) {
            $v_id = $variantIds[$i] ?? '';
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

            if ($v_id) {
                $currentStock = $existingStock[$v_id] ?? 0;
                $delta = $v_stock - $currentStock;
                if ($v_stock < 0) {
                    throw new Exception('Stock cannot be negative.');
                }
                if ($delta < 0 && abs($delta) > $currentStock) {
                    throw new Exception('Cannot reduce stock below zero for an existing variant.');
                }
                $stmtUpdate->execute([$v_name, $v_price, $v_offer, $v_cost, $v_expense, $v_stock, $v_sku, $v_id]);
                if ($delta !== 0) {
                    $stmtStock->execute([$id, $v_id, abs($delta), $delta > 0 ? 'in' : 'out']);
                }
            } else {
                $stmtInsert->execute([$id, $v_name, $v_price, $v_offer, $v_cost, $v_expense, $v_stock, $v_sku]);
                $new_v_id = $pdo->lastInsertId();
                $stmtStock->execute([$id, $new_v_id, $v_stock, 'in']);
            }
        }

        $uploadDir = realpath(__DIR__ . '/../uploads/products');
        if (!$uploadDir) {
            mkdir(__DIR__ . '/../uploads/products', 0775, true);
            $uploadDir = realpath(__DIR__ . '/../uploads/products');
        }

        // Delete selected images first
        if (isset($_POST['delete_image'])) {
            $stmtDelImg = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            $imagePathById = [];
            foreach ($images as $img) {
                $imagePathById[$img['id']] = $img['image_path'];
            }
            foreach ($_POST['delete_image'] as $imgId) {
                $stmtDelImg->execute([$imgId]);
                if (isset($imagePathById[$imgId])) {
                    $filePath = realpath(__DIR__ . '/../' . $imagePathById[$imgId]);
                    if ($filePath && file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }

        // Assess remaining flags after deletions
        $stmtCurrent = $pdo->prepare('SELECT id, is_primary, is_thumbnail FROM product_images WHERE product_id = ?');
        $stmtCurrent->execute([$id]);
        $currentImages = $stmtCurrent->fetchAll(PDO::FETCH_ASSOC);
        $primarySet = false;
        $thumbSet = false;
        foreach ($currentImages as $ci) {
            if ($ci['is_primary']) {
                $primarySet = true;
            }
            if ($ci['is_thumbnail']) {
                $thumbSet = true;
            }
        }

        $stmtImg = $pdo->prepare('INSERT INTO product_images (product_id, image_path, is_primary, is_thumbnail) VALUES (?, ?, ?, ?)');
        $thumbPath = null;

        // Optional new thumbnail upload
        if (!empty($_FILES['thumb_image']['name'])) {
            // Clear previous thumbnail flags
            $pdo->prepare('UPDATE product_images SET is_thumbnail = 0 WHERE product_id = ?')->execute([$id]);
            $thumbSet = false;

            $mime = mime_content_type($_FILES['thumb_image']['tmp_name']);
            $thumbName = uniqid('thumb_', true) . '.jpg';
            $thumbTarget = $uploadDir . DIRECTORY_SEPARATOR . $thumbName;
            compress_image($_FILES['thumb_image']['tmp_name'], $thumbTarget, $mime);
            if (!file_exists($thumbTarget)) {
                move_uploaded_file($_FILES['thumb_image']['tmp_name'], $thumbTarget);
            }
            if (file_exists($thumbTarget)) {
                $thumbPath = 'uploads/products/' . $thumbName;
                $stmtImg->execute([$id, $thumbPath, $primarySet ? 0 : 1, 1]);
                if (!$primarySet) {
                    $primarySet = true;
                }
                $thumbSet = true;
            }
        }

        // Additional gallery images
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if (empty($tmp_name)) {
                    continue;
                }
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
                    $stmtImg->execute([$id, 'uploads/products/' . $file_name, $is_primary, $is_thumbnail]);
                    if (!$primarySet) {
                        $primarySet = true;
                    }
                    if (!$thumbSet) {
                        $thumbSet = true;
                    }
                }
            }
        }

        // Ensure at least one primary and one thumbnail remain
        $stmtAll = $pdo->prepare('SELECT id, is_primary, is_thumbnail FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, is_thumbnail DESC, id ASC');
        $stmtAll->execute([$id]);
        $allImages = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($allImages)) {
            $hasPrimary = false;
            $hasThumb = false;
            foreach ($allImages as $img) {
                if ($img['is_primary']) {
                    $hasPrimary = true;
                }
                if ($img['is_thumbnail']) {
                    $hasThumb = true;
                }
            }
            if (!$hasPrimary) {
                $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?')->execute([$allImages[0]['id']]);
            }
            if (!$hasThumb) {
                $pdo->prepare('UPDATE product_images SET is_thumbnail = 1 WHERE id = ?')->execute([$allImages[0]['id']]);
            }
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
    <title>Edit Product - TechHat Admin</title>
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
        .img-preview { display: inline-block; margin: 5px; position: relative; }
        .img-preview img { width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; }
        .img-delete { position: absolute; top: 0; right: 0; background: red; color: white; cursor: pointer; padding: 2px 5px; font-size: 12px; }
        .img-badge { position: absolute; left: 0; bottom: 0; background: rgba(0,0,0,0.65); color: #fff; padding: 2px 6px; font-size: 11px; }
        .img-badge.thumb { top: 0; bottom: auto; background: #1e90ff; }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <h1>Edit Product</h1>
            <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="form-group">
                    <label>Product Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? $product['title']); ?>" required>
                </div>

                <!-- Category Searchable Dropdown -->
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" id="cat-search" placeholder="Search or Add Category..." autocomplete="off" value="<?php echo htmlspecialchars($_POST['category_name'] ?? $catName); ?>">
                    <input type="hidden" name="category_id" id="category_id" value="<?php echo isset($_POST['category_id']) ? (int)$_POST['category_id'] : $catId; ?>">
                    <div id="cat-results" class="dropdown-results"></div>
                </div>

                <!-- Sub Category Searchable Dropdown -->
                <div class="form-group">
                    <label>Sub Category (Optional)</label>
                    <input type="text" id="sub-cat-search" placeholder="Search or Add Sub Category..." autocomplete="off" <?php echo ($catId || !empty($_POST['category_id'])) ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars($_POST['sub_category_name'] ?? $subCatName); ?>">
                    <input type="hidden" name="sub_category_id" id="sub_category_id" value="<?php echo isset($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : $subCatId; ?>">
                    <div id="sub-cat-results" class="dropdown-results"></div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="5"><?php echo htmlspecialchars($_POST['description'] ?? $product['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Video URL (YouTube Embed)</label>
                    <input type="text" name="video_url" value="<?php echo htmlspecialchars($_POST['video_url'] ?? ($product['video_url'] ?? '')); ?>">
                </div>

                <div class="form-group">
                    <label>Current Images</label>
                    <div>
                        <?php foreach ($images as $img): ?>
                            <div class="img-preview">
                                <?php if (!empty($img['is_primary'])): ?>
                                    <span class="img-badge">Primary</span>
                                <?php endif; ?>
                                <?php if (!empty($img['is_thumbnail'])): ?>
                                    <span class="img-badge thumb">Thumbnail</span>
                                <?php endif; ?>
                                <img src="../<?php echo $img['image_path']; ?>">
                                <label class="img-delete">
                                    <input type="checkbox" name="delete_image[]" value="<?php echo $img['id']; ?>"> Del
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Thumbnail Image (optional)</label>
                    <input type="file" name="thumb_image" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Add New Images</label>
                    <input type="file" name="images[]" multiple accept="image/*">
                </div>

                <h3>Variants & Stock</h3>
                <div id="variants-container">
                    <?php
                    $postVariants = $_POST['variant_name'] ?? [];
                    $rows = count($postVariants) > 0 ? count($postVariants) : count($variants);
                    for ($i = 0; $i < $rows; $i++):
                        $fromPost = count($postVariants) > 0;
                        $vId = $fromPost ? ($_POST['variant_id'][$i] ?? '') : ($variants[$i]['id'] ?? '');
                        $vName = $fromPost ? ($postVariants[$i] ?? '') : ($variants[$i]['name'] ?? '');
                        $vPrice = $fromPost ? ($_POST['variant_price'][$i] ?? '') : ($variants[$i]['price'] ?? '');
                        $vOffer = $fromPost ? ($_POST['variant_offer'][$i] ?? '') : ($variants[$i]['offer_price'] ?? '');
                        $vStock = $fromPost ? ($_POST['variant_stock'][$i] ?? '') : ($variants[$i]['stock_quantity'] ?? '');
                        $vSku = $fromPost ? ($_POST['variant_sku'][$i] ?? '') : ($variants[$i]['sku'] ?? '');
                        $vCost = $fromPost ? ($_POST['variant_cost'][$i] ?? '') : ($variants[$i]['cost_price'] ?? '');
                        $vExpense = $fromPost ? ($_POST['variant_expense'][$i] ?? '') : ($variants[$i]['expense'] ?? '');
                    ?>
                    <div class="variant-row">
                        <input type="hidden" name="variant_id[]" value="<?php echo htmlspecialchars($vId); ?>">
                        <input type="text" name="variant_name[]" value="<?php echo htmlspecialchars($vName); ?>" placeholder="Name" required>
                        <input type="number" name="variant_price[]" value="<?php echo htmlspecialchars($vPrice); ?>" placeholder="Selling Price" step="0.01" required>
                        <input type="number" name="variant_offer[]" value="<?php echo htmlspecialchars($vOffer); ?>" placeholder="Offer Price" step="0.01">
                        <input type="number" name="variant_cost[]" value="<?php echo htmlspecialchars($vCost); ?>" placeholder="Purchase Price" step="0.01">
                        <input type="number" name="variant_expense[]" value="<?php echo htmlspecialchars($vExpense); ?>" placeholder="Expense" step="0.01">
                        <input type="number" name="variant_stock[]" value="<?php echo htmlspecialchars($vStock); ?>" placeholder="Stock Qty" step="1">
                        <input type="text" name="variant_sku[]" value="<?php echo htmlspecialchars($vSku); ?>" placeholder="SKU">
                        <span class="variant-profit" style="min-width:120px; align-self:center; font-weight:600;"></span>
                        <?php if ($rows > 1): ?><button type="button" onclick="this.parentElement.remove()" style="background:red; color:white; border:none; cursor:pointer;">X</button><?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="button" id="add-variant-btn" style="margin-bottom: 20px;">+ Add Another Variant</button>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_flash_sale" <?php echo (($_POST['is_flash_sale'] ?? ($product['is_flash_sale'] ?? 0)) ? 'checked' : ''); ?>> Add to Flash Sale
                    </label>
                </div>
                <div class="form-group">
                    <label>Flash Sale Discount (%)</label>
                    <input type="number" name="flash_discount" step="0.01" placeholder="e.g., 10" value="<?php echo htmlspecialchars($flashSale['discount'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Flash Sale Start (optional)</label>
                    <input type="datetime-local" name="flash_start" value="<?php echo isset($_POST['flash_start']) && $_POST['flash_start'] !== '' ? htmlspecialchars($_POST['flash_start']) : ((isset($flashSale['start_at']) && $flashSale['start_at']) ? date('Y-m-d\TH:i', strtotime($flashSale['start_at'])) : ''); ?>">
                </div>
                <div class="form-group">
                    <label>Flash Sale End</label>
                    <input type="datetime-local" name="flash_end" value="<?php echo isset($_POST['flash_end']) && $_POST['flash_end'] !== '' ? htmlspecialchars($_POST['flash_end']) : ((isset($flashSale['end_at']) && $flashSale['end_at']) ? date('Y-m-d\TH:i', strtotime($flashSale['end_at'])) : ''); ?>">
                </div>

                <button type="submit" class="btn">Update Product</button>
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
                <input type="hidden" name="variant_id[]" value="">
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