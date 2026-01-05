/**
 * SPA-like Navigation System
 * Intercepts link clicks and loads content via AJAX
 * Prevents page reloads for smooth user experience
 */

class SPANavigator {
    constructor() {
        this.contentArea = document.querySelector('main, #main-content, body');
        this.loadingBar = null;
        this.init();
    }

    init() {
        // Create loading bar
        this.createLoadingBar();

        // Intercept all link clicks
        this.interceptLinks();

        // Handle browser back/forward
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.url) {
                this.loadPage(e.state.url, false);
            }
        });

        // Add initial state
        history.replaceState({ url: window.location.href }, '', window.location.href);

        // Add smooth scroll
        document.documentElement.style.scrollBehavior = 'smooth';
    }

    createLoadingBar() {
        this.loadingBar = document.createElement('div');
        this.loadingBar.className = 'page-loading';
        this.loadingBar.style.display = 'none';
        document.body.appendChild(this.loadingBar);
    }

    showLoading() {
        if (this.loadingBar) {
            this.loadingBar.style.display = 'block';
        }
    }

    hideLoading() {
        if (this.loadingBar) {
            this.loadingBar.style.display = 'none';
        }
    }

    interceptLinks() {
        document.addEventListener('click', (e) => {
            // Find the closest anchor tag
            const link = e.target.closest('a');
            
            if (!link) return;
            
            // Skip cart widget links and buttons
            if (link.closest('#cartSidebar') || 
                link.closest('#floatingCartBtn') || 
                link.closest('.cart-widget')) {
                return;
            }

            // Skip if:
            // - External link
            // - Has download attribute
            // - Has target="_blank"
            // - Is admin link
            // - Is logout link
            // - Is cart/checkout link
            // - Has data-no-spa attribute
            if (
                link.href.startsWith('http') && !link.href.includes(window.location.host) ||
                link.hasAttribute('download') ||
                link.target === '_blank' ||
                link.href.includes('/admin/') ||
                link.href.includes('logout.php') ||
                link.href.includes('checkout.php') ||
                link.href.includes('cart.php') ||
                link.hasAttribute('data-no-spa') ||
                link.classList.contains('no-spa')
            ) {
                return;
            }

            // Skip if it's a hash link (anchor)
            if (link.getAttribute('href')?.startsWith('#')) {
                return;
            }

            // Skip if it's a javascript: link
            if (link.getAttribute('href')?.startsWith('javascript:')) {
                return;
            }

            // Prevent default and load via AJAX
            e.preventDefault();
            this.loadPage(link.href, true);
        });
    }

    async loadPage(url, updateHistory = true) {
        try {
            // Show loading
            this.showLoading();

            // Add fade out effect
            this.contentArea.style.opacity = '0.7';
            this.contentArea.style.transition = 'opacity 0.2s ease';

            // Fetch the page
            const response = await fetch(url);
            if (!response.ok) throw new Error('Page load failed');

            const html = await response.text();

            // Parse the HTML
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Extract the main content (adjust selector as needed)
            const newContent = doc.querySelector('main, #main-content, body');
            
            if (newContent) {
                // Smooth transition
                setTimeout(() => {
                    // Update content
                    this.contentArea.innerHTML = newContent.innerHTML;

                    // Fade in
                    this.contentArea.style.opacity = '1';

                    // Scroll to top smoothly
                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    // Update page title
                    const newTitle = doc.querySelector('title');
                    if (newTitle) {
                        document.title = newTitle.textContent;
                    }

                    // Re-initialize scripts (cart, wishlist, etc.)
                    this.reinitializeScripts();

                    // Update browser history
                    if (updateHistory) {
                        history.pushState({ url: url }, '', url);
                    }

                    // Hide loading
                    this.hideLoading();
                }, 200);
            }

        } catch (error) {
            console.error('Page load error:', error);
            // Fallback to normal navigation
            window.location.href = url;
        }
    }

    reinitializeScripts() {
        // Re-trigger cart widget if it exists
        if (typeof updateCartCount === 'function') {
            updateCartCount();
        }

        // Re-trigger wishlist if it exists
        if (typeof updateWishlistCount === 'function') {
            updateWishlistCount();
        }

        // Dispatch custom event for other scripts to listen to
        window.dispatchEvent(new CustomEvent('spaPageLoaded'));
    }
}

// Add smooth interactions to all buttons
function addSmoothInteractions() {
    // Add smooth class to all buttons
    document.querySelectorAll('button, .btn, [role="button"]').forEach(btn => {
        if (!btn.classList.contains('btn-smooth')) {
            btn.classList.add('btn-smooth');
        }
    });

    // Add smooth class to all cards
    document.querySelectorAll('.card, [class*="card-"]').forEach(card => {
        if (!card.classList.contains('card-smooth')) {
            card.classList.add('card-smooth');
        }
    });
}

// Prevent form submission page reload (for AJAX forms)
function smoothFormSubmissions() {
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Skip if form has data-no-ajax attribute
        if (form.hasAttribute('data-no-ajax') || form.classList.contains('no-ajax')) {
            return;
        }

        // For forms that should use AJAX (like cart, wishlist)
        if (form.hasAttribute('data-ajax-form')) {
            e.preventDefault();
            // Form-specific AJAX handling should be done in respective scripts
        }
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize SPA Navigator
        const spa = new SPANavigator();
        
        // Add smooth interactions
        addSmoothInteractions();
        
        // Setup smooth form submissions
        smoothFormSubmissions();
        
        // Re-add smooth classes after SPA page load
        window.addEventListener('spaPageLoaded', () => {
            addSmoothInteractions();
        });
    });
} else {
    // DOM already loaded
    const spa = new SPANavigator();
    addSmoothInteractions();
    smoothFormSubmissions();
    
    window.addEventListener('spaPageLoaded', () => {
        addSmoothInteractions();
    });
}
