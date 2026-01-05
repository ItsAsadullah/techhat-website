<!-- Compact Auth Modal with Morphing Animation -->
<style>
    .auth-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.75);
    }

    .auth-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        animation: fadeIn 0.3s ease;
    }

    .auth-modal-content {
        max-width: 440px;
        width: 100%;
        overflow: hidden;
    }

    /* Morphing Animation */
    .auth-form-container {
        position: relative;
        min-height: 500px;
    }

    .auth-form {
        position: absolute;
        width: 100%;
        opacity: 0;
        transform: scale(0.95);
        pointer-events: none;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .auth-form.active {
        opacity: 1;
        transform: scale(1);
        pointer-events: all;
        position: relative;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .social-btn {
        transition: all 0.2s ease;
    }

    .social-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .input-field:focus {
        box-shadow: 0 0 0 3px rgba(212, 20, 90, 0.1);
    }

    body.modal-open {
        overflow: hidden;
    }

    /* Hide scrollbar */
    .auth-modal-content::-webkit-scrollbar,
    .auth-form-container::-webkit-scrollbar {
        display: none;
    }
</style>

<!-- Single Compact Modal -->
<div id="authModal" class="auth-modal">
    <div class="auth-modal-content bg-white rounded-2xl shadow-2xl">
        
        <!-- Dynamic Header -->
        <div class="bg-gradient-to-r from-pink-50 to-rose-50 px-6 py-5 border-b border-gray-100">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h2 id="modalTitle" class="text-2xl font-bold text-gray-800">Welcome Back!</h2>
                    <p id="modalSubtitle" class="text-gray-600 text-sm mt-1">Login to access your account</p>
                </div>
                <button onclick="closeAuthModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Forms Container -->
        <div class="auth-form-container px-6 py-5">
            
            <!-- Login Form -->
            <div id="loginForm" class="auth-form active">
                <div id="loginError" class="hidden mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg flex items-center gap-2 text-sm">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="loginErrorText"></span>
                </div>

                <!-- Social Login -->
                <div class="space-y-2 mb-4">
                    <button type="button" class="social-btn w-full flex items-center justify-center gap-2 bg-white border-2 border-gray-200 rounded-lg py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                            <path d="M19.8 10.2C19.8 9.5 19.7 8.8 19.6 8.2H10.2V12H15.5C15.3 13.3 14.6 14.4 13.6 15.1V17.6H16.8C18.7 15.8 19.8 13.3 19.8 10.2Z" fill="#4285F4"/>
                            <path d="M10.2 20C12.9 20 15.2 19.1 16.8 17.6L13.6 15.1C12.7 15.7 11.6 16 10.2 16C7.6 16 5.4 14.3 4.6 11.9H1.3V14.5C2.9 17.8 6.3 20 10.2 20Z" fill="#34A853"/>
                            <path d="M4.6 11.9C4.4 11.3 4.3 10.7 4.3 10C4.3 9.3 4.4 8.7 4.6 8.1V5.5H1.3C0.6 6.9 0.2 8.4 0.2 10C0.2 11.6 0.6 13.1 1.3 14.5L4.6 11.9Z" fill="#FBBC05"/>
                            <path d="M10.2 4C11.7 4 13 4.5 14 5.5L16.9 2.6C15.2 1 12.9 0 10.2 0C6.3 0 2.9 2.2 1.3 5.5L4.6 8.1C5.4 5.7 7.6 4 10.2 4Z" fill="#EA4335"/>
                        </svg>
                        Google
                    </button>

                    <button type="button" class="social-btn w-full flex items-center justify-center gap-2 bg-white border-2 border-gray-200 rounded-lg py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                            <path d="M20 10C20 4.5 15.5 0 10 0C4.5 0 0 4.5 0 10C0 15 3.7 19.1 8.4 19.9V12.9H5.9V10H8.4V7.8C8.4 5.3 9.9 3.9 12.2 3.9C13.3 3.9 14.5 4.1 14.5 4.1V6.6H13.2C12 6.6 11.6 7.3 11.6 8.1V10H14.3L13.9 12.9H11.6V19.9C16.3 19.1 20 15 20 10Z" fill="#1877F2"/>
                        </svg>
                        Facebook
                    </button>
                </div>

                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-1 border-t border-gray-200"></div>
                    <span class="text-gray-400 text-xs font-medium">OR</span>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>

                <!-- Login Fields -->
                <form id="loginFormSubmit" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Email or Phone</label>
                        <input type="text" name="email" required placeholder="Enter email or phone"
                               class="input-field w-full px-3 py-2.5 text-sm border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="loginPassword" required placeholder="Enter password"
                                   class="input-field w-full px-3 pr-10 py-2.5 text-sm border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition">
                            <button type="button" onclick="togglePassword('loginPassword', 'loginEye')"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <i class="bi bi-eye text-sm" id="loginEye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" class="w-3.5 h-3.5 accent-pink-600 rounded">
                            <span class="text-gray-600">Remember me</span>
                        </label>
                        <a href="#" class="text-pink-600 font-semibold hover:text-pink-700">Forgot?</a>
                    </div>

                    <button type="submit" class="w-full accent-primary text-white py-2.5 rounded-lg font-bold hover:opacity-90 transition shadow-lg">
                        LOGIN
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <button type="button" onclick="switchToRegister()" class="text-pink-600 font-bold hover:text-pink-700">Sign Up</button>
                    </p>
                </div>
            </div>

            <!-- Register Form -->
            <div id="registerForm" class="auth-form">
                <div id="registerError" class="hidden mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg flex items-center gap-2 text-sm">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="registerErrorText"></span>
                </div>

                <div id="registerSuccess" class="hidden mb-4 bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded-lg flex items-center gap-2 text-sm">
                    <i class="bi bi-check-circle-fill"></i>
                    <span id="registerSuccessText"></span>
                </div>

                <!-- Social Signup -->
                <div class="space-y-2 mb-4">
                    <button type="button" class="social-btn w-full flex items-center justify-center gap-2 bg-white border-2 border-gray-200 rounded-lg py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                            <path d="M19.8 10.2C19.8 9.5 19.7 8.8 19.6 8.2H10.2V12H15.5C15.3 13.3 14.6 14.4 13.6 15.1V17.6H16.8C18.7 15.8 19.8 13.3 19.8 10.2Z" fill="#4285F4"/>
                            <path d="M10.2 20C12.9 20 15.2 19.1 16.8 17.6L13.6 15.1C12.7 15.7 11.6 16 10.2 16C7.6 16 5.4 14.3 4.6 11.9H1.3V14.5C2.9 17.8 6.3 20 10.2 20Z" fill="#34A853"/>
                            <path d="M4.6 11.9C4.4 11.3 4.3 10.7 4.3 10C4.3 9.3 4.4 8.7 4.6 8.1V5.5H1.3C0.6 6.9 0.2 8.4 0.2 10C0.2 11.6 0.6 13.1 1.3 14.5L4.6 11.9Z" fill="#FBBC05"/>
                            <path d="M10.2 4C11.7 4 13 4.5 14 5.5L16.9 2.6C15.2 1 12.9 0 10.2 0C6.3 0 2.9 2.2 1.3 5.5L4.6 8.1C5.4 5.7 7.6 4 10.2 4Z" fill="#EA4335"/>
                        </svg>
                        Google
                    </button>

                    <button type="button" class="social-btn w-full flex items-center justify-center gap-2 bg-white border-2 border-gray-200 rounded-lg py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                            <path d="M20 10C20 4.5 15.5 0 10 0C4.5 0 0 4.5 0 10C0 15 3.7 19.1 8.4 19.9V12.9H5.9V10H8.4V7.8C8.4 5.3 9.9 3.9 12.2 3.9C13.3 3.9 14.5 4.1 14.5 4.1V6.6H13.2C12 6.6 11.6 7.3 11.6 8.1V10H14.3L13.9 12.9H11.6V19.9C16.3 19.1 20 15 20 10Z" fill="#1877F2"/>
                        </svg>
                        Facebook
                    </button>
                </div>

                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-1 border-t border-gray-200"></div>
                    <span class="text-gray-400 text-xs font-medium">OR</span>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>

                <!-- Register Fields -->
                <form id="registerFormSubmit" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name</label>
                            <input type="text" name="name" id="registerName" required placeholder="Your name"
                                   value="<?php echo htmlspecialchars($_SESSION['checkout_data']['name'] ?? ''); ?>"
                                   class="input-field w-full px-3 py-2.5 text-sm border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Phone</label>
                            <input type="tel" name="phone" id="registerPhone" placeholder="Optional"
                                   value="<?php echo htmlspecialchars($_SESSION['checkout_data']['phone'] ?? ''); ?>"
                                   class="input-field w-full px-3 py-2.5 text-sm border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Email Address</label>
                        <input type="email" name="email" required placeholder="Enter your email"
                               class="input-field w-full px-3 py-2.5 text-sm border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Password</label>
                            <div class="relative">
                                <input type="password" name="password" id="regPassword" required placeholder="Password"
                                       class="input-field w-full px-3 pr-9 py-2.5 text-sm border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition">
                                <button type="button" onclick="togglePassword('regPassword', 'regEye')"
                                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600">
                                    <i class="bi bi-eye text-sm" id="regEye"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Confirm</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirmPassword" required placeholder="Confirm"
                                       class="input-field w-full px-3 pr-9 py-2.5 text-sm border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition">
                                <button type="button" onclick="togglePassword('confirmPassword', 'confirmEye')"
                                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600">
                                    <i class="bi bi-eye text-sm" id="confirmEye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <input type="checkbox" required class="w-3.5 h-3.5 accent-pink-600 rounded mt-0.5">
                        <label class="text-xs text-gray-600 leading-relaxed">
                            I agree to <a href="#" class="text-pink-600 hover:underline font-semibold">Terms & Conditions</a>
                        </label>
                    </div>

                    <button type="submit" class="w-full accent-primary text-white py-2.5 rounded-lg font-bold hover:opacity-90 transition shadow-lg">
                        CREATE ACCOUNT
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">
                        Already have an account? 
                        <button type="button" onclick="switchToLogin()" class="text-pink-600 font-bold hover:text-pink-700">Login</button>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentForm = 'login';

function openAuthModal(formType = 'login') {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        
        if (formType === 'loginModal') formType = 'login';
        if (formType === 'registerModal') formType = 'register';
        
        if (formType === 'register') {
            switchToRegister();
        } else {
            switchToLogin();
        }
    }
}

function closeAuthModal() {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

function switchToLogin() {
    if (currentForm === 'login') return;
    
    currentForm = 'login';
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const title = document.getElementById('modalTitle');
    const subtitle = document.getElementById('modalSubtitle');
    
    // Morphing effect
    registerForm.classList.remove('active');
    setTimeout(() => {
        loginForm.classList.add('active');
        title.textContent = 'Welcome Back!';
        subtitle.textContent = 'Login to access your account';
    }, 200);
}

function switchToRegister() {
    if (currentForm === 'register') return;
    
    currentForm = 'register';
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const title = document.getElementById('modalTitle');
    const subtitle = document.getElementById('modalSubtitle');
    
    // Morphing effect
    loginForm.classList.remove('active');
    setTimeout(() => {
        registerForm.classList.add('active');
        title.textContent = 'Create Account';
        subtitle.textContent = 'Sign up to get started with TechHat';
    }, 200);
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'authModal') {
        closeAuthModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAuthModal();
    }
});

