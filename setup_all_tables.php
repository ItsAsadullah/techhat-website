<?php
require_once 'core/db.php';

echo "========================================\n";
echo "TechHat Complete Database Setup\n";
echo "========================================\n\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

try {
    // 1. Categories Table
    echo "1. Checking categories table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'categories'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `categories` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `slug` varchar(255) DEFAULT NULL,
                `parent_id` int(10) unsigned DEFAULT NULL,
                `description` text,
                `image` varchar(255) DEFAULT NULL,
                `display_order` int(11) DEFAULT 0,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`),
                KEY `parent_id` (`parent_id`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created categories table\n";
        
        // Insert some default categories
        $pdo->exec("
            INSERT INTO categories (name, slug, is_active) VALUES
            ('Electronics', 'electronics', 1),
            ('Mobile Phones', 'mobile-phones', 1),
            ('Laptops', 'laptops', 1),
            ('Accessories', 'accessories', 1),
            ('Fashion', 'fashion', 1)
        ");
        echo "   ✓ Added default categories\n";
    } else {
        echo "   ✓ categories table exists\n";
    }

    // 2. Brands Table
    echo "2. Checking brands table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'brands'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `brands` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `slug` varchar(255) DEFAULT NULL,
                `logo` varchar(255) DEFAULT NULL,
                `description` text,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created brands table\n";
        
        $pdo->exec("
            INSERT INTO brands (name, slug, is_active) VALUES
            ('Apple', 'apple', 1),
            ('Samsung', 'samsung', 1),
            ('Xiaomi', 'xiaomi', 1),
            ('HP', 'hp', 1),
            ('Dell', 'dell', 1)
        ");
        echo "   ✓ Added default brands\n";
    } else {
        echo "   ✓ brands table exists\n";
    }

    // 3. Products Table - check if it exists and has correct structure
    echo "3. Checking products table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `products` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `category_id` int(10) unsigned DEFAULT NULL,
                `brand_id` int(10) unsigned DEFAULT NULL,
                `title` varchar(255) NOT NULL,
                `slug` varchar(255) DEFAULT NULL,
                `description` longtext,
                `specifications` longtext,
                `video_url` varchar(255) DEFAULT NULL,
                `is_flash_sale` tinyint(1) DEFAULT 0,
                `badge_text` varchar(50) DEFAULT NULL,
                `warranty_type` varchar(50) DEFAULT NULL,
                `warranty_period` varchar(50) DEFAULT NULL,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `category_id` (`category_id`),
                KEY `brand_id` (`brand_id`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created products table\n";
    } else {
        echo "   ✓ products table exists\n";
    }

    // 4. Product Images Table
    echo "4. Checking product_images table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'product_images'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `product_images` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `product_id` int(10) unsigned NOT NULL,
                `image_path` varchar(255) NOT NULL,
                `is_primary` tinyint(1) DEFAULT 0,
                `display_order` int(11) DEFAULT 0,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created product_images table\n";
    } else {
        echo "   ✓ product_images table exists\n";
    }

    // 5. Product Variants Table (for POS and legacy support)
    echo "5. Checking product_variants table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'product_variants'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `product_variants` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` int(10) unsigned NOT NULL,
                `name` varchar(255) DEFAULT 'Default',
                `sku` varchar(100) DEFAULT NULL,
                `price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `offer_price` decimal(10,2) DEFAULT NULL,
                `purchase_price` decimal(10,2) DEFAULT 0.00,
                `stock_quantity` int(11) NOT NULL DEFAULT 0,
                `status` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `product_id` (`product_id`),
                KEY `status` (`status`),
                KEY `sku` (`sku`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created product_variants table\n";
    } else {
        echo "   ✓ product_variants table exists\n";
    }

    // 6. Product Variations Table (new system with JSON)
    echo "6. Checking product_variations table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'product_variations'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `product_variations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` int(10) unsigned NOT NULL,
                `sku` varchar(100) DEFAULT NULL,
                `variation_data` longtext COMMENT 'JSON: {\"Color\":\"Red\", \"Size\":\"XL\"}',
                `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
                `price` decimal(12,2) NOT NULL DEFAULT 0.00,
                `offer_price` decimal(12,2) DEFAULT NULL,
                `stock_quantity` int(11) NOT NULL DEFAULT 0,
                `image` varchar(255) DEFAULT NULL,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `product_id` (`product_id`),
                KEY `sku` (`sku`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created product_variations table\n";
    } else {
        echo "   ✓ product_variations table exists\n";
    }

    // 7. Product Variants Legacy Table (for compatibility)
    echo "7. Checking product_variants_legacy table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'product_variants_legacy'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `product_variants_legacy` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created product_variants_legacy table\n";
    } else {
        echo "   ✓ product_variants_legacy table exists\n";
    }

    // 8. Attributes Table
    echo "8. Checking attributes table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'attributes'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `attributes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `slug` varchar(255) DEFAULT NULL,
                `type` varchar(50) DEFAULT 'select',
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created attributes table\n";
        
        $pdo->exec("
            INSERT INTO attributes (name, slug, type) VALUES
            ('Color', 'color', 'select'),
            ('Size', 'size', 'select'),
            ('RAM', 'ram', 'select'),
            ('Storage', 'storage', 'select')
        ");
        echo "   ✓ Added default attributes\n";
    } else {
        echo "   ✓ attributes table exists\n";
    }

    // 9. Attribute Values Table
    echo "9. Checking attribute_values table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'attribute_values'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `attribute_values` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `attribute_id` int(11) NOT NULL,
                `value` varchar(255) NOT NULL,
                `display_order` int(11) DEFAULT 0,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `attribute_id` (`attribute_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created attribute_values table\n";
    } else {
        echo "   ✓ attribute_values table exists\n";
    }

    // 10. Category Attributes Table
    echo "10. Checking category_attributes table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'category_attributes'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `category_attributes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `category_id` int(10) unsigned NOT NULL,
                `attribute_id` int(11) NOT NULL,
                `is_required` tinyint(1) DEFAULT 0,
                `display_order` int(11) DEFAULT 0,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `cat_attr` (`category_id`, `attribute_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created category_attributes table\n";
    } else {
        echo "   ✓ category_attributes table exists\n";
    }

    // 11. Services Table
    echo "11. Checking services table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'services'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `services` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `description` text,
                `price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `category` varchar(100) DEFAULT NULL,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created services table\n";
    } else {
        echo "   ✓ services table exists\n";
    }

    // 12. Users Table
    echo "12. Checking users table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `email` varchar(255) NOT NULL,
                `password` varchar(255) NOT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `address` text,
                `city` varchar(100) DEFAULT NULL,
                `state` varchar(100) DEFAULT NULL,
                `zip` varchar(20) DEFAULT NULL,
                `country` varchar(100) DEFAULT 'Bangladesh',
                `role` enum('admin','user','vendor') DEFAULT 'user',
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created users table\n";
    } else {
        echo "   ✓ users table exists\n";
    }

    // 13. Orders Table
    echo "13. Checking orders table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `orders` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(10) unsigned DEFAULT NULL,
                `order_number` varchar(50) NOT NULL,
                `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
                `discount` decimal(12,2) DEFAULT 0.00,
                `shipping` decimal(12,2) DEFAULT 0.00,
                `total` decimal(12,2) NOT NULL DEFAULT 0.00,
                `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
                `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
                `payment_method` varchar(50) DEFAULT NULL,
                `shipping_name` varchar(255) DEFAULT NULL,
                `shipping_phone` varchar(20) DEFAULT NULL,
                `shipping_address` text,
                `shipping_city` varchar(100) DEFAULT NULL,
                `notes` text,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `order_number` (`order_number`),
                KEY `user_id` (`user_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created orders table\n";
    } else {
        echo "   ✓ orders table exists\n";
    }

    // 14. Order Items Table
    echo "14. Checking order_items table...\n";
    $check = $pdo->query("SHOW TABLES LIKE 'order_items'");
    if ($check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `order_items` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `order_id` int(10) unsigned NOT NULL,
                `product_id` int(10) unsigned DEFAULT NULL,
                `variant_id` int(11) DEFAULT NULL,
                `product_name` varchar(255) NOT NULL,
                `variant_name` varchar(255) DEFAULT NULL,
                `sku` varchar(100) DEFAULT NULL,
                `price` decimal(12,2) NOT NULL,
                `quantity` int(11) NOT NULL DEFAULT 1,
                `total` decimal(12,2) NOT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `order_id` (`order_id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   ✓ Created order_items table\n";
    } else {
        echo "   ✓ order_items table exists\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n========================================\n";
    echo "✅ All tables setup completed!\n";
    echo "========================================\n";

} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    exit(1);
}
?>
