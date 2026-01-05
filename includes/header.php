<?php
require_once __DIR__ . '/../core/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch Categories for Menu
$stmtCatHeader = $pdo->query("SELECT * FROM categories LIMIT 10");
$categoriesHeader = $stmtCatHeader->fetchAll();

// Fetch Homepage Settings for site name
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
$settingsData = $settingsStmt->fetchAll();
$settings = [];
foreach($settingsData as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Count cart items
$cartCount = 0;
if(isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- Modern Professional Header -->
<header id="mainHeader" class="sticky top-0 z-50 bg-white shadow-md transition-transform duration-300">
    <!-- Top Bar -->
    <div class="accent-primary text-white py-2.5">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap items-center justify-between text-xs md:text-sm gap-2">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <i class="bi bi-award-fill"></i> 
                        <span class="hidden sm:inline">Welcome to</span> <?php echo htmlspecialchars($settings['site_name'] ?? 'TechHat'); ?>!
                    </span>
                    <span class="hidden md:flex items-center gap-1.5">
                        <i class="bi bi-telephone-fill"></i>
                        <?php echo htmlspecialchars($settings['footer_phone'] ?? '09678-300400'); ?>
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="hover:opacity-80 transition flex items-center gap-1">
                            <i class="bi bi-person-circle"></i> 
                            <span class="hidden sm:inline">My Account</span>
                        </a>
                        <span class="hidden sm:inline">|</span>
                        <a href="logout.php" class="hover:opacity-80 transition flex items-center gap-1">
                            <i class="bi bi-box-arrow-right"></i> 
                            <span class="hidden sm:inline">Logout</span>
                        </a>
                    <?php else: ?>
                        <button onclick="openAuthModal('loginModal')" class="hover:opacity-80 transition flex items-center gap-1">
                            <i class="bi bi-box-arrow-in-right"></i> 
                            <span class="hidden sm:inline">Login</span>
                        </button>
                        <span class="hidden sm:inline">|</span>
                        <button onclick="openAuthModal('registerModal')" class="hover:opacity-80 transition flex items-center gap-1">
                            <i class="bi bi-person-plus-fill"></i> 
                            <span class="hidden sm:inline">Register</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-3 md:py-4">
            <div class="flex items-center justify-between gap-3 md:gap-4">
                <!-- Logo -->
                <a href="index.php" class="flex-shrink-0">
                    <img src="assets/images/techhat.png" 
                         alt="<?php echo htmlspecialchars($settings['site_name'] ?? 'TechHat'); ?>" 
                         class="h-10 md:h-14 w-auto transition-transform hover:scale-105">
                </a>

                <!-- Search Bar (Desktop) -->
                <div class="hidden md:block flex-1 max-w-xl lg:max-w-2xl">
                    <form action="category.php" method="GET" class="relative">
                        <input type="text" 
                               name="search"
                               placeholder="Search for products, brands and more..." 
                               class="w-full px-5 py-3 pr-14 rounded-full border-2 border-gray-200 focus:border-pink-500 focus:outline-none transition-all placeholder-gray-400 text-sm">
                        <button type="submit" 
                                class="absolute right-1.5 top-1/2 -translate-y-1/2 accent-primary text-white w-10 h-10 rounded-full hover:opacity-90 transition-opacity flex items-center justify-center">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>

                <!-- Right Side Icons -->
                <div class="flex items-center gap-2 md:gap-4">
                    <!-- Mobile Search Toggle -->
                    <button onclick="toggleMobileSearch()" 
                            class="md:hidden text-gray-600 hover:text-pink-600 transition p-2">
                        <i class="bi bi-search text-xl"></i>
                    </button>

                    <!-- Wishlist (Optional) -->
                    <a href="#" class="hidden sm:flex items-center gap-1.5 text-gray-600 hover:text-pink-600 transition group">
                        <i class="bi bi-heart text-2xl group-hover:scale-110 transition-transform"></i>
                        <span class="hidden lg:block text-sm font-medium">Wishlist</span>
                    </a>

                    <!-- Cart -->
                    <a href="cart.php" class="relative flex items-center gap-2 accent-primary text-white px-4 md:px-6 py-2 md:py-2.5 rounded-full hover:opacity-90 transition-all font-semibold shadow-lg hover:shadow-xl">
                        <i class="bi bi-cart3 text-xl md:text-2xl"></i>
                        <span class="hidden md:inline text-sm">Cart</span>
                        <?php if($cartCount > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-white text-pink-600 text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center border-2 border-pink-600 animate-pulse">
                            <?php echo $cartCount > 99 ? '99+' : $cartCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Mobile Search Bar -->
            <div id="mobileSearch" class="md:hidden mt-3 hidden">
                <form action="category.php" method="GET" class="relative">
                    <input type="text" 
                           name="search"
                           placeholder="Search products..." 
                           class="w-full px-4 py-2.5 pr-12 rounded-full border-2 border-gray-200 focus:border-pink-500 focus:outline-none transition-all text-sm">
                    <button type="submit" 
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 accent-primary text-white w-9 h-9 rounded-full hover:opacity-90 transition-opacity flex items-center justify-center">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Categories Navigation -->
    <div id="categoriesNav" class="bg-gray-50 border-b border-gray-200 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center gap-2 overflow-x-auto py-2.5 scrollbar-hide">
                <!-- All Categories Button -->
                <button onclick="toggleCategoriesMenu()" 
                        class="flex-shrink-0 flex items-center gap-2 bg-pink-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-pink-700 transition-all">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    <span class="hidden sm:inline">All Categories</span>
                    <i class="bi bi-chevron-down text-sm"></i>
                </button>

                <!-- Category Links -->
                <?php foreach($categoriesHeader as $c): ?>
                <a href="category.php?id=<?php echo $c['id']; ?>" 
                   class="flex-shrink-0 px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap text-gray-700 hover:bg-pink-600 hover:text-white transition-all">
                    <?php echo htmlspecialchars($c['name']); ?>
                </a>
                <?php endforeach; ?>

                <!-- Special Offers -->
                <a href="category.php?offers=1" 
                   class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap bg-gradient-to-r from-orange-500 to-red-500 text-white hover:shadow-lg transition-all">
                    <i class="bi bi-lightning-charge-fill"></i>
                    Special Offers
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Categories Dropdown Menu (Hidden by default) -->
<div id="categoriesDropdown" class="hidden fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-40" onclick="toggleCategoriesMenu()">
    <div class="bg-white max-w-md w-full shadow-2xl rounded-r-2xl p-6 h-full overflow-y-auto" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="bi bi-grid-3x3-gap-fill text-pink-600"></i>
                All Categories
            </h3>
            <button onclick="toggleCategoriesMenu()" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="space-y-2">
            <?php foreach($categoriesHeader as $c): ?>
            <a href="category.php?id=<?php echo $c['id']; ?>" 
               class="flex items-center justify-between p-3 rounded-lg hover:bg-pink-50 transition group">
                <span class="font-semibold text-gray-700 group-hover:text-pink-600">
                    <?php echo htmlspecialchars($c['name']); ?>
                </span>
                <i class="bi bi-chevron-right text-gray-400 group-hover:text-pink-600"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
    body {
        font-family: 'Inter', sans-serif;
    }

    .accent-primary {
        background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%);
    }
    
    .accent-text {
        color: #D4145A;
    }
    
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }

    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<script>
    function toggleMobileSearch() {
        const mobileSearch = document.getElementById('mobileSearch');
        mobileSearch.classList.toggle('hidden');
    }

    function toggleCategoriesMenu() {
        const dropdown = document.getElementById('categoriesDropdown');
        dropdown.classList.toggle('hidden');
    }

    // Close dropdown when clicking escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('categoriesDropdown').classList.add('hidden');
        }
    });

    // Smart Header Scroll Hide/Show
    let lastScrollTop = 0;
    const header = document.getElementById('mainHeader');
    const categoriesNav = document.getElementById('categoriesNav');
    let isHidden = false;
    let ticking = false;

    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                // Calculate scroll difference
                let scrollDiff = scrollTop - lastScrollTop;
                
                // Scrolling down significantly
                if (scrollDiff > 20 && scrollTop > 150 && !isHidden) {
                    isHidden = true;
                    categoriesNav.style.maxHeight = '0';
                    categoriesNav.style.opacity = '0';
                    categoriesNav.style.overflow = 'hidden';
                }
                // Scrolling up significantly
                else if (scrollDiff < -20 && isHidden) {
                    isHidden = false;
                    categoriesNav.style.maxHeight = '100px';
                    categoriesNav.style.opacity = '1';
                    categoriesNav.style.overflow = 'visible';
                }
                // At top of page, always show
                else if (scrollTop < 100 && isHidden) {
                    isHidden = false;
                    categoriesNav.style.maxHeight = '100px';
                    categoriesNav.style.opacity = '1';
                    categoriesNav.style.overflow = 'visible';
                }
                
                lastScrollTop = scrollTop;
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
</script>

<?php 
// Include Auth Modal
if (!isset($_SESSION['user_id'])) {
    include __DIR__ . '/auth-modal.php';
}
?>