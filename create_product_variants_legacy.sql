-- Create product_variants_legacy table if it doesn't exist
-- This table is used for backward compatibility with older variant system

CREATE TABLE IF NOT EXISTS `product_variants_legacy` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(10) unsigned NOT NULL,
    `name` varchar(255) DEFAULT 'Default',
    `sku` varchar(100) DEFAULT NULL,
    `price` decimal(10,2) NOT NULL DEFAULT 0.00,
    `offer_price` decimal(10,2) DEFAULT NULL,
    `stock_quantity` int(11) NOT NULL DEFAULT 0,
    `status` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
