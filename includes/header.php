<?php
require_once __DIR__ . '/../core/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch Categories
try {
    $stmtCatHeader = $pdo->query("SELECT * FROM categories LIMIT 10");
    $categoriesHeader = $stmtCatHeader->fetchAll();
} catch (Exception $e) { $categoriesHeader = []; }

// Fetch Settings
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
    $settingsData = $settingsStmt->fetchAll();
    $settings = [];
    foreach($settingsData as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) { $settings = []; }

// Cart & Wishlist
$cartCount = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? array_sum($_SESSION['cart']) : 0;
$wishlistCount = 0;
if(isset($_SESSION['user_id'])) {
    try {
        $wishlistStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $wishlistStmt->execute([$_SESSION['user_id']]);
        $wishlistCount = $wishlistStmt->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .accent-primary { background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }

        /* --- FIXED & ANIMATION STYLES --- */
        
        #mainHeader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 50;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        /* 1. TOP BAR ANIMATION */
        #topBar {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 50px; /* Approximate max height */
            opacity: 1;
            overflow: hidden;
            transform-origin: top;
        }

        /* 2. BOTTOM NAV ANIMATION */
        #categoriesNav {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 80px;
            opacity: 1;
            transform-origin: top;
            overflow: hidden;
            border-bottom: 1px solid #e5e7eb;
        }

        /* 3. HIDDEN STATES (When Scrolled Down) */
        
        /* Hide Top Bar */
        #mainHeader.scrolled-down #topBar {
            max-height: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            opacity: 0 !important;
        }

        /* Hide Bottom Nav */
        #mainHeader.scrolled-down #categoriesNav {
            max-height: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            opacity: 0 !important;
            border-bottom-width: 0 !important;
            pointer-events: none;
        }

        /* Spacer to prevent page jump */
        #headerSpacer {
            display: block;
            width: 100%;
            /* Height set by JS */
        }
    </style>
</head>
<body>

<!-- Spacer to push content down -->
<div id="headerSpacer"></div>

