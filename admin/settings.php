<?php
require_once '../core/db.php';
require_once '../core/auth.php';

// Admin check
if (!is_logged_in() || !is_admin()) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100">
    
    <div class="flex">
        <?php include 'partials/sidebar.php'; ?>
        
        <div class="flex-1 ml-0 lg:ml-[280px] p-4 lg:p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="bi bi-gear-fill text-blue-600"></i>
                        Settings
                    </h1>
                    <p class="text-gray-600 mt-2">Manage your website settings and configurations</p>
                </div>

                <!-- Settings Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <!-- Site Branding Settings -->
                    <a href="site_settings.php" class="block bg-white rounded-xl shadow-sm hover:shadow-lg transition-all p-6 border-2 border-transparent hover:border-blue-500 group">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-blue-100 group-hover:bg-blue-600 rounded-lg flex items-center justify-center transition-colors">
                                <i class="bi bi-shop text-blue-600 group-hover:text-white text-2xl transition-colors"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Site Branding</h3>
                                <p class="text-sm text-gray-500">Website name & logo</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Configure your website name, logo, and branding elements</p>
                        <div class="flex items-center text-blue-600 group-hover:text-blue-700 font-medium text-sm">
                            Manage Settings <i class="bi bi-arrow-right ml-2"></i>
                        </div>
                    </a>

                    <!-- Delivery Settings -->
                    <a href="delivery_settings.php" class="block bg-white rounded-xl shadow-sm hover:shadow-lg transition-all p-6 border-2 border-transparent hover:border-orange-500 group">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-orange-100 group-hover:bg-orange-600 rounded-lg flex items-center justify-center transition-colors">
                                <i class="bi bi-truck text-orange-600 group-hover:text-white text-2xl transition-colors"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Delivery Settings</h3>
                                <p class="text-sm text-gray-500">Shipping & charges</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Manage delivery charges, zones, times, and COD options</p>
                        <div class="flex items-center text-orange-600 group-hover:text-orange-700 font-medium text-sm">
                            Manage Settings <i class="bi bi-arrow-right ml-2"></i>
                        </div>
                    </a>

                    <!-- Contact Information -->
                    <a href="contact_settings.php" class="block bg-white rounded-xl shadow-sm hover:shadow-lg transition-all p-6 border-2 border-transparent hover:border-green-500 group">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-green-100 group-hover:bg-green-600 rounded-lg flex items-center justify-center transition-colors">
                                <i class="bi bi-telephone text-green-600 group-hover:text-white text-2xl transition-colors"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Contact Info</h3>
                                <p class="text-sm text-gray-500">Phone, email & address</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Update business phone, email, and physical address</p>
                        <div class="flex items-center text-green-600 group-hover:text-green-700 font-medium text-sm">
                            Manage Settings <i class="bi bi-arrow-right ml-2"></i>
                        </div>
                    </a>

                    <!-- Social Media -->
                    <a href="social_settings.php" class="block bg-white rounded-xl shadow-sm hover:shadow-lg transition-all p-6 border-2 border-transparent hover:border-pink-500 group">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-pink-100 group-hover:bg-pink-600 rounded-lg flex items-center justify-center transition-colors">
                                <i class="bi bi-share text-pink-600 group-hover:text-white text-2xl transition-colors"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Social Media</h3>
                                <p class="text-sm text-gray-500">Facebook, Instagram, etc.</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Configure social media profile links and integrations</p>
                        <div class="flex items-center text-pink-600 group-hover:text-pink-700 font-medium text-sm">
                            Manage Settings <i class="bi bi-arrow-right ml-2"></i>
                        </div>
                    </a>

                    <!-- Payment Settings -->
                    <a href="payment_settings.php" class="block bg-white rounded-xl shadow-sm hover:shadow-lg transition-all p-6 border-2 border-transparent hover:border-purple-500 group">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-purple-100 group-hover:bg-purple-600 rounded-lg flex items-center justify-center transition-colors">
                                <i class="bi bi-credit-card text-purple-600 group-hover:text-white text-2xl transition-colors"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Payment Settings</h3>
                                <p class="text-sm text-gray-500">Payment methods & gateways</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Configure payment gateways and accepted payment methods</p>
                        <div class="flex items-center text-purple-600 group-hover:text-purple-700 font-medium text-sm">
                            Manage Settings <i class="bi bi-arrow-right ml-2"></i>
                        </div>
                    </a>

                    <!-- Return & Warranty -->
                    <a href="return_settings.php" class="block bg-white rounded-xl shadow-sm hover:shadow-lg transition-all p-6 border-2 border-transparent hover:border-red-500 group">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-red-100 group-hover:bg-red-600 rounded-lg flex items-center justify-center transition-colors">
                                <i class="bi bi-arrow-counterclockwise text-red-600 group-hover:text-white text-2xl transition-colors"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Return & Warranty</h3>
                                <p class="text-sm text-gray-500">Return policy settings</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Manage return period, warranty terms, and policies</p>
                        <div class="flex items-center text-red-600 group-hover:text-red-700 font-medium text-sm">
                            Manage Settings <i class="bi bi-arrow-right ml-2"></i>
                        </div>
                    </a>

                </div>

            </div>
        </div>
    </div>

