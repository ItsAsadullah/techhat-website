<?php
require_once '../core/auth.php';
require_admin();
require_once __DIR__ . '/partials/sidebar.php';

$pageTitle = 'Add New Product';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - TechHat Admin</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Tom Select CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    
    <!-- Summernote CSS & JS for Rich Text Editor -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <!-- Admin Styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .admin-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            position: relative;
        }
        
        .admin-content::before {
            content: '';
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        @media (max-width: 768px) {
            .admin-content { 
                margin-left: 0; 
                padding: 1rem; 
            }
            .admin-content::before {
                left: 0;
            }
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .page-header h1 {
            color: white;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
        }
        
        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            position: relative;
            z-index: 2;
        }
        
        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.12);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .section-title i {
            font-size: 1.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Form Inputs */
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        /* Product Type Toggle */
        .product-type-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 2px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        
        .radio-card {
            position: relative;
            padding: 1rem 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .radio-card:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .radio-card input:checked + .radio-content {
            color: #667eea;
        }
        
        .radio-card input:checked ~ * {
            border-color: #667eea;
        }
        
        /* Profit Display */
        .profit-positive {
            color: #10b981;
            font-weight: 700;
            font-size: 1.1rem;
            animation: pulse 2s infinite;
        }
        
        .profit-negative {
            color: #ef4444;
            font-weight: 700;
            font-size: 1.1rem;
            animation: shake 0.5s;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Image Preview */
        .image-preview {
            position: relative;
            display: inline-block;
            margin: 0.5rem;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .image-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .image-preview img {
            border-radius: 0.75rem;
            border: 3px solid white;
        }
        
        .image-preview .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            transition: all 0.2s ease;
        }
        
        .image-preview .remove-btn:hover {
            transform: rotate(90deg) scale(1.1);
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }
        
        /* Upload Zone */
        .upload-zone {
            border: 3px dashed #cbd5e1;
            border-radius: 1rem;
            padding: 3rem;
            text-align: center;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-zone:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #eef2ff 100%);
            transform: translateY(-2px);
        }
        
        .upload-zone i {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(107, 114, 128, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5);
        }
        
        /* Variation Table */
        .variation-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .variation-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .variation-table th {
            color: white;
            padding: 1rem;
            font-weight: 600;
            text-align: left;
            font-size: 0.875rem;
        }
        
        .variation-table tbody tr {
            background: white;
            transition: all 0.2s ease;
        }
        
        .variation-table tbody tr:hover {
            background: #f8f9ff;
            transform: scale(1.01);
        }
        
        .variation-table td {
            padding: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        /* Submit Bar */
        .submit-bar {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            border: 2px solid #667eea;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        
        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-required {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .badge-optional {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: white;
        }
        
        /* Loading Animation */
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        
        .loading {
            animation: shimmer 2s infinite;
            background: linear-gradient(to right, #f0f0f0 0%, #e0e0e0 50%, #f0f0f0 100%);
            background-size: 2000px 100%;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="admin-content">
    
    <!-- Page Header -->
    <div class="page-header">
        <h1>✨ Add New Product</h1>
        <p>Create a new product listing with complete details - Simple, Fast & Professional</p>
    </div>

    <!-- Main Form -->
    <form id="productForm" method="POST" enctype="multipart/form-data">
        
        <!-- Section 1: Basic Information -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-info-circle text-blue-600"></i> সাধারণ তথ্য (Basic Information)
            </h2>
            
            <div class="space-y-6">
                <!-- Product Name -->
                <div>
                    <label for="product_name" class="form-label">
                        পণ্যের নাম (Product Name) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="product_name" name="product_name" required
                        class="form-input"
                        placeholder="উদাহরণ: Redmi Note 12 Pro 5G">
                </div>

                <!-- Category - Dynamic Multi-level -->
                <div>
                    <label class="form-label">
                        ক্যাটাগরি (Category) <span class="text-red-500">*</span>
                    </label>
                    <div id="categoryContainer">
                        <select id="category_level_1" class="tom-select w-full" placeholder="Electronics, Mobile, Laptop...">
                            <option value="">-- Select Root Category --</option>
                        </select>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">
                        💡 টাইপ করে সার্চ করুন বা Enter দিয়ে নতুন ক্যাটাগরি তৈরি করুন
                    </p>
                    <div id="categoryPath" class="mt-3 text-sm font-medium text-blue-600"></div>
                </div>

                <!-- Brand -->
                <div>
                    <label for="brand" class="form-label">
                        ব্র্যান্ড (Brand) <span class="text-red-500">*</span>
                    </label>
                    <select id="brand" name="brand_id" class="tom-select w-full" placeholder="Samsung, Apple, Xiaomi...">
                        <option value="">-- Select or Create Brand --</option>
                        <?php
                        $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
                        foreach ($brands as $brand) {
                            echo "<option value='{$brand['id']}'>{$brand['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Tags -->
                <div>
                    <label for="tags" class="form-label">
                        ট্যাগস (Tags)
                    </label>
                    <input type="text" id="tags" name="tags"
                        class="form-input"
                        placeholder="Gaming Phone, 5G, Budget Phone (কমা দিয়ে আলাদা করুন)">
                    <p class="text-sm text-gray-500 mt-2">কমা (,) দিয়ে ট্যাগ আলাদা করুন</p>
                </div>

                <!-- Short Description -->
                <div>
                    <label for="short_description" class="form-label">
                        সংক্ষিপ্ত বিবরণ (Short Description)
                    </label>
                    <textarea id="short_description" name="short_description" rows="3"
                        class="form-input"
                        placeholder="২-৩ লাইনের ছোট বর্ণনা যা প্রোডাক্ট কার্ডে দেখাবে"></textarea>
                </div>

                <!-- Long Description - Rich Text Editor -->
                <div>
                    <label for="long_description" class="form-label">
                        বিস্তারিত বিবরণ (Long Description)
                    </label>
                    <textarea id="long_description" name="long_description"></textarea>
                    <p class="text-sm text-gray-500 mt-2">এখানে Bold, Italic, List ব্যবহার করে স্পেসিফিকেশন দিতে পারবেন</p>
                </div>
            </div>
        </div>

        <!-- Section 2: Variations & Pricing -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-tags text-purple-600"></i> ভেরিয়েশন ও প্রাইসিং (Variations & Pricing)
            </h2>

            <!-- Product Type Selection -->
            <div class="product-type-card">
                <label class="form-label mb-4">পণ্যের ধরন (Product Type) <span class="badge badge-required ml-2">Required</span></label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="radio-card">
                        <input type="radio" name="product_type" value="simple" checked onchange="toggleProductType()"
                            class="hidden">
                        <div class="radio-content">
                            <div class="flex items-center gap-3 mb-2">
                                <i class="bi bi-box-seam text-2xl text-blue-600"></i>
                                <span class="font-bold text-lg">সিম্পল প্রোডাক্ট (Simple)</span>
                            </div>
                            <p class="text-sm text-gray-600 ml-9">একটিমাত্র ভেরিয়েন্ট - কোনো কালার/সাইজ নেই (যেমন: পেনড্রাইভ, মাউস প্যাড)</p>
                        </div>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="product_type" value="variable" onchange="toggleProductType()"
                            class="hidden">
                        <div class="radio-content">
                            <div class="flex items-center gap-3 mb-2">
                                <i class="bi bi-palette text-2xl text-purple-600"></i>
                                <span class="font-bold text-lg">ভেরিয়েবল প্রোডাক্ট (Variable)</span>
                            </div>
                            <p class="text-sm text-gray-600 ml-9">একাধিক ভেরিয়েন্ট - কালার, র‍্যাম, সাইজ ইত্যাদি (যেমন: মোবাইল, ল্যাপটপ)</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Simple Product Section -->
            <div id="simpleProductSection" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Purchase Price -->
                    <div>
                        <label for="simple_purchase_price" class="form-label">
                            কেনা দাম (Purchase Price) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" step="0.01" id="simple_purchase_price" name="simple_purchase_price"
                            class="form-input"
                            placeholder="০" oninput="calculateSimpleProfit()">
                    </div>

                    <!-- Extra Cost -->
                    <div>
                        <label for="simple_extra_cost" class="form-label">
                            আনুষঙ্গিক খরচ (Extra Cost)
                        </label>
                        <input type="number" step="0.01" id="simple_extra_cost" name="simple_extra_cost"
                            class="form-input"
                            placeholder="শিপিং/কাস্টমস চার্জ" oninput="calculateSimpleProfit()">
                    </div>

                    <!-- Selling Price -->
                    <div>
                        <label for="simple_selling_price" class="form-label">
                            বিক্রয় মূল্য (Selling Price) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" step="0.01" id="simple_selling_price" name="simple_selling_price"
                            class="form-input"
                            placeholder="০" oninput="calculateSimpleProfit()">
                    </div>

                    <!-- Old Price -->
                    <div>
                        <label for="simple_old_price" class="form-label">
                            পূর্বের দাম (Old Price)
                        </label>
                        <input type="number" step="0.01" id="simple_old_price" name="simple_old_price"
                            class="form-input"
                            placeholder="ছাড় দেখানোর জন্য">
                    </div>

                    <!-- Stock Quantity -->
                    <div>
                        <label for="simple_stock" class="form-label">
                            স্টক পরিমাণ (Stock Quantity) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="simple_stock" name="simple_stock"
                            class="form-input"
                            placeholder="কত পিস আছে">
                    </div>

                    <!-- Profit Display -->
                    <div>
                        <label class="form-label">লাভ/লস (Profit/Loss)</label>
                        <div id="simpleProfitDisplay" class="px-4 py-3 border border-gray-200 rounded-lg bg-gray-50">
                            <span class="text-gray-400">দাম দিলে হিসাব দেখাবে</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variable Product Section -->
            <div id="variableProductSection" class="space-y-6" style="display:none;">
                <!-- Attribute Selection -->
                <div>
                    <label class="form-label">
                        অ্যাট্রিবিউট সিলেক্ট করুন (Select Attributes)
                    </label>
                    <div id="attributeContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <!-- Dynamically loaded attributes will appear here -->
                    </div>
                    <button type="button" onclick="addVariationRow()" 
                        class="btn-primary inline-flex items-center gap-2">
                        <i class="bi bi-plus-circle"></i> Add New Variation
                    </button>
                </div>

                <!-- Variation Table -->
                <div class="overflow-x-auto">
                    <table class="variation-table">
                        <thead>
                            <tr>
                                <th>Attributes</th>
                                <th>Purchase</th>
                                <th>Extra Cost</th>
                                <th>Selling</th>
                                <th>Old Price</th>
                                <th>Stock</th>
                                <th>Image</th>
                                <th>Profit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="variationTableBody">
                            <!-- Dynamic rows will be added here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Section 3: Images & Media -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-images text-green-600"></i> মিডিয়া এবং গ্যালারি (Images & Media)
            </h2>

            <div class="space-y-6">
                <!-- Thumbnail Image -->
                <div>
                    <label class="form-label">
                        মূল ছবি (Thumbnail Image) <span class="badge badge-required ml-2">Required</span>
                    </label>
                    <div class="flex items-center gap-4">
                        <label class="cursor-pointer">
                            <input type="file" id="thumbnail" name="thumbnail" accept="image/*" onchange="previewThumbnail(this)" class="hidden">
                            <div class="btn-primary inline-flex items-center gap-2">
                                <i class="bi bi-cloud-upload"></i> Upload Thumbnail
                            </div>
                        </label>
                        <div id="thumbnailPreview"></div>
                    </div>
                    <p class="text-sm text-gray-500 mt-3">
                        <i class="bi bi-info-circle"></i> প্রোডাক্ট কার্ড এবং হোমপেজে এই ছবি দেখাবে (সর্বোচ্চ 2MB)
                    </p>
                </div>

                <!-- Gallery Images -->
                <div>
                    <label class="form-label">
                        গ্যালারি ছবি (Gallery Images) <span class="badge badge-optional ml-2">Optional</span>
                    </label>
                    <div class="upload-zone" id="galleryDropZone" onclick="document.getElementById('gallery').click()">
                        <i class="bi bi-images"></i>
                        <p class="text-gray-700 font-semibold mt-3 mb-2">Drag & Drop images here or click to upload</p>
                        <p class="text-sm text-gray-500">Support: JPG, PNG, WebP (Max 5MB each)</p>
                        <input type="file" id="gallery" name="gallery[]" accept="image/*" multiple onchange="previewGallery(this)" class="hidden">
                    </div>
                        </button>
                    </div>
                    <div id="galleryPreview" class="grid grid-cols-4 gap-4 mt-4"></div>
                </div>

                <!-- Video URL -->
                <div>
                    <label for="video_url" class="form-label">
                        ভিডিও লিংক (Video URL)
                    </label>
                    <input type="url" id="video_url" name="video_url"
                        class="form-input"
                        placeholder="https://www.youtube.com/watch?v=...">
                    <p class="text-sm text-gray-500 mt-2">YouTube বা Vimeo রিভিউ ভিডিও লিংক দিন</p>
                </div>
            </div>
        </div>

        <!-- Section 4: SEO, Shipping & Warranty -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-gear text-orange-600"></i> এসইও, শিপিং ও ওয়ারেন্টি (SEO, Shipping & Warranty)
            </h2>

            <!-- SEO Section -->
            <div class="mb-8 pb-8 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800 mb-4">🔍 এসইও (SEO Optimization)</h3>
                <div class="space-y-4">
                    <div>
                        <label for="meta_title" class="form-label">
                            Meta Title
                        </label>
                        <input type="text" id="meta_title" name="meta_title"
                            class="form-input"
                            placeholder="ডিফল্টভাবে প্রোডাক্টের নাম থাকবে">
                    </div>
                    <div>
                        <label for="meta_keywords" class="form-label">
                            Meta Keywords
                        </label>
                        <input type="text" id="meta_keywords" name="meta_keywords"
                            class="form-input"
                            placeholder="keyword1, keyword2, keyword3">
                    </div>
                    <div>
                        <label for="meta_description" class="form-label">
                            Meta Description
                        </label>
                        <textarea id="meta_description" name="meta_description" rows="3"
                            class="form-input"
                            placeholder="সার্চ ইঞ্জিনে দেখানোর জন্য ছোট বর্ণনা"></textarea>
                    </div>
                </div>
            </div>

            <!-- Shipping Section -->
            <div class="mb-8 pb-8 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-800 mb-4">📦 শিপিং তথ্য (Shipping Info)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="weight" class="form-label">
                            ওজন (Weight in KG)
                        </label>
                        <input type="number" step="0.01" id="weight" name="weight"
                            class="form-input"
                            placeholder="0.5">
                    </div>
                    <div>
                        <label class="form-label">
                            মাত্রা (Dimensions - L x W x H cm)
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="number" step="0.01" name="length" placeholder="Length"
                                class="px-3 py-2 border border-gray-300 rounded-lg">
                            <input type="number" step="0.01" name="width" placeholder="Width"
                                class="px-3 py-2 border border-gray-300 rounded-lg">
                            <input type="number" step="0.01" name="height" placeholder="Height"
                                class="px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warranty Section -->
            <div>
                <h3 class="text-xl font-bold text-gray-800 mb-4">🛡️ ওয়ারেন্টি ও পলিসি (Warranty & Policy)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="warranty_type" class="form-label">
                            ওয়ারেন্টি ধরন (Warranty Type)
                        </label>
                        <select id="warranty_type" name="warranty_type"
                            class="form-input">
                            <option value="none">No Warranty</option>
                            <option value="brand">Brand Warranty</option>
                            <option value="shop">Shop Warranty</option>
                        </select>
                    </div>
                    <div>
                        <label for="warranty_period" class="form-label">
                            ওয়ারেন্টি সময়কাল (Warranty Period)
                        </label>
                        <select id="warranty_period" name="warranty_period"
                            class="form-input">
                            <option value="">Select Period</option>
                            <option value="7_days">7 Days Replacement</option>
                            <option value="6_months">6 Months</option>
                            <option value="1_year">1 Year</option>
                            <option value="2_years">2 Years</option>
                            <option value="3_years">3 Years</option>
                        </select>
                    </div>
                    <div>
                        <label for="return_policy" class="form-label">
                            রিটার্ন পলিসি (Return Policy)
                        </label>
                        <select id="return_policy" name="return_policy"
                            class="form-input">
                            <option value="no_return">No Return</option>
                            <option value="3_days">3 Days Return</option>
                            <option value="7_days">7 Days Return</option>
                            <option value="15_days">15 Days Return</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

    </form>

    <!-- Submit Action Bar -->
    <div class="submit-bar">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <i class="bi bi-shield-check text-2xl text-blue-600"></i>
                <div>
                    <p class="font-semibold text-gray-800">Ready to publish?</p>
                    <p class="text-sm text-gray-600">সব ফিল্ড সঠিকভাবে পূরণ করুন</p>
                </div>
            </div>
            <div class="flex gap-4">
                <button type="button" onclick="saveAsDraft()" 
                    class="btn-secondary inline-flex items-center gap-2">
                    <i class="bi bi-save"></i> Save as Draft
                </button>
                <button type="submit" onclick="publishProduct()" 
                    class="btn-success inline-flex items-center gap-2">
                    <i class="bi bi-check-circle"></i> Publish Product
                </button>
            </div>
        </div>
    </div>

</div>

<script>
// Initialize Summernote Rich Text Editor
$(document).ready(function() {
    $('#long_description').summernote({
        height: 300,
        placeholder: 'বিস্তারিত বর্ণনা লিখুন...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
});

// Initialize Tom Select for Category
let categorySelects = {};
let selectedCategoryId = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeRootCategory();
    initializeBrandSelect();
});

function initializeRootCategory() {
    fetch('api/get_children.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('category_level_1');
            data.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id;
                option.textContent = cat.name;
                select.appendChild(option);
            });
            
            categorySelects[1] = new TomSelect('#category_level_1', {
                create: true,
                placeholder: 'Electronics, Mobile, Laptop...',
                onOptionAdd: function(value, data) {
                    createNewCategory(value, null, 1);
                },
                onChange: function(value) {
                    if (value) {
                        const option = this.options[value];
                        onCategorySelected(value, option.text, 1, 'category_level_1');
                    }
                }
            });
        });
}

function initializeBrandSelect() {
    new TomSelect('#brand', {
        create: true,
        placeholder: 'Samsung, Apple, Xiaomi...',
        onOptionAdd: function(value, data) {
            createNewBrand(value);
        }
    });
}

function createNewCategory(name, parentId, level) {
    fetch('api/create_category.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, parent_id: parentId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Category created:', data);
        }
    });
}

function createNewBrand(name) {
    fetch('api/add_brand.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Brand created:', data);
    });
}

function onCategorySelected(categoryId, categoryName, level, selectId) {
    selectedCategoryId = categoryId;
    removeDeepLevels(level);
    updateCategoryPath();
    
    // Check if this category has children
    fetch(`api/get_children.php?parent_id=${categoryId}`)
        .then(response => response.json())
        .then(children => {
            if (children.length > 0) {
                addNextCategoryLevel(level + 1, categoryId);
            } else {
                // Load attributes for final category
                loadAttributesForCategory(categoryId);
            }
        });
}

function addNextCategoryLevel(level, parentId) {
    const container = document.getElementById('categoryContainer');
    const selectId = `category_level_${level}`;
    
    const selectWrapper = document.createElement('div');
    selectWrapper.className = 'mt-4';
    selectWrapper.id = `wrapper_level_${level}`;
    
    const select = document.createElement('select');
    select.id = selectId;
    select.className = 'tom-select w-full';
    
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = `-- Select Sub-Category (Level ${level}) --`;
    select.appendChild(defaultOption);
    
    selectWrapper.appendChild(select);
    container.appendChild(selectWrapper);
    
    // Load children
    fetch(`api/get_children.php?parent_id=${parentId}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id;
                option.textContent = cat.name;
                select.appendChild(option);
            });
            
            categorySelects[level] = new TomSelect(`#${selectId}`, {
                create: true,
                placeholder: 'Select or create sub-category...',
                onOptionAdd: function(value, data) {
                    createNewCategory(value, parentId, level);
                },
                onChange: function(value) {
                    if (value) {
                        const option = this.options[value];
                        onCategorySelected(value, option.text, level, selectId);
                    }
                }
            });
        });
}

