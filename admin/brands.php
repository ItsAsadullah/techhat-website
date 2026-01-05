<?php
require_once '../core/auth.php';
require_admin();

$msg = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Brand deleted successfully.";
    } catch (Exception $e) {
        $error = "Cannot delete brand. It might be linked to products.";
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if (empty($name)) {
        $error = "Name is required.";
    } else {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE brands SET name = ?, slug = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $id]);
            $msg = "Brand updated successfully.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO brands (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            $msg = "Brand added successfully.";
        }
    }
}

$brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Brands | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-50">
    <div class="flex">
        <?php include 'partials/sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-2xl font-bold">Manage Brands</h1>
                <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    + Add New Brand
                </button>
            </div>

            <?php if($msg): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $msg; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Name</th>
                            <th class="px-6 py-4 font-semibold">Slug</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach($brands as $b): ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($b['name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($b['slug']); ?></td>
                            <td class="px-6 py-4 text-right">
                                <button onclick='editBrand(<?php echo json_encode($b); ?>)' class="text-blue-600 hover:text-blue-800 mr-3">Edit</button>
                                <a href="?delete=<?php echo $b['id']; ?>" onclick="return confirm('Are you sure?')" class="text-red-600 hover:text-red-800">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="brandModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-xl p-8 w-full max-w-md">
            <h2 id="modalTitle" class="text-xl font-bold mb-4">Add New Brand</h2>
            <form method="POST">
                <input type="hidden" name="id" id="brandId">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Brand Name</label>
                    <input type="text" name="name" id="brandName" class="w-full px-4 py-2 border rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Brand</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('brandId').value = '';
            document.getElementById('brandName').value = '';
            document.getElementById('modalTitle').innerText = 'Add New Brand';
            document.getElementById('brandModal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('brandModal').classList.add('hidden');
        }
        function editBrand(brand) {
            document.getElementById('brandId').value = brand.id;
            document.getElementById('brandName').value = brand.name;
            document.getElementById('modalTitle').innerText = 'Edit Brand';
            document.getElementById('brandModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
