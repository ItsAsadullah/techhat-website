<?php
require_once 'core/db.php';
require_once 'core/auth.php';

// Fetch Categories
$stmtCat = $pdo->query("SELECT * FROM categories LIMIT 10");
$categories = $stmtCat->fetchAll();

// Fetch Homepage Settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
$settingsData = $settingsStmt->fetchAll();
$settings = [];
foreach($settingsData as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch Banners for Carousel
$bannersStmt = $pdo->query("SELECT * FROM banner_images WHERE is_active = 1 ORDER BY display_order ASC");
$banners = $bannersStmt->fetchAll();

// Fetch Latest Products - Optimized (Only 12 products for faster load)
$sql = "SELECT p.id, p.title, p.slug,
    (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as thumb,
    (SELECT MIN(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants WHERE product_id = p.id) as min_effective,
    (SELECT MAX(COALESCE(NULLIF(offer_price,0), price)) FROM product_variants WHERE product_id = p.id) as max_effective,
    (SELECT MIN(price) FROM product_variants WHERE product_id = p.id) as min_regular
    FROM products p 
    ORDER BY p.id DESC LIMIT 12";
$stmtProd = $pdo->query($sql);
$products = $stmtProd->fetchAll();

$siteName = $settings['site_name'] ?? 'TechHat';
$seoTitle = $settings['seo_title'] ?? 'TechHat | Modern Ecommerce';
$seoDescription = $settings['seo_description'] ?? 'Shop latest electronics and gadgets';
$seoExtended = $settings['seo_extended_text'] ?? '';
$seoFeatures = isset($settings['seo_features']) ? explode('|', $settings['seo_features']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> | Modern Ecommerce, POS-ready</title>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <link rel="canonical" href="<?php echo BASE_URL; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($seoTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f8f9fa;
        }
        
        /* Modern Clean Cards */
        .clean-card {
            background: white;
            border: 1px solid #e9ecef;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .clean-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        /* Product Card - Optimized */
        .product-card {
            background: white;
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .product-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        /* Accent Color - TechHat Logo Theme */
        .accent-primary {
            background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%);
        }
        
        .accent-secondary {
            background: #0066CC;
        }
        
        .accent-text {
            color: #D4145A;
        }
        
        .accent-text-blue {
            color: #0066CC;
        }
        
        .accent-border {
            border-color: #D4145A;
        }
        
        /* Category Pills */
        .category-pill {
            background: white;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .category-pill:hover {
            background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%);
            color: white;
            border-color: #D4145A;
        }
        
        /* Carousel - No Blur */
        .carousel-container {
            position: relative;
            overflow: hidden;
        }
        
        .carousel-slide {
            display: none;
        }
        
        .carousel-slide.active {
            display: block;
        }
        
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: 1px solid #e9ecef;
            padding: 0.75rem;
            cursor: pointer;
            border-radius: 50%;
            z-index: 10;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .carousel-btn:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .carousel-btn.prev { left: 1rem; }
        .carousel-btn.next { right: 1rem; }
        
        /* Feature Card */
        .feature-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .feature-card:hover {
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body class="min-h-screen">

    <?php include 'includes/header.php'; ?>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        
        <!-- Carousel Banner Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <!-- Carousel -->
            <div class="md:col-span-2 carousel-container clean-card rounded-xl overflow-hidden relative" style="height: 350px;">
                <?php if(!empty($banners)): ?>
                    <?php foreach($banners as $index => $banner): ?>
                    <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>" style="height: 100%;">
                        <div class="relative h-full bg-gradient-to-r from-purple-600 to-indigo-600 flex items-center justify-between p-8">
                            <div class="text-white z-10 max-w-md">
                                <h2 class="text-4xl font-bold mb-3"><?php echo htmlspecialchars($banner['title'] ?? 'Big Sale'); ?></h2>
                                <p class="text-xl mb-6 opacity-90"><?php echo htmlspecialchars($banner['subtitle'] ?? 'Shop Now'); ?></p>
                                <a href="<?php echo htmlspecialchars($banner['link_url'] ?? 'category.php'); ?>" 
                                   class="inline-block bg-white text-purple-600 font-bold px-8 py-3 rounded-full hover:shadow-xl transition-all">
                                    <?php echo htmlspecialchars($banner['button_text'] ?? 'Shop Now'); ?> <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="hidden md:block opacity-20">
                                <i class="bi bi-cart-check-fill" style="font-size: 200px;"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(count($banners) > 1): ?>
                    <button class="carousel-btn prev" onclick="changeSlide(-1)">
                        <i class="bi bi-chevron-left text-2xl"></i>
                    </button>
                    <button class="carousel-btn next" onclick="changeSlide(1)">
                        <i class="bi bi-chevron-right text-2xl"></i>
                    </button>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="h-full bg-gradient-to-r from-purple-600 to-indigo-600 flex items-center justify-center p-8">
                        <div class="text-white text-center">
                            <i class="bi bi-image text-9xl opacity-30 mb-4"></i>
                            <p class="text-xl">No banners available</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Flash Deal Card -->
            <div class="clean-card rounded-xl p-6 accent-primary text-white">
                <i class="bi bi-lightning-charge-fill text-5xl mb-3 block"></i>
                <h3 class="text-xl font-bold mb-2">Flash Deals</h3>
                <p class="mb-4 opacity-90 text-sm">Limited Time Offers</p>
                <div class="flex items-center gap-2 text-2xl font-bold">
                    <span class="bg-white text-purple-600 px-2 py-1 rounded">12</span>
                    <span>:</span>
                    <span class="bg-white text-purple-600 px-2 py-1 rounded">45</span>
                    <span>:</span>
                    <span class="bg-white text-purple-600 px-2 py-1 rounded">30</span>
                </div>
                <a href="category.php" class="mt-4 block text-center bg-white text-purple-600 font-bold py-2 px-4 rounded-lg hover:shadow-lg transition-all">
                    View Deals
                </a>
            </div>
        </div>

        <!-- Category Pills -->
        <div class="flex gap-2 mb-6 overflow-x-auto pb-2" style="scrollbar-width: none;">
            <?php foreach($categories as $cat): ?>
            <a href="category.php?id=<?php echo $cat['id']; ?>" 
               class="category-pill px-5 py-2 rounded-full text-sm font-semibold whitespace-nowrap">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Specially for You Section -->
        <div class="mb-8">
            <div class="text-center mb-6">
                <h2 class="text-3xl font-bold accent-text inline-flex items-center gap-2">
                    <i class="bi bi-bag-heart-fill"></i>
                    Specially for You
                </h2>
                <p class="text-gray-600 mt-2">Handpicked products curated just for you</p>
            </div>
            
            <!-- Product Grid - Optimized -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                <?php foreach($products as $p): ?>
                <?php 
                $minEffective = (float) $p['min_effective'];
                $maxEffective = (float) $p['max_effective'];
                $savePercent = ($p['min_regular'] && $p['min_regular'] > $minEffective) ? round((($p['min_regular'] - $minEffective) / $p['min_regular']) * 100) : 0;
                ?>
                <a href="product.php?slug=<?php echo $p['slug']; ?>" 
                   class="product-card rounded-lg overflow-hidden">
                    <!-- Product Image -->
                    <div class="relative bg-gray-50 p-3 h-40 flex items-center justify-center">
                        <?php if($savePercent > 0): ?>
                        <span class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full z-10">
                            -<?php echo $savePercent; ?>%
                        </span>
                        <?php endif; ?>
                        
                        <?php if($p['thumb']): ?>
                            <img src="<?php echo $p['thumb']; ?>" 
                                 alt="<?php echo htmlspecialchars($p['title']); ?>"
                                 class="max-w-full max-h-full object-contain">
                        <?php else: ?>
                            <i class="bi bi-image text-5xl text-gray-300"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Product Info -->
                    <div class="p-3">
                        <h3 class="font-semibold text-gray-800 text-xs mb-2 h-8 overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                            <?php echo htmlspecialchars($p['title']); ?>
                        </h3>
                        
                        <div class="flex items-center gap-1 mb-2">
                            <span class="text-lg font-bold accent-text">
                                ৳<?php echo number_format($minEffective); ?>
                            </span>
                            <?php if($p['min_regular'] && $p['min_regular'] > $minEffective): ?>
                            <span class="text-xs text-gray-400 line-through">
                                ৳<?php echo number_format($p['min_regular']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <button class="w-full accent-primary text-white py-1.5 px-3 rounded-lg font-semibold text-xs hover:opacity-90 transition-opacity">
                            <i class="bi bi-cart-plus"></i> Add
                        </button>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SEO Content Section -->
        <div class="clean-card rounded-xl p-8 md:p-12 mb-8">
            <div class="text-center mb-8">
                <i class="bi bi-shop text-6xl accent-text mb-4 inline-block"></i>
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">
                    <?php echo htmlspecialchars($seoTitle); ?>
                </h2>
            </div>
            
            <div class="prose prose-lg max-w-none text-gray-700 leading-relaxed">
                <p class="mb-4 text-lg">
                    <?php echo htmlspecialchars($seoDescription); ?>
                </p>
                
                <?php if($seoExtended): ?>
                <p class="mb-6">
                    <?php echo htmlspecialchars($seoExtended); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Features Grid -->
            <?php if(!empty($seoFeatures)): ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8">
                <?php 
                $featureIcons = ['bi-check-circle-fill', 'bi-truck', 'bi-leaf-fill', 'bi-shield-check'];
                foreach($seoFeatures as $index => $feature): 
                    $icon = $featureIcons[$index % count($featureIcons)];
                ?>
                <div class="feature-card rounded-lg p-4 text-center">
                    <i class="bi <?php echo $icon; ?> text-3xl accent-text mb-2 block"></i>
                    <p class="font-semibold text-sm text-gray-700"><?php echo htmlspecialchars($feature); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- CTA Button -->
            <div class="text-center mt-8">
                <a href="category.php" class="inline-flex items-center gap-3 accent-primary text-white font-bold px-10 py-4 rounded-full text-lg hover:opacity-90 transition-opacity">
                    <i class="bi bi-grid-3x3-gap-fill text-2xl"></i>
                    View All Products
                    <i class="bi bi-arrow-right text-2xl"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12 pt-12 pb-6">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <!-- Company Info -->
                <div class="md:col-span-1">
                    <h3 class="text-2xl font-bold accent-text mb-4">
                        <i class="bi bi-shop"></i> <?php echo htmlspecialchars($siteName); ?>™
                    </h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">
                        <?php echo htmlspecialchars($settings['footer_about'] ?? 'Your premier destination for quality electronics and gadgets.'); ?>
                    </p>
                    <div class="flex gap-3">
                        <a href="#" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gradient-to-r hover:from-pink-600 hover:to-rose-600 hover:text-white transition-all">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gradient-to-r hover:from-pink-600 hover:to-rose-600 hover:text-white transition-all">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gradient-to-r hover:from-pink-600 hover:to-rose-600 hover:text-white transition-all">
                            <i class="bi bi-youtube"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="font-bold text-gray-800 mb-4 text-lg flex items-center gap-2">
                        <i class="bi bi-link-45deg accent-text"></i> Quick Links
                    </h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="index.php" class="text-gray-600 hover:text-purple-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Home</a></li>
                        <li><a href="category.php" class="text-gray-600 hover:text-purple-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Shop</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-purple-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Special Offers</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-purple-600 transition-colors flex items-center gap-2"><i class="bi bi-chevron-right"></i> Track Order</a></li>
                    </ul>
                </div>
                
                <!-- Categories -->
                <div>
                    <h4 class="font-bold text-gray-800 mb-4 text-lg flex items-center gap-2">
                        <i class="bi bi-grid-fill accent-text"></i> Categories
                    </h4>
                    <ul class="space-y-2 text-sm">
                        <?php 
                        $footerCats = array_slice($categories, 0, 4);
                        foreach($footerCats as $cat): 
                        ?>
                        <li>
                            <a href="category.php?id=<?php echo $cat['id']; ?>" 
                               class="text-gray-600 hover:text-purple-600 transition-colors flex items-center gap-2">
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
                        <i class="bi bi-headset accent-text"></i> Customer Service
                    </h4>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-start gap-2 text-gray-600">
                            <i class="bi bi-telephone-fill accent-text mt-1"></i>
                            <div>
                                <div class="font-semibold text-gray-800">Phone</div>
                                <?php echo htmlspecialchars($settings['footer_phone'] ?? '09678-300400'); ?>
                            </div>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600">
                            <i class="bi bi-envelope-fill accent-text mt-1"></i>
                            <div>
                                <div class="font-semibold text-gray-800">Email</div>
                                <?php echo htmlspecialchars($settings['footer_email'] ?? 'info@bdshop.com'); ?>
                            </div>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600">
                            <i class="bi bi-clock-fill accent-text mt-1"></i>
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
                            <i class="bi bi-credit-card text-2xl accent-text"></i>
                        </div>
                        <div class="bg-gray-100 px-4 py-2 rounded-lg">
                            <span class="font-bold accent-text">VISA</span>
                        </div>
                        <div class="bg-gray-100 px-4 py-2 rounded-lg">
                            <span class="font-bold accent-text">Mastercard</span>
                        </div>
                        <div class="bg-gray-100 px-4 py-2 rounded-lg">
                            <span class="font-bold accent-text">American Express</span>
                        </div>
                        <div class="bg-gray-100 px-4 py-2 rounded-lg">
                            <span class="font-bold accent-text">bKash</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Copyright -->
            <div class="text-center text-gray-600 text-sm">
                <p>© 2026 <span class="font-bold accent-text"><?php echo htmlspecialchars($siteName); ?></span>. All rights reserved.</p>
                <p class="mt-1">Developed with <i class="bi bi-heart-fill text-red-500"></i> by SmartB</p>
            </div>
        </div>
    </footer>

    <!-- Carousel JavaScript -->
    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const totalSlides = slides.length;
        
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            
            if (index >= totalSlides) currentSlide = 0;
            else if (index < 0) currentSlide = totalSlides - 1;
            else currentSlide = index;
            
            slides[currentSlide].classList.add('active');
        }
        
        function changeSlide(direction) {
            showSlide(currentSlide + direction);
        }
        
        // Auto-advance carousel every 5 seconds
        if (totalSlides > 1) {
            setInterval(() => {
                changeSlide(1);
            }, 5000);
        }
    </script>

</body>
</html>