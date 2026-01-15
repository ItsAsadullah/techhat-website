ALTER TABLE product_variants ADD COLUMN image_path VARCHAR(255) NULL AFTER name;
ALTER TABLE product_variants ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER image_path;
ALTER TABLE product_variants ADD COLUMN image_alt_text VARCHAR(255) NULL AFTER thumbnail_path;
ALTER TABLE product_variants ADD COLUMN gallery JSON NULL AFTER image_alt_text;
