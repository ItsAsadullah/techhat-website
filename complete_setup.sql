-- TechHat Quick Setup - Complete Database Schema
-- This file consolidates all necessary tables

-- Disable checks temporarily
SET FOREIGN_KEY_CHECKS=0;

-- ============================================================================
-- Users
-- ============================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(120) NOT NULL,
    `email` varchar(190) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `phone` varchar(30),
    `role` enum('admin','user') NOT NULL DEFAULT 'user',
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `image` varchar(255),
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Categories (Parent-Child Support)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `parent_id` int(11) DEFAULT NULL,
    `name` varchar(255) NOT NULL,
    `slug` varchar(255) UNIQUE,
    `description` text,
    `display_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `parent_id` (`parent_id`),
    KEY `is_active` (`is_active`),
    FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Brands
-- ============================================================================
CREATE TABLE IF NOT EXISTS `brands` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `slug` varchar(120) UNIQUE,
    `image` varchar(255),
    `is_featured` tinyint(1) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Attributes (Color, Size, RAM, etc.)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `attributes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL UNIQUE,
    `slug` varchar(255),
    `type` varchar(50) DEFAULT 'select',
    `description` text,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Attribute Values (Red, Blue, 8GB, etc.)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `attribute_values` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `attribute_id` int(11) NOT NULL,
    `value` varchar(255) NOT NULL,
    `display_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_attr_value` (`attribute_id`, `value`),
    KEY `attribute_id` (`attribute_id`),
    FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Category-Attribute Mapping
-- ============================================================================
CREATE TABLE IF NOT EXISTS `category_attributes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category_id` int(11) NOT NULL,
    `attribute_id` int(11) NOT NULL,
    `is_required` tinyint(1) DEFAULT 0,
    `display_order` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_cat_attr` (`category_id`, `attribute_id`),
    KEY `category_id` (`category_id`),
    KEY `attribute_id` (`attribute_id`),
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Products
-- ============================================================================
CREATE TABLE IF NOT EXISTS `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category_id` int(11),
    `brand_id` int(11),
    `title` varchar(255) NOT NULL,
    `name` varchar(255) DEFAULT NULL,
    `slug` varchar(255) UNIQUE,
    `description` longtext,
    `video_url` varchar(255),
    `is_flash_sale` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `vendor_id` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category_id` (`category_id`),
    KEY `brand_id` (`brand_id`),
    KEY `is_active` (`is_active`),
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Product Images
-- ============================================================================
CREATE TABLE IF NOT EXISTS `product_images` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `image_path` varchar(255) NOT NULL,
    `is_primary` tinyint(1) DEFAULT 0,
    `is_thumbnail` tinyint(1) DEFAULT 0,
    `sort_order` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Product Variations (NEW - JSON based)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `product_variations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `sku` varchar(100),
    `variation_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
    `purchase_price` decimal(12,2) DEFAULT 0.00,
    `extra_cost` decimal(12,2) DEFAULT 0.00,
    `selling_price` decimal(12,2) DEFAULT 0.00,
    `old_price` decimal(12,2),
    `stock_qty` int(11) DEFAULT 0,
    `image` varchar(255),
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_sku` (`sku`),
    KEY `product_id` (`product_id`),
    KEY `is_active` (`is_active`),
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Product Variants (LEGACY - for backward compatibility)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `name` varchar(150),
    `sku` varchar(80),
    `price` decimal(12,2) NOT NULL,
    `offer_price` decimal(12,2),
    `cost_price` decimal(12,2) DEFAULT 0,
    `expense` decimal(12,2) DEFAULT 0,
    `stock_quantity` int(11) DEFAULT 0,
    `status` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    KEY `sku` (`sku`),
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Orders
-- ============================================================================
CREATE TABLE IF NOT EXISTS `orders` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` int(11),
    `status` enum('pending','processing','delivered','cancelled') DEFAULT 'pending',
    `payment_method` varchar(50),
    `payment_status` enum('pending','paid','failed','cod') DEFAULT 'pending',
    `transaction_id` varchar(120),
    `total_amount` decimal(12,2) NOT NULL,
    `shipping_address` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Order Items
