<?php
require_once '../core/auth.php';
require_once '../core/db.php';

// Check if user is logged in and is vendor/admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

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
</head>
<body class="bg-gray-50">

<?php require_once '../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">
    
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Add New Product</h1>
        <p class="text-gray-600">Create a new product listing with dynamic categories and attributes</p>
    </div>

    <!-- Main Form -->
    <form id="productForm" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg p-8">
        
        <!-- Basic Product Info -->
        <div class="mb-8 pb-8 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        Product Title <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title" 
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="e.g., iPhone 15 Pro Max"
                    >
                </div>
                
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">
                        SKU <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="sku" 
                        name="sku" 
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="e.g., SKU-2024-001"
                    >
                </div>
            </div>

            <div class="mt-6">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>
                <textarea 
                    id="description" 
                    name="description"
                    rows="4"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Product description..."
                ></textarea>
            </div>
        </div>

        <!-- Category Selection (Hierarchical with Tom Select) -->
        <div class="mb-8 pb-8 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Category</h2>
            
            <div id="categorySelectors" class="space-y-6">
                <!-- Root Category Selector (will be populated via JS) -->
                <div class="category-level" data-level="0">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Category Level 1 <span class="text-red-500">*</span>
                    </label>
                    <select 
                        id="category_0" 
                        name="categories[]"
                        class="category-select w-full"
                        data-level="0"
                        required
                    >
                        <option value="">-- Select Category --</option>
                    </select>
                </div>
            </div>

            <!-- Selected Category Display -->
            <div id="selectedCategoryPath" class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg hidden">
                <p class="text-sm text-gray-600 mb-1">Selected Path:</p>
                <p id="categoryPath" class="text-lg font-medium text-blue-900"></p>
            </div>
        </div>

        <!-- Attributes Section -->
        <div class="mb-8 pb-8 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Product Attributes</h2>
            <div id="attributesContainer" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 bg-gray-100 border border-gray-300 rounded text-center text-gray-500">
                    Select a category first to display attributes
                </div>
            </div>
        </div>

        <!-- Pricing & Stock -->
        <div class="mb-8 pb-8 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Pricing & Stock</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                        Price (Base) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input 
                            type="number" 
                            id="price" 
                            name="price" 
                            required
                            step="0.01"
                            min="0"
                            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00"
                        >
                    </div>
                </div>

                <div>
                    <label for="offer_price" class="block text-sm font-medium text-gray-700 mb-2">
                        Offer Price (Optional)
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input 
                            type="number" 
                            id="offer_price" 
                            name="offer_price" 
                            step="0.01"
                            min="0"
                            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00"
                        >
                    </div>
                </div>

                <div>
                    <label for="stock_quantity" class="block text-sm font-medium text-gray-700 mb-2">
                        Stock Quantity <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="number" 
                        id="stock_quantity" 
                        name="stock_quantity" 
                        required
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="0"
                    >
                </div>
            </div>
        </div>

        <!-- Product Images -->
        <div class="mb-8 pb-8 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Product Images</h2>
            
            <div>
                <label for="images" class="block text-sm font-medium text-gray-700 mb-2">
                    Upload Images
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition">
                    <input 
                        type="file" 
                        id="images" 
                        name="images[]" 
                        multiple
                        accept="image/*"
                        class="hidden"
                    >
                    <label for="images" class="cursor-pointer">
                        <div class="text-gray-500 mb-2">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </div>
                        <p class="font-medium">Click to upload or drag and drop</p>
                        <p class="text-xs">PNG, JPG, GIF up to 5MB</p>
                    </label>
                </div>
                <div id="imagePreview" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4"></div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex gap-4">
            <button 
                type="submit" 
                id="submitBtn"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition"
            >
                Create Product
            </button>
            <a 
                href="products.php"
                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 rounded-lg text-center transition"
            >
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// ============================================================================
// PRODUCT ADD PAGE - Dynamic Category & Attribute System with Tom Select
// ============================================================================

const API = {
    getChildren: '/techhat/admin/api/get_children.php',
    getAttributes: '/techhat/admin/api/get_attributes.php',
    createCategory: '/techhat/admin/api/create_category.php',
    createAttribute: '/techhat/admin/api/create_attribute.php'
};

const state = {
    selectedCategories: {},
    categorySelects: {},
    tomSelects: {}
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeRootCategory();
    setupEventListeners();
});

// ============================================================================
// 1. INITIALIZE ROOT CATEGORY SELECTOR
// ============================================================================

async function initializeRootCategory() {
    try {
        const response = await fetch(API.getChildren);
        const result = await response.json();
        
        if (result.status !== 'success') throw new Error('Failed to load categories');

        const select = document.getElementById('category_0');
        
        // Clear existing options except the placeholder
        while (select.options.length > 1) {
            select.remove(1);
        }

        // Add fetched categories
        result.data.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            select.appendChild(option);
        });

        // Initialize Tom Select on root category
        initializeTomSelect('category_0', null);

    } catch (error) {
        console.error('Error loading root categories:', error);
        alert('Failed to load categories');
    }
}

