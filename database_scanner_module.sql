-- ================================================================
-- TechHat Shop - Scanner Module Database Schema
-- Remote Mobile Scanner + Product Serial/IMEI Tracking
-- ================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ================================================================
-- 1. PRODUCTS TABLE (Extended with product type and warranty)
-- ================================================================
-- Note: If products table already exists, run ALTER instead

ALTER TABLE products
ADD COLUMN IF NOT EXISTS product_type ENUM('simple', 'variable') NOT NULL DEFAULT 'simple' AFTER slug,
ADD COLUMN IF NOT EXISTS warranty_months INT UNSIGNED DEFAULT 0 AFTER product_type,
ADD COLUMN IF NOT EXISTS warranty_type VARCHAR(50) DEFAULT NULL AFTER warranty_months,
ADD COLUMN IF NOT EXISTS has_serial TINYINT(1) NOT NULL DEFAULT 0 AFTER warranty_type,
ADD COLUMN IF NOT EXISTS unit VARCHAR(30) DEFAULT 'pc' AFTER has_serial;

-- ================================================================
-- 2. PRODUCT VARIATIONS TABLE (Extended)
-- ================================================================
-- If product_variants exists, extend it; otherwise use this

ALTER TABLE product_variants
ADD COLUMN IF NOT EXISTS barcode VARCHAR(100) NULL AFTER sku,
ADD COLUMN IF NOT EXISTS variant_image VARCHAR(255) NULL AFTER barcode,
ADD COLUMN IF NOT EXISTS weight DECIMAL(10,3) DEFAULT NULL AFTER variant_image,
ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER weight;

-- Add index for barcode
CREATE INDEX IF NOT EXISTS idx_variants_barcode ON product_variants(barcode);