function removeDeepLevels(currentLevel) {
    const container = document.getElementById('categoryContainer');
    for (let i = currentLevel + 1; i <= 10; i++) {
        const wrapper = document.getElementById(`wrapper_level_${i}`);
        if (wrapper) {
            wrapper.remove();
        }
        if (categorySelects[i]) {
            categorySelects[i].destroy();
            delete categorySelects[i];
        }
    }
}

function updateCategoryPath() {
    let path = [];
    for (let level in categorySelects) {
        const value = categorySelects[level].getValue();
        if (value) {
            const option = categorySelects[level].options[value];
            if (option) {
                path.push(option.text);
            }
        }
    }
    document.getElementById('categoryPath').innerHTML = path.length > 0 
        ? '<i class="bi bi-folder"></i> ' + path.join(' <i class="bi bi-chevron-right"></i> ')
        : '';
}

function loadAttributesForCategory(categoryId) {
    fetch(`api/get_attributes.php?category_id=${categoryId}`)
        .then(response => response.json())
        .then(attributes => {
            const container = document.getElementById('attributeContainer');
            container.innerHTML = '';
            
            attributes.forEach(attr => {
                const attrDiv = document.createElement('div');
                attrDiv.className = 'p-4 bg-gray-50 rounded-lg border border-gray-200';
                
                const label = document.createElement('label');
                label.className = 'block text-sm font-medium text-gray-700 mb-2';
                label.textContent = attr.name + (attr.is_required ? ' *' : '');
                
                const select = document.createElement('select');
                select.id = `attr_${attr.id}`;
                select.name = `attributes[${attr.id}]`;
                select.className = 'w-full px-3 py-2 border border-gray-300 rounded-lg';
                select.multiple = true;
                
                attr.values.forEach(val => {
                    const option = document.createElement('option');
                    option.value = val.id;
                    option.textContent = val.value;
                    select.appendChild(option);
                });
                
                attrDiv.appendChild(label);
                attrDiv.appendChild(select);
                container.appendChild(attrDiv);
                
                // Initialize Tom Select for multi-select
                new TomSelect(`#attr_${attr.id}`, {
                    plugins: ['remove_button'],
                    create: true,
                    onOptionAdd: function(value, data) {
                        createAttributeValue(attr.id, value);
                    }
                });
            });
        });
}

