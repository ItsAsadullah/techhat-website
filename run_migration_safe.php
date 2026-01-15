<?php
// Safe Migration Runner
require_once 'core/db.php';

echo "========================================\n";
echo "TechHat Migration Runner\n";
echo "========================================\n\n";

try {
    // Disable foreign key checks temporarily
    echo "1. Disabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Read migration file
    echo "2. Reading migration file...\n";
    $sql = file_get_contents('migrate_clean_variants_system.sql');
    
    // Execute migration
    echo "3. Executing migration...\n";
    $pdo->exec($sql);
    
    // Re-enable foreign key checks
    echo "4. Re-enabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n✅ Migration completed successfully!\n";
    echo "========================================\n";
    
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "========================================\n";
    exit(1);
}
?>