-- ================================================================
-- 3. ATTRIBUTES TABLE (For Variable Products)
-- ================================================================
CREATE TABLE IF NOT EXISTS attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    type ENUM('select', 'color', 'button') NOT NULL DEFAULT 'select',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_attributes_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 4. ATTRIBUTE VALUES TABLE
-- ================================================================
CREATE TABLE IF NOT EXISTS attribute_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attribute_id INT UNSIGNED NOT NULL,
    value VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    color_code VARCHAR(20) DEFAULT NULL COMMENT 'Hex color for color-type attributes',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attrval_attribute FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attr_value (attribute_id, slug),
    INDEX idx_attrval_attribute (attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 5. PRODUCT VARIANT ATTRIBUTES (Many-to-Many Junction)
-- ================================================================
CREATE TABLE IF NOT EXISTS product_variant_attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id INT UNSIGNED NOT NULL,
    attribute_id INT UNSIGNED NOT NULL,
    attribute_value_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pva_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    CONSTRAINT fk_pva_attribute FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pva_attrvalue FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE,
    UNIQUE KEY unique_variant_attribute (variant_id, attribute_id),
    INDEX idx_pva_variant (variant_id),
    INDEX idx_pva_attribute (attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 6. PRODUCT SERIALS TABLE (IMEI/Serial Number Tracking)
-- ================================================================
CREATE TABLE IF NOT EXISTS product_serials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NULL,
    serial_number VARCHAR(100) NOT NULL,
    status ENUM('available', 'sold', 'reserved', 'returned', 'damaged') NOT NULL DEFAULT 'available',
    purchase_id BIGINT UNSIGNED NULL COMMENT 'Reference to purchase order if applicable',
    sale_id BIGINT UNSIGNED NULL COMMENT 'Reference to pos_sales or orders when sold',
    sale_type ENUM('online', 'pos') NULL COMMENT 'Which sales channel',
    warranty_start DATE NULL,
    warranty_end DATE NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_serial_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_serial_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    UNIQUE KEY unique_serial (serial_number),
    INDEX idx_serial_product (product_id),
    INDEX idx_serial_variant (variant_id),
    INDEX idx_serial_status (status),
    INDEX idx_serial_sale (sale_id, sale_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 7. SCAN SESSIONS TABLE (CRITICAL - Remote Scanner Communication)
-- ================================================================
-- This table enables PC <-> Mobile communication via database polling
-- Mobile inserts scanned codes; PC reads and marks as consumed

CREATE TABLE IF NOT EXISTS scan_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL COMMENT 'Unique session identifier for pairing',
    scanned_code VARCHAR(255) NOT NULL COMMENT 'The barcode/QR code scanned',
    code_type ENUM('barcode', 'qrcode', 'serial', 'imei') NOT NULL DEFAULT 'barcode',
    is_consumed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=consumed by PC',
    device_info VARCHAR(255) NULL COMMENT 'Mobile device user agent info',
    ip_address VARCHAR(45) NULL COMMENT 'Scanner device IP',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consumed_at DATETIME NULL COMMENT 'When PC read the code',
    INDEX idx_scan_session (session_id),
    INDEX idx_scan_consumed (is_consumed),
    INDEX idx_scan_created (created_at),
    INDEX idx_scan_session_consumed (session_id, is_consumed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 8. SCAN SESSION REGISTRY (Track Active Sessions)
-- ================================================================
CREATE TABLE IF NOT EXISTS scan_session_registry (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    user_id INT UNSIGNED NULL COMMENT 'Admin user who created session',
    purpose ENUM('serial_entry', 'inventory', 'pos', 'lookup') NOT NULL DEFAULT 'serial_entry',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    CONSTRAINT fk_scanreg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_scanreg_active (is_active),
    INDEX idx_scanreg_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SEED DATA: Default Attributes
-- ================================================================
INSERT IGNORE INTO attributes (id, name, slug, type) VALUES
(1, 'Color', 'color', 'color'),
(2, 'Size', 'size', 'button'),
(3, 'RAM', 'ram', 'select'),
(4, 'Storage', 'storage', 'select'),
(5, 'Material', 'material', 'select');

-- Seed Color Values
INSERT IGNORE INTO attribute_values (attribute_id, value, slug, color_code, sort_order) VALUES
(1, 'Black', 'black', '#000000', 1),
(1, 'White', 'white', '#FFFFFF', 2),
(1, 'Red', 'red', '#FF0000', 3),
(1, 'Blue', 'blue', '#0066FF', 4),
(1, 'Green', 'green', '#00AA00', 5),
(1, 'Gold', 'gold', '#FFD700', 6),
(1, 'Silver', 'silver', '#C0C0C0', 7),
(1, 'Space Gray', 'space-gray', '#4A4A4A', 8);

-- Seed RAM Values
INSERT IGNORE INTO attribute_values (attribute_id, value, slug, sort_order) VALUES
(3, '4GB', '4gb', 1),
(3, '6GB', '6gb', 2),
(3, '8GB', '8gb', 3),
(3, '12GB', '12gb', 4),
(3, '16GB', '16gb', 5);

-- Seed Storage Values
INSERT IGNORE INTO attribute_values (attribute_id, value, slug, sort_order) VALUES
(4, '64GB', '64gb', 1),
(4, '128GB', '128gb', 2),
(4, '256GB', '256gb', 3),
(4, '512GB', '512gb', 4),
(4, '1TB', '1tb', 5);

-- Seed Size Values
INSERT IGNORE INTO attribute_values (attribute_id, value, slug, sort_order) VALUES
(2, 'XS', 'xs', 1),
(2, 'S', 's', 2),
(2, 'M', 'm', 3),
(2, 'L', 'l', 4),
(2, 'XL', 'xl', 5),
(2, 'XXL', 'xxl', 6);

-- ================================================================
-- CLEANUP: Auto-delete old scan sessions (run via cron or manually)
-- ================================================================
-- DELETE FROM scan_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
-- DELETE FROM scan_session_registry WHERE expires_at < NOW();

-- ================================================================
-- EVENTS (Optional - Auto cleanup if MySQL Events are enabled)
-- ================================================================
-- Note: May not work on all shared hosting

-- DELIMITER //
-- CREATE EVENT IF NOT EXISTS cleanup_scan_sessions
-- ON SCHEDULE EVERY 1 HOUR
-- DO
-- BEGIN
--     DELETE FROM scan_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
--     DELETE FROM scan_session_registry WHERE expires_at < NOW();
-- END//
-- DELIMITER ;

SELECT 'Scanner Module Schema Created Successfully!' AS Status;
