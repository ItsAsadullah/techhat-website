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
            'cod_available' => isset($_POST['cod_available']) ? '1' : '0',
            'bkash_available' => isset($_POST['bkash_available']) ? '1' : '0',
            'nagad_available' => isset($_POST['nagad_available']) ? '1' : '0',
            'rocket_available' => isset($_POST['rocket_available']) ? '1' : '0',
            'card_payment_available' => isset($_POST['card_payment_available']) ? '1' : '0',
            'bkash_number' => $_POST['bkash_number'] ?? '',
            'nagad_number' => $_POST['nagad_number'] ?? '',
            'rocket_number' => $_POST['rocket_number'] ?? '',
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $message = "Payment settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings WHERE setting_key IN ('cod_available', 'bkash_available', 'nagad_available', 'rocket_available', 'card_payment_available', 'bkash_number', 'nagad_number', 'rocket_number')");
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
    <title>Payment Settings - Admin Panel</title>
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
                            <i class="bi bi-credit-card text-purple-600"></i>
                            Payment Settings
                        </h1>
                    </div>
                    <p class="text-gray-600 ml-10">Configure payment gateways and accepted payment methods</p>
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
                    <!-- Payment Methods -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-wallet2 text-purple-600"></i>
                            Available Payment Methods
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-cash text-2xl text-green-600"></i>
                                    <div>
                                        <p class="font-medium text-gray-900">Cash on Delivery (COD)</p>
                                        <p class="text-xs text-gray-500">Pay when you receive</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="cod_available" class="sr-only peer" <?php echo getSetting($settings, 'cod_available', '1') == '1' ? 'checked' : ''; ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-phone text-2xl text-pink-600"></i>
                                    <div>
                                        <p class="font-medium text-gray-900">bKash</p>
                                        <p class="text-xs text-gray-500">Mobile financial service</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="bkash_available" class="sr-only peer" <?php echo getSetting($settings, 'bkash_available', '0') == '1' ? 'checked' : ''; ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-phone text-2xl text-orange-600"></i>
                                    <div>
                                        <p class="font-medium text-gray-900">Nagad</p>
                                        <p class="text-xs text-gray-500">Mobile financial service</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="nagad_available" class="sr-only peer" <?php echo getSetting($settings, 'nagad_available', '0') == '1' ? 'checked' : ''; ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-phone text-2xl text-purple-600"></i>
                                    <div>
                                        <p class="font-medium text-gray-900">Rocket</p>
                                        <p class="text-xs text-gray-500">Mobile financial service</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="rocket_available" class="sr-only peer" <?php echo getSetting($settings, 'rocket_available', '0') == '1' ? 'checked' : ''; ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-credit-card text-2xl text-blue-600"></i>
                                    <div>
                                        <p class="font-medium text-gray-900">Card Payment</p>
                                        <p class="text-xs text-gray-500">Credit/Debit card</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="card_payment_available" class="sr-only peer" <?php echo getSetting($settings, 'card_payment_available', '0') == '1' ? 'checked' : ''; ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Banking Numbers -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-phone text-purple-600"></i>
                            Mobile Banking Account Numbers
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-phone text-pink-600"></i> bKash Number
                                </label>
                                <input type="text" name="bkash_number" value="<?php echo htmlspecialchars(getSetting($settings, 'bkash_number', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="01XXXXXXXXX">
                                <p class="mt-1 text-xs text-gray-500">Your bKash merchant or personal number</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-phone text-orange-600"></i> Nagad Number
                                </label>
                                <input type="text" name="nagad_number" value="<?php echo htmlspecialchars(getSetting($settings, 'nagad_number', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="01XXXXXXXXX">
                                <p class="mt-1 text-xs text-gray-500">Your Nagad merchant or personal number</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-phone text-purple-600"></i> Rocket Number
                                </label>
                                <input type="text" name="rocket_number" value="<?php echo htmlspecialchars(getSetting($settings, 'rocket_number', '')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="01XXXXXXXXX">
                                <p class="mt-1 text-xs text-gray-500">Your Rocket account number</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-3">
                        <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition-colors flex items-center gap-2">
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