function createAttributeValue(attributeId, value) {
    fetch('api/create_attribute.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ attribute_id: attributeId, value: value })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Attribute value created:', data);
    });
}

// Product Type Toggle
function toggleProductType() {
    const productType = document.querySelector('input[name="product_type"]:checked').value;
    const simpleSection = document.getElementById('simpleProductSection');
    const variableSection = document.getElementById('variableProductSection');
    
    if (productType === 'simple') {
        simpleSection.style.display = 'block';
        variableSection.style.display = 'none';
    } else {
        simpleSection.style.display = 'none';
        variableSection.style.display = 'block';
    }
}

// Simple Product Profit Calculator
function calculateSimpleProfit() {
    const purchasePrice = parseFloat(document.getElementById('simple_purchase_price').value) || 0;
    const extraCost = parseFloat(document.getElementById('simple_extra_cost').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('simple_selling_price').value) || 0;
    
    const totalCost = purchasePrice + extraCost;
    const profit = sellingPrice - totalCost;
    const profitPercentage = totalCost > 0 ? ((profit / totalCost) * 100).toFixed(2) : 0;
    
    const displayElement = document.getElementById('simpleProfitDisplay');
    
    if (sellingPrice > 0 && totalCost > 0) {
        if (profit > 0) {
            displayElement.innerHTML = `<span class="profit-positive">
                ✅ লাভ: ${profit.toFixed(2)} Tk (${profitPercentage}%)
            </span>`;
        } else if (profit < 0) {
            displayElement.innerHTML = `<span class="profit-negative">
                ❌ লস: ${Math.abs(profit).toFixed(2)} Tk (${profitPercentage}%)
            </span>`;
        } else {
            displayElement.innerHTML = `<span class="text-gray-600">
                ⚖️ সমান (No Profit/Loss)
            </span>`;
        }
    } else {
        displayElement.innerHTML = '<span class="text-gray-400">দাম দিলে হিসাব দেখাবে</span>';
    }
}

