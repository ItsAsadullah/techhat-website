-- ========================================
-- NEW VARIANT SYSTEM MIGRATION
-- ========================================

-- 1. ATTRIBUTES TABLE
CREATE TABLE IF NOT EXISTS attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    input_type ENUM('select', 'number', 'text') NOT NULL DEFAULT 'select',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attributes_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ATTRIBUTE VALUES TABLE
CREATE TABLE IF NOT EXISTS attribute_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attribute_id INT UNSIGNED NOT NULL,
    value VARCHAR(150) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attr_val_attr FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    INDEX idx_attr_values_attribute (attribute_id),
    UNIQUE KEY unique_attr_value (attribute_id, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CATEGORY ATTRIBUTES MAPPING TABLE
CREATE TABLE IF NOT EXISTS category_attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    attribute_id INT UNSIGNED NOT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cat_attr_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    CONSTRAINT fk_cat_attr_attr FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cat_attr (category_id, attribute_id),
    INDEX idx_cat_attributes_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. NEW PRODUCT VARIATIONS TABLE (replaces old product_variants with improved structure)
CREATE TABLE IF NOT EXISTS product_variations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    price DECIMAL(12,2) NOT NULL,
    offer_price DECIMAL(12,2) DEFAULT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    image VARCHAR(255),
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prod_var_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_prod_var_product (product_id),
    INDEX idx_prod_var_sku (sku),
    INDEX idx_prod_var_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. VARIATION ATTRIBUTES MAPPING TABLE
CREATE TABLE IF NOT EXISTS variation_attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variation_id INT UNSIGNED NOT NULL,
    attribute_id INT UNSIGNED NOT NULL,
    attribute_value_id INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_var_attr_var FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE,
    CONSTRAINT fk_var_attr_attr FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    CONSTRAINT fk_var_attr_val FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE,
    UNIQUE KEY unique_var_attr (variation_id, attribute_id),
    INDEX idx_var_attributes_variation (variation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- INSERT DEFAULT ATTRIBUTES
-- ========================================

INSERT IGNORE INTO attributes (name, slug, input_type) VALUES
('Color', 'color', 'select'),
('Storage', 'storage', 'select'),
('RAM', 'ram', 'select'),
('Processor', 'processor', 'select'),
('Wattage', 'wattage', 'number'),
('DPI', 'dpi', 'number'),
('Size', 'size', 'select'),
('Switch Type', 'switch-type', 'select'),
('Strap Type', 'strap-type', 'select'),
('Resolution', 'resolution', 'select'),
('Refresh Rate', 'refresh-rate', 'select'),
('Frequency', 'frequency', 'select'),
('Type', 'type', 'select'),
('Connectivity', 'connectivity', 'select'),
('Power', 'power', 'number'),
('Ports', 'ports', 'text'),
('Length', 'length', 'text'),
('Capacity', 'capacity', 'select');

-- ========================================
-- MIGRATE DATA FROM OLD VARIANT SYSTEM
-- ========================================

-- Create temporary table for migration
CREATE TEMPORARY TABLE temp_variant_migration AS
SELECT 
    ROW_NUMBER() OVER (PARTITION BY p.id ORDER BY pv.id) as variant_seq,
    pv.id as old_variant_id,
    p.id as product_id,
    pv.sku,
    pv.price,
    pv.offer_price,
    pv.stock_quantity,
    pv.image_path,
    pv.color,
    pv.size,
    pv.storage,
    pv.sim_type,
    pv.status
FROM product_variants pv
JOIN products p ON pv.product_id = p.id;

-- Insert migrated variants into new table
INSERT INTO product_variations (product_id, sku, price, offer_price, stock_quantity, image, status)
SELECT 
    product_id,
    IF(sku IS NOT NULL AND sku != '', sku, CONCAT('SKU-', product_id, '-', variant_seq)),
    price,
    offer_price,
    stock_quantity,
    image_path,
    status
FROM temp_variant_migration;

-- Note: Variation attributes mapping would need manual assignment
-- based on product categories. This requires admin interface.

-- ========================================
-- PRESERVE OLD TABLE FOR SAFETY
-- ========================================

-- Rename old table instead of deleting
ALTER TABLE product_variants RENAME TO product_variants_legacy;

-- ========================================
-- MIGRATION NOTES
-- ========================================

-- After running this migration:
-- 1. Admin needs to assign attributes to categories via new UI
-- 2. Existing products' variant attributes need to be mapped
-- 3. Test product pages thoroughly
-- 4. Once verified, can drop product_variants_legacy table
