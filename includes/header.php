<?php
require_once __DIR__ . '/../core/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch Categories for Menu
$stmtCatHeader = $pdo->query("SELECT * FROM categories LIMIT 10");
$categoriesHeader = $stmtCatHeader->fetchAll();

// Fetch Homepage Settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
$settingsData = $settingsStmt->fetchAll();
$settings = [];
foreach ($settingsData as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Cart count
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
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

.accent-primary {
    background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%);
}

/* ===== FIXED, JITTER-FREE CATEGORY BAR ===== */
#categoriesNav {
    transition: transform 0.25s ease, opacity 0.2s ease;
    will-change: transform;
}
#categoriesNav.hidden-nav {
    transform: translateY(-100%);
    opacity: 0;
}

/* Hide scrollbar */
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
</head>

<body>

<header id="mainHeader" class="sticky top-0 z-50 bg-white shadow-md">

    <!-- Top Bar -->
    <div class="accent-primary text-white py-2.5">
        <div class="max-w-7xl mx-auto px-4 flex justify-between text-sm">
            <div class="flex items-center gap-2">
                <i class="bi bi-award-fill"></i>
                <span>Welcome to <?= htmlspecialchars($settings['site_name'] ?? 'TechHat') ?></span>
            </div>
            <div class="flex items-center gap-3">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php">My Account</a> |
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <button onclick="openAuthModal('loginModal')">Login</button> |
                    <button onclick="openAuthModal('registerModal')">Register</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <div class="border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
            <a href="index.php">
                <img src="assets/images/techhat.png" class="h-12">
            </a>

            <div class="hidden md:block flex-1 max-w-xl">
                <form action="category.php" method="GET" class="relative">
                    <input name="search" class="w-full px-5 py-3 pr-14 rounded-full border">
                    <button class="absolute right-2 top-1/2 -translate-y-1/2 accent-primary text-white w-10 h-10 rounded-full">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>

            <a href="cart.php" class="relative accent-primary text-white px-5 py-2 rounded-full">
                <i class="bi bi-cart3"></i>
                <?php if ($cartCount > 0): ?>
                <span class="absolute -top-2 -right-2 bg-white text-pink-600 text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center">
                    <?= $cartCount ?>
                </span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Categories Bar -->
    <div id="categoriesNav" class="bg-gray-50 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex gap-2 py-2 overflow-x-auto scrollbar-hide">
                <?php foreach ($categoriesHeader as $c): ?>
                    <a href="category.php?id=<?= $c['id'] ?>" class="px-4 py-2 rounded-lg text-sm font-semibold hover:bg-pink-600 hover:text-white">
                        <?= htmlspecialchars($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</header>

<script>
/* ===== SMOOTH, PRODUCTION-GRADE SCROLL LOGIC ===== */
(function () {
    let lastScroll = window.pageYOffset || 0;
    const nav = document.getElementById('categoriesNav');
    let hidden = false;

    window.addEventListener('scroll', () => {
        const current = window.pageYOffset || document.documentElement.scrollTop;

        if (current > lastScroll + 10 && current > 120) {
            if (!hidden) {
                nav.classList.add('hidden-nav');
                hidden = true;
            }
        } else if (current < lastScroll - 10) {
            if (hidden) {
                nav.classList.remove('hidden-nav');
                hidden = false;
            }
        }

        if (current < 80 && hidden) {
            nav.classList.remove('hidden-nav');
            hidden = false;
        }

        lastScroll = current;
    }, { passive: true });
})();
</script>

<?php
if (!isset($_SESSION['user_id'])) {
    include __DIR__ . '/auth-modal.php';
}
?>

</body>
</html>