// ============================================================================
// 2. INITIALIZE TOM SELECT WITH CREATE FUNCTIONALITY
// ============================================================================

function initializeTomSelect(selectId, parentId = null) {
    const selectEl = document.getElementById(selectId);
    const level = parseInt(selectEl.dataset.level);

    // Destroy existing Tom Select if it exists
    if (state.tomSelects[selectId]) {
        state.tomSelects[selectId].destroy();
    }

    // Initialize Tom Select with create option
    state.tomSelects[selectId] = new TomSelect(selectEl, {
        create: true,
        placeholder: 'Select or create category',
        createOnBlur: true,
        allowEmptyOption: false,
        onChange: async (value) => {
            if (!value) return;

            // Check if this is a new category being created
            const selectedOption = selectEl.querySelector(`option[value="${value}"]`);
            
            if (!selectedOption || selectedOption.textContent === '') {
                // New category - create it via AJAX
                await createNewCategory(value, parentId, selectId);
            } else {
                // Existing category selected
                const categoryName = selectedOption.textContent;
                await onCategorySelected(value, categoryName, level, selectId);
            }
        }
    });

    state.categorySelects[selectId] = selectEl;
}

// ============================================================================
// 3. CREATE NEW CATEGORY VIA AJAX
// ============================================================================

async function createNewCategory(name, parentId, selectId) {
    try {
        const formData = new FormData();
        formData.append('name', name);
        if (parentId) formData.append('parent_id', parentId);

        const response = await fetch(API.createCategory, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        
        if (result.status !== 'success') {
            alert('Error: ' + result.message);
            return;
        }

        // Add the new category to the select and select it
        const selectEl = document.getElementById(selectId);
        const option = document.createElement('option');
        option.value = result.id;
        option.textContent = result.name;
        selectEl.appendChild(option);

        // Refresh Tom Select
        state.tomSelects[selectId].clearOptions();
        const optionsHTML = Array.from(selectEl.options).map(opt => ({
            value: opt.value,
            text: opt.textContent
        }));
        state.tomSelects[selectId].load(() => optionsHTML);
        state.tomSelects[selectId].setValue(result.id);

        // Handle the selection
        const level = parseInt(selectEl.dataset.level);
        await onCategorySelected(result.id, result.name, level, selectId);

    } catch (error) {
        console.error('Error creating category:', error);
        alert('Failed to create category');
    }
}

// ============================================================================
// 4. HANDLE CATEGORY SELECTION & LOAD CHILDREN
// ============================================================================

async function onCategorySelected(categoryId, categoryName, level, selectId) {
    state.selectedCategories[level] = {
        id: categoryId,
        name: categoryName
    };

    // Load attributes for this category
    await loadAttributes(categoryId);

    // Check if this category has children
    try {
        const response = await fetch(`${API.getChildren}?parent_id=${categoryId}`);
        const result = await response.json();

        if (result.data.length > 0) {
            // Create a new selector for the next level
            createNextLevelSelector(level, categoryId, result.data);
        } else {
            // No children - remove any deeper levels
            removeDeepLevels(level);
        }

        // Update the category path display
        updateCategoryPath();

    } catch (error) {
        console.error('Error checking for child categories:', error);
    }
}

// ============================================================================
// 5. CREATE NEXT LEVEL CATEGORY SELECTOR
// ============================================================================

function createNextLevelSelector(currentLevel, parentId, childCategories) {
    const nextLevel = currentLevel + 1;
    const selectId = `category_${nextLevel}`;
    const container = document.getElementById('categorySelectors');

    // Remove any selectors deeper than this level
    const toRemove = container.querySelectorAll(`[data-level]`);
    toRemove.forEach(el => {
        if (parseInt(el.dataset.level) > currentLevel) {
            el.remove();
        }
    });

    // Check if this level selector already exists
    let existingSelect = document.getElementById(selectId);
    if (existingSelect) {
        existingSelect.parentElement.remove();
    }

    // Create new level container
    const levelDiv = document.createElement('div');
    levelDiv.className = 'category-level';
    levelDiv.setAttribute('data-level', nextLevel);

    const label = document.createElement('label');
    label.className = 'block text-sm font-medium text-gray-700 mb-2';
    label.innerHTML = `Category Level ${nextLevel + 1} <span class="text-red-500">*</span>`;

    const select = document.createElement('select');
    select.id = selectId;
    select.className = 'category-select w-full';
    select.name = 'categories[]';
    select.setAttribute('data-level', nextLevel);

    // Add placeholder option
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = `-- Select Level ${nextLevel + 1} --`;
    select.appendChild(placeholder);

    // Add child categories
    childCategories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id;
        option.textContent = cat.name;
        select.appendChild(option);
    });

    levelDiv.appendChild(label);
    levelDiv.appendChild(select);
    container.appendChild(levelDiv);

    // Initialize Tom Select on new selector
    initializeTomSelect(selectId, parentId);
}

