-- Drop existing tables to recreate with proper encoding
DROP TABLE IF EXISTS purchase_items;
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS suppliers;

-- Suppliers/Vendors Table
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    company_name VARCHAR(200),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    balance DECIMAL(12, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchases Table
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    paid_amount DECIMAL(12, 2) DEFAULT 0.00,
    payment_status ENUM('paid', 'due', 'partial') DEFAULT 'due',
    payment_method VARCHAR(50),
    memo_file VARCHAR(255),
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Items Table
CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample suppliers with Bengali names
INSERT INTO suppliers (name, company_name, phone, email, address) VALUES
('আব্দুর রহমান', 'Rahman Electronics', '01711111111', 'rahman@example.com', 'ঢাকা, বাংলাদেশ'),
('কামাল উদ্দিন', 'Kamal Trading', '01722222222', 'kamal@example.com', 'চট্টগ্রাম, বাংলাদেশ');
