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
            'return_days' => $_POST['return_days'],
            'warranty_policy' => $_POST['warranty_policy'] ?? '',
            'return_policy_text' => $_POST['return_policy_text'] ?? '',
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $message = "Return & warranty settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings WHERE setting_key IN ('return_days', 'warranty_policy', 'return_policy_text')");
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
    <title>Return & Warranty Settings - Admin Panel</title>
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
                            <i class="bi bi-arrow-counterclockwise text-red-600"></i>
                            Return & Warranty Settings
                        </h1>
                    </div>
                    <p class="text-gray-600 ml-10">Manage return period, warranty terms, and policies</p>
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
                                    <i class="bi bi-calendar-check text-red-600"></i> Return Period (Days) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="return_days" value="<?php echo htmlspecialchars(getSetting($settings, 'return_days', '14')); ?>" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       min="0" step="1" required>
                                <p class="mt-1 text-xs text-gray-500">Number of days customers can return products</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-file-text text-red-600"></i> Return Policy Details
                                </label>
                                <textarea name="return_policy_text" rows="8" 
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                          placeholder="Enter detailed return policy, conditions, and procedures..."><?php echo htmlspecialchars(getSetting($settings, 'return_policy_text', '')); ?></textarea>
                                <p class="mt-1 text-xs text-gray-500">Detailed return policy shown in product pages and modals</p>
                                
                                <!-- Default Examples -->
                                <details class="mt-2">
                                    <summary class="text-xs text-blue-600 cursor-pointer hover:text-blue-800 font-medium">
                                        <i class="bi bi-info-circle"></i> Click to see example return policy
                                    </summary>
                                    <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded text-xs text-gray-700 space-y-1">
                                        <p><strong>Return Conditions:</strong></p>
                                        <p>• Product must be unused, unwashed, and in original condition</p>
                                        <p>• All tags, labels, and packaging must be intact</p>
                                        <p>• Original invoice/receipt required for returns</p>
                                        <p>• Product must not show any signs of use or damage</p>
                                        <p><br><strong>Non-Returnable Items:</strong></p>
                                        <p>• Undergarments, socks, and personal care items</p>
                                        <p>• Opened software, games, or digital products</p>
                                        <p>• Customized or personalized items</p>
                                        <p>• Items damaged due to misuse or negligence</p>
                                        <p><br><strong>Return Process:</strong></p>
                                        <p>• Contact customer support within the return period</p>
                                        <p>• Provide order number and reason for return</p>
                                        <p>• Ship item back with original packaging</p>
                                        <p>• Refund processed within 7-10 business days after inspection</p>
                                    </div>
                                </details>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="bi bi-shield-check text-red-600"></i> Warranty Policy Details
                                </label>
                                <textarea name="warranty_policy" rows="8" 
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                          placeholder="Enter warranty policy details, terms and conditions..."><?php echo htmlspecialchars(getSetting($settings, 'warranty_policy', '')); ?></textarea>
                                <p class="mt-1 text-xs text-gray-500">General warranty policy information (product-specific warranty is set per product)</p>
                                
                                <!-- Default Examples -->
                                <details class="mt-2">
                                    <summary class="text-xs text-blue-600 cursor-pointer hover:text-blue-800 font-medium">
                                        <i class="bi bi-info-circle"></i> Click to see example warranty policy
                                    </summary>
                                    <div class="mt-2 p-3 bg-green-50 border border-green-200 rounded text-xs text-gray-700 space-y-1">
                                        <p><strong>Warranty Coverage:</strong></p>
                                        <p>• Manufacturing defects covered as per manufacturer's warranty</p>
                                        <p>• Warranty period varies by product and manufacturer</p>
                                        <p>• Original purchase receipt required for warranty claims</p>
                                        <p>• Warranty covers repair or replacement at manufacturer's discretion</p>
                                        <p><br><strong>Warranty Exclusions:</strong></p>
                                        <p>• Physical damage, water damage, or accident</p>
                                        <p>• Unauthorized repairs or modifications</p>
                                        <p>• Normal wear and tear</p>
                                        <p>• Misuse, abuse, or improper installation</p>
                                        <p>• Consumable items (batteries, bulbs, filters)</p>
                                        <p><br><strong>Claiming Warranty:</strong></p>
                                        <p>• Contact our support team with order details</p>
                                        <p>• Provide photos/videos of the defect</p>
                                        <p>• Ship defective item to our service center (if required)</p>
                                        <p>• Repair/replacement completed within 15-30 business days</p>
                                        <p>• All products are 100% genuine with authorized warranty</p>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="bi bi-info-circle-fill text-blue-600 text-xl mt-0.5"></i>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-1">Important Notes:</p>
                                <ul class="list-disc ml-4 space-y-1">
                                    <li>Return period applies from the date of delivery</li>
                                    <li>Clearly specify conditions for return eligibility</li>
                                    <li>Individual product warranties can be set in product edit page</li>
                                    <li>These policies are displayed in product detail pages</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-3">
                        <button type="submit" class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors flex items-center gap-2">
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