</body>
</html>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Site Branding</h2>
                                <p class="text-sm text-gray-600">Your website name and branding</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Site Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="site_name" value="<?php echo htmlspecialchars(getSetting($settings, 'site_name', 'TechHat')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="TechHat" required>
                                <p class="mt-1 text-xs text-gray-500">This name will appear in header, footer, and page titles</p>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Settings -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="bi bi-truck text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Delivery Settings</h2>
                                <p class="text-sm text-gray-600">Configure delivery charges and information</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-geo-alt text-blue-600"></i> Home District <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="home_district" value="<?php echo htmlspecialchars(getSetting($settings, 'home_district', 'Jhenaidah')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="Jhenaidah" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Inside District Charge (৳) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="delivery_charge_inside" value="<?php echo htmlspecialchars(getSetting($settings, 'delivery_charge_inside', '70')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       min="0" step="1" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Outside District Charge (৳) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="delivery_charge_outside" value="<?php echo htmlspecialchars(getSetting($settings, 'delivery_charge_outside', '150')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       min="0" step="1" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Free Delivery Threshold (৳) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="free_delivery_threshold" value="<?php echo htmlspecialchars(getSetting($settings, 'free_delivery_threshold', '5000')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       min="0" step="100" required>
                                <p class="mt-1 text-xs text-gray-500">Orders above this amount get free delivery</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Return Period (Days) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="return_days" value="<?php echo htmlspecialchars(getSetting($settings, 'return_days', '14')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       min="0" step="1" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Inside District Delivery Time
                                </label>
                                <input type="text" name="delivery_time_inside" value="<?php echo htmlspecialchars(getSetting($settings, 'delivery_time_inside', '2-3 business days')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="e.g., 2-3 business days">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Outside District Delivery Time
                                </label>
                                <input type="text" name="delivery_time_outside" value="<?php echo htmlspecialchars(getSetting($settings, 'delivery_time_outside', '3-5 business days')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="e.g., 3-5 business days">
                            </div>

                            <div class="md:col-span-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="cod_available" value="1" 
                                           <?php echo getSetting($settings, 'cod_available', '1') == '1' ? 'checked' : ''; ?>
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm font-medium text-gray-700">Cash on Delivery Available</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="bi bi-telephone text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Contact Information</h2>
                                <p class="text-sm text-gray-600">Your business contact details</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-phone text-green-600"></i> Contact Phone
                                </label>
                                <input type="text" name="contact_phone" value="<?php echo htmlspecialchars(getSetting($settings, 'contact_phone', '09678-300400')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="09678-300400">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-envelope text-green-600"></i> Contact Email
                                </label>
                                <input type="email" name="contact_email" value="<?php echo htmlspecialchars(getSetting($settings, 'contact_email', 'support@techhat.com')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="support@techhat.com">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-geo-alt text-green-600"></i> Business Address
                                </label>
                                <textarea name="contact_address" rows="2" 
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                          placeholder="Enter your business address"><?php echo htmlspecialchars(getSetting($settings, 'contact_address', '')); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-200">
                            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
                                <i class="bi bi-share text-pink-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Social Media</h2>
                                <p class="text-sm text-gray-600">Your social media profile links</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-facebook text-blue-600"></i> Facebook Page URL
                                </label>
                                <input type="url" name="facebook_url" value="<?php echo htmlspecialchars(getSetting($settings, 'facebook_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://facebook.com/yourpage">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-instagram text-pink-600"></i> Instagram Profile URL
                                </label>
                                <input type="url" name="instagram_url" value="<?php echo htmlspecialchars(getSetting($settings, 'instagram_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://instagram.com/yourprofile">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-youtube text-red-600"></i> YouTube Channel URL
                                </label>
                                <input type="url" name="youtube_url" value="<?php echo htmlspecialchars(getSetting($settings, 'youtube_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://youtube.com/yourchannel">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-3 sticky bottom-4 bg-white p-4 rounded-xl shadow-lg border border-gray-200">
                        <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                            <i class="bi bi-save"></i> Save All Settings
                        </button>
                        <a href="index.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors flex items-center justify-center gap-2">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
