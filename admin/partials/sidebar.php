<?php
$active = basename($_SERVER['PHP_SELF']);
$menu = [
    ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    ['href' => 'products.php', 'label' => 'Products', 'icon' => 'bi-box-seam'],
    ['href' => 'categories.php', 'label' => 'Categories', 'icon' => 'bi-grid-fill'],
    ['href' => 'orders.php', 'label' => 'Orders', 'icon' => 'bi-receipt'],
    ['href' => 'pos.php', 'label' => 'POS System', 'icon' => 'bi-calculator'],
    ['href' => 'purchases.php', 'label' => 'Purchases', 'icon' => 'bi-cart-plus'],
    ['href' => 'accounts.php', 'label' => 'Accounts', 'icon' => 'bi-currency-dollar'],
    ['href' => 'banners.php', 'label' => 'Banners', 'icon' => 'bi-images'],
    ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'bi-gear-fill'],
];
?>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Modern Sidebar -->
<aside id="adminSidebar" class="admin-sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="bi bi-shop-window"></i>
            <span class="logo-text">TechHat</span>
        </div>
        <button id="sidebarToggle" class="sidebar-toggle">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- Sidebar Menu -->
    <nav class="sidebar-menu">
        <?php foreach ($menu as $item): ?>
            <?php 
            $isActive = $active === basename($item['href']);
            $activeClass = $isActive ? 'active' : '';
            ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="menu-item <?php echo $activeClass; ?>">
                <i class="bi <?php echo $item['icon']; ?>"></i>
                <span class="menu-text"><?php echo htmlspecialchars($item['label']); ?></span>
                <?php if ($isActive): ?>
                <span class="active-indicator"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="../index.php" target="_blank" class="menu-item">
            <i class="bi bi-globe"></i>
            <span class="menu-text">Visit Site</span>
        </a>
        <a href="../logout.php" class="menu-item logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            <span class="menu-text">Logout</span>
        </a>
    </div>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<style>
/* Sidebar Styles */
.admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}

.admin-sidebar.collapsed {
    transform: translateX(-280px);
}

/* Sidebar Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.sidebar-logo i {
    font-size: 2rem;
    color: #ffd700;
}

.sidebar-toggle {
    display: none;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    padding: 0.5rem;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.3s;
}

.sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.2);
}

.sidebar-toggle i {
    font-size: 1.5rem;
}

/* Sidebar Menu */
.sidebar-menu {
    flex: 1;
    padding: 1rem;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

.sidebar-menu::-webkit-scrollbar {
    width: 6px;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem 1rem;
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    border-radius: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.menu-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.1) 0%, transparent 100%);
    transform: translateX(-100%);
    transition: transform 0.3s;
}

.menu-item:hover::before {
    transform: translateX(0);
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transform: translateX(5px);
}

.menu-item i {
    font-size: 1.25rem;
    min-width: 1.5rem;
}

.menu-item.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.active-indicator {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 70%;
    background: #ffd700;
    border-radius: 2px 0 0 2px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-50%) translateX(10px);
    }
    to {
        opacity: 1;
        transform: translateY(-50%) translateX(0);
    }
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.logout-btn {
    background: rgba(231, 76, 60, 0.2);
    margin-bottom: 0;
}

.logout-btn:hover {
    background: rgba(231, 76, 60, 0.4);
}

/* Sidebar Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-sidebar {
        transform: translateX(-280px);
    }
    
    .admin-sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-toggle {
        display: block;
    }
}

/* Content Area Adjustment */
.admin-content {
    margin-left: 280px;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@media (max-width: 768px) {
    .admin-content {
        margin-left: 0;
    }
}
</style>

<script>
// Sidebar Toggle Functionality
(function() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
    
    // Close sidebar on menu click (mobile)
    if (window.innerWidth <= 768) {
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        });
    }
})();
</script>
