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
            'contact_phone' => $_POST['contact_phone'],
            'contact_email' => $_POST['contact_email'],
            'contact_address' => $_POST['contact_address'],
            'contact_whatsapp' => $_POST['contact_whatsapp'] ?? '',
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $message = "Contact information updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings WHERE setting_key IN ('contact_phone', 'contact_email', 'contact_address', 'contact_whatsapp')");
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
    <title>Contact Information Settings - Admin Panel</title>
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
                            <i class="bi bi-telephone text-green-600"></i>
                            Contact Information Settings
                        </h1>
                    </div>
                    <p class="text-gray-600 ml-10">Update business phone, email, and physical address</p>
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
                                    <i class="bi bi-phone text-green-600"></i> Contact Phone <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="contact_phone" value="<?php echo htmlspecialchars(getSetting($settings, 'contact_phone', '09678-300400')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="09678-300400" required>
                                <p class="mt-1 text-xs text-gray-500">Your primary business phone number</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-whatsapp text-green-600"></i> WhatsApp Number
                                </label>
                                <input type="text" name="contact_whatsapp" value="<?php echo htmlspecialchars(getSetting($settings, 'contact_whatsapp', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="+8801XXXXXXXXX">
                                <p class="mt-1 text-xs text-gray-500">WhatsApp number with country code (e.g., +8801712345678)</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-envelope text-green-600"></i> Contact Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="contact_email" value="<?php echo htmlspecialchars(getSetting($settings, 'contact_email', 'support@techhat.com')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="support@techhat.com" required>
                                <p class="mt-1 text-xs text-gray-500">Your business email address for customer inquiries</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-geo-alt text-green-600"></i> Business Address <span class="text-red-500">*</span>
                                </label>
                                <textarea name="contact_address" rows="3" 
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                          placeholder="Enter your complete business address with area, district, and postal code" required><?php echo htmlspecialchars(getSetting($settings, 'contact_address', '')); ?></textarea>
                                <p class="mt-1 text-xs text-gray-500">Your physical business address (shown in footer and contact page)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-3">
                        <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors flex items-center gap-2">
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
