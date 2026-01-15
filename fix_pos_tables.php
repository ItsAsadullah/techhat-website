<?php
require_once 'core/db.php';

echo "========================================\n";
echo "Creating Product Variants Table for POS\n";
echo "========================================\n\n";

try {
    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'product_variants'");
    
    if ($checkTable->rowCount() > 0) {
        echo "✓ Table 'product_variants' already exists\n";
    } else {
        echo "Creating 'product_variants' table...\n";
        
        $sql = "
        CREATE TABLE IF NOT EXISTS `product_variants` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `product_id` int(11) NOT NULL,
            `name` varchar(255) DEFAULT 'Default',
            `sku` varchar(100) DEFAULT NULL,
            `price` decimal(10,2) NOT NULL DEFAULT 0.00,
            `offer_price` decimal(10,2) DEFAULT NULL,
            `stock_quantity` int(11) NOT NULL DEFAULT 0,
            `status` tinyint(1) DEFAULT 1,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `product_id` (`product_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        echo "✓ Table 'product_variants' created successfully\n";
    }
    
    // Check if we have any products without variants
    $checkProducts = $pdo->query("SELECT COUNT(*) as count FROM products WHERE id NOT IN (SELECT DISTINCT product_id FROM product_variants)");
    $productsWithoutVariants = $checkProducts->fetch()['count'];
    
    if ($productsWithoutVariants > 0) {
        echo "\n$productsWithoutVariants products found without variants\n";
        echo "Creating default variants for these products...\n";
        
        $createDefaultVariants = "
        INSERT INTO product_variants (product_id, name, sku, price, offer_price, stock_quantity, status)
        SELECT 
            id,
            'Default',
            CONCAT('SKU-', id),
            0.00,
            NULL,
            0,
            is_active
        FROM products
        WHERE id NOT IN (SELECT DISTINCT product_id FROM product_variants)
        ";
        
        $pdo->exec($createDefaultVariants);
        echo "✓ Default variants created for $productsWithoutVariants products\n";
    } else {
        echo "\n✓ All products already have variants\n";
    }
    
    echo "\n========================================\n";
    echo "✅ Setup completed successfully!\n";
    echo "========================================\n";
    
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "========================================\n";
    exit(1);
}
?>
