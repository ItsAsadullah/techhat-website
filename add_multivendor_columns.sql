-- Add additional columns to products table for multi-vendor features

ALTER TABLE `products` 
ADD COLUMN `short_description` TEXT AFTER `description`,
ADD COLUMN `tags` VARCHAR(500) AFTER `short_description`,
ADD COLUMN `meta_title` VARCHAR(255) AFTER `tags`,
ADD COLUMN `meta_keywords` VARCHAR(500) AFTER `meta_title`,
ADD COLUMN `meta_description` TEXT AFTER `meta_keywords`,
ADD COLUMN `weight` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Weight in KG',
ADD COLUMN `length` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Length in CM',
ADD COLUMN `width` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Width in CM',
ADD COLUMN `height` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Height in CM',
ADD COLUMN `warranty_type` ENUM('none', 'brand', 'shop') DEFAULT 'none',
ADD COLUMN `warranty_period` VARCHAR(50) DEFAULT NULL,
ADD COLUMN `return_policy` VARCHAR(50) DEFAULT 'no_return',
ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL,
ADD COLUMN `gallery_images` TEXT COMMENT 'JSON array of gallery images',
ADD COLUMN `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
ADD COLUMN `vendor_id` INT(11) DEFAULT NULL COMMENT 'User ID of vendor';

-- Add index for better performance
ALTER TABLE `products`
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_vendor` (`vendor_id`);

-- Create product_attribute_values table (for linking products to attribute values)
CREATE TABLE IF NOT EXISTS `product_attribute_values` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `attribute_id` INT(11) NOT NULL,
    `value_id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_product_attr_value` (`product_id`, `attribute_id`, `value_id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_attribute` (`attribute_id`),
    KEY `idx_value` (`value_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`value_id`) REFERENCES `attribute_values`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update product_variations table to ensure compatibility
ALTER TABLE `product_variations`
MODIFY COLUMN `variation_json` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL 
    COMMENT 'JSON format: {"Color":"Black", "RAM":"8GB", "Storage":"128GB"}';

-- Sample data: Insert some warranty periods if not exists
-- (This is optional, just for reference)

-- Insert sample tags for testing (optional)
-- UPDATE products SET tags = 'Gaming, 5G, Android' WHERE id = 1;
