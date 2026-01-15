-- Create services table for digital services
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT 'General',
    duration VARCHAR(50), -- e.g., '2 hours', '1 day'
    turnaround_time VARCHAR(100), -- e.g., '24-48 hours'
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample services
INSERT INTO services (name, description, price, category, duration, turnaround_time) VALUES
('Computer Repair', 'কম্পিউটার মেরামত সেবা', 500.00, 'Technical', '1-3 hours', 'Same Day'),
('Facebook Setup', 'ফেসবুক একাউন্ট সেটআপ', 200.00, 'Digital Marketing', '30 minutes', 'Within 1 hour'),
('Video Editing', 'ভিডিও এডিটিং সেবা', 1000.00, 'Multimedia', '2 hours', '1-2 Days'),
('Graphic Design', 'গ্রাফিক ডিজাইন সেবা', 800.00, 'Design', '1 hour', '24 hours'),
('Software Installation', 'সফটওয়্যার ইনস্টলেশন', 300.00, 'Technical', '30 minutes', 'Within 1 hour'),
('Data Recovery', 'ডাটা রিকভারি সেবা', 1500.00, 'Technical', 'Varies', '2-5 Days'),
('Social Media Management', 'সোশ্যাল মিডিয়া ম্যানেজমেন্ট', 2000.00, 'Digital Marketing', 'Monthly', 'Ongoing'),
('Photo Editing', 'ফটো এডিটিং', 400.00, 'Multimedia', '15 minutes per photo', '24 hours');

-- Add service_items to track services in POS sales
CREATE TABLE IF NOT EXISTS service_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    service_id INT NOT NULL,
    service_provider_id INT UNSIGNED, -- To track who did the service
    service_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 1,
    subtotal DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sale_id (sale_id),
    INDEX idx_service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
