<?php
// Ensure we have settings and categories if not already set
if (!isset($settings)) {
    // Check if $pdo is available, if not try to require it (though usually it should be there)
    if (!isset($pdo)) {
        require_once __DIR__ . '/../core/db.php';
    }
    
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
    $settingsData = $settingsStmt->fetchAll();
    $settings = [];
    foreach($settingsData as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

if (!isset($categories)) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../core/db.php';
    }
    $stmtCatFooter = $pdo->query("SELECT * FROM categories LIMIT 5");
    $categories = $stmtCatFooter->fetchAll();
}

$siteName = $settings['site_name'] ?? 'TechHat';
?>
<!-- Footer -->
<footer class="bg-white border-t mt-12 pt-12 pb-6">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <!-- Company Info -->
            <div class="md:col-span-1">
                <h3 class="text-2xl font-bold text-blue-600 mb-4">
                    <i class="bi bi-shop"></i> <?php echo htmlspecialchars($siteName); ?>™
                </h3>
                <p class="text-gray-600 mb-4 text-sm leading-relaxed">
                    <?php echo htmlspecialchars($settings['footer_about'] ?? 'Your premier destination for quality electronics and gadgets.'); ?>
                </p>
                <div class="flex gap-3">
                    <a href="#" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-pink-600 hover:text-white transition-all">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-red-600 hover:text-white transition-all">
                        <i class="bi bi-youtube"></i>
                    </a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 text-lg flex items-center gap-2">
                    <i class="bi bi-link-45deg text-blue-600"></i> Quick Links
                </h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="index.php" class="text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Home</a></li>
                    <li><a href="category.php" class="text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Shop</a></li>
                    <li><a href="#" class="text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Special Offers</a></li>
                    <li><a href="#" class="text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Track Order</a></li>
                </ul>
            </div>
            
            <!-- Categories -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 text-lg flex items-center gap-2">
                    <i class="bi bi-grid-fill text-blue-600"></i> Categories
                </h4>
                <ul class="space-y-2 text-sm">
                    <?php 
                    $footerCats = array_slice($categories, 0, 4);
                    foreach($footerCats as $cat): 
                    ?>
                    <li>
                        <a href="category.php?slug=<?php echo $cat['slug']; ?>" 
                           class="text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-2">
                            <i class="bi bi-chevron-right"></i> 
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Customer Service -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 text-lg flex items-center gap-2">
                    <i class="bi bi-headset text-blue-600"></i> Customer Service
                </h4>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-start gap-2 text-gray-600">
                        <i class="bi bi-telephone-fill text-blue-600 mt-1"></i>
                        <div>
                            <div class="font-semibold text-gray-800">Phone</div>
                            <?php echo htmlspecialchars($settings['footer_phone'] ?? '09678-300400'); ?>
                        </div>
                    </li>
                    <li class="flex items-start gap-2 text-gray-600">
                        <i class="bi bi-envelope-fill text-blue-600 mt-1"></i>
                        <div>
                            <div class="font-semibold text-gray-800">Email</div>
                            <?php echo htmlspecialchars($settings['footer_email'] ?? 'info@techhat.com'); ?>
                        </div>
                    </li>
                    <li class="flex items-start gap-2 text-gray-600">
                        <i class="bi bi-clock-fill text-blue-600 mt-1"></i>
                        <div>
                            <div class="font-semibold text-gray-800">Business Hours</div>
                            <?php echo htmlspecialchars($settings['footer_hours'] ?? '10:00 AM - 11:00 PM'); ?>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Payment Methods -->
        <div class="border-t border-gray-200 pt-6 mb-6">
            <div class="flex flex-wrap items-center justify-center gap-4">
                <span class="text-sm font-semibold text-gray-600">Secure Payment:</span>
                <div class="flex gap-3">
                    <div class="bg-gray-100 px-4 py-2 rounded-lg">
                        <i class="bi bi-credit-card text-2xl text-blue-600"></i>
                    </div>
                    <div class="bg-gray-100 px-4 py-2 rounded-lg">
                        <span class="font-bold text-blue-800">VISA</span>
                    </div>
                    <div class="bg-gray-100 px-4 py-2 rounded-lg">
                        <span class="font-bold text-red-600">Mastercard</span>
                    </div>
                    <div class="bg-gray-100 px-4 py-2 rounded-lg">
                        <span class="font-bold text-blue-500">Amex</span>
                    </div>
                    <div class="bg-gray-100 px-4 py-2 rounded-lg">
                        <span class="font-bold text-pink-600">bKash</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="text-center text-gray-600 text-sm">
            <p>© <?php echo date('Y'); ?> <span class="font-bold text-blue-600"><?php echo htmlspecialchars($siteName); ?></span>. All rights reserved.</p>
        </div>
    </div>
</footer>
