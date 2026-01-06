<?php
require_once 'core/auth.php';
require_once 'core/stock.php';
require_once 'core/db.php';

// Fetch delivery settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings WHERE setting_key IN ('home_district', 'delivery_charge_inside', 'delivery_charge_outside')");
$settingsData = $settingsStmt->fetchAll();
$deliverySettings = [];
foreach($settingsData as $row) {
    $deliverySettings[$row['setting_key']] = $row['setting_value'];
}

// Defaults
$homeDistrict = $deliverySettings['home_district'] ?? 'Jhenaidah';
$chargeInside = $deliverySettings['delivery_charge_inside'] ?? 70;
$chargeOutside = $deliverySettings['delivery_charge_outside'] ?? 150;

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$cartItems = $_SESSION['cart'];
$ids = array_keys($cartItems);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT v.id as variant_id, v.name as variant_name, v.price, v.offer_price, v.product_id,
            p.title, p.slug
     FROM product_variations v
     JOIN products p ON p.id = v.product_id
     WHERE v.id IN ($placeholders)
     UNION ALL
     SELECT v.id as variant_id, v.name as variant_name, v.price, v.offer_price, v.product_id,
            p.title, p.slug
     FROM product_variants_legacy v
     JOIN products p ON p.id = v.product_id
     WHERE v.id IN ($placeholders)"
);
$stmt->execute(array_merge($ids, $ids));
$variants = $stmt->fetchAll();

$total = 0;
foreach ($variants as &$v) {
    $qty = $cartItems[$v['variant_id']] ?? 0;
    $unit = ($v['offer_price'] && $v['offer_price'] > 0) ? $v['offer_price'] : $v['price'];
    $v['qty'] = $qty;
    $v['unit'] = $unit;
    $v['line_total'] = $unit * $qty;
    $total += $v['line_total'];
}

$success = '';
$error = '';
$order_id = null;

