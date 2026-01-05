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
        // Update home district
        $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES ('home_district', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$_POST['home_district'], $_POST['home_district']]);
        
        // Update inside charge
        $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES ('delivery_charge_inside', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$_POST['charge_inside'], $_POST['charge_inside']]);
        
        // Update outside charge
        $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES ('delivery_charge_outside', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$_POST['charge_outside'], $_POST['charge_outside']]);
        
        // Update free delivery threshold
        $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES ('free_delivery_threshold', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$_POST['free_delivery_threshold'], $_POST['free_delivery_threshold']]);
        
        // Update inside delivery time
        $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES ('delivery_time_inside', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$_POST['delivery_time_inside'], $_POST['delivery_time_inside']]);
        
        // Update outside delivery time
        $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES ('delivery_time_outside', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$_POST['delivery_time_outside'], $_POST['delivery_time_outside']]);
        
        $message = "Delivery settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings WHERE setting_key IN ('home_district', 'delivery_charge_inside', 'delivery_charge_outside', 'free_delivery_threshold', 'delivery_time_inside', 'delivery_time_outside')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Defaults
$homeDistrict = $settings['home_district'] ?? 'Jhenaidah';
$chargeInside = $settings['delivery_charge_inside'] ?? 70;
$chargeOutside = $settings['delivery_charge_outside'] ?? 150;
$freeThreshold = $settings['free_delivery_threshold'] ?? 5000;
$timeInside = $settings['delivery_time_inside'] ?? '2-3 business days';
$timeOutside = $settings['delivery_time_outside'] ?? '3-5 business days';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Settings - Admin Panel</title>
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
                            <i class="bi bi-truck text-orange-600"></i>
                            Delivery Settings
                        </h1>
                    </div>
                    <p class="text-gray-600 ml-10">Manage delivery charges, zones, times, and COD options</p>
                </div>

                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                        <i class="bi bi-check-circle-fill mr-2"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <i class="bi bi-exclamation-triangle-fill mr-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="bg-white rounded-xl shadow-sm p-6 space-y-6">
                    
                    <!-- Home District -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="bi bi-geo-alt text-blue-600"></i> Home District
                        </label>
                        <input type="text" name="home_district" value="<?php echo htmlspecialchars($homeDistrict); ?>" 
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               required>
                        <p class="mt-1 text-xs text-gray-500">The main district where your business is located</p>
                    </div>

                    <!-- Delivery Charges -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-truck text-green-600"></i> Inside Home District Charge (৳)
                            </label>
                            <input type="number" name="charge_inside" value="<?php echo htmlspecialchars($chargeInside); ?>" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                   min="0" step="1" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-truck text-orange-600"></i> Outside Home District Charge (৳)
                            </label>
                            <input type="number" name="charge_outside" value="<?php echo htmlspecialchars($chargeOutside); ?>" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                   min="0" step="1" required>
                        </div>
                    </div>

                    <!-- Free Delivery Threshold -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="bi bi-tag text-purple-600"></i> Free Delivery Threshold (৳)
                        </label>
                        <input type="number" name="free_delivery_threshold" value="<?php echo htmlspecialchars($freeThreshold); ?>" 
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               min="0" step="100" required>
                        <p class="mt-1 text-xs text-gray-500">Orders above this amount get free delivery</p>
                    </div>

                    <!-- Delivery Times -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-clock text-blue-600"></i> Inside District Delivery Time
                            </label>
                            <input type="text" name="delivery_time_inside" value="<?php echo htmlspecialchars($timeInside); ?>" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                   placeholder="e.g., 2-3 business days" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-clock text-orange-600"></i> Outside District Delivery Time
                            </label>
                            <input type="text" name="delivery_time_outside" value="<?php echo htmlspecialchars($timeOutside); ?>" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                   placeholder="e.g., 3-5 business days" required>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-sm font-bold text-blue-900 mb-3">
                            <i class="bi bi-eye"></i> Preview
                        </h3>
                        <div class="space-y-2 text-sm text-blue-800">
                            <p><strong>Inside <?php echo htmlspecialchars($homeDistrict); ?>:</strong> ৳<?php echo $chargeInside; ?> (<?php echo htmlspecialchars($timeInside); ?>)</p>
                            <p><strong>Outside <?php echo htmlspecialchars($homeDistrict); ?>:</strong> ৳<?php echo $chargeOutside; ?> (<?php echo htmlspecialchars($timeOutside); ?>)</p>
                            <p><strong>Free Delivery:</strong> Orders over ৳<?php echo $freeThreshold; ?></p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-3">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                        <a href="homepage_settings.php" class="bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
