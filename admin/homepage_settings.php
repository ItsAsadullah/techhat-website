<?php
require_once '../core/db.php';
require_once '../core/auth.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Fetch current settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
$settingsData = $settingsStmt->fetchAll();
$settings = [];
foreach($settingsData as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $pdo->beginTransaction();
        
        $updateFields = [
            'seo_title',
            'seo_description',
            'seo_extended_text',
            'seo_features',
            'footer_about',
            'footer_phone',
            'footer_email',
            'footer_address',
            'footer_hours',
            'site_name'
        ];
        
        foreach($updateFields as $field) {
            $value = $_POST[$field] ?? '';
            $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$field, $value, $value]);
        }
        
        $pdo->commit();
        $success = 'Settings updated successfully!';
        
        // Refresh settings
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
        $settingsData = $settingsStmt->fetchAll();
        $settings = [];
        foreach($settingsData as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Settings - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-100">

    <?php include 'partials/sidebar.php'; ?>

    <div class="admin-content ml-0 md:ml-64 p-6">
        <div class="max-w-5xl mx-auto">
            
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 gradient-bg rounded-lg flex items-center justify-center text-white text-2xl">
                        <i class="bi bi-gear-fill"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Homepage Settings</h1>
                        <p class="text-gray-600 text-sm">Manage SEO content, footer info, and homepage sections</p>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
                <i class="bi bi-check-circle-fill text-xl"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
                <i class="bi bi-exclamation-triangle-fill text-xl"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <!-- Settings Form -->
            <form method="POST" action="">
                
                <!-- Site Branding -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="bi bi-brush-fill text-purple-600"></i> Site Branding
                    </h2>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Site Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="site_name" 
                               value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                               required>
                    </div>
                </div>

                <!-- SEO Content Section -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="bi bi-search text-purple-600"></i> SEO Content
                    </h2>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            SEO Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="seo_title" 
                               value="<?php echo htmlspecialchars($settings['seo_title'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                               required>
                        <p class="text-xs text-gray-500 mt-1">Main heading shown in SEO section</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            SEO Description (First Paragraph) <span class="text-red-500">*</span>
                        </label>
                        <textarea name="seo_description" 
                                  rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                                  required><?php echo htmlspecialchars($settings['seo_description'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">First paragraph of content</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            SEO Extended Text (Second Paragraph)
                        </label>
                        <textarea name="seo_extended_text" 
                                  rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"><?php echo htmlspecialchars($settings['seo_extended_text'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Additional content paragraph (optional)</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Features (Separate with |)
                        </label>
                        <input type="text" 
                               name="seo_features" 
                               value="<?php echo htmlspecialchars($settings['seo_features'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                               placeholder="Feature 1|Feature 2|Feature 3|Feature 4">
                        <p class="text-xs text-gray-500 mt-1">Example: 100% Genuine Products|Fast Delivery|Free Shipping|Official Warranty</p>
                    </div>
                </div>

                <!-- Footer Information -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="bi bi-layout-text-window-reverse text-purple-600"></i> Footer Information
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                About Text
                            </label>
                            <textarea name="footer_about" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"><?php echo htmlspecialchars($settings['footer_about'] ?? ''); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Phone Number
                            </label>
                            <input type="text" 
                                   name="footer_phone" 
                                   value="<?php echo htmlspecialchars($settings['footer_phone'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent mb-4">
                            
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Email Address
                            </label>
                            <input type="email" 
                                   name="footer_email" 
                                   value="<?php echo htmlspecialchars($settings['footer_email'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent mb-4">
                            
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Business Hours
                            </label>
                            <input type="text" 
                                   name="footer_hours" 
                                   value="<?php echo htmlspecialchars($settings['footer_hours'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                                   placeholder="10:00 AM - 11:00 PM">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-4">
                    <button type="submit" 
                            name="update_settings"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold py-3 px-6 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center justify-center gap-2">
                        <i class="bi bi-check-circle-fill text-xl"></i>
                        Update Settings
                    </button>
                    
                    <a href="../index.php" 
                       target="_blank"
                       class="bg-gray-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-700 transition-all duration-300 flex items-center justify-center gap-2">
                        <i class="bi bi-eye-fill text-xl"></i>
                        Preview Homepage
                    </a>
                </div>

            </form>

        </div>
    </div>

</body>
</html>