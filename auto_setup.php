<?php
/**
 * Automatic Database Setup Script
 * Runs all necessary migrations and SQL files
 * Created: January 14, 2026
 */

require_once 'core/db.php';

echo "=== TechHat Database Setup Started ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Database: " . DB_NAME . "\n";
echo "Host: " . DB_HOST . "\n";
echo str_repeat("=", 50) . "\n\n";

$successCount = 0;
$errors = [];

// SQL files to execute in order
$sqlFiles = [
    'migrate_clean_variants_system.sql',
    'create_banners_table.sql',
    'create_homepage_settings.sql',
    'create_reviews_table.sql',
    'create_services_table.sql',
    'create_wishlist_table.sql',
    'create_purchase_tables.sql',
    'create_return_tables.sql',
    'database_migration_pos_custom_return.sql'
];

foreach ($sqlFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        $errors[] = "⚠️  Skipped: $file (not found)";
        continue;
    }
    
    echo "📄 Processing: $file\n";
    
    $sql = file_get_contents($filePath);
    
    // Split by semicolon but handle special cases
    $statements = array_filter(array_map('trim', preg_split('/;[\s]*\n/', $sql)));
    
    $fileSuccess = 0;
    $fileErrors = [];
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        // Add semicolon back if not present
        if (substr(trim($statement), -1) !== ';') {
            $statement = trim($statement) . ';';
        }
        
        try {
            $pdo->exec($statement);
            $fileSuccess++;
        } catch (PDOException $e) {
            $error = $e->getMessage();
            // Ignore table already exists errors
            if (strpos($error, 'already exists') === false) {
                $fileErrors[] = $error;
            }
        }
    }
    
    $successCount += $fileSuccess;
    
    if (empty($fileErrors)) {
        echo "   ✅ $fileSuccess statements executed\n";
    } else {
        echo "   ⚠️  $fileSuccess statements executed, some warnings\n";
        foreach ($fileErrors as $err) {
            if (strlen($err) > 100) {
                $err = substr($err, 0, 100) . '...';
            }
            $errors[] = "   └─ [$file] $err";
        }
    }
    echo "\n";
}

// Verify critical tables exist
echo "\n📋 Verifying Database Tables...\n";
echo str_repeat("-", 50) . "\n";

$requiredTables = [
    'categories', 'products', 'product_variations', 'product_images',
    'users', 'orders', 'order_items', 'attributes', 'attribute_values',
    'banner_images', 'homepage_settings'
];

$missingTables = [];

try {
    $checkStmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
    $existingTables = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "✅ $table\n";
        } else {
            echo "❌ $table (MISSING)\n";
            $missingTables[] = $table;
        }
    }
} catch (Exception $e) {
    echo "⚠️  Could not verify tables: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

if (empty($missingTables)) {
    echo "✅ SETUP COMPLETED SUCCESSFULLY!\n";
    echo "✅ All required tables are present\n";
    echo "✅ Total statements executed: $successCount\n";
    echo "\n🚀 You can now:\n";
    echo "   1. Visit http://localhost/techhat\n";
    echo "   2. Log in or register a new account\n";
    echo "   3. Start adding products\n";
} else {
    echo "⚠️  SETUP COMPLETED WITH WARNINGS\n";
    echo "Missing tables: " . implode(', ', $missingTables) . "\n";
}

if (!empty($errors)) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "⚠️  WARNINGS/ERRORS:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Setup completed at: " . date('Y-m-d H:i:s') . "\n";

?>