// ============================================================================
// 6. REMOVE DEEPER LEVEL SELECTORS
// ============================================================================

function removeDeepLevels(level) {
    const container = document.getElementById('categorySelectors');
    const toRemove = container.querySelectorAll(`[data-level]`);
    
    toRemove.forEach(el => {
        if (parseInt(el.dataset.level) > level) {
            el.remove();
        }
    });

    // Clean state
    Object.keys(state.selectedCategories).forEach(key => {
        if (parseInt(key) > level) {
            delete state.selectedCategories[key];
        }
    });
}

// ============================================================================
// 7. UPDATE CATEGORY PATH DISPLAY
// ============================================================================

function updateCategoryPath() {
    const path = [];
    for (let i = 0; i <= Object.keys(state.selectedCategories).length; i++) {
        if (state.selectedCategories[i]) {
            path.push(state.selectedCategories[i].name);
        }
    }

    if (path.length > 0) {
        const pathEl = document.getElementById('categoryPath');
        pathEl.textContent = path.join(' → ');
        document.getElementById('selectedCategoryPath').classList.remove('hidden');
    }
}

// ============================================================================
// 8. LOAD ATTRIBUTES FOR SELECTED CATEGORY
// ============================================================================

async function loadAttributes(categoryId) {
    try {
        const response = await fetch(`${API.getAttributes}?category_id=${categoryId}`);
        const result = await response.json();

        const container = document.getElementById('attributesContainer');
        container.innerHTML = '';

        if (result.data.length === 0) {
            container.innerHTML = '<div class="col-span-2 p-4 bg-gray-100 text-center text-gray-500">No attributes for this category</div>';
            return;
        }

        // Create inputs for each attribute
        result.data.forEach(attr => {
            const div = document.createElement('div');
            div.className = 'attribute-field';

            const label = document.createElement('label');
            label.className = 'block text-sm font-medium text-gray-700 mb-2';
            label.innerHTML = `${attr.name} ${attr.is_required ? '<span class="text-red-500">*</span>' : ''}`;

            if (attr.type === 'select' || attr.type === 'multiselect') {
                const select = document.createElement('select');
                select.name = `attributes[${attr.id}][]`;
                select.className = 'attribute-select w-full';
                if (attr.type === 'multiselect') select.multiple = true;
                select.setAttribute('data-attribute-id', attr.id);
                if (attr.is_required) select.required = true;

                // Add placeholder
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = `Select ${attr.name}...`;
                select.appendChild(placeholder);

                // Add existing values
                attr.values.forEach(val => {
                    const option = document.createElement('option');
                    option.value = val.id;
                    option.textContent = val.value;
                    if (val.color_code) option.setAttribute('data-color', val.color_code);
                    select.appendChild(option);
                });

                // Initialize Tom Select with create option
                new TomSelect(select, {
                    create: false,
                    placeholder: `Select or create ${attr.name}`,
                    maxItems: attr.type === 'multiselect' ? null : 1
                });

                div.appendChild(label);
                div.appendChild(select);
            } else {
                // Text input for non-select attributes
                const input = document.createElement('input');
                input.type = 'text';
                input.name = `attributes[${attr.id}]`;
                input.className = 'w-full px-4 py-2 border border-gray-300 rounded-lg';
                if (attr.is_required) input.required = true;
                
                div.appendChild(label);
                div.appendChild(input);
            }

            container.appendChild(div);
        });

    } catch (error) {
        console.error('Error loading attributes:', error);
    }
}

// ============================================================================
// 9. FORM SUBMISSION & IMAGE PREVIEW
// ============================================================================

function setupEventListeners() {
    // Image preview
    const imageInput = document.getElementById('images');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';

            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const div = document.createElement('div');
                    div.className = 'relative aspect-square';
                    div.innerHTML = `
                        <img src="${event.target.result}" class="w-full h-full object-cover rounded-lg">
                        <button type="button" class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1">✕</button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    // Form submission
    document.getElementById('productForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';

        try {
            const formData = new FormData(document.getElementById('productForm'));
            
            // Add final category ID
            const finalLevel = Math.max(...Object.keys(state.selectedCategories).map(k => parseInt(k)));
            if (finalLevel >= 0 && state.selectedCategories[finalLevel]) {
                formData.append('category_id', state.selectedCategories[finalLevel].id);
            }

            // TODO: Send to actual product creation endpoint
            console.log('Form data ready:', Object.fromEntries(formData));
            alert('Product creation endpoint not yet implemented');

        } catch (error) {
            console.error('Error submitting form:', error);
            alert('Error submitting form');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Product';
        }
    });
}
</script>

</body>
</html>
