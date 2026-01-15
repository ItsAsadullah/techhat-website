<?php
/**
 * ================================================================
 * TechHat Shop - Scanner Module Setup
 * Run this file once to create all required tables
 * ================================================================
 * 
 * Usage: Access this file in browser or run from command line
 * URL: http://localhost/techhat/setup_scanner_module.php
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'core/db.php';

echo "<pre style='font-family: Consolas, monospace; background: #1e1e1e; color: #0f0; padding: 20px; border-radius: 8px;'>";
echo "================================================================\n";
echo "  TechHat Scanner Module - Database Setup\n";
echo "================================================================\n\n";

$errors = [];
$success = [];

try {
    // Note: We don't use transactions for DDL statements (CREATE TABLE, ALTER TABLE)
    // as they cause implicit commits in MySQL
    
    // ============================================
    // 1. ALTER PRODUCTS TABLE
    // ============================================
    echo "[1/8] Updating products table...\n";
    
    $alterProducts = [
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS product_type ENUM('simple', 'variable') NOT NULL DEFAULT 'simple' AFTER slug",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_months INT UNSIGNED DEFAULT 0 AFTER product_type",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_type VARCHAR(50) DEFAULT NULL AFTER warranty_months",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS has_serial TINYINT(1) NOT NULL DEFAULT 0 AFTER warranty_type",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS unit VARCHAR(30) DEFAULT 'pc' AFTER has_serial"
    ];
    
    foreach ($alterProducts as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore duplicate column errors
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
    }
    $success[] = "Products table updated";
    
    // ============================================
    // 2. ALTER PRODUCT VARIANTS TABLE
    // ============================================
    echo "[2/8] Updating product_variants table...\n";
    
    $alterVariants = [
        "ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS barcode VARCHAR(100) NULL AFTER sku",
        "ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS variant_image VARCHAR(255) NULL AFTER barcode",
        "ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS weight DECIMAL(10,3) DEFAULT NULL AFTER variant_image",
        "ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER weight"
    ];
    
    foreach ($alterVariants as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
    }
    
    // Add barcode index
    try {
        $pdo->exec("CREATE INDEX idx_variants_barcode ON product_variants(barcode)");
    } catch (PDOException $e) {
        // Index may already exist
    }
    $success[] = "Product variants table updated";
    
    // ============================================
    // 3. CREATE ATTRIBUTES TABLE
    // ============================================
    echo "[3/8] Creating attributes table...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attributes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            type ENUM('select', 'color', 'button') NOT NULL DEFAULT 'select',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_attributes_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "Attributes table created";
    
    // ============================================
    // 4. CREATE ATTRIBUTE VALUES TABLE
    // ============================================
    echo "[4/8] Creating attribute_values table...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attribute_values (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attribute_id INT UNSIGNED NOT NULL,
            value VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            color_code VARCHAR(20) DEFAULT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_attrval_attribute FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attr_value (attribute_id, slug),
            INDEX idx_attrval_attribute (attribute_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "Attribute values table created";
    
    // ============================================
    // 5. CREATE PRODUCT VARIANT ATTRIBUTES TABLE
    // ============================================
    echo "[5/8] Creating product_variant_attributes table...\n";
    
    // Note: Using INT (signed) to match existing product_variants.id type
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_variant_attributes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            variant_id INT NOT NULL,
            attribute_id INT UNSIGNED NOT NULL,
            attribute_value_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_variant_attribute (variant_id, attribute_id),
            INDEX idx_pva_variant (variant_id),
            INDEX idx_pva_attribute (attribute_id),
            INDEX idx_pva_attrvalue (attribute_value_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add foreign keys separately (safer approach)
    try {
        $pdo->exec("ALTER TABLE product_variant_attributes ADD CONSTRAINT fk_pva_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE");
    } catch (PDOException $e) { /* FK may already exist */ }
    
    try {
        $pdo->exec("ALTER TABLE product_variant_attributes ADD CONSTRAINT fk_pva_attribute FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE");
    } catch (PDOException $e) { /* FK may already exist */ }
    
    try {
        $pdo->exec("ALTER TABLE product_variant_attributes ADD CONSTRAINT fk_pva_attrvalue FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE");
    } catch (PDOException $e) { /* FK may already exist */ }
    
    $success[] = "Product variant attributes table created";
    
    // ============================================
    // 6. CREATE PRODUCT SERIALS TABLE
    // ============================================
    echo "[6/8] Creating product_serials table...\n";
    
    // Note: Using INT (signed) to match existing products.id and product_variants.id types
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_serials (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            variant_id INT NULL,
            serial_number VARCHAR(100) NOT NULL,
            status ENUM('available', 'sold', 'reserved', 'returned', 'damaged') NOT NULL DEFAULT 'available',
            purchase_id BIGINT NULL,
            sale_id BIGINT NULL,
            sale_type ENUM('online', 'pos') NULL,
            warranty_start DATE NULL,
            warranty_end DATE NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_serial (serial_number),
            INDEX idx_serial_product (product_id),
            INDEX idx_serial_variant (variant_id),
            INDEX idx_serial_status (status),
            INDEX idx_serial_sale (sale_id, sale_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add foreign keys separately
    try {
        $pdo->exec("ALTER TABLE product_serials ADD CONSTRAINT fk_serial_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE");
    } catch (PDOException $e) { /* FK may already exist */ }
    
    try {
        $pdo->exec("ALTER TABLE product_serials ADD CONSTRAINT fk_serial_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL");
    } catch (PDOException $e) { /* FK may already exist */ }
    
    $success[] = "Product serials table created";
    
    // ============================================
    // 7. CREATE SCAN SESSIONS TABLE
    // ============================================
    echo "[7/8] Creating scan_sessions table...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scan_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            scanned_code VARCHAR(255) NOT NULL,
            code_type ENUM('barcode', 'qrcode', 'serial', 'imei') NOT NULL DEFAULT 'barcode',
            is_consumed TINYINT(1) NOT NULL DEFAULT 0,
            device_info VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            consumed_at DATETIME NULL,
            INDEX idx_scan_session (session_id),
            INDEX idx_scan_consumed (is_consumed),
            INDEX idx_scan_created (created_at),
            INDEX idx_scan_session_consumed (session_id, is_consumed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "Scan sessions table created";
    
    // ============================================
    // 8. CREATE SCAN SESSION REGISTRY TABLE
    // ============================================
    echo "[8/8] Creating scan_session_registry table...\n";
    
    // Note: Using INT (signed) to match existing users.id type
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scan_session_registry (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL UNIQUE,
            user_id INT NULL,
            purpose ENUM('serial_entry', 'inventory', 'pos', 'lookup') NOT NULL DEFAULT 'serial_entry',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX idx_scanreg_active (is_active),
            INDEX idx_scanreg_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add foreign key separately
    try {
        $pdo->exec("ALTER TABLE scan_session_registry ADD CONSTRAINT fk_scanreg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    } catch (PDOException $e) { /* FK may already exist */ }
    
    $success[] = "Scan session registry table created";
    
    // ============================================
    // SEED DATA
    // ============================================
    echo "\nInserting seed data...\n";
    
    // Insert default attributes
    $pdo->exec("INSERT IGNORE INTO attributes (id, name, slug, type) VALUES
        (1, 'Color', 'color', 'color'),
        (2, 'Size', 'size', 'button'),
        (3, 'RAM', 'ram', 'select'),
        (4, 'Storage', 'storage', 'select'),
        (5, 'Material', 'material', 'select')
    ");
    
    // Insert attribute values - using existing table structure (attribute_id, value, display_order)
    // Insert color values
    $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES
        (1, 'Black', 1),
        (1, 'White', 2),
        (1, 'Red', 3),
        (1, 'Blue', 4),
        (1, 'Green', 5),
        (1, 'Gold', 6),
        (1, 'Silver', 7),
        (1, 'Space Gray', 8)
    ");
    
    // Insert RAM values
    $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES
        (3, '4GB', 1),
        (3, '6GB', 2),
        (3, '8GB', 3),
        (3, '12GB', 4),
        (3, '16GB', 5)
    ");
    
    // Insert Storage values
    $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES
        (4, '64GB', 1),
        (4, '128GB', 2),
        (4, '256GB', 3),
        (4, '512GB', 4),
        (4, '1TB', 5)
    ");
    
    // Insert Size values
    $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES
        (2, 'XS', 1),
        (2, 'S', 2),
        (2, 'M', 3),
        (2, 'L', 4),
        (2, 'XL', 5),
        (2, 'XXL', 6)
    ");
    
    $success[] = "Seed data inserted";
    
    // Setup completed successfully
    echo "\n================================================================\n";
    echo "  SETUP COMPLETED SUCCESSFULLY!\n";
    echo "================================================================\n\n";
    
    echo "✓ " . count($success) . " operations completed:\n";
    foreach ($success as $msg) {
        echo "  • {$msg}\n";
    }
    
    echo "\n================================================================\n";
    echo "  NEXT STEPS:\n";
    echo "================================================================\n";
    echo "1. Access: http://localhost/techhat/admin/add-product.php\n";
    echo "2. Create a product with serial tracking enabled\n";
    echo "3. Click 'Connect Mobile Scanner' to generate QR code\n";
    echo "4. Scan QR with mobile phone to open scanner\n";
    echo "5. Scan barcodes - they appear on PC automatically!\n";
    echo "================================================================\n";
    
} catch (Exception $e) {
    echo "\n================================================================\n";
    echo "  ERROR OCCURRED!\n";
    echo "================================================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "• Database connection settings in core/config.php\n";
    echo "• MySQL user has CREATE/ALTER permissions\n";
    echo "• Existing tables for conflicts\n";
    echo "================================================================\n";
}

echo "</pre>";

// Link to add product page
echo "<br><a href='admin/add-product.php' style='display: inline-block; padding: 12px 24px; background: #4f46e5; color: white; text-decoration: none; border-radius: 8px; font-family: sans-serif;'>→ Go to Add Product Page</a>";
?>
