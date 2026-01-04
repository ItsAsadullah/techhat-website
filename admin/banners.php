<?php
require_once '../core/db.php';
require_once '../core/auth.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM banner_images WHERE id = ?")->execute([$id]);
    $success = 'Banner deleted successfully!';
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_banner'])) {
    try {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $subtitle = $_POST['subtitle'];
        $link_url = $_POST['link_url'];
        $button_text = $_POST['button_text'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = (int)$_POST['display_order'];
        
        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE banner_images SET title=?, subtitle=?, link_url=?, button_text=?, is_active=?, display_order=? WHERE id=?");
            $stmt->execute([$title, $subtitle, $link_url, $button_text, $is_active, $display_order, $id]);
            $success = 'Banner updated successfully!';
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO banner_images (image_path, title, subtitle, link_url, button_text, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['uploads/banners/default.jpg', $title, $subtitle, $link_url, $button_text, $is_active, $display_order]);
            $success = 'Banner added successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch all banners
$banners = $pdo->query("SELECT * FROM banner_images ORDER BY display_order ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100">

    <?php include 'partials/sidebar.php'; ?>

    <div class="admin-content ml-0 md:ml-64 p-6">
        <div class="max-w-6xl mx-auto">
            
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Banner Management</h1>
                    <p class="text-gray-600 text-sm">Manage homepage carousel banners</p>
                </div>
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                        class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg">
                    <i class="bi bi-plus-circle"></i> Add Banner
                </button>
            </div>

            <!-- Messages -->
            <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Banners List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtitle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Button</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($banners as $banner): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $banner['display_order']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($banner['title']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($banner['subtitle']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($banner['button_text']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($banner['is_active']): ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                                <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Inactive
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick='editBanner(<?php echo json_encode($banner); ?>)' 
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <a href="?delete=<?php echo $banner['id']; ?>" 
                                   onclick="return confirm('Delete this banner?')"
                                   class="text-red-600 hover:text-red-900">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4">
            <h2 class="text-2xl font-bold mb-4" id="modalTitle">Add Banner</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="id" id="bannerId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" id="bannerTitle" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Button Text</label>
                        <input type="text" name="button_text" id="bannerButton" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subtitle</label>
                    <input type="text" name="subtitle" id="bannerSubtitle" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Link URL</label>
                        <input type="text" name="link_url" id="bannerLink" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Display Order</label>
                        <input type="number" name="display_order" id="bannerOrder" value="1" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-600">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" id="bannerActive" checked class="w-4 h-4">
                        <span class="text-sm font-semibold text-gray-700">Active</span>
                    </label>
                </div>
                
                <div class="flex gap-4">
                    <button type="submit" name="save_banner"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-2 px-4 rounded-lg font-semibold hover:shadow-lg">
                        <i class="bi bi-check-circle"></i> Save Banner
                    </button>
                    <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                            class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-gray-700">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editBanner(banner) {
            document.getElementById('modalTitle').textContent = 'Edit Banner';
            document.getElementById('bannerId').value = banner.id;
            document.getElementById('bannerTitle').value = banner.title;
            document.getElementById('bannerSubtitle').value = banner.subtitle;
            document.getElementById('bannerLink').value = banner.link_url;
            document.getElementById('bannerButton').value = banner.button_text;
            document.getElementById('bannerOrder').value = banner.display_order;
            document.getElementById('bannerActive').checked = banner.is_active == 1;
            document.getElementById('addModal').classList.remove('hidden');
        }
    </script>

</body>
</html>