<header id="mainHeader">
    
    <!-- SECTION 1: Top Bar (Added id="topBar" for animation) -->
    <div id="topBar" class="accent-primary text-white py-2.5">
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
                            <i class="bi bi-person-circle"></i> <span class="hidden sm:inline">My Account</span>
                        </a>
                        <span class="hidden sm:inline">|</span>
                        <a href="logout.php" class="hover:opacity-80 transition flex items-center gap-1">
                            <i class="bi bi-box-arrow-right"></i> <span class="hidden sm:inline">Logout</span>
                        </a>
                    <?php else: ?>
                        <button onclick="openAuthModal('loginModal')" class="hover:opacity-80 transition flex items-center gap-1">
                            <i class="bi bi-box-arrow-in-right"></i> <span class="hidden sm:inline">Login</span>
                        </button>
                        <span class="hidden sm:inline">|</span>
                        <button onclick="openAuthModal('registerModal')" class="hover:opacity-80 transition flex items-center gap-1">
                            <i class="bi bi-person-plus-fill"></i> <span class="hidden sm:inline">Register</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: Main Header (Logo/Search) - ALWAYS VISIBLE -->
    <div class="bg-white relative z-20">
        <div class="max-w-7xl mx-auto px-4 py-3 md:py-4">
            <div class="flex items-center justify-between gap-3 md:gap-4">
                <!-- Logo -->
                <a href="index.php" class="flex-shrink-0">
                    <img src="assets/images/techhat.png" 
                         alt="TechHat" 
                         class="h-10 md:h-14 w-auto transition-transform hover:scale-105"
                         onerror="this.src='https://placehold.co/150x50?text=TechHat'">
                </a>

                <!-- Search Bar -->
                <div class="hidden md:block flex-1 max-w-xl lg:max-w-2xl">
                    <form action="search.php" method="GET" class="relative">
                        <input type="text" name="search" placeholder="Search for products..." 
                               class="w-full px-5 py-3 pr-14 rounded-full border-2 border-gray-200 focus:border-pink-500 focus:outline-none transition-all placeholder-gray-400 text-sm">
                        <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 accent-primary text-white w-10 h-10 rounded-full hover:opacity-90 flex items-center justify-center">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>

                <!-- Icons -->
                <div class="flex items-center gap-2 md:gap-4">
                    <button onclick="toggleMobileSearch()" class="md:hidden text-gray-600 hover:text-pink-600 p-2">
                        <i class="bi bi-search text-xl"></i>
                    </button>
                    <a href="dashboard.php#wishlist" class="hidden sm:flex items-center gap-1.5 text-gray-600 hover:text-pink-600 group relative">
                        <i class="bi bi-heart text-2xl group-hover:scale-110 transition-transform"></i>
                        <span class="hidden lg:block text-sm font-medium">Wishlist</span>
                        <?php if($wishlistCount > 0): ?>
                        <span class="absolute -top-2 -right-1 bg-pink-600 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center"><?php echo $wishlistCount > 99 ? '99+' : $wishlistCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="cart.php" class="relative flex items-center gap-2 accent-primary text-white px-4 md:px-6 py-2 md:py-2.5 rounded-full hover:opacity-90 shadow-lg font-semibold">
                        <i class="bi bi-cart3 text-xl md:text-2xl"></i>
                        <span class="hidden md:inline text-sm">Cart</span>
                        <span id="headerCartCount" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-2 -right-2 bg-white text-pink-600 text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center border-2 border-pink-600"><?php echo $cartCount > 99 ? '99+' : $cartCount; ?></span>
                    </a>
                </div>
            </div>
            <!-- Mobile Search -->
            <div id="mobileSearch" class="md:hidden mt-3 hidden">
                <form action="search.php" method="GET" class="relative">
                    <input type="text" name="search" placeholder="Search..." class="w-full px-4 py-2.5 pr-12 rounded-full border-2 border-gray-200 focus:border-pink-500 focus:outline-none text-sm">
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 accent-primary text-white w-9 h-9 rounded-full hover:opacity-90 flex items-center justify-center">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SECTION 3: Categories Nav (Target for Hiding) -->
    <div id="categoriesNav" class="bg-gray-50 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center gap-2 overflow-x-auto py-2.5 scrollbar-hide">
                <a href="categories.php" class="flex-shrink-0 flex items-center gap-2 bg-pink-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-pink-700 transition-all">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    <span class="hidden sm:inline">All Categories</span>
                </a>
                <?php foreach($categoriesHeader as $c): ?>
                <a href="category.php?id=<?php echo $c['id']; ?>" class="flex-shrink-0 px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap text-gray-700 hover:bg-pink-600 hover:text-white transition-all">
                    <?php echo htmlspecialchars($c['name']); ?>
                </a>
                <?php endforeach; ?>
                <a href="category.php?offers=1" class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap bg-gradient-to-r from-orange-500 to-red-500 text-white hover:shadow-lg transition-all">
                    <i class="bi bi-lightning-charge-fill"></i> Special Offers
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Dropdown Menu -->
<div id="categoriesDropdown" class="hidden fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-[100]" onclick="toggleCategoriesMenu()">
    <div class="bg-white max-w-md w-full shadow-2xl rounded-r-2xl p-6 h-full overflow-y-auto" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-gray-800 flex items-center gap-2"><i class="bi bi-grid-3x3-gap-fill text-pink-600"></i> All Categories</h3>
            <button onclick="toggleCategoriesMenu()" class="text-gray-400 hover:text-gray-600 text-2xl"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="space-y-2">
            <?php foreach($categoriesHeader as $c): ?>
            <a href="category.php?id=<?php echo $c['id']; ?>" class="flex items-center justify-between p-3 rounded-lg hover:bg-pink-50 transition group">
                <span class="font-semibold text-gray-700 group-hover:text-pink-600"><?php echo htmlspecialchars($c['name']); ?></span>
                <i class="bi bi-chevron-right text-gray-400 group-hover:text-pink-600"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // --- UI Logic ---
    function toggleMobileSearch() {
        document.getElementById('mobileSearch').classList.toggle('hidden');
        setTimeout(updateSpacerHeight, 300);
    }
    function toggleCategoriesMenu() {
        const d = document.getElementById('categoriesDropdown');
        d.classList.toggle('hidden');
        document.body.style.overflow = d.classList.contains('hidden') ? '' : 'hidden';
    }
    document.addEventListener('keydown', e => { if(e.key === 'Escape') { const d = document.getElementById('categoriesDropdown'); if(!d.classList.contains('hidden')) toggleCategoriesMenu(); }});

    // --- SCROLL ANIMATION LOGIC ---
    const header = document.getElementById('mainHeader');
    const spacer = document.getElementById('headerSpacer');
    let lastScrollY = window.scrollY;
    let isHeaderHidden = false;

    // Smart Height Calculation
    function updateSpacerHeight() {
        if(header && spacer) {
            // Temporarily remove hidden class to get TRUE full height
            const wasHidden = header.classList.contains('scrolled-down');
            header.classList.remove('scrolled-down');
            
            // Set spacer to full expanded height
            spacer.style.height = header.offsetHeight + 'px';
            
            // Restore hidden state if it was hidden
            if(wasHidden) header.classList.add('scrolled-down');
        }
    }

    function handleScroll() {
        const currentScrollY = window.scrollY;
        if (Math.abs(currentScrollY - lastScrollY) < 10) return; // Jitter buffer

        // Hide when scrolling DOWN past 100px
        if (currentScrollY > lastScrollY && currentScrollY > 100) {
            if (!isHeaderHidden) {
                header.classList.add('scrolled-down');
                isHeaderHidden = true;
            }
        } else {
            // Show when scrolling UP
            if (isHeaderHidden) {
                header.classList.remove('scrolled-down');
                isHeaderHidden = false;
            }
        }
        lastScrollY = currentScrollY;
    }

    window.addEventListener('load', updateSpacerHeight);
    window.addEventListener('resize', updateSpacerHeight);
    
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                handleScroll();
                ticking = false;
            });
            ticking = true;
        }
    });

    // Note: updateCartCount, updateCartTotal, showCartNotification functions 
    // are defined in cart-widget.php to avoid conflicts
</script>

<style>
    @keyframes slide-in {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .animate-slide-in {
        animation: slide-in 0.3s ease-out;
        transition: all 0.3s ease;
    }
</style>

<?php 
if (!isset($_SESSION['user_id']) && file_exists(__DIR__ . '/auth-modal.php')) include __DIR__ . '/auth-modal.php';
if (file_exists(__DIR__ . '/cart-widget.php')) include __DIR__ . '/cart-widget.php';
?>
</body>
</html>