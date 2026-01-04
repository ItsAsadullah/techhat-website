-- Banner Images Table
CREATE TABLE IF NOT EXISTS `banner_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `subtitle` varchar(300) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample banners
INSERT INTO `banner_images` (`image_path`, `title`, `subtitle`, `link_url`, `button_text`, `is_active`, `display_order`) VALUES
('uploads/banners/banner1.jpg', 'Big Sale', 'Up to 50% OFF on Selected Items', 'category.php', 'Shop Now', 1, 1),
('uploads/banners/banner2.jpg', 'New Arrivals', 'Check out our latest products', 'category.php', 'Explore', 1, 2),
('uploads/banners/banner3.jpg', 'Flash Deals', 'Limited Time Offers', 'category.php', 'Grab Now', 1, 3);
