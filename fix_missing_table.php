<?php
/**
 * Fix Missing Tables and Columns
 * Run this script to fix database structure issues
 */

require_once 'core/db.php';

echo "<!DOCTYPE html><html><head><title>Fix Database Issues</title></head><body>";
echo "<h2>Checking Database Structure</h2>";
echo "<pre>";

function checkAndAddColumn($pdo, $table, $column, $definition) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            echo "Adding column '$column' to '$table'...\n";
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
            echo "✓ Added '$column' to '$table'\n";
        } else {
            echo "✓ Column '$column' already exists in '$table'\n";
        }
    } catch (PDOException $e) {
        echo "Error checking/adding column '$column' in '$table': " . $e->getMessage() . "\n";
    }
}

try {
    // 1. Check product_variants_legacy
    $check = $pdo->query("SHOW TABLES LIKE 'product_variants_legacy'");
    if ($check->rowCount() == 0) {
        echo "Creating product_variants_legacy table...\n";
        $sql = "CREATE TABLE IF NOT EXISTS `product_variants_legacy` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `product_id` int(10) unsigned NOT NULL,
            `name` varchar(255) DEFAULT 'Default',
            `sku` varchar(100) DEFAULT NULL,
            `price` decimal(10,2) NOT NULL DEFAULT 0.00,
            `offer_price` decimal(10,2) DEFAULT NULL,
            `stock_quantity` int(11) NOT NULL DEFAULT 0,
            `status` tinyint(1) DEFAULT 1,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
        echo "✓ Successfully created product_variants_legacy table!\n";
    } else {
        echo "✓ product_variants_legacy table exists. Checking columns...\n";
        checkAndAddColumn($pdo, 'product_variants_legacy', 'offer_price', "decimal(10,2) DEFAULT NULL AFTER price");
    }

    // 2. Check product_variations
    $checkVariations = $pdo->query("SHOW TABLES LIKE 'product_variations'");
    if ($checkVariations->rowCount() > 0) {
        echo "✓ product_variations table exists. Checking columns...\n";
        checkAndAddColumn($pdo, 'product_variations', 'offer_price', "decimal(12,2) DEFAULT NULL AFTER price");
    } else {
        echo "! product_variations table does not exist. Checking product_variants...\n";
        $checkVariants = $pdo->query("SHOW TABLES LIKE 'product_variants'");
        if ($checkVariants->rowCount() > 0) {
             checkAndAddColumn($pdo, 'product_variants', 'offer_price', "decimal(12,2) DEFAULT NULL AFTER price");
        }
    }

    echo "\n<strong style='color: green;'>Success! Database check complete.</strong>\n";
    echo "\n<a href='index.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#4CAF50; color:white; text-decoration:none; border-radius:5px;'>Go to Homepage</a>";
    
} catch (PDOException $e) {
    echo "<strong style='color: red;'>Fatal Error: " . $e->getMessage() . "</strong>\n";
}

echo "</pre>";
echo "</body></html>";
?>
