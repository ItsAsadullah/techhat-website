<?php
require_once '../core/auth.php';
require_admin();

$id = $_GET['id'] ?? null;

// Initialize default variables
$product = ['name' => '', 'purchase_price' => '', 'selling_price' => '', 'stock' => '', 'status' => 1];
$variants = [];
$images = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - TechHat Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { margin: 0; background: #f1f5f9; }
        .container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-bottom: 1.5rem; }
        .card-header { padding: 1rem; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px 12px 0 0; }
        .card-header h3 { color: white; margin: 0; font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .card-body { padding: 1.5rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .form-label .req { color: #ef4444; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; font-family: inherit; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .grid-2 { grid-template-columns: 1fr; } }
        
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(102,126,234,0.4); }
        .btn-danger { background: #ef4444; color: white; padding: 0.5rem 0.75rem; font-size: 0.85rem; }
        .btn-outline { background: transparent; border: 2px solid #667eea; color: #667eea; }
        .btn-outline:hover { background: #f0f4ff; }
        
        .img-preview { display: inline-block; position: relative; margin: 0.5rem 0.5rem 0.5rem 0; }
        .img-preview img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; }
        .img-preview-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
        
        .sticky-footer { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -4px 12px rgba(0,0,0,0.1); z-index: 100; }
        .container.with-footer { padding-bottom: 6rem; }
        
        .variant-row { display: grid; grid-template-columns: repeat(5, 1fr) auto; gap: 0.75rem; align-items: center; padding: 0.75rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .variant-row input { padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; }
    </style>
</head>
<body>
<div class="container with-footer">
    <div class="mb-6">
        <h1 style="font-size: 2rem; font-weight: bold; color: #1e293b; margin: 0;">
            <i class="bi bi-pencil-square" style="color: #667eea;"></i> Edit Product
        </h1>
    </div>
    
    <form id="productForm" enctype="multipart/form-data" method="post" action="api/product_save.php">
        <input type="hidden" name="id" value="<?php echo (int)($id ?? 0); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        
        <div class="main-grid">
            <!-- LEFT COLUMN -->
            <div>
                <!-- Basic Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-info-circle"></i> Product Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Product Name <span class="req">*</span></label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Category <span class="req">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Brand</label>
                                <select name="brand_id" class="form-select">
                                    <option value="">Select Brand</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Short Description</label>
                            <textarea name="short_desc" class="form-textarea" rows="2"><?php echo htmlspecialchars($product['short_desc'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Long Description</label>
                            <textarea name="long_desc" class="form-textarea" id="summernote"><?php echo htmlspecialchars($product['long_desc'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-currency-dollar"></i> Pricing & Stock</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Purchase Price <span class="req">*</span></label>
                                <input type="number" name="purchase_price" class="form-input" min="0" step="0.01" value="<?php echo htmlspecialchars($product['purchase_price'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Selling Price <span class="req">*</span></label>
                                <input type="number" name="selling_price" class="form-input" min="0" step="0.01" value="<?php echo htmlspecialchars($product['selling_price'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Stock Quantity <span class="req">*</span></label>
                            <input type="number" name="stock" class="form-input" min="0" value="<?php echo htmlspecialchars($product['stock'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <!-- Variants Section -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-palette"></i> Product Variants</h3>
                    </div>
                    <div class="card-body">
                        <div id="variantsContainer" class="space-y-3">
                            <?php if (!empty($variants)): ?>
                                <?php foreach ($variants as $v): ?>
                                <div class="variant-row">
                                    <input type="text" name="variant_name[]" class="form-input" placeholder="Variant Name" value="<?php echo htmlspecialchars($v['name'] ?? ''); ?>" required>
                                    <input type="text" name="variant_sku[]" class="form-input" placeholder="SKU" value="<?php echo htmlspecialchars($v['sku'] ?? ''); ?>">
                                    <input type="number" name="variant_price[]" class="form-input" placeholder="Price" min="0" step="0.01" value="<?php echo htmlspecialchars($v['price'] ?? ''); ?>">
                                    <input type="number" name="variant_stock[]" class="form-input" placeholder="Stock" min="0" value="<?php echo htmlspecialchars($v['stock'] ?? ''); ?>">
                                    <button type="button" class="btn btn-danger remove-variant"><i class="bi bi-x"></i></button>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" id="addVariantBtn" class="btn btn-outline mt-4"><i class="bi bi-plus"></i> Add Variant</button>
                    </div>
                </div>
            </div>
            
            <!-- RIGHT COLUMN -->
            <div>
                <!-- Images Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-images"></i> Product Images</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Upload Images</label>
                            <input type="file" name="images[]" class="form-input" multiple accept="image/*">
                            <div id="imagePreview" class="img-preview-grid">
                                <?php if (!empty($images)): ?>
                                    <?php foreach ($images as $img): ?>
                                    <div class="img-preview">
                                        <img src="<?php echo htmlspecialchars($img); ?>" alt="Product">
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-toggle-on"></i> Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="status" value="1" <?php echo (($product['status'] ?? 1) == 1) ? 'checked' : ''; ?> style="width: 1.25rem; height: 1.25rem; cursor: pointer;">
                                <span class="form-label" style="margin-bottom: 0;">Active</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="sticky-footer">
    <div style="font-size: 0.9rem; color: #6b7280;">
        <i class="bi bi-info-circle"></i> Fill all required fields marked with *
    </div>
    <div style="display: flex; gap: 1rem;">
        <a href="products.php" class="btn" style="background: #6b7280; color: white;">
            <i class="bi bi-x"></i> Cancel
        </a>
        <button type="submit" form="productForm" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Save Changes
        </button>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#summernote').summernote({
        height: 200,
        placeholder: 'Product description...',
        toolbar: [
            ['style', ['bold', 'italic', 'underline']],
            ['para', ['ul', 'ol']],
            ['insert', ['link', 'picture']]
        ]
    });
});

// Image preview
const imageInput = document.querySelector('input[name="images[]"]');
const imagePreview = document.getElementById('imagePreview');
if(imageInput) {
    imageInput.addEventListener('change', function() {
        imagePreview.innerHTML = '';
        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'img-preview';
                div.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                imagePreview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });
}

// Variant management
document.getElementById('addVariantBtn').onclick = function() {
    const container = document.getElementById('variantsContainer');
    const row = document.createElement('div');
    row.className = 'variant-row';
    row.innerHTML = `
        <input type="text" name="variant_name[]" class="form-input" placeholder="Variant Name" required>
        <input type="text" name="variant_sku[]" class="form-input" placeholder="SKU">
        <input type="number" name="variant_price[]" class="form-input" placeholder="Price" min="0" step="0.01">
        <input type="number" name="variant_stock[]" class="form-input" placeholder="Stock" min="0">
        <button type="button" class="btn btn-danger remove-variant"><i class="bi bi-x"></i></button>
    `;
    container.appendChild(row);
    row.querySelector('.remove-variant').onclick = function() {
        row.remove();
    };
};

// Remove variant
document.querySelectorAll('.remove-variant').forEach(btn => {
    btn.onclick = function() {
        this.closest('.variant-row').remove();
    };
});

// Form submission
document.getElementById('productForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('api/product_save.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Product updated successfully!');
            window.location.href = 'products.php';
        } else {
            alert('❌ Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('❌ Failed to save product');
    });
});
</script>
</body>
</html>
