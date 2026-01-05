<?php
require_once '../core/db.php';
require_once '../core/auth.php';

// Admin check
if (!is_logged_in() || !is_admin()) {
    header("Location: ../login.php");
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'facebook_url' => $_POST['facebook_url'],
            'instagram_url' => $_POST['instagram_url'],
            'youtube_url' => $_POST['youtube_url'],
            'twitter_url' => $_POST['twitter_url'] ?? '',
            'linkedin_url' => $_POST['linkedin_url'] ?? '',
            'tiktok_url' => $_POST['tiktok_url'] ?? '',
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $message = "Social media settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings WHERE setting_key IN ('facebook_url', 'instagram_url', 'youtube_url', 'twitter_url', 'linkedin_url', 'tiktok_url')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function getSetting($settings, $key, $default = '') {
    return $settings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Settings - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100">
    
    <div class="flex">
        <?php include 'partials/sidebar.php'; ?>
        
        <div class="flex-1 ml-0 lg:ml-[280px] p-4 lg:p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-3 mb-2">
                        <a href="settings.php" class="text-gray-400 hover:text-gray-600">
                            <i class="bi bi-arrow-left text-xl"></i>
                        </a>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                            <i class="bi bi-share text-pink-600"></i>
                            Social Media Settings
                        </h1>
                    </div>
                    <p class="text-gray-600 ml-10">Configure social media profile links and integrations</p>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                        <i class="bi bi-check-circle-fill text-xl"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-xl"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-facebook text-blue-600"></i> Facebook Page URL
                                </label>
                                <input type="url" name="facebook_url" value="<?php echo htmlspecialchars(getSetting($settings, 'facebook_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://facebook.com/yourpage">
                                <p class="mt-1 text-xs text-gray-500">Your Facebook business page URL</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-instagram text-pink-600"></i> Instagram Profile URL
                                </label>
                                <input type="url" name="instagram_url" value="<?php echo htmlspecialchars(getSetting($settings, 'instagram_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://instagram.com/yourprofile">
                                <p class="mt-1 text-xs text-gray-500">Your Instagram business profile URL</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-youtube text-red-600"></i> YouTube Channel URL
                                </label>
                                <input type="url" name="youtube_url" value="<?php echo htmlspecialchars(getSetting($settings, 'youtube_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://youtube.com/@yourchannel">
                                <p class="mt-1 text-xs text-gray-500">Your YouTube channel URL</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-twitter text-blue-400"></i> Twitter/X Profile URL
                                </label>
                                <input type="url" name="twitter_url" value="<?php echo htmlspecialchars(getSetting($settings, 'twitter_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://twitter.com/yourhandle">
                                <p class="mt-1 text-xs text-gray-500">Your Twitter/X profile URL</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-linkedin text-blue-700"></i> LinkedIn Profile URL
                                </label>
                                <input type="url" name="linkedin_url" value="<?php echo htmlspecialchars(getSetting($settings, 'linkedin_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://linkedin.com/company/yourcompany">
                                <p class="mt-1 text-xs text-gray-500">Your LinkedIn company page URL</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-tiktok text-gray-900"></i> TikTok Profile URL
                                </label>
                                <input type="url" name="tiktok_url" value="<?php echo htmlspecialchars(getSetting($settings, 'tiktok_url', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="https://tiktok.com/@yourprofile">
                                <p class="mt-1 text-xs text-gray-500">Your TikTok profile URL</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-3">
                        <button type="submit" class="bg-pink-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-pink-700 transition-colors flex items-center gap-2">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                        <a href="settings.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors flex items-center gap-2">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
