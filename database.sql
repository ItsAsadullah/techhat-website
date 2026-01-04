-- TechHat ecommerce + POS + accounting schema (shared hosting ready)
-- Charset: utf8mb4, Engine: InnoDB
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    status TINYINT(1) NOT NULL DEFAULT 1,
    image VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories (supports parent/child)
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description LONGTEXT,
    video_url VARCHAR(255),
    is_flash_sale TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_products_category (category_id),
    INDEX idx_products_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product variants (price + stock)
CREATE TABLE IF NOT EXISTS product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(80),
    price DECIMAL(12,2) NOT NULL,
    offer_price DECIMAL(12,2) DEFAULT NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    expense DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_quantity INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_variants_product (product_id),
    INDEX idx_variants_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images
CREATE TABLE IF NOT EXISTS product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_thumbnail TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock movements (single source of truth)
CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    quantity INT NOT NULL,
    movement_type ENUM('in','out') NOT NULL,
    source ENUM('online','pos','adjustment','return','correction') NOT NULL DEFAULT 'adjustment',
    reference_table VARCHAR(50),
    reference_id BIGINT UNSIGNED NULL,
    note VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_stock_variant (variant_id),
    INDEX idx_stock_product (product_id),
    INDEX idx_stock_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    status ENUM('pending','processing','delivered','cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_status ENUM('pending','paid','failed','cod') NOT NULL DEFAULT 'pending',
    transaction_id VARCHAR(120),
    total_amount DECIMAL(12,2) NOT NULL,
    shipping_address TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounting: income and expense
CREATE TABLE IF NOT EXISTS accounts_income (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(120),
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_income_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts_expense (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(120),
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expense_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items
CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_order_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POS sales
CREATE TABLE IF NOT EXISTS pos_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_user_id INT UNSIGNED NULL,
    customer_name VARCHAR(100) NULL,
    customer_phone VARCHAR(20) NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
    note VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pos_staff FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pos_staff (staff_user_id),
    INDEX idx_pos_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POS sale items
CREATE TABLE IF NOT EXISTS pos_sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pos_sale_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    quantity INT NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_pos_items_sale FOREIGN KEY (pos_sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_pos_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_pos_items_sale (pos_sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounting: income
CREATE TABLE IF NOT EXISTS accounts_income (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(120),
    amount DECIMAL(12,2) NOT NULL,
    reference_table VARCHAR(50),
    reference_id BIGINT UNSIGNED NULL,
    note VARCHAR(255),
    txn_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_income_date (txn_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounting: expense
CREATE TABLE IF NOT EXISTS accounts_expense (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(120),
    amount DECIMAL(12,2) NOT NULL,
    note VARCHAR(255),
    txn_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expense_date (txn_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Flash sale campaigns
CREATE TABLE IF NOT EXISTS flash_sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT NULL,
    start_at DATETIME,
    end_at DATETIME,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Flash sale items (optional per product/variant)
CREATE TABLE IF NOT EXISTS flash_sale_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flash_sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    discount_percentage DECIMAL(5,2) DEFAULT NULL,
    CONSTRAINT fk_flash_items_sale FOREIGN KEY (flash_sale_id) REFERENCES flash_sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_flash_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_flash_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    INDEX idx_flash_items_sale (flash_sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Banners
CREATE TABLE IF NOT EXISTS banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    image_path VARCHAR(255) NOT NULL,
    link_url VARCHAR(255),
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: admin user (password: password)
INSERT IGNORE INTO users (id, name, email, password, role, status) VALUES
(1, 'Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Seed: a couple categories
INSERT IGNORE INTO categories (id, parent_id, name, slug) VALUES
(1, NULL, 'Electronics', 'electronics'),
(2, 1, 'Mobiles', 'mobiles'),
(3, NULL, 'Fashion', 'fashion');
