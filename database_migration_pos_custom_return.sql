-- Migration for POS Custom Price & Returns Feature
-- Date: 2026-01-03

-- Add commission column to pos_sales (if not exists)
ALTER TABLE pos_sales 
ADD COLUMN IF NOT EXISTS commission DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount,
ADD COLUMN IF NOT EXISTS discount DECIMAL(10,2) DEFAULT 0.00 AFTER commission;

-- Create pos_returns table
CREATE TABLE IF NOT EXISTS pos_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pos_sale_id INT NOT NULL,
    return_amount DECIMAL(10,2) NOT NULL,
    return_reason TEXT,
    returned_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pos_sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
    INDEX idx_sale_id (pos_sale_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create pos_return_items table for detailed tracking
CREATE TABLE IF NOT EXISTS pos_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pos_return_id INT NOT NULL,
    pos_sale_item_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pos_return_id) REFERENCES pos_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (pos_sale_item_id) REFERENCES pos_sale_items(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update pos_sale_items to store original price for commission calculation
ALTER TABLE pos_sale_items 
ADD COLUMN original_price DECIMAL(10,2) DEFAULT 0.00 AFTER price,
ADD COLUMN custom_price DECIMAL(10,2) DEFAULT 0.00 AFTER original_price;