// Variation Management
let variationCounter = 0;

function addVariationRow() {
    variationCounter++;
    const tbody = document.getElementById('variationTableBody');
    const row = document.createElement('tr');
    row.id = `variation_row_${variationCounter}`;
    row.className = 'border-b';
    
    row.innerHTML = `
        <td class="px-4 py-3">
            <input type="text" name="variations[${variationCounter}][attributes]" 
                class="w-full px-2 py-1 border rounded" placeholder="Black, 8GB, 128GB">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="variations[${variationCounter}][purchase_price]" 
                class="w-full px-2 py-1 border rounded" oninput="calculateVariationProfit(${variationCounter})">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="variations[${variationCounter}][extra_cost]" 
                class="w-full px-2 py-1 border rounded" oninput="calculateVariationProfit(${variationCounter})">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="variations[${variationCounter}][selling_price]" 
                class="w-full px-2 py-1 border rounded" oninput="calculateVariationProfit(${variationCounter})">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="variations[${variationCounter}][old_price]" 
                class="w-full px-2 py-1 border rounded">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="variations[${variationCounter}][stock]" 
                class="w-full px-2 py-1 border rounded">
        </td>
        <td class="px-4 py-3">
            <input type="file" name="variations[${variationCounter}][image]" 
                accept="image/*" class="w-full text-sm">
        </td>
        <td class="px-4 py-3" id="profit_${variationCounter}">
            <span class="text-gray-400 text-sm">-</span>
        </td>
        <td class="px-4 py-3">
            <button type="button" onclick="removeVariationRow(${variationCounter})" 
                class="text-red-600 hover:text-red-800">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function removeVariationRow(id) {
    document.getElementById(`variation_row_${id}`).remove();
}

function calculateVariationProfit(id) {
    const row = document.querySelector(`#variation_row_${id}`);
    const purchasePrice = parseFloat(row.querySelector('[name*="purchase_price"]').value) || 0;
    const extraCost = parseFloat(row.querySelector('[name*="extra_cost"]').value) || 0;
    const sellingPrice = parseFloat(row.querySelector('[name*="selling_price"]').value) || 0;
    
    const totalCost = purchasePrice + extraCost;
    const profit = sellingPrice - totalCost;
    const profitPercentage = totalCost > 0 ? ((profit / totalCost) * 100).toFixed(2) : 0;
    
    const profitCell = document.getElementById(`profit_${id}`);
    
    if (sellingPrice > 0 && totalCost > 0) {
        if (profit > 0) {
            profitCell.innerHTML = `<span class="profit-positive text-sm">
                +${profit.toFixed(2)} (${profitPercentage}%)
            </span>`;
        } else if (profit < 0) {
            profitCell.innerHTML = `<span class="profit-negative text-sm">
                ${profit.toFixed(2)} (${profitPercentage}%)
            </span>`;
        } else {
            profitCell.innerHTML = `<span class="text-gray-600 text-sm">0.00</span>`;
        }
    } else {
        profitCell.innerHTML = '<span class="text-gray-400 text-sm">-</span>';
    }
}

