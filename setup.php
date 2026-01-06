<?php
/**
 * Setup Script: Execute SQL schema and create tables
 * Updated: January 6, 2026
 * Supports both old and new migration files
 */

require_once 'core/db.php';

$successCount = 0;
$errors = [];
$migrationFiles = [];

// Determine which migration to run
if (!empty($_POST['migration_type'])) {
    $type = $_POST['migration_type'];
    if ($type === 'clean') {
        $migrationFiles[] = 'migrate_clean_variants_system.sql';
    } elseif ($type === 'hierarchical') {
        $migrationFiles[] = 'schema_hierarchical_categories.sql';
    }
} else {
    // Default: load clean variants system
    $migrationFiles[] = 'migrate_clean_variants_system.sql';
}

// Execute migration files
foreach ($migrationFiles as $file) {
    if (!file_exists($file)) {
        $errors[] = "Migration file not found: $file";
        continue;
    }
    
    $sql = file_get_contents($file);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TechHat Database Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 p-8 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">TechHat Database Setup</h1>
            <p class="text-gray-600">Initialize your e-commerce database with the latest schema</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Setup Card -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">📊 Setup Options</h2>
                
                <form method="POST" class="space-y-4">
                    <div class="bg-blue-50 border border-blue-300 rounded-lg p-4 mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="migration_type" value="clean" checked class="w-4 h-4 text-blue-600">
                            <span class="ml-3">
                                <span class="font-bold">✨ Clean Variants System (Recommended)</span>
                                <p class="text-sm text-gray-600">Latest schema with JSON variations</p>
                            </span>
                        </label>
                    </div>

                    <div class="bg-gray-50 border border-gray-300 rounded-lg p-4 mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="migration_type" value="hierarchical" class="w-4 h-4 text-blue-600">
                            <span class="ml-3">
                                <span class="font-bold">📁 Hierarchical Categories System</span>
                                <p class="text-sm text-gray-600">Previous version with normalized tables</p>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                        ▶️ Run Migration
                    </button>
                </form>
            </div>

            <!-- Info Card -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">ℹ️ Schema Information</h2>
                
                <div class="space-y-4">
                    <div>
                        <h3 class="font-bold text-blue-600 mb-2">📋 Tables Created:</h3>
                        <ul class="text-sm text-gray-700 space-y-1 ml-4">
                            <li>✅ categories (Hierarchical)</li>
                            <li>✅ attributes (Color, Size, RAM...)</li>
                            <li>✅ attribute_values (Red, Blue, 8GB...)</li>
                            <li>✅ products (Main product data)</li>
                            <li>✅ product_variations (Pricing & Stock)</li>
                            <li>✅ category_attributes (Mapping)</li>
                        </ul>
                    </div>

                    <div class="bg-blue-50 border-l-4 border-blue-600 p-3 rounded">
                        <p class="text-sm text-blue-900">
                            <strong>📝 JSON Variations:</strong> Each variation stores attributes as JSON for flexibility
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <?php if ($successCount > 0): ?>
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <div class="bg-green-50 border border-green-300 rounded-lg p-4 mb-6">
                <p class="text-green-800 text-lg">
                    <strong>✅ Success!</strong> <br>
                    <span class="text-sm"><?php echo $successCount; ?> SQL statements executed successfully</span>
                </p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-6">
                <p class="text-yellow-800 font-bold mb-3">⚠️ Warnings/Info:</p>
                <ul class="text-yellow-700 text-sm space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li>• <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="border-t pt-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">✨ Next Steps:</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="admin/product_add.php" class="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                        📦 Add New Product
                    </a>
                    <a href="admin/products.php" class="flex items-center justify-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition">
                        📊 View Products
                    </a>
                    <a href="admin/categories.php" class="flex items-center justify-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition">
                        📁 Manage Categories
                    </a>
                    <a href="index.php" class="flex items-center justify-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition">
                        🏠 Go to Homepage
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Database Info -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">🗄️ Database Structure</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded">
                    <h4 class="font-bold text-blue-900 mb-2">Categories</h4>
                    <p class="text-sm text-gray-700">Hierarchical with infinite nesting</p>
                </div>
                <div class="bg-purple-50 p-4 rounded">
                    <h4 class="font-bold text-purple-900 mb-2">Attributes</h4>
                    <p class="text-sm text-gray-700">Color, Size, RAM, Storage, etc.</p>
                </div>
                <div class="bg-green-50 p-4 rounded">
                    <h4 class="font-bold text-green-900 mb-2">Variations</h4>
                    <p class="text-sm text-gray-700">JSON-based pricing & stock</p>
                </div>
            </div>

            <div class="bg-gray-50 p-4 rounded text-sm text-gray-700 font-mono">
                <p>📝 Sample Query:</p>
                <pre class="mt-2 overflow-x-auto">SELECT p.name, pv.variation_json, pv.selling_price, pv.stock_qty
FROM products p
JOIN product_variations pv ON p.id = pv.product_id
WHERE p.id = 1;</pre>
            </div>
        </div>
    </div>
</body>
</html>