-- ============================================================================
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `order_id` bigint(20) NOT NULL,
    `product_id` int(11),
    `variant_id` int(11),
    `quantity` int(11) NOT NULL,
    `unit_price` decimal(12,2) NOT NULL,
    `line_total` decimal(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`),
    KEY `product_id` (`product_id`),
    KEY `variant_id` (`variant_id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Stock Movements
-- ============================================================================
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `product_id` int(11),
    `variant_id` int(11),
    `quantity` int(11) NOT NULL,
    `movement_type` enum('in','out') NOT NULL,
    `source` enum('online','pos','adjustment','return','correction') DEFAULT 'adjustment',
    `reference_table` varchar(50),
    `reference_id` bigint(20),
    `note` varchar(255),
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `variant_id` (`variant_id`),
    KEY `product_id` (`product_id`),
    KEY `created_at` (`created_at`),
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Banner Images
-- ============================================================================
CREATE TABLE IF NOT EXISTS `banner_images` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `image_path` varchar(255) NOT NULL,
    `link` varchar(500),
    `title` varchar(255),
    `description` text,
    `display_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Homepage Settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS `homepage_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(255) UNIQUE NOT NULL,
    `setting_value` longtext,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Reviews
-- ============================================================================
CREATE TABLE IF NOT EXISTS `product_reviews` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `user_id` int(11),
    `rating` tinyint(4) NOT NULL DEFAULT 5,
    `review_text` text,
    `is_verified` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    KEY `user_id` (`user_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Wishlist
-- ============================================================================
CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_product` (`user_id`, `product_id`),
    KEY `user_id` (`user_id`),
    KEY `product_id` (`product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POS Sales
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pos_sales` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `staff_user_id` int(11),
    `customer_name` varchar(100),
    `customer_phone` varchar(20),
    `total_amount` decimal(12,2) NOT NULL,
    `payment_method` varchar(50) DEFAULT 'cash',
    `note` varchar(255),
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `staff_user_id` (`staff_user_id`),
    KEY `created_at` (`created_at`),
    FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POS Sale Items
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pos_sale_items` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `pos_sale_id` bigint(20) NOT NULL,
    `product_id` int(11),
    `variant_id` int(11),
    `quantity` int(11) NOT NULL,
    `price` decimal(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `pos_sale_id` (`pos_sale_id`),
    FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Purchases
-- ============================================================================
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `supplier_id` int(11),
    `total_amount` decimal(12,2) NOT NULL,
    `status` enum('pending','completed','cancelled') DEFAULT 'pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Purchase Items
-- ============================================================================
CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `purchase_id` bigint(20) NOT NULL,
    `product_id` int(11),
    `quantity` int(11) NOT NULL,
    `unit_price` decimal(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `purchase_id` (`purchase_id`),
    FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Services
-- ============================================================================
CREATE TABLE IF NOT EXISTS `services` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `price` decimal(12,2) DEFAULT 0.00,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Service Items
-- ============================================================================
CREATE TABLE IF NOT EXISTS `service_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `service_id` int(11) NOT NULL,
    `order_id` bigint(20),
    `quantity` int(11) DEFAULT 1,
    `price` decimal(12,2) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Returns
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pos_returns` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `pos_sale_id` bigint(20),
    `reason` varchar(255),
    `total_return_amount` decimal(12,2) NOT NULL,
    `status` enum('pending','approved','rejected') DEFAULT 'pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Return Items
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pos_return_items` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `return_id` bigint(20) NOT NULL,
    `product_id` int(11),
    `quantity` int(11) NOT NULL,
    `return_amount` decimal(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`return_id`) REFERENCES `pos_returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Accounting
-- ============================================================================
CREATE TABLE IF NOT EXISTS `accounts_income` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `source` varchar(120),
    `amount` decimal(12,2) NOT NULL,
    `description` text,
    `date` date NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounts_expense` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category` varchar(120),
    `amount` decimal(12,2) NOT NULL,
    `description` text,
    `date` date NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;
