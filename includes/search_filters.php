<form action="search.php" method="GET" class="space-y-6">
    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

    <!-- Active Filters Summary -->
    <?php if(!empty($selected_brands) || !empty($selected_cats) || $min_price > 0 || $max_price < 500000 || $in_stock_only): ?>
    <div class="bg-pink-50 border border-pink-200 rounded-xl p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold uppercase text-pink-700 flex items-center gap-2">
                <i class="bi bi-funnel-fill"></i> Active Filters
            </span>
            <a href="search.php?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
               class="text-xs text-pink-600 hover:text-pink-800 font-semibold hover:underline">
                Clear All
            </a>
        </div>
        <div class="flex flex-wrap gap-2 mt-2">
            <?php if($min_price > 0 || $max_price < 500000): ?>
                <span class="inline-flex items-center gap-1 bg-white px-2 py-1 rounded-lg text-xs font-medium border border-pink-200">
                    ৳<?php echo number_format($min_price); ?> - ৳<?php echo number_format($max_price); ?>
                </span>
            <?php endif; ?>
            <?php if($in_stock_only): ?>
                <span class="inline-flex items-center gap-1 bg-white px-2 py-1 rounded-lg text-xs font-medium border border-pink-200">
                    In Stock Only
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Price Range -->
    <div class="border-b border-gray-200 pb-6">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="bi bi-currency-dollar text-pink-600"></i>
            Price Range
        </h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between text-sm text-gray-600">
                <span>Min: ৳<span id="minDisplay"><?php echo number_format($min_price); ?></span></span>
                <span>Max: ৳<span id="maxDisplay"><?php echo number_format($max_price); ?></span></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" name="min_price" value="<?php echo $min_price; ?>" 
                       placeholder="Min" min="0" step="100"
                       onchange="document.getElementById('minDisplay').textContent = parseInt(this.value).toLocaleString()"
                       class="price-input w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:outline-none transition">
                <input type="number" name="max_price" value="<?php echo $max_price; ?>" 
                       placeholder="Max" min="0" step="100"
                       onchange="document.getElementById('maxDisplay').textContent = parseInt(this.value).toLocaleString()"
                       class="price-input w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:outline-none transition">
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-pink-600 to-purple-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:from-pink-700 hover:to-purple-700 transition shadow-md">
                Apply Price Filter
            </button>
        </div>
    </div>

    <!-- Availability -->
    <div class="border-b border-gray-200 pb-6">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="bi bi-box-seam text-pink-600"></i>
            Availability
        </h3>
        <label class="flex items-center gap-3 cursor-pointer group">
            <input type="checkbox" name="in_stock" value="1" 
                   onchange="this.form.submit()"
                   <?php echo $in_stock_only ? 'checked' : ''; ?>
                   class="w-5 h-5 text-pink-600 border-gray-300 rounded focus:ring-pink-500">
            <span class="text-sm text-gray-700 group-hover:text-pink-600 transition font-medium">
                In Stock Only
            </span>
        </label>
    </div>

    <!-- Categories -->
    <?php if(!empty($filterCats)): ?>
    <div class="border-b border-gray-200 pb-6">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="bi bi-grid-3x3-gap-fill text-pink-600"></i>
            Categories
        </h3>
        <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
            <?php foreach($filterCats as $cat): ?>
            <label class="flex items-center gap-3 cursor-pointer group hover:bg-pink-50 p-2 rounded-lg transition">
                <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" 
                       onchange="this.form.submit()"
                       <?php echo in_array($cat['id'], $selected_cats) ? 'checked' : ''; ?>
                       class="w-4 h-4 text-pink-600 border-gray-300 rounded focus:ring-pink-500">
                <span class="text-sm text-gray-700 group-hover:text-pink-600 transition flex-1 font-medium">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </span>
                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full group-hover:bg-pink-100 group-hover:text-pink-600">
                    <?php echo $cat['count']; ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Brands -->
    <?php if(!empty($filterBrands)): ?>
    <div class="pb-2">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="bi bi-award-fill text-pink-600"></i>
            Brands
        </h3>
        <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
            <?php foreach($filterBrands as $brand): ?>
            <label class="flex items-center gap-3 cursor-pointer group hover:bg-pink-50 p-2 rounded-lg transition">
                <input type="checkbox" name="brands[]" value="<?php echo $brand['id']; ?>" 
                       onchange="this.form.submit()"
                       <?php echo in_array($brand['id'], $selected_brands) ? 'checked' : ''; ?>
                       class="w-4 h-4 text-pink-600 border-gray-300 rounded focus:ring-pink-500">
                <span class="text-sm text-gray-700 group-hover:text-pink-600 transition font-medium">
                    <?php echo htmlspecialchars($brand['name']); ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</form>
