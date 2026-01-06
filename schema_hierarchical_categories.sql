-- Hierarchical Categories Table (supports unlimited nesting)
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    parent_id INT DEFAULT NULL,
    level INT DEFAULT 0,
    display_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_parent_id (parent_id),
    INDEX idx_is_active (is_active),
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attributes Table (Color, Size, Storage, RAM, Wattage, etc.)
CREATE TABLE IF NOT EXISTS attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) UNIQUE,
    type ENUM('select', 'multiselect', 'text', 'number', 'color') DEFAULT 'select',
    description TEXT,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attribute Values (e.g., "Red", "Blue" for Color attribute)
CREATE TABLE IF NOT EXISTS attribute_values (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attribute_id INT NOT NULL,
    value VARCHAR(255) NOT NULL,
    color_code VARCHAR(7),
    display_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attr_value (attribute_id, value),
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    INDEX idx_attribute_id (attribute_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Category-Attribute Mapping (Which attributes apply to which categories)
CREATE TABLE IF NOT EXISTS category_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    attribute_id INT NOT NULL,
    is_required TINYINT DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cat_attr (category_id, attribute_id),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    INDEX idx_category_id (category_id),
    INDEX idx_attribute_id (attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Data: Root Categories
INSERT INTO categories (name, slug, parent_id, level, display_order) VALUES
('Electronics', 'electronics', NULL, 0, 1),
('Fashion', 'fashion', NULL, 0, 2),
('Home & Garden', 'home-garden', NULL, 0, 3);

-- Sample Data: Sub Categories
INSERT INTO categories (name, slug, parent_id, level, display_order) VALUES
('Mobile Phones', 'mobile-phones', 1, 1, 1),
('Laptops', 'laptops', 1, 1, 2),
('Accessories', 'accessories', 1, 1, 3),
('Men Clothing', 'men-clothing', 2, 1, 1),
('Women Clothing', 'women-clothing', 2, 1, 2);

-- Sample Data: Attributes
INSERT INTO attributes (name, slug, type) VALUES
('Color', 'color', 'select'),
('Size', 'size', 'select'),
('Storage', 'storage', 'select'),
('RAM', 'ram', 'select'),
('Brand', 'brand', 'select'),
('Material', 'material', 'select');

-- Sample Data: Color Values
INSERT INTO attribute_values (attribute_id, value, color_code) VALUES
(1, 'Black', '#000000'),
(1, 'White', '#FFFFFF'),
(1, 'Red', '#FF0000'),
(1, 'Blue', '#0000FF'),
(1, 'Green', '#008000'),
(1, 'Gold', '#FFD700'),
(1, 'Silver', '#C0C0C0');

-- Sample Data: Size Values
INSERT INTO attribute_values (attribute_id, value) VALUES
(2, 'XS'),
(2, 'S'),
(2, 'M'),
(2, 'L'),
(2, 'XL'),
(2, 'XXL');

-- Sample Data: Storage Values
INSERT INTO attribute_values (attribute_id, value) VALUES
(3, '64GB'),
(3, '128GB'),
(3, '256GB'),
(3, '512GB'),
(3, '1TB');

-- Sample Data: RAM Values
INSERT INTO attribute_values (attribute_id, value) VALUES
(4, '2GB'),
(4, '4GB'),
(4, '6GB'),
(4, '8GB'),
(4, '12GB'),
(4, '16GB');

-- Link categories to attributes (Electronics category needs Color, Storage, RAM)
INSERT INTO category_attributes (category_id, attribute_id, is_required, display_order) VALUES
(4, 1, 1, 1), -- Mobile Phones: Color (required)
(4, 3, 0, 2), -- Mobile Phones: Storage
(4, 4, 0, 3), -- Mobile Phones: RAM
(5, 1, 0, 1), -- Laptops: Color
(5, 3, 0, 2), -- Laptops: Storage
(5, 4, 1, 3); -- Laptops: RAM (required)
