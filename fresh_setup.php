<?php
/**
 * Complete Fresh Database Setup
 * Drops all existing tables and creates fresh schema
 * Created: January 14, 2026
 */

require_once 'core/db.php';

echo "=== TechHat Database FRESH Setup Started ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Database: " . DB_NAME . "\n";
echo "Host: " . DB_HOST . "\n";
echo str_repeat("=", 50) . "\n\n";

// First, drop all existing tables safely
echo "🗑️  Step 1: Dropping existing tables (if any)...\n";
try {
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Get all tables
    $checkStmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
    $tables = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "   ✅ Dropped: $table\n";
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "   Done!\n\n";
} catch (Exception $e) {
    echo "   ⚠️  Error dropping tables: " . $e->getMessage() . "\n\n";
}

// Now run the main migration file
echo "📋 Step 2: Creating core tables from database.sql...\n";
echo str_repeat("-", 50) . "\n";

$coreFile = __DIR__ . '/database.sql';
if (!file_exists($coreFile)) {
    die("❌ Core database file not found: $coreFile\n");
}

$sql = file_get_contents($coreFile);
// Split by semicolon
$statements = array_filter(array_map('trim', preg_split('/;[\s]*\n/', $sql)));

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    // Add semicolon back if not present
    if (substr(trim($statement), -1) !== ';') {
        $statement = trim($statement) . ';';
    }
    
    try {
        $pdo->exec($statement);
        $successCount++;
    } catch (PDOException $e) {
        $errorCount++;
        // echo "⚠️  Skipped: " . substr($e->getMessage(), 0, 60) . "...\n";
    }
}

echo "✅ Statements executed: $successCount\n";
echo "\n";

// Now run migration file for variations
echo "📋 Step 3: Creating product variations from migration file...\n";
echo str_repeat("-", 50) . "\n";

$migrationFile = __DIR__ . '/migrate_clean_variants_system.sql';
if (!file_exists($migrationFile)) {
    echo "⏭️  Migration file not found (optional): $migrationFile\n";
} else {
    $sql = file_get_contents($migrationFile);
    $statements = array_filter(array_map('trim', preg_split('/;[\s]*\n/', $sql)));
    
    $migSuccess = 0;
    $migError = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        if (substr(trim($statement), -1) !== ';') {
            $statement = trim($statement) . ';';
        }
        
        try {
            $pdo->exec($statement);
            $migSuccess++;
        } catch (PDOException $e) {
            $migError++;
        }
    }
    
    echo "✅ Statements executed: $migSuccess\n";
    $successCount += $migSuccess;
}

echo "\n";

// Now run additional setup files
echo "📋 Step 4: Creating additional tables...\n";
echo str_repeat("-", 50) . "\n";

$additionalFiles = [
    'create_banners_table.sql',
    'create_homepage_settings.sql',
    'create_services_table.sql',
    'create_wishlist_table.sql',
    'create_purchase_tables.sql',
    'create_return_tables.sql',
    'database_migration_pos_custom_return.sql'
];

foreach ($additionalFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "⏭️  Skipped: $file (not found)\n";
        continue;
    }
    
    $sql = file_get_contents($filePath);
    $statements = array_filter(array_map('trim', preg_split('/;[\s]*\n/', $sql)));
    
    $fileSuccess = 0;
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        if (substr(trim($statement), -1) !== ';') {
            $statement = trim($statement) . ';';
        }
        
        try {
            $pdo->exec($statement);
            $fileSuccess++;
        } catch (PDOException $e) {
            // Silently skip errors for optional tables
        }
    }
    
    echo "✅ $file ($fileSuccess statements)\n";
}

echo "\n";

// Verify critical tables
echo "📋 Step 5: Verifying Database Tables...\n";
echo str_repeat("-", 50) . "\n";

$requiredTables = [
    'categories',
    'products',
    'product_variations',
    'product_images',
    'users',
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
            echo "✅ $table\n";
        } else {
            echo "❌ $table\n";
            $missingTables[] = $table;
        }
    }
} catch (Exception $e) {
    echo "⚠️  Could not verify tables\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

if (empty($missingTables)) {
    echo "✅ SUCCESS! Database setup completed!\n";
    echo "✅ All required tables are present\n";
    echo "\n📝 Next Steps:\n";
    echo "   1. Visit: http://localhost/techhat\n";
    echo "   2. Register a new account or log in\n";
    echo "   3. Go to: http://localhost/techhat/admin\n";
    echo "   4. Start adding products\n";
} else {
    echo "⚠️  Setup completed but some tables are missing:\n";
    echo "   " . implode(', ', $missingTables) . "\n";
}

echo "\n✅ Setup completed at: " . date('Y-m-d H:i:s') . "\n";

?>
