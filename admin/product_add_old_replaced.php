<?php
require_once '../core/auth.php'; // আপনার পাথ অনুযায়ী ঠিক করে নিবেন
require_admin();

// ডাটাবেস কানেকশন (যদি auth.php তে না থাকে)
// require_once '../core/db_connect.php'; 

// ব্র্যান্ড লোড করা হচ্ছে
$brands = $pdo->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin Panel</title>
    
    <!-- CSS Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        /* Custom Font & Base */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }

        /* Tooltip Styling */
        .info-group { position: relative; display: inline-block; cursor: pointer; }
        .info-icon { color: #94a3b8; transition: color 0.2s; }
        .info-group:hover .info-icon { color: #3b82f6; }
        .tooltip-text {
            visibility: hidden; width: 220px; background-color: #1e293b; color: #fff;
            text-align: center; border-radius: 6px; padding: 8px; position: absolute;
            z-index: 50; top: 125%; right: 0; margin-left: -60px;
            opacity: 0; transition: opacity 0.3s; font-size: 0.75rem; font-weight: 400;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .tooltip-text::after {
            content: ""; position: absolute; bottom: 100%; right: 10px;
            margin-left: -5px; border-width: 5px; border-style: solid;
            border-color: transparent transparent #1e293b transparent;
        }
        .info-group:hover .tooltip-text { visibility: visible; opacity: 1; }

        /* Card & Layout */
        .glass-card { background: white; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .form-input { 
            width: 100%; border-radius: 0.5rem; border: 1px solid #cbd5e1; padding: 0.625rem; font-size: 0.875rem; transition: all 0.2s;
        }
        .form-input:focus { outline: none; border-color: #3b82f6; ring: 2px; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

        /* Tom Select Customization */
        .ts-control { border-radius: 0.5rem; padding: 0.625rem; border-color: #cbd5e1; }
        .ts-wrapper.focus .ts-control { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        /* Sidebar Fix */
        .layout-wrapper { display: flex; min-height: 100vh; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-x: hidden; }
    </style>
</head>
<body>

<div class="layout-wrapper">
    
    <!-- SIDEBAR SECTION -->
    <!-- আপনার সাইডবার ফাইলটি এখানে ইনক্লুড করা হচ্ছে। ফ্লেক্সবক্সের কারণে এটি বামে ফিক্সড থাকবে -->
    <div class="w-64 bg-slate-900 text-white hidden md:block flex-shrink-0">
        <?php include 'partials/sidebar.php'; ?>
    </div>

    <!-- MAIN CONTENT SECTION -->
    <main class="main-content bg-gray-50">
        
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30 px-8 py-4 flex justify-between items-center shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Add New Product</h1>
                <p class="text-sm text-slate-500 mt-1">Create a product with variations and SEO details.</p>
            </div>
            <div class="flex gap-3">
                <a href="products.php" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition">Cancel</a>
                <button type="submit" form="productForm" class="px-6 py-2.5 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition flex items-center gap-2">
                    <i class="bi bi-check2-circle"></i> Save Product
                </button>
            </div>
        </header>

        <!-- Form Content -->
        <div class="p-8 overflow-y-auto">
            <form id="productForm" action="api/save_product_new.php" method="POST" enctype="multipart/form-data">
                
                <!-- Main Grid Layout -->
                <div class="grid grid-cols-12 gap-8">
                    
                    <!-- LEFT COLUMN (Main Data) - 8 Columns -->
                    <div class="col-span-12 lg:col-span-8 space-y-8">
                        
                        <!-- 1. Basic Info -->
                        <div class="glass-card p-6 relative">
                            <div class="flex justify-between items-start mb-6">
                                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm">1</span>
                                    Basic Information
                                </h3>
                                <!-- Tooltip -->
                                <div class="info-group">
                                    <i class="bi bi-info-circle-fill info-icon text-xl"></i>
                                    <span class="tooltip-text">পণ্যের মূল নাম, বিবরণ এবং ব্র্যান্ড নির্বাচন করুন। স্টারে মার্ক করা ফিল্ডগুলো বাধ্যতামূলক।</span>
                                </div>
                            </div>
                            
                            <div class="space-y-5">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" class="form-input" placeholder="e.g. Samsung Galaxy S23 Ultra" required>
                                </div>
                                <div class="grid grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Brand</label>
                                        <select name="brand_id" id="brand-select" placeholder="Select Brand...">
                                            <option value="">Select Brand...</option>
                                            <?php foreach ($brands as $brand): ?>
                                            <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Unit</label>
                                        <input type="text" name="unit" class="form-input" placeholder="e.g. pc, kg, box">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                                    <textarea name="description" id="summernote"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Variations & Pricing -->
                        <div class="glass-card p-6">
                            <div class="flex justify-between items-start mb-6">
                                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm">2</span>
                                    Variations & Pricing
                                </h3>
                                <div class="info-group">
                                    <i class="bi bi-info-circle-fill info-icon text-xl"></i>
                                    <span class="tooltip-text">কালার বা সাইজ অনুযায়ী ভেরিয়েশন তৈরি করুন। লাভ (Profit) অটোমেটিক হিসাব হবে।</span>
                                </div>
                            </div>

                            <!-- Attribute Adder -->
                            <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-5 mb-6">
                                <div class="grid grid-cols-12 gap-4 items-end">
                                    <div class="col-span-3">
                                        <label class="text-xs font-bold uppercase text-slate-500 mb-1 block">Attribute</label>
                                        <select id="attr-name" class="form-input bg-white">
                                            <option value="Color">Color</option>
                                            <option value="RAM">RAM</option>
                                            <option value="Storage">Storage</option>
                                            <option value="Size">Size</option>
                                        </select>
                                    </div>
                                    <div class="col-span-7">
                                        <label class="text-xs font-bold uppercase text-slate-500 mb-1 block">Values (Type & Enter)</label>
                                        <input type="text" id="attr-values" placeholder="e.g. Red, Blue, 8GB...">
                                    </div>
                                    <div class="col-span-2">
                                        <button type="button" onclick="generateRows()" class="w-full py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition text-sm font-medium">
                                            <i class="bi bi-plus-lg"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Table -->
                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-gray-100 text-slate-600 font-semibold uppercase text-xs">
                                        <tr>
                                            <th class="px-4 py-3 border-b">Variant</th>
                                            <th class="px-4 py-3 border-b w-32">Buying Price</th>
                                            <th class="px-4 py-3 border-b w-24">Extra</th>
                                            <th class="px-4 py-3 border-b w-32">Selling Price</th>
                                            <th class="px-4 py-3 border-b w-24">Stock</th>
                                            <th class="px-4 py-3 border-b w-20">Profit</th>
                                            <th class="px-4 py-3 border-b">Image</th>
                                            <th class="px-4 py-3 border-b text-center"><i class="bi bi-trash"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody id="variation-table-body" class="bg-white divide-y divide-gray-100">
                                        <!-- Default Row -->
                                        <tr id="default-row">
                                            <td class="px-4 py-3 text-slate-500 italic">Default Product</td>
                                            <td class="px-4 py-3"><input type="number" name="variations[0][buy]" id="buy_0" oninput="calcProfit(0)" class="form-input py-1 h-8 text-xs" required></td>
                                            <td class="px-4 py-3"><input type="number" name="variations[0][extra]" id="extra_0" oninput="calcProfit(0)" class="form-input py-1 h-8 text-xs" value="0"></td>
                                            <td class="px-4 py-3"><input type="number" name="variations[0][sell]" id="sell_0" oninput="calcProfit(0)" class="form-input py-1 h-8 text-xs font-bold text-blue-600" required></td>
                                            <td class="px-4 py-3"><input type="number" name="variations[0][stock]" class="form-input py-1 h-8 text-xs" required></td>
                                            <td class="px-4 py-3 font-bold" id="profit_0">-</td>
                                            <td class="px-4 py-3"><input type="file" name="variations[0][image]" class="text-xs w-24"></td>
                                            <td class="px-4 py-3 text-center text-gray-300"><i class="bi bi-lock"></i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                    
                    <!-- RIGHT COLUMN (Side Widgets) - 4 Columns -->
                    <div class="col-span-12 lg:col-span-4 space-y-8">
                        
                        <!-- 3. Category Tree (Recursive) -->
                        <div class="glass-card p-6 border-t-4 border-t-purple-500">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-base font-bold text-slate-800">Category Selection</h3>
                                <div class="info-group">
                                    <i class="bi bi-info-circle-fill info-icon"></i>
                                    <span class="tooltip-text">ক্যাটাগরি সিলেক্ট করুন। সাব-ক্যাটাগরি থাকলে অটোমেটিক লোড হবে। নতুন তৈরি করতে টাইপ করে এন্টার দিন।</span>
                                </div>
                            </div>
                            
                            <div id="category-chain" class="space-y-3">
                                <!-- Level 1 Select will be injected here via JS -->
                            </div>
                            
                            <!-- Hidden input to store the final selected category -->
                            <input type="hidden" name="final_category_id" id="final_category_id" required>
                            
                            <div class="mt-3 p-3 bg-yellow-50 rounded border border-yellow-100 text-xs text-yellow-700">
                                <i class="bi bi-lightbulb-fill mr-1"></i> Tip: Select nested categories until you reach the final level.
                            </div>
                        </div>

                        <!-- 4. Media Gallery -->
                        <div class="glass-card p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-base font-bold text-slate-800">Gallery Images</h3>
                                <div class="info-group">
                                    <i class="bi bi-info-circle-fill info-icon"></i>
                                    <span class="tooltip-text">একাধিক ছবি আপলোড করতে পারেন। এগুলো প্রোডাক্ট স্লাইডারে দেখাবে।</span>
                                </div>
                            </div>
                            
                            <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center hover:bg-slate-50 transition cursor-pointer relative">
                                <input type="file" name="gallery[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" id="gallery-input">
                                <i class="bi bi-cloud-arrow-up text-3xl text-slate-400"></i>
                                <p class="text-sm font-medium text-slate-600 mt-2">Click to Upload</p>
                                <p class="text-xs text-slate-400">or drag and drop</p>
                            </div>
                            <div id="gallery-preview" class="grid grid-cols-4 gap-2 mt-4"></div>
                        </div>

                        <!-- 5. Status & Organization -->
                        <div class="glass-card p-6">
                            <h3 class="text-base font-bold text-slate-800 mb-4">Organization</h3>
                            
                            <div class="flex items-center justify-between mb-4 p-3 bg-gray-50 rounded border border-gray-200">
                                <span class="text-sm font-medium text-slate-700">Active Status</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="status" value="1" checked class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <div class="mb-3">
                                <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">SKU Code</label>
                                <input type="text" name="sku" class="form-input" placeholder="Auto-generated if empty">
                            </div>
                            
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">Tags</label>
                                <input type="text" name="tags" class="form-input" placeholder="Gaming, Budget, 5G">
                            </div>
                        </div>

                    </div> <!-- End Right Column -->

                </div> <!-- End Grid -->
            </form>
        </div>
    </main>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
    // --- 1. Summernote Init ---
    $('#summernote').summernote({
        placeholder: 'Write detailed product description...',
        tabsize: 2,
        height: 200,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'clear']],
            ['para', ['ul', 'ol']],
            ['insert', ['link', 'picture']]
        ]
    });

    // --- 2. Tom Select for Brand ---
    new TomSelect("#brand-select", { create: true });

    // --- 3. RECURSIVE CATEGORY LOGIC (The Main Fix) ---
    
    // Initial Load of Level 1
    fetchSubCategories(null, 0);

    function fetchSubCategories(parentId, level) {
        // Prepare API URL
        let url = `api/get_categories.php${parentId ? '?parent_id=' + parentId : ''}`;
        
        fetch(url)
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success' && response.data.length > 0) {
                    createCategorySelect(response.data, level);
                } else if (level === 0) {
                    // No categories at all?
                    document.getElementById('category-chain').innerHTML = '<p class="text-red-500 text-sm">No categories found.</p>';
                }
            })
            .catch(err => console.error('Error fetching categories:', err));
    }

    function createCategorySelect(categories, level) {
        const container = document.getElementById('category-chain');
        
        // Remove any selects that are deeper than current level (cleanup)
        // e.g. if I change Level 1, remove Level 2 and 3
        while (container.children.length > level) {
            container.removeChild(container.lastChild);
        }

        // Create wrapper for select
        const wrapper = document.createElement('div');
        wrapper.className = "mb-2 relative";
        
        const select = document.createElement('select');
        select.className = `cat-select level-${level} w-full`;
        select.setAttribute('placeholder', level === 0 ? 'Select Main Category...' : 'Select Sub Category...');
        
        // Add default empty option
        select.innerHTML = '<option value="">Select...</option>';
        
        categories.forEach(cat => {
            select.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
        });

        wrapper.appendChild(select);
        container.appendChild(wrapper);

        // Init Tom Select
        let ts = new TomSelect(select, {
            create: true, // Allow creating new categories on the fly
            onOptionAdd: function(value, $item) {
                // Logic to save new category via AJAX could go here
                // For now, we assume user selects existing or creates local
                alert("New category creation via API needs to be implemented here if desired.");
            },
            onChange: function(value) {
                if (value) {
                    // Update hidden input
                    document.getElementById('final_category_id').value = value;
                    // Try to fetch children
                    fetchSubCategories(value, level + 1);
                } else {
                    // Cleared selection, remove children
                    while (container.children.length > level + 1) {
                        container.removeChild(container.lastChild);
                    }
                    // Reset ID to parent if available
                    // Logic omitted for brevity
                }
            }
        });
    }

    // --- 4. Variation & Attribute Logic (With Tom Select) ---
    let attrTom = new TomSelect('#attr-values', {
        create: true,
        plugins: ['remove_button'],
        delimiter: ',',
        persist: false,
        create: function(input) { return { value: input, text: input }; }
    });

    function generateRows() {
        let attrName = document.getElementById('attr-name').value;
        let attrVals = attrTom.getValue(); // Array e.g. ["Red", "Blue"]

        if (attrVals.length === 0) { alert('Please enter values!'); return; }

        let tbody = document.getElementById('variation-table-body');
        
        // Remove default row
        let defaultRow = document.getElementById('default-row');
        if(defaultRow) defaultRow.remove();

        attrVals.forEach(val => {
            let rowId = Math.random().toString(36).substr(2, 9);
            let tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 border-b";
            tr.innerHTML = `
                <td class="px-4 py-3 font-medium">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                        ${attrName}: ${val}
                    </span>
                    <input type="hidden" name="variations[${rowId}][attr]" value='{"${attrName}": "${val}"}'>
                </td>
                <td class="px-4 py-3"><input type="number" step="0.01" oninput="calcProfit('${rowId}')" id="buy_${rowId}" name="variations[${rowId}][buy]" class="form-input py-1 h-8 text-xs" required></td>
                <td class="px-4 py-3"><input type="number" step="0.01" oninput="calcProfit('${rowId}')" id="extra_${rowId}" name="variations[${rowId}][extra]" class="form-input py-1 h-8 text-xs" value="0"></td>
                <td class="px-4 py-3"><input type="number" step="0.01" oninput="calcProfit('${rowId}')" id="sell_${rowId}" name="variations[${rowId}][sell]" class="form-input py-1 h-8 text-xs font-bold text-blue-600" required></td>
                <td class="px-4 py-3"><input type="number" name="variations[${rowId}][stock]" class="form-input py-1 h-8 text-xs" required></td>
                <td class="px-4 py-3 text-sm font-bold" id="profit_${rowId}">0.00</td>
                <td class="px-4 py-3"><input type="file" name="variations[${rowId}][image]" class="text-xs w-24"></td>
                <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
        });
        
        attrTom.clear();
    }

    function calcProfit(id) {
        let buy = parseFloat(document.getElementById('buy_' + id).value) || 0;
        let extra = parseFloat(document.getElementById('extra_' + id).value) || 0;
        let sell = parseFloat(document.getElementById('sell_' + id).value) || 0;

        let totalCost = buy + extra;
        let profit = sell - totalCost;
        let el = document.getElementById('profit_' + id);

        el.innerText = profit.toFixed(2);
        el.className = profit >= 0 ? 'px-4 py-3 text-sm font-bold text-green-600' : 'px-4 py-3 text-sm font-bold text-red-600';
    }

    // --- 5. Gallery Preview ---
    document.getElementById('gallery-input').addEventListener('change', function(e) {
        const preview = document.getElementById('gallery-preview');
        preview.innerHTML = '';
        Array.from(e.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(event) {
                const div = document.createElement('div');
                div.innerHTML = `<img src="${event.target.result}" class="w-full h-16 object-cover rounded border border-gray-200">`;
                preview.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    });

</script>
</body>
</html>