// Check if user is logged in for payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        // Save checkout form data to session for later use
        $_SESSION['checkout_data'] = [
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'payment_method' => $_POST['payment_method'] ?? 'COD',
            'transaction_id' => trim($_POST['transaction_id'] ?? '')
        ];
        $_SESSION['checkout_redirect'] = true;
        $error = 'login_required'; // Special error code
    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $division = trim($_POST['division'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $upazila = trim($_POST['upazila'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'COD';
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    if (!$name || !$phone || !$address || !$division || !$district || !$upazila) {
        $error = 'Please fill all required fields.';
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Calculate delivery charge based on district
            $delivery_charge = ($district === $homeDistrict) ? $chargeInside : $chargeOutside;
            $final_total = $total + $delivery_charge;

            $full_address = "Division: $division, District: $district, Upazila: $upazila\nAddress: $address";
            $shipping_address = $name . "\n" . $phone . "\n" . $full_address;
            
            $payment_status = ($payment_method === 'COD') ? 'cod' : 'pending';
            $user_id = $_SESSION['user_id'] ?? null;

            // Update user's default address if logged in
            if ($user_id) {
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET division = ?, district = ?, upazila = ?, address = ? WHERE id = ?");
                $stmtUpdateUser->execute([$division, $district, $upazila, $address, $user_id]);
            }

            $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, status, payment_method, payment_status, transaction_id, total_amount, shipping_address) VALUES (?, 'pending', ?, ?, ?, ?, ?)");
            $stmtOrder->execute([$user_id, $payment_method, $payment_status, $transaction_id, $final_total, $shipping_address]);
            $order_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($variants as $item) {
                $stmtItem->execute([$order_id, $item['product_id'], $item['variant_id'], $item['qty'], $item['unit'], $item['line_total']]);
                adjustStock($item['variant_id'], $item['qty'], 'out', 'online', $order_id, 'checkout');
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to place order: ' . $e->getMessage();
        }
    }
}

// Retrieve saved checkout data if exists
$saved_data = $_SESSION['checkout_data'] ?? [];
$prefill_name = $saved_data['name'] ?? '';
$prefill_phone = $saved_data['phone'] ?? '';
$prefill_address = $saved_data['address'] ?? '';
$prefill_division = $saved_data['division'] ?? '';
$prefill_district = $saved_data['district'] ?? '';
$prefill_upazila = $saved_data['upazila'] ?? '';
$prefill_payment = $saved_data['payment_method'] ?? 'COD';
$prefill_transaction = $saved_data['transaction_id'] ?? '';

// If logged in, try to fetch user data if session data is empty
if (is_logged_in() && empty($prefill_name)) {
    $userId = current_user_id();
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();
    
    if ($user) {
        $prefill_name = $user['name'];
        $prefill_phone = $user['phone'];
        $prefill_address = $user['address'];
        $prefill_division = $user['division'];
        $prefill_district = $user['district'];
        $prefill_upazila = $user['upazila'];
    }
}

// Default to Jhenaidah if no district set
if (empty($prefill_district)) {
    $prefill_district = $homeDistrict;
}
?>
<?php include 'includes/header.php'; ?>

<!-- Modern Checkout Page -->
<div class="min-h-screen bg-gray-50 py-8 md:py-12">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-600 mb-3">
                <a href="index.php" class="hover:text-pink-600 transition">Home</a>
                <i class="bi bi-chevron-right text-xs"></i>
                <a href="cart.php" class="hover:text-pink-600 transition">Cart</a>
                <i class="bi bi-chevron-right text-xs"></i>
                <span class="text-pink-600 font-semibold">Checkout</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Checkout</h1>
            <p class="text-gray-600 mt-2">Complete your order in just a few steps</p>
        </div>

        <!-- Progress Steps -->
        <div class="mb-8 bg-white rounded-xl shadow-sm p-4 md:p-6">
            <div class="flex items-center justify-between max-w-2xl mx-auto">
                <div class="flex flex-col items-center flex-1">
                    <div class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center font-bold mb-2">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <span class="text-xs md:text-sm font-medium text-gray-900">Cart</span>
                </div>
                <div class="flex-1 h-1 bg-pink-600 mx-2"></div>
                <div class="flex flex-col items-center flex-1">
                    <div class="w-10 h-10 rounded-full bg-pink-600 text-white flex items-center justify-center font-bold mb-2">
                        2
                    </div>
                    <span class="text-xs md:text-sm font-medium text-gray-900">Checkout</span>
                </div>
                <div class="flex-1 h-1 bg-gray-200 mx-2"></div>
                <div class="flex flex-col items-center flex-1">
                    <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold mb-2">
                        3
                    </div>
                    <span class="text-xs md:text-sm font-medium text-gray-500">Complete</span>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
        <!-- Success Message -->
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Success Header -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-8 text-center">
                    <div class="w-20 h-20 mx-auto bg-white rounded-full flex items-center justify-center mb-4 animate-bounce">
                        <i class="bi bi-check-circle-fill text-5xl text-green-500"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-white mb-2">অর্ডার সফল হয়েছে! 🎉</h2>
                    <p class="text-green-100 text-lg">আপনার অর্ডারটি সফলভাবে প্রক্রিয়া করা হয়েছে</p>
                </div>

                <!-- Order Details -->
                <div class="p-8">
                    <div class="bg-gray-50 rounded-xl p-6 mb-6">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200">
                            <span class="text-gray-600 font-medium">Order ID</span>
                            <span class="text-2xl font-bold text-pink-600">#<?php echo $order_id; ?></span>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <i class="bi bi-calendar-check text-green-600 text-xl"></i>
                                <div>
                                    <div class="text-sm text-gray-600">অর্ডারের তারিখ</div>
                                    <div class="font-semibold text-gray-900"><?php echo date('d F, Y h:i A'); ?></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="bi bi-truck text-blue-600 text-xl"></i>
                                <div>
                                    <div class="text-sm text-gray-600">ডেলিভারি স্ট্যাটাস</div>
                                    <div class="font-semibold text-gray-900">প্রক্রিয়াধীন</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="bi bi-credit-card text-purple-600 text-xl"></i>
                                <div>
                                    <div class="text-sm text-gray-600">পেমেন্ট মেথড</div>
                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($_POST['payment_method'] ?? 'COD'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Thank You Message -->
                    <div class="bg-gradient-to-br from-pink-50 to-purple-50 rounded-xl p-6 mb-6">
                        <h3 class="font-bold text-gray-900 mb-2 flex items-center gap-2">
                            <i class="bi bi-heart-fill text-pink-600"></i>
                            ধন্যবাদ আপনার অর্ডারের জন্য!
                        </h3>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            আমরা শীঘ্রই আপনার অর্ডার প্রসেসিং শুরু করব। ডেলিভারি সম্পর্কিত যেকোনো আপডেট আপনার ফোন নম্বরে SMS এর মাধ্যমে জানানো হবে।
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="index.php" 
                           class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-pink-600 to-pink-700 text-white rounded-xl hover:from-pink-700 hover:to-pink-800 transition font-semibold shadow-lg hover:shadow-xl">
                            <i class="bi bi-house-fill"></i> 
                            হোমে ফিরুন
                        </a>
                        <a href="category.php" 
                           class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-pink-600 border-2 border-pink-600 rounded-xl hover:bg-pink-50 transition font-semibold">
                            <i class="bi bi-bag-fill"></i> 
                            শপিং চালিয়ে যান
                        </a>
                    </div>

                    <!-- Contact Info -->
                    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                        <p class="text-sm text-gray-600 mb-2">সহায়তার জন্য যোগাযোগ করুন:</p>
                        <div class="flex items-center justify-center gap-4 text-sm">
                            <a href="tel:09678300400" class="text-pink-600 hover:text-pink-700 font-semibold flex items-center gap-1">
                                <i class="bi bi-telephone-fill"></i>
                                09678-300400
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Show checkout form only if order not placed -->

        <?php if ($error === 'login_required'): ?>
        <!-- Login Required Message with Modal Trigger -->
        <div class="mb-8 bg-yellow-50 border-l-4 border-yellow-500 rounded-lg p-6 shadow-sm">
            <div class="flex items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill text-yellow-600 text-2xl flex-shrink-0"></i>
                <div class="flex-1">
                    <h3 class="text-yellow-900 font-bold mb-2 text-lg">লগইন প্রয়োজন</h3>
                    <p class="text-yellow-800 mb-4">অর্ডার সম্পন্ন করতে আপনাকে প্রথমে লগইন করতে হবে। আপনার তথ্য সংরক্ষিত রয়েছে।</p>
                    <div class="flex gap-3">
                        <button onclick="openAuthModal('loginModal')" 
                                class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-pink-600 to-pink-700 text-white rounded-lg hover:from-pink-700 hover:to-pink-800 transition font-semibold shadow-lg">
                            <i class="bi bi-box-arrow-in-right"></i>
                            লগইন করুন
                        </button>
                        <button onclick="openAuthModal('registerModal')" 
                                class="inline-flex items-center gap-2 px-6 py-3 bg-white text-pink-600 border-2 border-pink-600 rounded-lg hover:bg-pink-50 transition font-semibold">
                            <i class="bi bi-person-plus-fill"></i>
                            নতুন একাউন্ট তৈরি করুন
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error && $error !== 'login_required'): ?>
        <!-- Error Message -->
        <div class="mb-8 bg-red-50 border-l-4 border-red-500 rounded-lg p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill text-red-600 text-xl flex-shrink-0"></i>
                <div>
                    <h3 class="text-red-900 font-bold mb-1">Error</h3>
                    <p class="text-red-800"><?php echo $error; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="grid lg:grid-cols-3 gap-6 md:gap-8">
            <!-- Left Column - Checkout Form -->
            <div class="lg:col-span-2">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    
                    <!-- Shipping Information -->
                    <div class="bg-white rounded-xl shadow-sm p-6 md:p-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-lg bg-pink-100 text-pink-600 flex items-center justify-center">
                                <i class="bi bi-truck text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Shipping Information</h2>
                                <p class="text-sm text-gray-600">Enter your delivery details</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="name" 
                                       required
                                       value="<?php echo htmlspecialchars($prefill_name); ?>"
                                       placeholder="Enter your full name"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition-colors text-gray-900">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Phone Number <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       required
                                       value="<?php echo htmlspecialchars($prefill_phone); ?>"
                                       placeholder="01XXXXXXXXX"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition-colors text-gray-900">
                            </div>

                            <!-- Location Dropdowns -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Division <span class="text-red-500">*</span>
                                    </label>
                                    <select name="division" id="division" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition-colors text-gray-900">
                                        <option value="">Select Division</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        District <span class="text-red-500">*</span>
                                    </label>
                                    <select name="district" id="district" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition-colors text-gray-900" disabled>
                                        <option value="">Select District</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Upazila <span class="text-red-500">*</span>
                                    </label>
                                    <select name="upazila" id="upazila" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition-colors text-gray-900" disabled>
                                        <option value="">Select Upazila</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Detailed Address <span class="text-red-500">*</span>
                                </label>
                                <textarea name="address" 
                                          rows="3" 
                                          required
                                          placeholder="House/Flat, Road, Area"
                                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition-colors resize-none text-gray-900"><?php echo htmlspecialchars($prefill_address); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-white rounded-xl shadow-sm p-6 md:p-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i class="bi bi-credit-card text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Payment Method</h2>
                                <p class="text-sm text-gray-600">Select your preferred payment option</p>
                            </div>
                        </div>

                        <div class="space-y-3" id="paymentMethods">
                            <label class="flex items-center gap-4 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-pink-500 transition-all group">
                                <input type="radio" name="payment_method" value="COD" <?php echo ($prefill_payment === 'COD') ? 'checked' : ''; ?> class="w-5 h-5 text-pink-600 focus:ring-pink-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900 group-hover:text-pink-600 transition">Cash on Delivery</div>
                                    <div class="text-sm text-gray-600">Pay when you receive your order</div>
                                </div>
                                <i class="bi bi-cash-stack text-2xl text-gray-400 group-hover:text-pink-600 transition"></i>
                            </label>

                            <label class="flex items-center gap-4 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-pink-500 transition-all group">
                                <input type="radio" name="payment_method" value="bKash" <?php echo ($prefill_payment === 'bKash') ? 'checked' : ''; ?> class="w-5 h-5 text-pink-600 focus:ring-pink-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900 group-hover:text-pink-600 transition">bKash</div>
                                    <div class="text-sm text-gray-600">Pay via bKash mobile banking</div>
                                </div>
                                <div class="text-2xl font-bold text-pink-500">bKash</div>
                            </label>
                            <div id="bkashInstructions" class="hidden ml-9 mb-3 p-4 bg-pink-50 border-l-4 border-pink-500 rounded-lg">
                                <h4 class="font-bold text-pink-900 mb-3 flex items-center gap-2">
                                    <i class="bi bi-info-circle-fill"></i>
                                    bKash Payment Instructions
                                </h4>
                                <ol class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-pink-600 flex-shrink-0">1.</span>
                                        <span>Go to your bKash Mobile Menu by dialing *247#</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-pink-600 flex-shrink-0">2.</span>
                                        <span>Choose "Send Money"</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-pink-600 flex-shrink-0">3.</span>
                                        <span>Enter our bKash number: <strong class="font-bold text-pink-700">01712-345678</strong></span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-pink-600 flex-shrink-0">4.</span>
                                        <span>Enter amount: <strong class="font-bold text-pink-700">৳<?php echo number_format($total); ?></strong></span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-pink-600 flex-shrink-0">5.</span>
                                        <span>Enter your bKash PIN and confirm</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-pink-600 flex-shrink-0">6.</span>
                                        <span>Save the Transaction ID and enter it below</span>
                                    </li>
                                </ol>
                            </div>

                            <label class="flex items-center gap-4 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-pink-500 transition-all group">
                                <input type="radio" name="payment_method" value="Nagad" <?php echo ($prefill_payment === 'Nagad') ? 'checked' : ''; ?> class="w-5 h-5 text-pink-600 focus:ring-pink-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900 group-hover:text-pink-600 transition">Nagad</div>
                                    <div class="text-sm text-gray-600">Pay via Nagad mobile banking</div>
                                </div>
                                <div class="text-2xl font-bold text-orange-500">Nagad</div>
                            </label>
                            <div id="nagadInstructions" class="hidden ml-9 mb-3 p-4 bg-orange-50 border-l-4 border-orange-500 rounded-lg">
                                <h4 class="font-bold text-orange-900 mb-3 flex items-center gap-2">
                                    <i class="bi bi-info-circle-fill"></i>
                                    Nagad Payment Instructions
                                </h4>
                                <ol class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-orange-600 flex-shrink-0">1.</span>
                                        <span>Go to your Nagad Mobile Menu by dialing *167#</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-orange-600 flex-shrink-0">2.</span>
                                        <span>Choose "Send Money"</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-orange-600 flex-shrink-0">3.</span>
                                        <span>Enter our Nagad number: <strong class="font-bold text-orange-700">01812-345678</strong></span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-orange-600 flex-shrink-0">4.</span>
                                        <span>Enter amount: <strong class="font-bold text-orange-700">৳<?php echo number_format($total); ?></strong></span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-orange-600 flex-shrink-0">5.</span>
                                        <span>Enter your Nagad PIN and confirm</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-orange-600 flex-shrink-0">6.</span>
                                        <span>Save the Transaction ID and enter it below</span>
                                    </li>
                                </ol>
                            </div>

                            <label class="flex items-center gap-4 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-pink-500 transition-all group">
                                <input type="radio" name="payment_method" value="Rocket" <?php echo ($prefill_payment === 'Rocket') ? 'checked' : ''; ?> class="w-5 h-5 text-pink-600 focus:ring-pink-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900 group-hover:text-pink-600 transition">Rocket</div>
                                    <div class="text-sm text-gray-600">Pay via Rocket mobile banking</div>
                                </div>
                                <div class="text-2xl font-bold text-purple-600">Rocket</div>
                            </label>
                            <div id="rocketInstructions" class="hidden ml-9 mb-3 p-4 bg-purple-50 border-l-4 border-purple-500 rounded-lg">
                                <h4 class="font-bold text-purple-900 mb-3 flex items-center gap-2">
                                    <i class="bi bi-info-circle-fill"></i>
                                    Rocket Payment Instructions
                                </h4>
                                <ol class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-purple-600 flex-shrink-0">1.</span>
                                        <span>Go to your Rocket Mobile Menu by dialing *322#</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-purple-600 flex-shrink-0">2.</span>
                                        <span>Choose "Send Money"</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-purple-600 flex-shrink-0">3.</span>
                                        <span>Enter our Rocket number: <strong class="font-bold text-purple-700">01912-345678</strong></span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-purple-600 flex-shrink-0">4.</span>
                                        <span>Enter amount: <strong class="font-bold text-purple-700">৳<?php echo number_format($total); ?></strong></span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-purple-600 flex-shrink-0">5.</span>
                                        <span>Enter your Rocket PIN and confirm</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-bold text-purple-600 flex-shrink-0">6.</span>
                                        <span>Save the Transaction ID and enter it below</span>
                                    </li>
                                </ol>
                            </div>

                            <label class="flex items-center gap-4 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-pink-500 transition-all group">
                                <input type="radio" name="payment_method" value="Bank" <?php echo ($prefill_payment === 'Bank') ? 'checked' : ''; ?> class="w-5 h-5 text-pink-600 focus:ring-pink-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900 group-hover:text-pink-600 transition">Bank Transfer</div>
                                    <div class="text-sm text-gray-600">Direct bank transfer</div>
                                </div>
                                <i class="bi bi-bank text-2xl text-blue-600"></i>
                            </label>
                            <div id="bankInstructions" class="hidden ml-9 mb-3 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-lg">
                                <h4 class="font-bold text-blue-900 mb-3 flex items-center gap-2">
                                    <i class="bi bi-info-circle-fill"></i>
                                    Bank Transfer Instructions
                                </h4>
                                <div class="space-y-3 text-sm text-gray-700">
                                    <div class="bg-white rounded-lg p-3 border border-blue-200">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="text-gray-600">Bank Name:</div>
                                            <div class="font-bold text-blue-900">Dutch Bangla Bank</div>
                                            <div class="text-gray-600">Account Name:</div>
                                            <div class="font-bold text-blue-900">TechHat Limited</div>
                                            <div class="text-gray-600">Account Number:</div>
                                            <div class="font-bold text-blue-900">123-456-789012</div>
                                            <div class="text-gray-600">Branch:</div>
                                            <div class="font-bold text-blue-900">Dhanmondi, Dhaka</div>
                                            <div class="text-gray-600">Amount:</div>
                                            <div class="font-bold text-blue-900">৳<?php echo number_format($total); ?></div>
                                        </div>
                                    </div>
                                    <p class="text-gray-600">
                                        <i class="bi bi-exclamation-circle text-blue-600"></i>
                                        After transferring, please enter the transaction reference number below.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div id="transactionIdField" class="mt-4 <?php echo ($prefill_payment !== 'COD' && $prefill_payment) ? '' : 'hidden'; ?>">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Transaction ID <span class="text-gray-500">(Optional)</span>
                            </label>
                            <input type="text" 
                                   name="transaction_id" 
                                   value="<?php echo htmlspecialchars($prefill_transaction); ?>"
                                   placeholder="Enter transaction/reference ID"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-pink-500 focus:outline-none transition-colors text-gray-900">
                            <p class="text-xs text-gray-500 mt-2">If you've already made the payment, enter your transaction ID</p>
                        </div>
                    </div>

                    <!-- Submit Button - Sticky -->
                    <div class="sticky bottom-0 bg-white pt-4 pb-6 -mx-6 md:-mx-8 px-6 md:px-8 border-t-2 border-gray-100 shadow-lg z-10">
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-pink-600 to-pink-700 hover:from-pink-700 hover:to-pink-800 text-white font-bold py-4 rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-3 text-lg">
                            <i class="bi bi-check-circle-fill text-2xl"></i>
                            <span id="submitBtnText">Place Order - ৳<?php echo number_format($total); ?></span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm p-6 sticky top-24">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                            <i class="bi bi-receipt text-xl"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900">Order Summary</h2>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4 mb-6 max-h-96 overflow-y-auto">
                        <?php foreach ($variants as $item): ?>
                        <div class="flex gap-3 pb-4 border-b border-gray-100">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 text-sm mb-1">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </h4>
                                <p class="text-xs text-gray-600 mb-2">
                                    <?php echo htmlspecialchars($item['variant_name']); ?>
                                </p>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">Qty:</span>
                                    <span class="px-2 py-1 bg-gray-100 rounded text-xs font-semibold text-gray-900">
                                        <?php echo $item['qty']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-pink-600">
                                    ৳<?php echo number_format($item['line_total']); ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    ৳<?php echo number_format($item['unit']); ?> each
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Price Breakdown -->
                    <div class="space-y-3 mb-6 pt-4 border-t-2 border-gray-200">
                        <div class="flex justify-between text-gray-700">
                            <span>Subtotal</span>
                            <span class="font-semibold">৳<?php echo number_format($total); ?></span>
                        </div>
                        <div class="flex justify-between text-gray-700">
                            <span>Shipping</span>
                            <span class="font-semibold text-green-600" id="shippingCostDisplay">Calculating...</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold text-gray-900 pt-3 border-t-2 border-gray-200">
                            <span>Total</span>
                            <span class="text-pink-600" id="totalCostDisplay">৳<?php echo number_format($total); ?></span>
                        </div>
                    </div>

                    <!-- Delivery Date -->
                    <div class="bg-blue-50 rounded-lg p-4 mb-6 flex items-center gap-3">
                        <i class="bi bi-calendar-check text-blue-600 text-xl"></i>
                        <div>
                            <p class="text-xs text-blue-600 font-bold uppercase">Estimated Delivery</p>
                            <p class="text-sm font-semibold text-gray-900" id="deliveryDateDisplay">
                                <?php echo date('d M', strtotime('+3 days')) . ' - ' . date('d M', strtotime('+5 days')); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Trust Badges -->
                    <div class="bg-gradient-to-br from-pink-50 to-purple-50 rounded-lg p-4 space-y-3">
                        <div class="flex items-center gap-3 text-sm">
                            <i class="bi bi-shield-check text-green-600 text-xl"></i>
                            <span class="text-gray-700">Secure Checkout</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <i class="bi bi-truck text-blue-600 text-xl"></i>
                            <span class="text-gray-700">Fast Delivery</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <i class="bi bi-arrow-clockwise text-purple-600 text-xl"></i>
                            <span class="text-gray-700">Easy Returns</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="assets/js/bd-locations.js"></script>
<script>
    // Delivery Settings from PHP
    const homeDistrict = "<?php echo htmlspecialchars($homeDistrict); ?>";
    const chargeInside = <?php echo (int)$chargeInside; ?>;
    const chargeOutside = <?php echo (int)$chargeOutside; ?>;
    const subtotal = <?php echo (float)$total; ?>;

    // Pre-filled values
    const prefillDivision = "<?php echo htmlspecialchars($prefill_division); ?>";
    const prefillDistrict = "<?php echo htmlspecialchars($prefill_district); ?>";
    const prefillUpazila = "<?php echo htmlspecialchars($prefill_upazila); ?>";

    document.addEventListener('DOMContentLoaded', function() {
        const divisionSelect = document.getElementById('division');
        const districtSelect = document.getElementById('district');
        const upazilaSelect = document.getElementById('upazila');
        const shippingDisplay = document.getElementById('shippingCostDisplay');
        const totalDisplay = document.getElementById('totalCostDisplay');
        const submitBtnText = document.getElementById('submitBtnText');

        // Initialize Locations
        if (typeof bdLocations !== 'undefined') {
            // Populate Divisions
            Object.keys(bdLocations).sort().forEach(div => {
                const option = document.createElement('option');
                option.value = div;
                option.textContent = div;
                if (div === prefillDivision) option.selected = true;
                divisionSelect.appendChild(option);
            });

            // Handle Division Change
            divisionSelect.addEventListener('change', function() {
                const division = this.value;
                districtSelect.innerHTML = '<option value="">Select District</option>';
                upazilaSelect.innerHTML = '<option value="">Select Upazila</option>';
                upazilaSelect.disabled = true;
                
                if (division) {
                    populateDistricts(division);
                    districtSelect.disabled = false;
                } else {
                    districtSelect.disabled = true;
                    updateShippingCost(null); // Reset shipping
                }
            });

            // Handle District Change
            districtSelect.addEventListener('change', function() {
                const division = divisionSelect.value;
                const district = this.value;
                upazilaSelect.innerHTML = '<option value="">Select Upazila</option>';
                
                if (district) {
                    populateUpazilas(division, district);
                    upazilaSelect.disabled = false;
                    updateShippingCost(district);
                } else {
                    upazilaSelect.disabled = true;
                    updateShippingCost(null);
                }
            });

            // Initial Population if data exists
            if (prefillDivision) {
                populateDistricts(prefillDivision);
                districtSelect.disabled = false;
                
                if (prefillDistrict) {
                    // Select district
                    Array.from(districtSelect.options).forEach(opt => {
                        if (opt.value === prefillDistrict) opt.selected = true;
                    });
                    
                    populateUpazilas(prefillDivision, prefillDistrict);
                    upazilaSelect.disabled = false;
                    
                    if (prefillUpazila) {
                        Array.from(upazilaSelect.options).forEach(opt => {
                            if (opt.value === prefillUpazila) opt.selected = true;
                        });
                    }
                    
                    updateShippingCost(prefillDistrict);
                } else {
                    // Default to Jhenaidah logic if no district selected yet?
                    // Actually, if prefillDistrict is empty, we might want to default to Home District visually
                    // But user needs to select it explicitly or we auto-select it.
                    // Requirement: "If not set, show default Jhenaidah location"
                    // Let's auto-select Home District if nothing else is selected
                    // But we need to find which Division it belongs to first.
                    // For simplicity, we only auto-calculate cost based on default if nothing selected.
                    updateShippingCost(homeDistrict); 
                }
            } else {
                // No prefill data - Default state
                // Try to find homeDistrict in the locations to auto-select it
                let homeDiv = null;
                Object.keys(bdLocations).forEach(div => {
                    if (bdLocations[div][homeDistrict]) {
                        homeDiv = div;
                    }
                });

                if (homeDiv) {
                    // Auto-select Home Location
                    Array.from(divisionSelect.options).forEach(opt => {
                        if (opt.value === homeDiv) opt.selected = true;
                    });
                    populateDistricts(homeDiv);
                    districtSelect.disabled = false;
                    
                    Array.from(districtSelect.options).forEach(opt => {
                        if (opt.value === homeDistrict) opt.selected = true;
                    });
                    
                    populateUpazilas(homeDiv, homeDistrict);
                    upazilaSelect.disabled = false;
                    
                    // Try to select "Sadar" if exists
                    Array.from(upazilaSelect.options).forEach(opt => {
                        if (opt.value.includes('Sadar')) opt.selected = true;
                    });
                }
                
                updateShippingCost(homeDistrict);
            }
        }

        function populateDistricts(division) {
            if (!bdLocations[division]) return;
            Object.keys(bdLocations[division]).sort().forEach(dist => {
                const option = document.createElement('option');
                option.value = dist;
                option.textContent = dist;
                districtSelect.appendChild(option);
            });
        }

        function populateUpazilas(division, district) {
            if (!bdLocations[division] || !bdLocations[division][district]) return;
            bdLocations[division][district].sort().forEach(upz => {
                const option = document.createElement('option');
                option.value = upz;
                option.textContent = upz;
                upazilaSelect.appendChild(option);
            });
        }

        function updateShippingCost(district) {
            let cost = 0;
            // If district is null (not selected), use default home district logic?
            // User said: "If location not set, show default Jhenaidah location"
            // This implies the cost should be calculated as if they are in Jhenaidah until they change it.
            
            const targetDistrict = district || homeDistrict;
            
            if (targetDistrict === homeDistrict) {
                cost = chargeInside;
            } else {
                cost = chargeOutside;
            }

            shippingDisplay.textContent = '৳' + cost;
            const total = subtotal + cost;
            const formattedTotal = total.toLocaleString('en-BD');
            
            totalDisplay.textContent = '৳' + formattedTotal;
            submitBtnText.textContent = 'Place Order - ৳' + formattedTotal;
        }
    });

    // Show/hide transaction ID field and instructions based on payment method
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const transactionField = document.getElementById('transactionIdField');
            const bkashInstructions = document.getElementById('bkashInstructions');
            const nagadInstructions = document.getElementById('nagadInstructions');
            const rocketInstructions = document.getElementById('rocketInstructions');
            const bankInstructions = document.getElementById('bankInstructions');
            
            // Hide all instructions first
            bkashInstructions.classList.add('hidden');
            nagadInstructions.classList.add('hidden');
            rocketInstructions.classList.add('hidden');
            bankInstructions.classList.add('hidden');
            
            // Show/hide transaction field and respective instructions
            if (this.value === 'COD') {
                transactionField.classList.add('hidden');
            } else {
                transactionField.classList.remove('hidden');
                
                // Show instructions based on selected method
                if (this.value === 'bKash') {
                    bkashInstructions.classList.remove('hidden');
                } else if (this.value === 'Nagad') {
                    nagadInstructions.classList.remove('hidden');
                } else if (this.value === 'Rocket') {
                    rocketInstructions.classList.remove('hidden');
                } else if (this.value === 'Bank') {
                    bankInstructions.classList.remove('hidden');
                }
            }
        });
    });

    // On page load, show instructions for pre-selected payment method
    document.addEventListener('DOMContentLoaded', function() {
        const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
        if (selectedPayment && selectedPayment.value !== 'COD') {
            const transactionField = document.getElementById('transactionIdField');
            transactionField.classList.remove('hidden');
            
            // Show respective instructions
            if (selectedPayment.value === 'bKash') {
                document.getElementById('bkashInstructions').classList.remove('hidden');
            } else if (selectedPayment.value === 'Nagad') {
                document.getElementById('nagadInstructions').classList.remove('hidden');
            } else if (selectedPayment.value === 'Rocket') {
                document.getElementById('rocketInstructions').classList.remove('hidden');
            } else if (selectedPayment.value === 'Bank') {
                document.getElementById('bankInstructions').classList.remove('hidden');
            }
        }
    });
</script>

</body>
</html>