// Image Previews
function previewThumbnail(input) {
    const preview = document.getElementById('thumbnailPreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'image-preview';
            div.innerHTML = `
                <img src="${e.target.result}" width="120" height="120" style="object-fit: cover;">
                <span class="remove-btn" onclick="removeThumbnail()">×</span>
            `;
            preview.appendChild(div);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeThumbnail() {
    document.getElementById('thumbnail').value = '';
    document.getElementById('thumbnailPreview').innerHTML = '';
}

function previewGallery(input) {
    const preview = document.getElementById('galleryPreview');
    preview.innerHTML = '';
    
    if (input.files) {
        Array.from(input.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'image-preview';
                div.innerHTML = `
                    <img src="${e.target.result}" width="100" height="100" style="object-fit: cover;">
                    <span class="remove-btn" onclick="removeGalleryImage(${index})">×</span>
                `;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
}

function removeGalleryImage(index) {
    // Implementation for removing specific gallery image
    console.log('Remove gallery image:', index);
}

// Form Submission
function saveAsDraft() {
    const form = document.getElementById('productForm');
    const formData = new FormData(form);
    formData.append('status', 'draft');
    
    submitProductForm(formData);
}

function publishProduct() {
    const form = document.getElementById('productForm');
    const formData = new FormData(form);
    formData.append('status', 'published');
    
    submitProductForm(formData);
}

function submitProductForm(formData) {
    fetch('api/save_product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('✅ Product saved successfully!');
            window.location.href = 'products.php';
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to save product');
    });
}

// Auto-fill meta title from product name
document.getElementById('product_name').addEventListener('input', function() {
    const metaTitleField = document.getElementById('meta_title');
    if (!metaTitleField.value) {
        metaTitleField.value = this.value;
    }
});
</script>

</body>
</html>
