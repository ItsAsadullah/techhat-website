ALTER TABLE products ADD COLUMN specifications LONGTEXT DEFAULT NULL AFTER description;
ALTER TABLE products ADD COLUMN model VARCHAR(100) DEFAULT NULL AFTER specifications;
ALTER TABLE products ADD COLUMN country_of_origin VARCHAR(100) DEFAULT NULL AFTER model;
ALTER TABLE products ADD COLUMN manufacturer VARCHAR(100) DEFAULT NULL AFTER country_of_origin;
