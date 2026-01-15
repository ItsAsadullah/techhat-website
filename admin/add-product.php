<?php
/**
 * ================================================================
 * TechHat Shop - Add Product Page
 * Modern Tabbed Interface with Remote Mobile Scanning
 * ================================================================
 */

require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

// Load necessary data
$brands = $pdo->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY name")->fetchAll();
$attributes = $pdo->query("SELECT id, name, type FROM attributes WHERE is_active = 1 ORDER BY name")->fetchAll();

// Build category tree for hierarchical display
function buildCategoryTree($categories, $parentId = null, $prefix = '') {
    $tree = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $tree[] = [
                'id' => $cat['id'],
                'name' => $prefix . $cat['name']
            ];
            $tree = array_merge($tree, buildCategoryTree($categories, $cat['id'], $prefix . '— '));
        }
    }
    return $tree;
}
$categoryTree = buildCategoryTree($categories);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get base URL for scanner
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - TechHat Admin</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/add-product.css">
    
    <!-- QRious for QR Code Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    
    <!-- TomSelect for Better Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
</head>
<body class="add-product-page">
    
    <!-- Admin Content Wrapper -->
    <div class="admin-content">
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <div class="add-product-container">
        
        <!-- Page Header -->
        <header class="page-header">
            <div>
                <h1><i class="bi bi-box-seam"></i> Add New Product</h1>
                <p>Create a new product with variations, serial tracking, and mobile scanner support</p>
            </div>
            <div class="header-actions">
                <a href="products.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancel
                </a>
                <button type="button" id="btnSaveDraft" class="btn btn-secondary">
                    <i class="bi bi-file-earmark"></i> Save Draft
                </button>
                <button type="submit" form="productForm" class="btn btn-primary btn-lg">
                    <i class="bi bi-check2-circle"></i> Save Product
                </button>
            </div>
        </header>
        
        <!-- Main Form -->
        <form id="productForm" action="api/save_product_scanner.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <!-- Tabs Container -->
            <div class="tabs-container">
                
                <!-- Tabs Navigation -->
                <nav class="tabs-nav">
                    <button type="button" class="tab-btn active" data-tab="general">
                        <i class="bi bi-info-circle"></i>
                        <span>General</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="pricing">
                        <i class="bi bi-currency-dollar"></i>
                        <span>Pricing</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="inventory">
                        <i class="bi bi-box2"></i>
                        <span>Inventory</span>
                        <span class="tab-badge" id="serialBadge" style="display: none;">0</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="attributes">
                        <i class="bi bi-tags"></i>
                        <span>Attributes</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="media">
                        <i class="bi bi-images"></i>
                        <span>Media</span>
                    </button>
                </nav>
                
                <!-- ============================================ -->
                <!-- TAB 1: General Information -->
                <!-- ============================================ -->
                <div class="tab-content active" id="tab-general">
                    <div class="form-row cols-2">
                        <!-- Left Column -->
                        <div>
                            <div class="form-card">
                                <div class="form-card-header">
                                    <h3 class="form-card-title">
                                        <span class="icon primary"><i class="bi bi-info-circle"></i></span>
                                        Basic Information
                                    </h3>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Product Name <span class="required">*</span></label>
                                    <input type="text" name="name" class="form-input" placeholder="Enter product name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Category Hierarchy</label>
                                    <div id="categoryBuilder" class="category-builder">
                                        <!-- Dynamic category selects will be added here -->
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Brand</label>
                                    <select name="brand_id" id="brandSelect" class="form-select">
                                        <option value="">Select Brand</option>
                                        <?php foreach ($brands as $brand): ?>
                                        <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <input type="hidden" name="category_id" id="finalCategoryId">
                                
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-textarea" rows="5" placeholder="Enter product description"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div>
                            <div class="form-card">
                                <div class="form-card-header">
                                    <h3 class="form-card-title">
                                        <span class="icon info"><i class="bi bi-gear"></i></span>
                                        Product Settings
                                    </h3>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Product Type <span class="required">*</span></label>
                                    <div class="form-row cols-2" style="margin-top: 0.5rem;">
                                        <label class="checkbox-wrapper">
                                            <input type="radio" name="product_type" value="simple" checked>
                                            <span class="checkbox-label">
                                                <strong>Simple Product</strong>
                                                <small>Single product without variations</small>
                                            </span>
                                        </label>
                                        <label class="checkbox-wrapper">
                                            <input type="radio" name="product_type" value="variable">
                                            <span class="checkbox-label">
                                                <strong>Variable Product</strong>
                                                <small>Product with color, size, etc.</small>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-row cols-2">
                                    <div class="form-group">
                                        <label class="form-label">Unit</label>
                                        <input type="text" name="unit" class="form-input" placeholder="pc, kg, box..." value="pc">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Video URL</label>
                                        <input type="url" name="video_url" class="form-input" placeholder="YouTube/Vimeo URL">
                                    </div>
                                </div>
                                
                                <div class="form-row cols-2">
                                    <div class="form-group">
                                        <label class="form-label">Warranty (Months)</label>
                                        <input type="number" name="warranty_months" class="form-input" min="0" value="0" placeholder="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Warranty Type</label>
                                        <select name="warranty_type" class="form-select">
                                            <option value="">No Warranty</option>
                                            <option value="brand">Brand Warranty</option>
                                            <option value="shop">Shop Warranty</option>
                                            <option value="international">International</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="toggle-wrapper">
                                        <label class="toggle">
                                            <input type="checkbox" name="is_active" value="1" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Product is Active</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="toggle-wrapper">
                                        <label class="toggle">
                                            <input type="checkbox" name="is_flash_sale" value="1">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Include in Flash Sale</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================ -->
                <!-- TAB 2: Pricing -->
                <!-- ============================================ -->
                <div class="tab-content" id="tab-pricing">
                    <div class="form-card">
                        <div class="form-card-header">
                            <h3 class="form-card-title">
                                <span class="icon success"><i class="bi bi-currency-dollar"></i></span>
                                Pricing Information
                            </h3>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle alert-icon"></i>
                            <div class="alert-content">
                                <div class="alert-title">Simple Product Pricing</div>
                                <div class="alert-text">For variable products, pricing will be set per variation in the Attributes tab.</div>
                            </div>
                        </div>
                        
                        <div id="simplePricingSection">
                            <div class="form-row cols-4">
                                <div class="form-group">
                                    <label class="form-label">Cost Price (Buying) <span class="required">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input type="number" name="cost_price" id="costPrice" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Extra Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input type="number" name="expense" id="expense" class="form-input" step="0.01" min="0" value="0" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Selling Price <span class="required">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input type="number" name="price" id="sellingPrice" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Offer Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input type="number" name="offer_price" id="offerPrice" class="form-input" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Profit Calculator -->
                            <div class="form-card" style="background: var(--gray-50); margin-top: 1rem;">
                                <div class="form-row cols-3">
                                    <div class="form-group">
                                        <label class="form-label">Total Cost</label>
                                        <div class="form-input" id="totalCostDisplay" style="background: white;">৳ 0.00</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Profit Amount</label>
                                        <div class="form-input" id="profitDisplay" style="background: white; font-weight: 600; color: var(--success);">৳ 0.00</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Profit Margin</label>
                                        <div class="form-input" id="profitMarginDisplay" style="background: white; font-weight: 600;">0%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row cols-2" style="margin-top: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label">SKU (Stock Keeping Unit)</label>
                                <input type="text" name="sku" class="form-input" placeholder="Auto-generated if empty">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Barcode</label>
                                <input type="text" name="barcode" class="form-input" placeholder="Product barcode">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================ -->
                <!-- TAB 3: Inventory (With Scanner) -->
                <!-- ============================================ -->
                <div class="tab-content" id="tab-inventory">
                    <div class="form-row cols-2">
                        <!-- Left: Stock Settings -->
                        <div>
                            <div class="form-card">
                                <div class="form-card-header">
                                    <h3 class="form-card-title">
                                        <span class="icon warning"><i class="bi bi-box2"></i></span>
                                        Stock Management
                                    </h3>
                                </div>
                                
                                <div class="form-row cols-2">
                                    <div class="form-group">
                                        <label class="form-label">Stock Quantity <span class="required">*</span></label>
                                        <input type="number" name="stock_quantity" id="stockQuantity" class="form-input" min="0" value="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Low Stock Alert</label>
                                        <input type="number" name="low_stock_alert" class="form-input" min="0" value="5" placeholder="5">
                                    </div>
                                </div>
                                
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label class="checkbox-wrapper">
                                        <input type="checkbox" name="has_serial" id="hasSerial" value="1">
                                        <span class="checkbox-label">
                                            <strong>Has Serial/IMEI Number</strong>
                                            <small>Enable tracking of individual serial numbers for this product</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Serial Number Inputs (Dynamic) -->
                            <div class="form-card" id="serialSection" style="display: none;">
                                <div class="form-card-header">
                                    <h3 class="form-card-title">
                                        <span class="icon primary"><i class="bi bi-upc-scan"></i></span>
                                        Serial/IMEI Numbers
                                    </h3>
                                </div>
                                
                                <div class="serial-inputs-container">
                                    <div class="serial-inputs-header">
                                        <h4><i class="bi bi-list-ol"></i> Enter Serial Numbers</h4>
                                        <span class="serial-count-badge" id="serialFilledCount">0 / 0</span>
                                    </div>
                                    <div class="serial-inputs-list" id="serialInputsList">
                                        <!-- Dynamic serial inputs will be inserted here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right: Mobile Scanner -->
                        <div>
                            <div class="form-card">
                                <div class="form-card-header">
                                    <h3 class="form-card-title">
                                        <span class="icon info"><i class="bi bi-phone"></i></span>
                                        Remote Mobile Scanner
                                    </h3>
                                </div>
                                
                                <div class="scanner-section" id="scannerSection">
                                    <div class="scanner-icon">
                                        <i class="bi bi-qr-code-scan"></i>
                                    </div>
                                    <h4 class="scanner-title">Connect Mobile Scanner</h4>
                                    <p class="scanner-description">
                                        Use your mobile phone as a barcode scanner. Scan barcodes with your phone camera and they'll appear here automatically!
                                    </p>
                                    
                                    <button type="button" id="btnConnectScanner" class="btn btn-primary">
                                        <i class="bi bi-link-45deg"></i> Connect Mobile Scanner
                                    </button>
                                    
                                    <!-- QR Code Display -->
                                    <div class="qr-code-wrapper" id="qrCodeWrapper">
                                        <canvas id="qrCodeCanvas"></canvas>
                                        <div class="qr-code-info">
                                            <p><strong>Scan this QR with your mobile</strong></p>
                                            <p>Or open this URL on your phone:</p>
                                            <code id="scannerUrl"></code>
                                        </div>
                                    </div>
                                    
                                    <div class="scanner-status" id="scannerStatus">
                                        <span class="pulse"></span>
                                        <span id="statusText">Not Connected</span>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info" style="margin-top: 1rem;">
                                    <i class="bi bi-lightbulb alert-icon"></i>
                                    <div class="alert-content">
                                        <div class="alert-title">How it works:</div>
                                        <div class="alert-text">
                                            1. Click "Connect Mobile Scanner"<br>
                                            2. Scan the QR code with your phone<br>
                                            3. Allow camera access on your phone<br>
                                            4. Scan barcodes - they appear here instantly!
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================ -->
                <!-- TAB 4: Attributes (For Variable Products) -->
                <!-- ============================================ -->
                <div class="tab-content" id="tab-attributes">
                    <div class="form-card" id="variableProductSection">
                        <div class="form-card-header">
                            <h3 class="form-card-title">
                                <span class="icon primary"><i class="bi bi-tags"></i></span>
                                Product Variations
                            </h3>
                        </div>
                        
                        <div id="simpleProductNotice">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle alert-icon"></i>
                                <div class="alert-content">
                                    <div class="alert-title">Simple Product Selected</div>
                                    <div class="alert-text">
                                        Variations are only available for Variable Products. 
                                        Go to the General tab and select "Variable Product" to enable this section.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="variationsBuilder" style="display: none;">
                            <!-- Attribute Selector -->
                            <div style="background: var(--primary-bg); padding: 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                                <div class="form-row cols-3" style="align-items: end;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Attribute</label>
                                        <select id="attrName" class="form-select">
                                            <?php foreach ($attributes as $attr): ?>
                                            <option value="<?= $attr['id'] ?>" data-type="<?= $attr['type'] ?>">
                                                <?= htmlspecialchars($attr['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Values (comma separated)</label>
                                        <input type="text" id="attrValues" class="form-input" placeholder="e.g. Red, Blue, Green">
                                    </div>
                                    <div>
                                        <button type="button" id="btnAddAttribute" class="btn btn-primary">
                                            <i class="bi bi-plus-lg"></i> Add Attribute
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Selected Attributes Display -->
                            <div id="selectedAttributes" class="form-card" style="background: var(--gray-50); display: none;">
                                <h4 style="margin-bottom: 1rem; font-size: 0.875rem; color: var(--gray-600);">
                                    <i class="bi bi-check2-square"></i> Selected Attributes
                                </h4>
                                <div id="attributeTags"></div>
                            </div>
                            
                            <!-- Generate Button -->
                            <div style="text-align: center; margin: 1.5rem 0;">
                                <button type="button" id="btnGenerateVariations" class="btn btn-success btn-lg" style="display: none;">
                                    <i class="bi bi-magic"></i> Generate Variations
                                </button>
                            </div>
                            
                            <!-- Variations Table -->
                            <div class="variations-table-wrapper" id="variationsTableWrapper" style="display: none;">
                                <table class="variations-table">
                                    <thead>
                                        <tr>
                                            <th>Variation</th>
                                            <th>SKU</th>
                                            <th>Cost Price</th>
                                            <th>Extra</th>
                                            <th>Sell Price</th>
                                            <th>Offer Price</th>
                                            <th>Stock</th>
                                            <th>Profit</th>
                                            <th>Image</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="variationsTableBody">
                                        <!-- Dynamic rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================ -->
                <!-- TAB 5: Media -->
                <!-- ============================================ -->
                <div class="tab-content" id="tab-media">
                    <div class="form-card">
                        <div class="form-card-header">
                            <h3 class="form-card-title">
                                <span class="icon info"><i class="bi bi-images"></i></span>
                                Product Images
                            </h3>
                        </div>
                        
                        <div class="image-upload-zone" id="imageUploadZone">
                            <i class="bi bi-cloud-upload"></i>
                            <p>Drag & drop images here or click to browse</p>
                            <p class="hint">Recommended: 800x800px, Max 2MB per image</p>
                            <input type="file" name="images[]" id="imageInput" multiple accept="image/*" style="display: none;">
                        </div>
                        
                        <div class="image-preview-grid" id="imagePreviewGrid">
                            <!-- Image previews will appear here -->
                        </div>
                        
                        <input type="hidden" name="primary_image_index" id="primaryImageIndex" value="0">
                    </div>
                </div>
                
            </div><!-- End Tabs Container -->
        </form>
    </div>
    
    <!-- Audio for Scanner Beep -->
    <audio id="beepSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleRQOSoXM66lbMC5NlfzqpmNANVJyxfZ6TjBEf7j+mkFBbJG86mtLT3mwzn5ZWXefvcWJZ2J6n6y9e2dnenR0bHZ7eXt9gICDg4WFh4iIiYqKioqKiYmIh4aEg4GAfXt5d3VzcnBvbm5ub3Byc3V4ent/gYSHiYuNj5GSlJWWl5iYmJiXlpWTkY+Mi4iFg4B+e3l3dXRycXBwcHFydHZ4e36BhIeKjI+RlJaYmpudnp6enp2cmpmWlJKPjImGg4B9ent4dnRzcnJycnN0dnh7foGEh4qNkJOWmJudnp+goKCfn56cmpiVkpCNioeEgX57eXd1c3JxcXFxcnR2eHt+gYSHio2QkpaYmp2en6ChoaGhoKCenJqYlZKPjImGg4B9e3h2dHNycXBxcXJ0dnh7foGEh4qNkJOWmJqdnp+goaGhoaCfnZuZl5SRjouIhYJ/fHp3dXNycXBwcHFzdHd5fH+ChYiLjo+RlJeZm52en6ChoqKioaCfnZuZl5SRjo2KiIWDgH58enh3dXRzcnNzdHZ4eXx/goWIi46QkpSXmZucnZ6foKChoaCfnp2bmJaTkY6LiIaEgX98e3l3dXRzcnJyc3R2eHp9f4KEh4qMjpGTlpiZm5ydnp6fn5+enZyamJaTkI6LiIaEgX98e3l3dXRzcnJyc3R2eHp9f4KEh4qMjpGTlpiZm5ydnp6fn5+enZyamJaTkI6LiIaEgX98e3l3dXRzcnJyc3R2eHp9gIKFiIqNj5GTlZeYmpucnZ2dnp6dnJuZl5WSj42KiIWDgX58e3l3dnVzcnFxcnN0dnh6fH+BhIaJi42QkpSWl5manJ2dnZ6dnJuamJaSj42KiIWDgH58e3l3dXRzcnFxcnN0dnh6fX+ChYeJjI6QkpSWmJqbnJycnZ2cnJqZl5WTkI6LiYaEgX98e3l3dXRzcnFxcXJzdXd5fH6BhIaJi42PkZOVl5ibo==" type="audio/wav">
    </audio>
    
    <!-- JavaScript -->
    <script>
    /**
     * ================================================================
     * TechHat Add Product - Main JavaScript
     * ================================================================
     */
    
    // Configuration
    const CONFIG = {
        baseUrl: '<?= rtrim($baseUrl, '/') ?>',
        scannerCheckInterval: 1000, // 1 second polling
        sessionExpiry: 3600000, // 1 hour in ms
    };
    
    // State Management
    const state = {
        productType: 'simple',
        hasSerial: false,
        stockQuantity: 0,
        scannerSessionId: null,
        scannerInterval: null,
        selectedAttributes: {},
        variations: [],
        uploadedImages: [],
        focusedSerialIndex: 0
    };
    
    // DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        initTabs();
        initProductType();
        initPriceCalculator();
        initSerialTracking();
        initScanner();
        initAttributes();
        initImageUpload();
        initFormSubmit();
        initTomSelect();
        initCategorySystem();
    });
    
    /**
     * Initialize Tab Navigation
     */
    function initTabs() {
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetId = tab.dataset.tab;
                
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById('tab-' + targetId).classList.add('active');
            });
        });
    }
    
    /**
     * Initialize TomSelect for better dropdowns
     */
    function initTomSelect() {
        if (typeof TomSelect !== 'undefined') {
            new TomSelect('#brandSelect', { create: false, allowEmptyOption: true });
        }
    }
    
    /**
     * Initialize Product Type Toggle
     */
    function initProductType() {
        const radios = document.querySelectorAll('input[name="product_type"]');
        
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                state.productType = this.value;
                updateProductTypeUI();
            });
        });
    }
    
    function updateProductTypeUI() {
        const isVariable = state.productType === 'variable';
        const simplePricing = document.getElementById('simplePricingSection');
        const variationsBuilder = document.getElementById('variationsBuilder');
        const simpleNotice = document.getElementById('simpleProductNotice');
        
        if (isVariable) {
            simplePricing.style.display = 'none';
            variationsBuilder.style.display = 'block';
            simpleNotice.style.display = 'none';
        } else {
            simplePricing.style.display = 'block';
            variationsBuilder.style.display = 'none';
            simpleNotice.style.display = 'block';
        }
    }
    
    /**
     * Initialize Price Calculator
     */
    function initPriceCalculator() {
        const costInput = document.getElementById('costPrice');
        const expenseInput = document.getElementById('expense');
        const sellInput = document.getElementById('sellingPrice');
        const offerInput = document.getElementById('offerPrice');
        
        [costInput, expenseInput, sellInput, offerInput].forEach(input => {
            if (input) {
                input.addEventListener('input', calculateProfit);
            }
        });
    }
    
    function calculateProfit() {
        const cost = parseFloat(document.getElementById('costPrice').value) || 0;
        const expense = parseFloat(document.getElementById('expense').value) || 0;
        const sell = parseFloat(document.getElementById('sellingPrice').value) || 0;
        const offer = parseFloat(document.getElementById('offerPrice').value) || 0;
        
        const totalCost = cost + expense;
        const effectivePrice = offer > 0 ? offer : sell;
        const profit = effectivePrice - totalCost;
        const margin = effectivePrice > 0 ? (profit / effectivePrice * 100) : 0;
        
        document.getElementById('totalCostDisplay').textContent = '৳ ' + totalCost.toFixed(2);
        
        const profitDisplay = document.getElementById('profitDisplay');
        profitDisplay.textContent = '৳ ' + profit.toFixed(2);
        profitDisplay.style.color = profit >= 0 ? 'var(--success)' : 'var(--danger)';
        
        const marginDisplay = document.getElementById('profitMarginDisplay');
        marginDisplay.textContent = margin.toFixed(1) + '%';
        marginDisplay.style.color = margin >= 0 ? 'var(--success)' : 'var(--danger)';
    }
    
    /**
     * Initialize Serial Number Tracking
     */
    function initSerialTracking() {
        const hasSerialCheckbox = document.getElementById('hasSerial');
        const stockInput = document.getElementById('stockQuantity');
        
        hasSerialCheckbox.addEventListener('change', function() {
            state.hasSerial = this.checked;
            updateSerialSection();
        });
        
        stockInput.addEventListener('input', function() {
            state.stockQuantity = parseInt(this.value) || 0;
            if (state.hasSerial) {
                generateSerialInputs();
            }
            updateSerialBadge();
        });
    }
    
    function updateSerialSection() {
        const serialSection = document.getElementById('serialSection');
        serialSection.style.display = state.hasSerial ? 'block' : 'none';
        
        if (state.hasSerial) {
            generateSerialInputs();
        }
    }
    
    function generateSerialInputs() {
        const container = document.getElementById('serialInputsList');
        container.innerHTML = '';
        
        for (let i = 0; i < state.stockQuantity; i++) {
            const div = document.createElement('div');
            div.className = 'serial-input-item';
            div.innerHTML = `
                <span class="serial-number">${i + 1}</span>
                <input type="text" 
                       name="serials[]" 
                       class="form-input serial-field" 
                       data-index="${i}"
                       placeholder="Enter serial/IMEI number"
                       onfocus="setFocusedSerial(${i})"
                       onblur="clearFocusedSerial(${i})">
                <button type="button" class="btn-scan" onclick="focusSerialInput(${i})" title="Click to focus for scanning">
                    <i class="bi bi-upc-scan"></i>
                </button>
            `;
            container.appendChild(div);
        }
        
        updateSerialCount();
        updateSerialBadge();
    }
    
    function setFocusedSerial(index) {
        state.focusedSerialIndex = index;
        document.querySelectorAll('.serial-field').forEach((input, i) => {
            input.classList.toggle('active', i === index);
        });
    }
    
    function clearFocusedSerial(index) {
        const input = document.querySelector(`.serial-field[data-index="${index}"]`);
        input.classList.remove('active');
    }
    
    function focusSerialInput(index) {
        const input = document.querySelector(`.serial-field[data-index="${index}"]`);
        if (input) {
            input.focus();
            state.focusedSerialIndex = index;
        }
    }
    
    function updateSerialCount() {
        const filled = document.querySelectorAll('.serial-field').length > 0 
            ? Array.from(document.querySelectorAll('.serial-field')).filter(i => i.value.trim()).length 
            : 0;
        const total = state.stockQuantity;
        document.getElementById('serialFilledCount').textContent = `${filled} / ${total}`;
    }
    
    function updateSerialBadge() {
        const badge = document.getElementById('serialBadge');
        if (state.hasSerial && state.stockQuantity > 0) {
            badge.style.display = 'inline-flex';
            badge.textContent = state.stockQuantity;
        } else {
            badge.style.display = 'none';
        }
    }
    
    /**
     * Initialize Remote Scanner
     */
    function initScanner() {
        const connectBtn = document.getElementById('btnConnectScanner');
        
        connectBtn.addEventListener('click', function() {
            if (state.scannerSessionId) {
                disconnectScanner();
            } else {
                connectScanner();
            }
        });
    }
    
    function connectScanner() {
        // Generate unique session ID
        state.scannerSessionId = generateSessionId();
        
        // Create scanner URL
        const scannerUrl = CONFIG.baseUrl + '/mobile-scanner.php?session=' + state.scannerSessionId;
        document.getElementById('scannerUrl').textContent = scannerUrl;
        
        // Generate QR Code
        const qr = new QRious({
            element: document.getElementById('qrCodeCanvas'),
            value: scannerUrl,
            size: 200,
            level: 'M',
            background: '#ffffff',
            foreground: '#1f2937'
        });
        
        // Show QR wrapper
        document.getElementById('qrCodeWrapper').classList.add('visible');
        
        // Update UI
        document.getElementById('scannerSection').classList.add('active');
        document.getElementById('btnConnectScanner').innerHTML = '<i class="bi bi-x-lg"></i> Disconnect Scanner';
        document.getElementById('btnConnectScanner').classList.remove('btn-primary');
        document.getElementById('btnConnectScanner').classList.add('btn-danger');
        document.getElementById('statusText').textContent = 'Waiting for mobile...';
        
        // Register session on server
        registerScannerSession();
        
        // Start polling
        startPolling();
        
        showToast('Scanner connected!', 'Scan the QR code with your mobile phone', 'success');
    }
    
    function disconnectScanner() {
        // Stop polling
        if (state.scannerInterval) {
            clearInterval(state.scannerInterval);
            state.scannerInterval = null;
        }
        
        // Reset UI
        document.getElementById('qrCodeWrapper').classList.remove('visible');
        document.getElementById('scannerSection').classList.remove('active');
        document.getElementById('btnConnectScanner').innerHTML = '<i class="bi bi-link-45deg"></i> Connect Mobile Scanner';
        document.getElementById('btnConnectScanner').classList.remove('btn-danger');
        document.getElementById('btnConnectScanner').classList.add('btn-primary');
        document.getElementById('statusText').textContent = 'Not Connected';
        
        state.scannerSessionId = null;
        
        showToast('Scanner disconnected', 'Mobile scanner has been disconnected', 'info');
    }
    
    function generateSessionId() {
        return 'scan_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    async function registerScannerSession() {
        try {
            await fetch(CONFIG.baseUrl + '/api/scan_endpoints.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'register',
                    session_id: state.scannerSessionId
                })
            });
        } catch (error) {
            console.error('Failed to register session:', error);
        }
    }
    
    function startPolling() {
        state.scannerInterval = setInterval(async () => {
            if (!state.scannerSessionId) return;
            
            try {
                const response = await fetch(
                    CONFIG.baseUrl + '/api/scan_endpoints.php?action=check&session=' + state.scannerSessionId
                );
                const data = await response.json();
                
                if (data.status === 'found' && data.code) {
                    handleScannedCode(data.code);
                    document.getElementById('statusText').textContent = 'Connected & Active';
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, CONFIG.scannerCheckInterval);
    }
    
    function handleScannedCode(code) {
        // Play beep sound
        const beep = document.getElementById('beepSound');
        if (beep) {
            beep.currentTime = 0;
            beep.play().catch(() => {});
        }
        
        // Find next empty serial input or use focused one
        const serialInputs = document.querySelectorAll('.serial-field');
        let targetInput = null;
        
        // First, try to use the currently focused input if it's empty
        const focusedInput = document.querySelector('.serial-field.active');
        if (focusedInput && !focusedInput.value.trim()) {
            targetInput = focusedInput;
        } else {
            // Find first empty input
            for (const input of serialInputs) {
                if (!input.value.trim()) {
                    targetInput = input;
                    break;
                }
            }
        }
        
        if (targetInput) {
            targetInput.value = code;
            targetInput.classList.add('filled');
            
            // Focus next empty input
            const nextIndex = parseInt(targetInput.dataset.index) + 1;
            const nextInput = document.querySelector(`.serial-field[data-index="${nextIndex}"]`);
            if (nextInput && !nextInput.value.trim()) {
                nextInput.focus();
            }
            
            updateSerialCount();
            showToast('Code Scanned!', `Serial: ${code}`, 'success');
        } else {
            showToast('All fields filled', 'All serial number fields are already filled', 'warning');
        }
    }
    
    /**
     * Initialize Attributes & Variations
     */
    function initAttributes() {
        const addBtn = document.getElementById('btnAddAttribute');
        const generateBtn = document.getElementById('btnGenerateVariations');
        
        addBtn.addEventListener('click', addAttribute);
        generateBtn.addEventListener('click', generateVariations);
    }
    
    function addAttribute() {
        const select = document.getElementById('attrName');
        const valuesInput = document.getElementById('attrValues');
        
        const attrId = select.value;
        const attrName = select.options[select.selectedIndex].text;
        const values = valuesInput.value.split(',').map(v => v.trim()).filter(v => v);
        
        if (!values.length) {
            showToast('Error', 'Please enter at least one attribute value', 'error');
            return;
        }
        
        state.selectedAttributes[attrId] = {
            name: attrName,
            values: values
        };
        
        renderAttributeTags();
        valuesInput.value = '';
        
        document.getElementById('selectedAttributes').style.display = 'block';
        document.getElementById('btnGenerateVariations').style.display = 'inline-flex';
    }
    
    function renderAttributeTags() {
        const container = document.getElementById('attributeTags');
        container.innerHTML = '';
        
        for (const [id, attr] of Object.entries(state.selectedAttributes)) {
            const tag = document.createElement('div');
            tag.style.cssText = 'display: inline-flex; align-items: center; gap: 0.5rem; background: white; padding: 0.5rem 1rem; border-radius: var(--radius); margin: 0.25rem; border: 1px solid var(--gray-200);';
            tag.innerHTML = `
                <strong>${attr.name}:</strong>
                <span>${attr.values.join(', ')}</span>
                <button type="button" onclick="removeAttribute('${id}')" style="background: none; border: none; cursor: pointer; color: var(--danger);">
                    <i class="bi bi-x-circle"></i>
                </button>
            `;
            container.appendChild(tag);
        }
    }
    
    function removeAttribute(id) {
        delete state.selectedAttributes[id];
        renderAttributeTags();
        
        if (Object.keys(state.selectedAttributes).length === 0) {
            document.getElementById('selectedAttributes').style.display = 'none';
            document.getElementById('btnGenerateVariations').style.display = 'none';
            document.getElementById('variationsTableWrapper').style.display = 'none';
        }
    }
    
    function generateVariations() {
        const attrs = Object.entries(state.selectedAttributes);
        if (!attrs.length) return;
        
        // Generate all combinations
        const combinations = cartesianProduct(attrs.map(([id, attr]) => 
            attr.values.map(v => ({ attrId: id, attrName: attr.name, value: v }))
        ));
        
        state.variations = combinations.map((combo, index) => ({
            id: index,
            name: combo.map(c => c.value).join(' / '),
            attributes: combo,
            sku: '',
            cost: '',
            expense: 0,
            price: '',
            offerPrice: '',
            stock: 0
        }));
        
        renderVariationsTable();
    }
    
    function cartesianProduct(arrays) {
        return arrays.reduce((acc, curr) => 
            acc.flatMap(a => curr.map(c => [...a, c])), [[]]
        );
    }
    
    function renderVariationsTable() {
        const tbody = document.getElementById('variationsTableBody');
        tbody.innerHTML = '';
        
        state.variations.forEach((variation, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="variant-name">${variation.name}</td>
                <td><input type="text" name="variations[${index}][sku]" class="form-input input-sm" placeholder="Auto"></td>
                <td><input type="number" name="variations[${index}][cost]" class="form-input input-sm" step="0.01" oninput="calcVariationProfit(${index})"></td>
                <td><input type="number" name="variations[${index}][expense]" class="form-input input-sm" step="0.01" value="0" oninput="calcVariationProfit(${index})"></td>
                <td><input type="number" name="variations[${index}][price]" class="form-input input-sm" step="0.01" oninput="calcVariationProfit(${index})"></td>
                <td><input type="number" name="variations[${index}][offer_price]" class="form-input input-sm" step="0.01" oninput="calcVariationProfit(${index})"></td>
                <td><input type="number" name="variations[${index}][stock]" class="form-input input-sm" min="0" value="0"></td>
                <td class="profit-cell" id="varProfit_${index}">৳ 0</td>
                <td>
                    <input type="file" name="variation_images[${index}]" accept="image/*" style="width: 80px; font-size: 0.75rem;">
                </td>
                <td>
                    <button type="button" class="btn btn-icon btn-danger btn-sm" onclick="removeVariation(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
                <input type="hidden" name="variations[${index}][name]" value="${variation.name}">
                <input type="hidden" name="variations[${index}][attributes]" value='${JSON.stringify(variation.attributes)}'>
            `;
            tbody.appendChild(row);
        });
        
        document.getElementById('variationsTableWrapper').style.display = 'block';
    }
    
    function calcVariationProfit(index) {
        const row = document.getElementById('variationsTableBody').children[index];
        const cost = parseFloat(row.querySelector(`[name="variations[${index}][cost]"]`).value) || 0;
        const expense = parseFloat(row.querySelector(`[name="variations[${index}][expense]"]`).value) || 0;
        const price = parseFloat(row.querySelector(`[name="variations[${index}][price]"]`).value) || 0;
        const offer = parseFloat(row.querySelector(`[name="variations[${index}][offer_price]"]`).value) || 0;
        
        const effectivePrice = offer > 0 ? offer : price;
        const profit = effectivePrice - cost - expense;
        
        const profitCell = document.getElementById(`varProfit_${index}`);
        profitCell.textContent = '৳ ' + profit.toFixed(0);
        profitCell.className = 'profit-cell ' + (profit >= 0 ? 'positive' : 'negative');
    }
    
    function removeVariation(index) {
        state.variations.splice(index, 1);
        renderVariationsTable();
    }
    
    /**
     * Initialize Image Upload
     */
    function initImageUpload() {
        const zone = document.getElementById('imageUploadZone');
        const input = document.getElementById('imageInput');
        const grid = document.getElementById('imagePreviewGrid');
        
        zone.addEventListener('click', () => input.click());
        
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        
        zone.addEventListener('dragleave', () => {
            zone.classList.remove('dragover');
        });
        
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        input.addEventListener('change', () => handleFiles(input.files));
    }
    
    function handleFiles(files) {
        const grid = document.getElementById('imagePreviewGrid');
        
        Array.from(files).forEach((file, index) => {
            if (!file.type.startsWith('image/')) return;
            
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageIndex = state.uploadedImages.length;
                state.uploadedImages.push(file);
                
                const div = document.createElement('div');
                div.className = 'image-preview-item' + (imageIndex === 0 ? ' primary' : '');
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-btn" onclick="removeImage(${imageIndex}, this)">
                        <i class="bi bi-x"></i>
                    </button>
                `;
                div.onclick = () => setPrimaryImage(imageIndex, div);
                grid.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
    
    function removeImage(index, btn) {
        event.stopPropagation();
        btn.parentElement.remove();
        state.uploadedImages[index] = null;
    }
    
    function setPrimaryImage(index, div) {
        document.querySelectorAll('.image-preview-item').forEach(item => {
            item.classList.remove('primary');
        });
        div.classList.add('primary');
        document.getElementById('primaryImageIndex').value = index;
    }
    
    /**
     * Form Submit Handler
     */
    function initFormSubmit() {
        const form = document.getElementById('productForm');
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate
            if (!validateForm()) return;
            
            // Show loading
            showLoading(true);
            
            try {
                const formData = new FormData(form);
                
                // Add image files
                state.uploadedImages.forEach((file, index) => {
                    if (file) {
                        formData.append('product_images[]', file);
                    }
                });
                
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Success!', 'Product saved successfully', 'success');
                    setTimeout(() => {
                        window.location.href = 'products.php';
                    }, 1500);
                } else {
                    showToast('Error', result.message || 'Failed to save product', 'error');
                }
            } catch (error) {
                console.error('Submit error:', error);
                showToast('Error', 'An error occurred while saving', 'error');
            } finally {
                showLoading(false);
            }
        });
    }
    
    function validateForm() {
        const name = document.querySelector('input[name="name"]').value.trim();
        if (!name) {
            showToast('Validation Error', 'Product name is required', 'error');
            return false;
        }
        
        // Add more validation as needed
        return true;
    }
    
    /**
     * ================================================================
     * Dynamic Category System
     * ================================================================
     */
    const categoryState = {
        levels: [],
        selectedPath: []
    };
    
    function initCategorySystem() {
        // Add first category level
        addCategoryLevel(null, 0);
    }
    
    async function addCategoryLevel(parentId, level) {
        const container = document.getElementById('categoryBuilder');
        
        // Create wrapper for this level
        const levelWrapper = document.createElement('div');
        levelWrapper.className = 'category-level';
        levelWrapper.dataset.level = level;
        levelWrapper.style.cssText = 'display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem;';
        
        // Create select element
        const selectId = `categorySelect_${level}`;
        const select = document.createElement('select');
        select.id = selectId;
        select.className = 'form-select';
        select.style.flex = '1';
        
        // Add to DOM first
        levelWrapper.appendChild(select);
        container.appendChild(levelWrapper);
        
        // Load categories for this level
        const categories = await loadCategories(parentId);
        
        // Initialize TomSelect with create option
        const tomSelect = new TomSelect('#' + selectId, {
            create: true,
            createOnBlur: false,
            placeholder: level === 0 ? 'Select or type main category...' : 'Select or add sub-category...',
            allowEmptyOption: true,
            render: {
                option_create: function(data, escape) {
                    return '<div class="create-option"><i class="bi bi-plus-circle"></i> Add: <strong>' + escape(data.input) + '</strong></div>';
                },
                no_results: function(data, escape) {
                    return '<div class="no-results">No categories found. Type to add new.</div>';
                }
            },
            onChange: function(value) {
                handleCategoryChange(value, level, parentId, this);
            },
            onCreate: function(input, callback) {
                createNewCategory(input, parentId, level, callback);
            }
        });
        
        // Add options
        tomSelect.addOption({ value: '', text: level === 0 ? 'Main Category' : 'Sub Category' });
        categories.forEach(cat => {
            tomSelect.addOption({ value: cat.id, text: cat.name });
        });
        
        // Store reference
        categoryState.levels[level] = {
            select: tomSelect,
            wrapper: levelWrapper,
            parentId: parentId
        };
    }
    
    async function loadCategories(parentId) {
        try {
            const url = parentId 
                ? `${CONFIG.baseUrl}/api/get_children.php?parent_id=${parentId}`
                : `${CONFIG.baseUrl}/api/get_children.php?parent_id=null`;
            
            const response = await fetch(url);
            const data = await response.json();
            return data.categories || [];
        } catch (error) {
            console.error('Failed to load categories:', error);
            return [];
        }
    }
    
    async function createNewCategory(name, parentId, level, callback) {
        try {
            const response = await fetch(CONFIG.baseUrl + '/api/create_category.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: name,
                    parent_id: parentId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                callback({ value: result.category.id, text: result.category.name });
                showToast('Success', `Category "${name}" created successfully`, 'success');
                return result.category;
            } else {
                callback(false);
                showToast('Error', result.message || 'Failed to create category', 'error');
                return null;
            }
        } catch (error) {
            console.error('Error creating category:', error);
            callback(false);
            showToast('Error', 'Failed to create category', 'error');
            return null;
        }
    }
    
    function handleCategoryChange(categoryId, level, parentId, tomSelectInstance) {
        // Remove all levels after this one
        removeSubsequentLevels(level);
        
        if (!categoryId) {
            // If cleared, update final category
            updateFinalCategory(level > 0 ? parentId : null);
            return;
        }
        
        // Update selected path
        categoryState.selectedPath[level] = categoryId;
        categoryState.selectedPath = categoryState.selectedPath.slice(0, level + 1);
        
        // Update final category ID
        updateFinalCategory(categoryId);
        
        // Add "Add Sub Category" button
        addSubCategoryButton(categoryId, level);
    }
    
    function removeSubsequentLevels(fromLevel) {
        const container = document.getElementById('categoryBuilder');
        
        // Remove all levels after fromLevel
        for (let i = categoryState.levels.length - 1; i > fromLevel; i--) {
            if (categoryState.levels[i]) {
                categoryState.levels[i].select.destroy();
                categoryState.levels[i].wrapper.remove();
                categoryState.levels.pop();
            }
        }
    }
    
    function addSubCategoryButton(parentId, level) {
        const levelWrapper = categoryState.levels[level].wrapper;
        
        // Remove existing button if any
        const existingBtn = levelWrapper.querySelector('.add-sub-btn');
        if (existingBtn) existingBtn.remove();
        
        // Create add sub-category button
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-secondary btn-sm add-sub-btn';
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add Sub';
        btn.style.cssText = 'white-space: nowrap; padding: 0.5rem 1rem;';
        
        btn.onclick = function() {
            addCategoryLevel(parentId, level + 1);
            btn.remove(); // Remove button after adding level
        };
        
        levelWrapper.appendChild(btn);
    }
    
    function updateFinalCategory(categoryId) {
        document.getElementById('finalCategoryId').value = categoryId || '';
    }
    
    /**
     * Utility Functions
     */
    function showToast(title, message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };
        
        toast.innerHTML = `
            <i class="bi ${icons[type]} toast-icon"></i>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    
    function showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        overlay.classList.toggle('visible', show);
    }
    </script>
    
    </div><!-- /.admin-content -->
</body>
</html>
