<?php
require_once 'core/auth.php';
require_once 'core/db.php';

require_login();

$userId = current_user_id();
$message = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $division = trim($_POST['division'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $upazila = trim($_POST['upazila'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($name)) {
        $error = 'Name is required.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, division = ?, district = ?, upazila = ?, address = ? WHERE id = ?");
        if ($stmt->execute([$name, $phone, $division, $district, $upazila, $address, $userId])) {
            $message = 'Profile updated successfully.';
            // Update session name if needed
            $_SESSION['user_name'] = $name;
        } else {
            $error = 'Failed to update profile.';
        }
    }
}

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userPass = $stmt->fetchColumn();
        
        if (password_verify($current_password, $userPass)) {
            $newHash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$newHash, $userId])) {
                $message = 'Password updated successfully.';
            } else {
                $error = 'Failed to update password.';
            }
        } else {
            $error = 'Incorrect current password.';
        }
    }
}

// Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch Orders
$stmtOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmtOrders->execute([$userId]);
$orders = $stmtOrders->fetchAll();

// Fetch Wishlist
$wishlist = [];
try {
    $stmtWishlist = $pdo->prepare("
        SELECT w.*, p.title, p.slug, p.id as product_id,
        (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as thumb,
        (SELECT MIN(price) FROM product_variants WHERE product_id = p.id) as price
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
    ");
    $stmtWishlist->execute([$userId]);
    $wishlist = $stmtWishlist->fetchAll();
} catch (Exception $e) {
    // Table might not exist, ignore
}

$view = $_GET['view'] ?? 'overview';

require_once 'includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="index.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <i class="bi bi-house-door-fill mr-2"></i>
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="bi bi-chevron-right text-gray-400"></i>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">My Account</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xl font-bold">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></h2>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                    </div>
                    <nav class="p-2">
                        <a href="?view=overview" class="flex items-center px-4 py-3 text-sm font-medium rounded-md <?php echo $view === 'overview' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <i class="bi bi-grid-1x2-fill mr-3 text-lg"></i>
                            Overview
                        </a>
                        <a href="?view=orders" class="flex items-center px-4 py-3 text-sm font-medium rounded-md <?php echo $view === 'orders' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <i class="bi bi-box-seam-fill mr-3 text-lg"></i>
                            My Orders
                        </a>
                        <a href="?view=wishlist" class="flex items-center px-4 py-3 text-sm font-medium rounded-md <?php echo $view === 'wishlist' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <i class="bi bi-heart-fill mr-3 text-lg"></i>
                            Wishlist
                        </a>
                        <a href="?view=profile" class="flex items-center px-4 py-3 text-sm font-medium rounded-md <?php echo $view === 'profile' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <i class="bi bi-person-fill mr-3 text-lg"></i>
                            Profile & Billing Address
                        </a>
                        <a href="logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-red-600 hover:bg-red-50">
                            <i class="bi bi-box-arrow-right mr-3 text-lg"></i>
                            Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Overview View -->
                <?php if ($view === 'overview'): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Total Orders</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count($orders); ?></p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full text-blue-600">
                                    <i class="bi bi-bag-check-fill text-xl"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-pink-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Wishlist Items</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count($wishlist); ?></p>
                                </div>
                                <div class="p-3 bg-pink-100 rounded-full text-pink-600">
                                    <i class="bi bi-heart-fill text-xl"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Cart Items</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?></p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full text-green-600">
                                    <i class="bi bi-cart-fill text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Recent Orders</h3>
                            <a href="?view=orders" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No orders found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $order['id']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                            echo match($order['status']) {
                                                                'delivered' => 'bg-green-100 text-green-800',
                                                                'processing' => 'bg-blue-100 text-blue-800',
                                                                'cancelled' => 'bg-red-100 text-red-800',
                                                                default => 'bg-yellow-100 text-yellow-800'
                                                            };
                                                        ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="order_view.php?id=<?php echo $order['id']; ?>" class="text-blue-600 hover:text-blue-900">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Orders View -->
                <?php if ($view === 'orders'): ?>
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">My Orders</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No orders found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $order['id']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                            echo match($order['status']) {
                                                                'delivered' => 'bg-green-100 text-green-800',
                                                                'processing' => 'bg-blue-100 text-blue-800',
                                                                'cancelled' => 'bg-red-100 text-red-800',
                                                                default => 'bg-yellow-100 text-yellow-800'
                                                            };
                                                        ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ucfirst($order['payment_status']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="order_view.php?id=<?php echo $order['id']; ?>" class="text-blue-600 hover:text-blue-900">View Details</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Wishlist View -->
                <?php if ($view === 'wishlist'): ?>
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">My Wishlist</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($wishlist)): ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-4">
                                        <i class="bi bi-heart text-6xl"></i>
                                    </div>
                                    <p class="text-gray-500 text-lg">Your wishlist is empty.</p>
                                    <a href="index.php" class="mt-4 inline-block text-blue-600 hover:underline">Start Shopping</a>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($wishlist as $item): ?>
                                        <div class="border rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                                            <div class="aspect-w-1 aspect-h-1 bg-gray-200">
                                                <img src="<?php echo !empty($item['thumb']) ? htmlspecialchars($item['thumb']) : 'assets/images/placeholder.png'; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                                     class="w-full h-48 object-cover">
                                            </div>
                                            <div class="p-4">
                                                <h4 class="text-lg font-medium text-gray-900 truncate">
                                                    <a href="product.php?slug=<?php echo $item['slug']; ?>"><?php echo htmlspecialchars($item['title']); ?></a>
                                                </h4>
                                                <p class="text-blue-600 font-bold mt-2">$<?php echo number_format($item['price'], 2); ?></p>
                                                <div class="mt-4 flex gap-2">
                                                    <a href="product.php?slug=<?php echo $item['slug']; ?>" class="flex-1 bg-blue-600 text-white text-center py-2 rounded hover:bg-blue-700 text-sm">
                                                        View
                                                    </a>
                                                    <!-- Remove from wishlist logic could be added here -->
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Profile View -->
                <?php if ($view === 'profile'): ?>
                    <div class="space-y-6">
                        <!-- Personal Info -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Personal Information & Billing Address</h3>
                            </div>
                            <div class="p-6">
                                <form method="POST" action="?view=profile">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Full Name</label>
                                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Email Address</label>
                                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm border p-2 cursor-not-allowed">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2">
                                        </div>
                                        
                                        <!-- Address Fields -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Division</label>
                                            <select name="division" id="division" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2">
                                                <option value="">Select Division</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">District</label>
                                            <select name="district" id="district" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2" disabled>
                                                <option value="">Select District</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Upazila</label>
                                            <select name="upazila" id="upazila" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2" disabled>
                                                <option value="">Select Upazila</option>
                                            </select>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Full Address</label>
                                            <textarea name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="update_profile" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Change Password</h3>
                            </div>
                            <div class="p-6">
                                <form method="POST" action="?view=profile">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Current Password</label>
                                            <input type="password" name="current_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">New Password</label>
                                            <input type="password" name="new_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                            <input type="password" name="confirm_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2">
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="update_password" class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                            Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- Footer (Simplified for Dashboard) -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <p class="text-center text-sm text-gray-500">
            &copy; <?php echo date('Y'); ?> TechHat. All rights reserved.
        </p>
    </div>
</footer>

<script src="assets/js/bd-locations.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const divisionSelect = document.getElementById('division');
        const districtSelect = document.getElementById('district');
        const upazilaSelect = document.getElementById('upazila');
        
        const userDivision = "<?php echo htmlspecialchars($user['division'] ?? ''); ?>";
        const userDistrict = "<?php echo htmlspecialchars($user['district'] ?? ''); ?>";
        const userUpazila = "<?php echo htmlspecialchars($user['upazila'] ?? ''); ?>";

        // Populate Divisions
        if (typeof bdLocations !== 'undefined') {
            Object.keys(bdLocations).sort().forEach(div => {
                const option = document.createElement('option');
                option.value = div;
                option.textContent = div;
                if (div === userDivision) option.selected = true;
                divisionSelect.appendChild(option);
            });
            
            // Trigger change if value exists
            if (userDivision) {
                populateDistricts(userDivision);
                districtSelect.disabled = false;
            }
        }

        if (divisionSelect) {
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
                }
            });
        }

        if (districtSelect) {
            districtSelect.addEventListener('change', function() {
                const division = divisionSelect.value;
                const district = this.value;
                upazilaSelect.innerHTML = '<option value="">Select Upazila</option>';
                
                if (district) {
                    populateUpazilas(division, district);
                    upazilaSelect.disabled = false;
                } else {
                    upazilaSelect.disabled = true;
                }
            });
        }

        function populateDistricts(division) {
            if (!bdLocations[division]) return;
            
            Object.keys(bdLocations[division]).sort().forEach(dist => {
                const option = document.createElement('option');
                option.value = dist;
                option.textContent = dist;
                if (dist === userDistrict) option.selected = true;
                districtSelect.appendChild(option);
            });
            
            if (userDistrict) {
                populateUpazilas(division, userDistrict);
                upazilaSelect.disabled = false;
            }
        }

        function populateUpazilas(division, district) {
            if (!bdLocations[division] || !bdLocations[division][district]) return;
            
            bdLocations[division][district].sort().forEach(upz => {
                const option = document.createElement('option');
                option.value = upz;
                option.textContent = upz;
                if (upz === userUpazila) option.selected = true;
                upazilaSelect.appendChild(option);
            });
        }
    });
</script>

</body>
</html>
