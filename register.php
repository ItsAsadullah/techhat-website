<?php
require_once 'core/auth.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $result = register($name, $email, $password, $phone);
        if ($result === true) {
            $success = "Registration successful! You can now login.";
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - TechHat</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .accent-primary {
            background: linear-gradient(135deg, #D4145A 0%, #C41E3A 100%);
        }

        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .social-btn {
            transition: all 0.2s ease;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .input-field {
            transition: all 0.2s ease;
        }

        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(212, 20, 90, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <!-- Register Modal -->
    <div class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto py-8">
        <div class="modal-content bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden my-8">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-pink-50 to-rose-50 px-8 py-6 border-b border-gray-100">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-2xl font-bold text-gray-800">Create Account</h2>
                    <a href="index.php" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="bi bi-x-lg text-2xl"></i>
                    </a>
                </div>
                <p class="text-gray-600 text-sm">Sign up to get started with TechHat</p>
            </div>

            <!-- Body -->
            <div class="px-8 py-6">
                
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center gap-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <span><?php echo htmlspecialchars($success); ?> <a href="login.php" class="font-bold underline">Login now</a></span>
                    </div>
                <?php endif; ?>

                <!-- Social Signup Buttons -->
                <div class="space-y-3 mb-6">
                    <button class="social-btn w-full flex items-center justify-center gap-3 bg-white border-2 border-gray-200 rounded-xl py-3 font-semibold text-gray-700 hover:bg-gray-50">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19.8 10.2273C19.8 9.51819 19.7364 8.83637 19.6182 8.18182H10.2V12.05H15.5091C15.2909 13.3 14.6091 14.3591 13.5818 15.0682V17.5773H16.8182C18.7091 15.8364 19.8 13.2727 19.8 10.2273Z" fill="#4285F4"/>
                            <path d="M10.2 20C12.9 20 15.1727 19.1045 16.8182 17.5773L13.5818 15.0682C12.7091 15.6682 11.5636 16.0227 10.2 16.0227C7.59091 16.0227 5.37273 14.2636 4.59545 11.9H1.25455V14.4909C2.89091 17.7591 6.27273 20 10.2 20Z" fill="#34A853"/>
                            <path d="M4.59545 11.9C4.40909 11.3 4.30455 10.6591 4.30455 10C4.30455 9.34091 4.40909 8.7 4.59545 8.1V5.50909H1.25455C0.572727 6.85909 0.2 8.38636 0.2 10C0.2 11.6136 0.572727 13.1409 1.25455 14.4909L4.59545 11.9Z" fill="#FBBC05"/>
                            <path d="M10.2 3.97727C11.6818 3.97727 13.0045 4.48182 14.0364 5.47273L16.9091 2.6C15.1682 0.986364 12.8955 0 10.2 0C6.27273 0 2.89091 2.24091 1.25455 5.50909L4.59545 8.1C5.37273 5.73636 7.59091 3.97727 10.2 3.97727Z" fill="#EA4335"/>
                        </svg>
                        Sign up with Google
                    </button>

                    <button class="social-btn w-full flex items-center justify-center gap-3 bg-white border-2 border-gray-200 rounded-xl py-3 font-semibold text-gray-700 hover:bg-gray-50">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 10C20 4.47715 15.5228 0 10 0C4.47715 0 0 4.47715 0 10C0 14.9912 3.65684 19.1283 8.4375 19.8785V12.8906H5.89844V10H8.4375V7.79688C8.4375 5.29063 9.93047 3.90625 12.2146 3.90625C13.3084 3.90625 14.4531 4.10156 14.4531 4.10156V6.5625H13.1922C11.95 6.5625 11.5625 7.3334 11.5625 8.125V10H14.3359L13.8926 12.8906H11.5625V19.8785C16.3432 19.1283 20 14.9912 20 10Z" fill="#1877F2"/>
                        </svg>
                        Sign up with Facebook
                    </button>
                </div>

                <!-- Divider -->
                <div class="flex items-center gap-4 mb-6">
                    <div class="flex-1 border-t border-gray-200"></div>
                    <span class="text-gray-400 text-sm font-medium">OR</span>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>

                <!-- Register Form -->
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Full Name
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-person text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   name="name" 
                                   required
                                   placeholder="Enter your full name"
                                   class="input-field w-full pl-11 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-pink-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-envelope text-gray-400"></i>
                            </div>
                            <input type="email" 
                                   name="email" 
                                   required
                                   placeholder="Enter your email"
                                   class="input-field w-full pl-11 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-pink-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-phone text-gray-400"></i>
                            </div>
                            <input type="tel" 
                                   name="phone" 
                                   placeholder="Enter your phone number (optional)"
                                   class="input-field w-full pl-11 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-pink-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-lock text-gray-400"></i>
                            </div>
                            <input type="password" 
                                   name="password" 
                                   id="regPassword"
                                   required
                                   placeholder="Create a password"
                                   class="input-field w-full pl-11 pr-12 py-3 border-2 border-gray-200 rounded-xl focus:border-pink-500 focus:outline-none transition">
                            <button type="button" 
                                    onclick="togglePassword('regPassword', 'regEye')"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                                <i class="bi bi-eye" id="regEye"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-lock-fill text-gray-400"></i>
                            </div>
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirmPassword"
                                   required
                                   placeholder="Confirm your password"
                                   class="input-field w-full pl-11 pr-12 py-3 border-2 border-gray-200 rounded-xl focus:border-pink-500 focus:outline-none transition">
                            <button type="button" 
                                    onclick="togglePassword('confirmPassword', 'confirmEye')"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                                <i class="bi bi-eye" id="confirmEye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <input type="checkbox" required class="w-4 h-4 accent-pink-600 rounded mt-1">
                        <label class="text-sm text-gray-600 leading-relaxed">
                            I agree to TechHat's 
                            <a href="#" class="text-pink-600 hover:underline font-semibold">Terms & Conditions</a> and 
                            <a href="#" class="text-pink-600 hover:underline font-semibold">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" 
                            class="w-full accent-primary text-white py-3.5 rounded-xl font-bold text-lg hover:opacity-90 transition shadow-lg hover:shadow-xl">
                        CREATE ACCOUNT
                    </button>
                </form>

                <!-- Login Link -->
                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="login.php" class="text-pink-600 font-bold hover:text-pink-700 ml-1">
                            Login
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>