// Login Form Submit
document.getElementById('loginFormSubmit')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'login');
    
    try {
        const response = await fetch('core/auth_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = result.redirect || 'dashboard.php';
        } else {
            document.getElementById('loginError').classList.remove('hidden');
            document.getElementById('loginErrorText').textContent = result.message || 'Login failed';
        }
    } catch (error) {
        document.getElementById('loginError').classList.remove('hidden');
        document.getElementById('loginErrorText').textContent = 'An error occurred. Please try again.';
    }
});

// Register Form Submit
document.getElementById('registerFormSubmit')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'register');
    
    try {
        const response = await fetch('core/auth_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success message briefly
            document.getElementById('registerSuccess').classList.remove('hidden');
            document.getElementById('registerSuccessText').textContent = result.message || 'Registration successful!';
            document.getElementById('registerError').classList.add('hidden');
            
            // Auto-login successful - redirect immediately
            setTimeout(() => {
                window.location.href = result.redirect || 'index.php';
            }, 800);
        } else {
            document.getElementById('registerError').classList.remove('hidden');
            document.getElementById('registerErrorText').textContent = result.message || 'Registration failed';
            document.getElementById('registerSuccess').classList.add('hidden');
        }
    } catch (error) {
        document.getElementById('registerError').classList.remove('hidden');
        document.getElementById('registerErrorText').textContent = 'An error occurred. Please try again.';
    }
});
</script>
