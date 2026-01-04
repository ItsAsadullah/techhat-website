-- Create services table for digital services
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT 'General',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample services
INSERT INTO services (name, description, price, category) VALUES
('Computer Repair', 'কম্পিউটার মেরামত সেবা', 500.00, 'Technical'),
('Facebook Setup', 'ফেসবুক একাউন্ট সেটআপ', 200.00, 'Digital Marketing'),
('Video Editing', 'ভিডিও এডিটিং সেবা', 1000.00, 'Multimedia'),
('Graphic Design', 'গ্রাফিক ডিজাইন সেবা', 800.00, 'Design'),
('Software Installation', 'সফটওয়্যার ইনস্টলেশন', 300.00, 'Technical'),
('Data Recovery', 'ডাটা রিকভারি সেবা', 1500.00, 'Technical'),
('Social Media Management', 'সোশ্যাল মিডিয়া ম্যানেজমেন্ট', 2000.00, 'Digital Marketing'),
('Photo Editing', 'ফটো এডিটিং', 400.00, 'Multimedia');

-- Add service_items to track services in POS sales
CREATE TABLE IF NOT EXISTS service_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    service_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 1,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sale_id (sale_id),
    INDEX idx_service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
