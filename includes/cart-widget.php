<?php
// Calculate Cart Total and Count
$cartCount = 0;
$cartTotal = 0;

if (!empty($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
    
    $variantIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
    $stmtCart = $pdo->prepare("
        SELECT id, price, offer_price FROM product_variations WHERE id IN ($placeholders)
        UNION ALL
        SELECT id, price, offer_price FROM product_variants_legacy WHERE id IN ($placeholders)
    ");
    $stmtCart->execute(array_merge($variantIds, $variantIds));
    $cartVariants = $stmtCart->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cartVariants as $cv) {
        $price = $cv['offer_price'] > 0 ? $cv['offer_price'] : $cv['price'];
        $qty = $_SESSION['cart'][$cv['id']];
        $cartTotal += $price * $qty;
    }
}
?>

<script>
    function updateCartCount(count) {
        // Update new header cart count (ID: headerCartCount)
        const headerCartCount = document.getElementById('headerCartCount');
        if (headerCartCount) {
            headerCartCount.textContent = count > 99 ? '99+' : count;
            if (count > 0) {
                headerCartCount.classList.remove('hidden');
            } else {
                headerCartCount.classList.add('hidden');
            }
        }
        
        // Legacy: Header cart badge (in menu) - using ID selector
        const headerCartBadge = document.getElementById('headerCartBadge');
        if (headerCartBadge) {
            headerCartBadge.textContent = count > 99 ? '99+' : count;
            if (count === 0) {
                headerCartBadge.classList.add('hidden');
                headerCartBadge.classList.remove('flex');
            } else {
                headerCartBadge.classList.remove('hidden');
                headerCartBadge.classList.add('flex');
            }
        }
        
        // Floating cart badge
        const floatingBadge = document.querySelector('.floating-cart-badge');
        const floatingBtn = document.getElementById('floatingCartBtn');
        
        // Update floating badge
        if (floatingBadge) {
            floatingBadge.textContent = count;
            floatingBadge.classList.toggle('hidden', count === 0);
        }
        
        // Update item text (floating button always visible)
        if (floatingBtn) {
            const itemText = floatingBtn.querySelector('.item-count-text');
            if (itemText) {
                itemText.textContent = `${count} item${count > 1 ? 's' : ''}`;
            }
        }
    }
    
    function updateCartTotal(total) {
        const floatingBtn = document.getElementById('floatingCartBtn');
        if (floatingBtn) {
            const totalText = floatingBtn.querySelector('.cart-total-text');
            if (totalText) {
                totalText.textContent = '৳' + total.toLocaleString('en-BD');
            }
        }
    }
    
    function showCartNotification(message) {
        // Simple notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[9999] transition-all';
        notification.style.transform = 'translateX(400px)';
        notification.style.opacity = '0';
        notification.innerHTML = `<i class="bi bi-check-circle-fill mr-2"></i>${message}`;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
        }, 10);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Open/Close Cart Sidebar
    function toggleCartSidebar() {
        const sidebar = document.getElementById('cartSidebar');
        const overlay = document.getElementById('cartOverlay');
        
        if (sidebar.classList.contains('translate-x-full')) {
            sidebar.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            loadCartSidebar();
        } else {
            sidebar.classList.add('translate-x-full');
            overlay.classList.add('hidden');
        }
    }

    function loadCartSidebar() {
        const cartContent = document.getElementById('cartSidebarContent');
        cartContent.innerHTML = '<div class="text-center py-8"><i class="bi bi-hourglass-split text-2xl text-gray-400 animate-spin"></i></div>';
        
        fetch('api/cart_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_cart'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartCount(data.count);
                updateCartTotal(data.total || 0);
                renderCartSidebar(data);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function updateCartQuantity(variantId, newQty, event) {
        if (newQty < 1) {
            removeFromCart(variantId);
            return;
        }
        
        // Debounced update - prevent rapid clicks
        clearTimeout(window.qtyUpdateTimer);
        
        // Update UI immediately for better UX
        const qtySpan = event.target.closest('.flex').querySelector('span');
        if (qtySpan) {
            qtySpan.textContent = newQty;
        }
        
        window.qtyUpdateTimer = setTimeout(() => {
            fetch('api/cart_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update_qty&variant_id=${variantId}&qty=${newQty}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartCount(data.cartCount);
                    updateCartTotal(data.total || 0);
                    loadCartSidebar();
                } else if (data.message) {
                    // Show stock limit message
                    showCartNotification(data.message, 'warning');
                    // Reset to max stock
                    if (data.maxStock && qtySpan) {
                        qtySpan.textContent = data.maxStock;
                    }
                    loadCartSidebar();
                }
            })
            .catch(error => console.error('Error:', error));
        }, 300); // 300ms debounce
    }

    function removeFromCart(variantId) {
        showConfirmModal('Are you sure you want to remove this item?', () => {
            fetch('api/cart_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=remove&variant_id=${variantId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartCount(data.cartCount);
                    updateCartTotal(data.total || 0);
                    loadCartSidebar();
                    showCartNotification('Item removed from cart');
                    
                    // Dispatch custom event for product page to listen
                    window.dispatchEvent(new CustomEvent('cartUpdated', {
                        detail: {
                            variantId: variantId,
                            action: 'remove',
                            inCart: false
                        }
                    }));
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    function renderCartSidebar(data) {
        const content = document.getElementById('cartSidebarContent');
        
        if (data.items.length === 0) {
            content.innerHTML = `
                <div class="text-center py-12">
                    <i class="bi bi-cart-x text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Your cart is empty</p>
                    <a href="index.php" class="inline-block mt-4 text-blue-600 hover:text-blue-700 font-medium">
                        <i class="bi bi-arrow-left mr-1"></i>Continue Shopping
                    </a>
                </div>
            `;
            return;
        }
        
        let html = '<div class="space-y-4">';
        data.items.forEach(item => {
            const variantInfo = [item.color, item.storage, item.size].filter(Boolean).join(', ');
            const subtotal = (item.price || 0) * (item.quantity || 0);
            const itemTitle = item.title || 'Product';
            const itemImage = item.image || 'assets/images/placeholder.png';
            const itemPrice = item.price || 0;
            const itemQty = item.quantity || 0;
            const itemStock = item.stock || 999;
            
            html += `
                <div class="border-b border-gray-200 pb-4">
                    <div class="flex gap-3">
                        <img src="${itemImage}" alt="${itemTitle}" class="w-20 h-20 object-contain rounded border">
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start gap-2 mb-1">
                                <h4 class="text-sm font-semibold text-gray-900 line-clamp-2">${itemTitle}</h4>
                                <button onclick="removeFromCart(${item.id})" class="text-red-500 hover:text-red-700 flex-shrink-0" title="Remove">
                                    <i class="bi bi-x-circle text-xl"></i>
                                </button>
                            </div>
                            ${variantInfo ? `<p class="text-xs text-gray-500 mb-2">${variantInfo}</p>` : ''}
                            ${itemStock > 0 ? `<p class="text-xs text-green-600 mb-2"><i class="bi bi-check-circle"></i> ${itemStock} in stock</p>` : ''}
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 border border-gray-300 rounded">
                                    <button onclick="updateCartQuantity(${item.id}, ${itemQty - 1}, event)" class="px-2 py-1 hover:bg-gray-100 text-gray-600 btn-smooth" type="button">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <span class="px-3 py-1 min-w-[2rem] text-center font-medium qty-display-${item.id}">${itemQty}</span>
                                    <button onclick="updateCartQuantity(${item.id}, ${itemQty + 1}, event)" class="px-2 py-1 hover:bg-gray-100 text-gray-600 btn-smooth" type="button" ${itemQty >= itemStock ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''}>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">৳${itemPrice.toLocaleString()} each</div>
                                    <div class="text-sm font-bold text-blue-600">৳${subtotal.toLocaleString()}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        html += `
            <div class="mt-6 pt-4 border-t-2 border-gray-300 bg-white">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-bold text-gray-900">Total:</span>
                    <span class="text-2xl font-bold text-blue-600">৳${data.total.toLocaleString()}</span>
                </div>
                <a href="checkout.php" class="block w-full bg-blue-600 text-white text-center py-3 rounded-lg font-semibold hover:bg-blue-700 transition-all hover:shadow-2xl hover:scale-105 animate-pulse-slow">
                    <i class="bi bi-bag-check mr-2 animate-bounce-slow"></i>Proceed to Checkout
                </a>
                <a href="cart.php" class="block w-full mt-2 bg-gray-200 text-gray-700 text-center py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors text-sm">
                    View Full Cart
                </a>
            </div>
            
            <!-- Wishlist Section -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <button onclick="toggleWishlistSection()" class="w-full flex items-center justify-between text-left py-2">
                    <span class="font-semibold text-gray-900"><i class="bi bi-heart text-pink-500 mr-2"></i>My Wishlist</span>
                    <i class="bi bi-chevron-up text-gray-400" id="wishlistToggleIcon"></i>
                </button>
                <div id="wishlistContent" class="mt-3 space-y-3 max-h-64 overflow-y-auto">
                    <!-- Wishlist items will be loaded here -->
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        
        // Load wishlist
        loadWishlistSection();
    }

    function toggleWishlistSection() {
        const content = document.getElementById('wishlistContent');
        const icon = document.getElementById('wishlistToggleIcon');
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.remove('bi-chevron-down');
            icon.classList.add('bi-chevron-up');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('bi-chevron-up');
            icon.classList.add('bi-chevron-down');
        }
    }

    function loadWishlistSection() {
        <?php if (!is_logged_in()): ?>
        document.getElementById('wishlistContent').innerHTML = '<p class="text-center text-gray-500 py-4 text-sm">Please login to view wishlist</p>';
        return;
        <?php endif; ?>

        const wishlistContent = document.getElementById('wishlistContent');
        if (!wishlistContent) return;
        
        wishlistContent.innerHTML = '<div class="text-center py-4"><i class="bi bi-hourglass-split text-gray-400 animate-spin"></i></div>';

        fetch('api/wishlist_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_wishlist'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderWishlistItems(data.items);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function renderWishlistItems(items) {
        const content = document.getElementById('wishlistContent');
        if (!content) return;
        
        if (items.length === 0) {
            content.innerHTML = '<p class="text-center text-gray-500 py-4 text-sm">No items in wishlist</p>';
            return;
        }
        
        let html = '';
        items.forEach(item => {
            html += `
                <div class="flex gap-2 items-center bg-gray-50 p-2 rounded border border-gray-200">
                    <img src="${item.image}" alt="${item.title}" class="w-16 h-16 object-contain rounded">
                    <div class="flex-1 min-w-0">
                        <a href="product.php?slug=${item.slug}" class="text-sm font-medium text-gray-900 hover:text-blue-600 line-clamp-1">${item.title}</a>
                        <div class="text-sm font-bold text-blue-600">৳${item.price.toLocaleString()}</div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <button onclick="addWishlistToCart(${item.variant_id}, ${item.product_id})" class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700" title="Add to Cart">
                            <i class="bi bi-cart-plus"></i>
                        </button>
                        <button onclick="removeFromWishlist(${item.product_id})" class="px-3 py-1 bg-red-100 text-red-600 text-xs rounded hover:bg-red-200" title="Remove">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        content.innerHTML = html;
    }

    function addWishlistToCart(variantId, productId) {
        if (!variantId) {
            alert('Please select product options from product page');
            return;
        }

        const formData = new FormData();
        formData.append('variant_id', variantId);
        formData.append('qty', 1);
        formData.append('action', 'add');

        fetch('api/cart_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartCount(data.cartCount);
                updateCartTotal(data.total || 0);
                loadCartSidebar();
                showCartNotification('Added to cart');
                
                // Dispatch custom event for product page
                window.dispatchEvent(new CustomEvent('cartUpdated', {
                    detail: {
                        variantId: variantId,
                        action: 'add',
                        inCart: true
                    }
                }));
                
                // Remove from wishlist after adding to cart
                removeFromWishlist(productId);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function removeFromWishlist(productId) {
        fetch('api/wishlist_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=remove&product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadWishlistSection();
                updateWishlistCount(data.wishlistCount);
                showCartNotification('Removed from wishlist');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function updateWishlistCount(count) {
        const wishlistBadges = document.querySelectorAll('.wishlist-count-badge');
        wishlistBadges.forEach(badge => {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        });
    }

    function showCartNotification(message, type = 'success') {
        const notification = document.createElement('div');
        const bgColor = type === 'warning' ? 'bg-yellow-600' : type === 'error' ? 'bg-red-600' : 'bg-green-600';
        const icon = type === 'warning' ? 'exclamation-triangle' : type === 'error' ? 'x-circle' : 'check-circle';
        
        notification.className = `fixed top-20 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-[10000] animate-fade-in`;
        notification.innerHTML = `<i class="bi bi-${icon} mr-2"></i>${message}`;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('animate-fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    function showConfirmModal(message, onConfirm) {
        const modal = document.createElement('div');
        modal.id = 'confirmModal';
        modal.className = 'fixed inset-0 z-[10001] flex items-center justify-center animate-fade-in';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black/60" onclick="closeConfirmModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 transform animate-scale-in">
                <div class="p-6">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full">
                        <i class="bi bi-exclamation-triangle text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Confirm Removal</h3>
                    <p class="text-gray-600 text-center mb-6">${message}</p>
                    <div class="flex gap-3">
                        <button onclick="closeConfirmModal()" class="flex-1 px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                            Cancel
                        </button>
                        <button onclick="confirmAction()" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        window.confirmCallback = onConfirm;
        
        // Add scale animation
        const modalContent = modal.querySelector('.animate-scale-in');
        setTimeout(() => modalContent.classList.add('scale-100'), 10);
    }

    function confirmAction() {
        if (window.confirmCallback) {
            window.confirmCallback();
            window.confirmCallback = null;
        }
        closeConfirmModal();
    }

    function closeConfirmModal() {
        const modal = document.getElementById('confirmModal');
        if (modal) {
            modal.classList.add('animate-fade-out');
            setTimeout(() => modal.remove(), 300);
        }
    }
</script>

<!-- Floating Cart Button -->
<button onclick="toggleCartSidebar()" id="floatingCartBtn" class="fixed right-4 bottom-20 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-2xl z-[9999] transition-all hover:scale-105 overflow-hidden">
    <div class="flex items-center gap-3 px-4 py-3">
        <div class="relative">
            <i class="bi bi-cart3 text-2xl"></i>
            <span class="floating-cart-badge absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center <?php echo $cartCount > 0 ? '' : 'hidden'; ?>"><?php echo $cartCount; ?></span>
        </div>
        <div class="text-left">
            <div class="item-count-text text-xs opacity-90"><?php echo $cartCount; ?> item<?php echo $cartCount > 1 ? 's' : ''; ?></div>
            <div class="cart-total-text text-sm font-bold">৳<?php echo number_format($cartTotal); ?></div>
        </div>
    </div>
</button>

<!-- Cart Sidebar -->
<div id="cartOverlay" class="fixed inset-0 bg-black/50 z-[9998] hidden" onclick="toggleCartSidebar()"></div>
<div id="cartSidebar" class="fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl z-[9999] transform translate-x-full transition-transform duration-300">
    <div class="flex flex-col h-full">
        <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900"><i class="bi bi-cart3 mr-2"></i>Shopping Cart</h3>
            <button onclick="toggleCartSidebar()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-lg text-2xl"></i>
            </button>
        </div>
        <div id="cartSidebarContent" class="flex-1 overflow-y-auto p-4">
            <!-- Cart items will be loaded here -->
        </div>
    </div>
</div>

<style>
.animate-fade-in {
    animation: fadeIn 0.3s ease-in;
}
.animate-fade-out {
    animation: fadeOut 0.3s ease-out;
}
.animate-scale-in {
    animation: scaleIn 0.3s ease-out;
    transform: scale(0.9);
}
.scale-100 {
    transform: scale(1);
}
.animate-pulse-slow {
    animation: pulseSlow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
.animate-bounce-slow {
    animation: bounceSlow 2s infinite;
}
.animate-heartbeat {
    animation: heartbeat 1.5s ease-in-out infinite;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
@keyframes scaleIn {
    from { 
        opacity: 0;
        transform: scale(0.9);
    }
    to { 
        opacity: 1;
        transform: scale(1);
    }
}
@keyframes pulseSlow {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.02);
        opacity: 0.95;
    }
}
@keyframes bounceSlow {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}
@keyframes heartbeat {
    0%, 100% {
        transform: scale(1);
    }
    10%, 30% {
        transform: scale(1.1);
    }
    20%, 40% {
        transform: scale(1);
    }
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
