-- Homepage Settings Table
CREATE TABLE IF NOT EXISTS `homepage_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default SEO content
INSERT INTO `homepage_settings` (`setting_key`, `setting_value`) VALUES
('seo_title', 'TechHat - Your Premier Electronics & Gadgets Store in Bangladesh'),
('seo_description', 'TechHat is Bangladesh\'s leading destination for premium electronics, cutting-edge gadgets, and innovative green energy solutions. We specialize in providing high-quality products including smartphones, laptops, gaming accessories, audio devices, and our comprehensive range of renewable energy systems.'),
('seo_extended_text', 'Our commitment to sustainability drives our extensive collection of solar panels, wind power systems, renewable energy solutions, and energy-efficient appliances. Whether you\'re looking for the latest technology or eco-friendly alternatives, TechHat offers fast delivery, genuine products, and exceptional customer service across all districts of Bangladesh.'),
('seo_features', '100% Genuine Products|Fast Nationwide Delivery|Green Energy Solutions|Official Warranty'),
('footer_about', 'Your premier destination for quality electronics and gadgets. We deliver excellence in every product.'),
('footer_phone', '09678-300400'),
('footer_email', 'info@techhat.com'),
('footer_address', 'Dhaka, Bangladesh'),
('footer_hours', '10:00 AM - 11:00 PM'),
('site_name', 'TechHat')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
