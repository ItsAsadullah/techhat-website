<?php
/**
 * TechHat Complete Database Setup
 * Single command to setup everything
 * Created: January 14, 2026
 */

require_once 'core/db.php';

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "🚀 TechHat Database Setup Started\n";
echo str_repeat("=", 60) . "\n";
echo "Database: " . DB_NAME . "\n";
echo "Host: " . DB_HOST . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

// Step 1: Drop all existing tables
echo "📋 Step 1: Clearing existing tables...\n";
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $checkStmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
    $tables = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $droppedCount = 0;
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        $droppedCount++;
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "   ✅ Cleared $droppedCount table(s)\n\n";
} catch (Exception $e) {
    echo "   ⚠️  Warning: " . $e->getMessage() . "\n\n";
}

// Step 2: Load complete schema
echo "📋 Step 2: Creating database schema...\n";

$schemaFile = __DIR__ . '/complete_setup.sql';
if (!file_exists($schemaFile)) {
    die("   ❌ Error: Schema file not found!\n");
}

$sql = file_get_contents($schemaFile);

// Split statements properly
$statements = array_filter(array_map('trim', preg_split('/;[\s]*\n/', $sql)));

$successCount = 0;
$errorCount = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    if (substr(trim($statement), -1) !== ';') {
        $statement = trim($statement) . ';';
    }
    
    try {
        $pdo->exec($statement);
        $successCount++;
    } catch (PDOException $e) {
        $errorCount++;
        $errors[] = $e->getMessage();
    }
}

echo "   ✅ Executed $successCount statements\n";
if ($errorCount > 0) {
    echo "   ⚠️  $errorCount warnings/errors (non-critical)\n";
}
echo "\n";

// Step 3: Add sample data
echo "📋 Step 3: Adding sample data...\n";

$sampleQueries = [
    "INSERT INTO `categories` (`name`, `parent_id`, `slug`, `description`) VALUES
        ('Electronics', NULL, 'electronics', 'Electronic devices and gadgets'),
        ('Fashion', NULL, 'fashion', 'Clothing and accessories'),
        ('Home & Garden', NULL, 'home-garden', 'Home appliances and garden tools'),
        ('Mobile Phones', 1, 'mobile-phones', 'Smartphones and mobile devices'),
        ('Laptops', 1, 'laptops', 'Laptops and notebooks'),
        ('Accessories', 1, 'accessories', 'Tech accessories'),
        ('Men Clothing', 2, 'men-clothing', 'Men fashion items'),
        ('Women Clothing', 2, 'women-clothing', 'Women fashion items'),
        ('Kitchen', 3, 'kitchen', 'Kitchen appliances')",
    
    "INSERT INTO `attributes` (`name`, `slug`, `type`) VALUES
        ('Color', 'color', 'select'),
        ('Size', 'size', 'select'),
        ('RAM', 'ram', 'select'),
        ('Storage', 'storage', 'select'),
        ('Brand', 'brand', 'select')",
    
    "INSERT INTO `homepage_settings` (`setting_key`, `setting_value`) VALUES
        ('site_name', 'TechHat'),
        ('site_description', 'Your favorite e-commerce store')"
];

$sampleSuccess = 0;
foreach ($sampleQueries as $query) {
    try {
        $pdo->exec($query);
        $sampleSuccess++;
    } catch (Exception $e) {
        // Silently skip if already exists
    }
}

echo "   ✅ Added sample data\n\n";

// Step 4: Verify tables
echo "📋 Step 4: Verifying tables...\n";
echo str_repeat("-", 60) . "\n";

$requiredTables = [
    'users',
    'categories',
    'products',
    'product_variations',
    'product_images',
    'orders',
    'order_items',
    'attributes',
    'attribute_values',
    'banner_images',
    'homepage_settings'
];

$missingTables = [];
try {
    $checkStmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
    $existingTables = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "   ✅ $table\n";
        } else {
            echo "   ❌ $table (MISSING)\n";
            $missingTables[] = $table;
        }
    }
} catch (Exception $e) {
    echo "   ⚠️  Could not verify\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";

if (empty($missingTables)) {
    echo "✅ SUCCESS! Database setup completed successfully!\n\n";
    echo "📝 Next Steps:\n";
    echo "   1️⃣  Visit: http://localhost/techhat\n";
    echo "   2️⃣  Register a new account\n";
    echo "   3️⃣  Go to Admin: http://localhost/techhat/admin\n";
    echo "   4️⃣  Start adding products\n";
} else {
    echo "⚠️  Setup completed with warnings\n";
    echo "Missing: " . implode(', ', $missingTables) . "\n";
}

echo "\n";
echo "✅ Setup completed at: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

?>
