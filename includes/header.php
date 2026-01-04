<?php
require_once __DIR__ . '/../core/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch Categories for Menu
$stmtCatHeader = $pdo->query("SELECT * FROM categories LIMIT 8");
$categoriesHeader = $stmtCatHeader->fetchAll();
?>
<!-- Modern Header with Glassmorphism -->
<header class="sticky top-0 z-50 backdrop-blur-md bg-white/80 shadow-lg">
    <!-- Top Bar -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-2">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center text-sm">
            <span class="flex items-center gap-2">
                <i class="bi bi-award-fill"></i> Welcome to <?php echo htmlspecialchars($settings['site_name'] ?? 'TechHat'); ?>!
            </span>
            <div class="flex items-center gap-4">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="hover:text-yellow-300 transition flex items-center gap-1">
                        <i class="bi bi-person-circle"></i> My Account
                    </a>
                    <a href="logout.php" class="hover:text-yellow-300 transition flex items-center gap-1">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="hover:text-yellow-300 transition flex items-center gap-1">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                    <a href="register.php" class="hover:text-yellow-300 transition flex items-center gap-1">
                        <i class="bi bi-person-plus-fill"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="flex items-center justify-between gap-4">
            <!-- Logo -->
            <a href="index.php" class="flex items-center gap-2 font-bold text-2xl gradient-text">
                <i class="bi bi-shop text-3xl"></i>
                <span><?php echo htmlspecialchars($settings['site_name'] ?? 'TechHat'); ?></span>
            </a>

            <!-- Search Bar -->
            <div class="flex-1 max-w-2xl">
                <div class="relative">
                    <input type="text" 
                           placeholder="Search products..." 
                           class="w-full px-4 py-2.5 pr-12 rounded-full border-2 border-purple-200 focus:border-purple-600 focus:outline-none transition-all">
                    <button class="absolute right-2 top-1/2 -translate-y-1/2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 py-1.5 rounded-full hover:shadow-lg transition-all">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>

            <!-- Cart Icon -->
            <a href="cart.php" class="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-2.5 rounded-full hover:shadow-xl transition-all font-semibold">
                <i class="bi bi-cart3 text-xl"></i>
                <span class="hidden md:inline">Cart</span>
            </a>
        </div>
    </div>

    <!-- Categories Navigation -->
    <div class="border-t border-purple-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex gap-1 overflow-x-auto py-2 scrollbar-hide">
                <?php foreach($categoriesHeader as $c): ?>
                <a href="category.php?slug=<?php echo $c['slug']; ?>" 
                   class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap hover:bg-purple-600 hover:text-white transition-all duration-300">
                    <?php echo htmlspecialchars($c['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</header>

<style>
    .gradient-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>