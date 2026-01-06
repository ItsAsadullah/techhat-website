-- Add sample attribute values for testing - Fixed version

-- Get Color attribute ID first
SET @color_id = (SELECT id FROM attributes WHERE name = 'Color');
SET @storage_id = (SELECT id FROM attributes WHERE name = 'Storage');
SET @ram_id = (SELECT id FROM attributes WHERE name = 'RAM');
SET @wattage_id = (SELECT id FROM attributes WHERE name = 'Wattage');

-- Color values
INSERT IGNORE INTO attribute_values (attribute_id, value) VALUES
(@color_id, 'Red'),
(@color_id, 'Blue'),
(@color_id, 'Black'),
(@color_id, 'White'),
(@color_id, 'Silver'),
(@color_id, 'Gold'),
(@color_id, 'Green');

-- Storage values
INSERT IGNORE INTO attribute_values (attribute_id, value) VALUES
(@storage_id, '64GB'),
(@storage_id, '128GB'),
(@storage_id, '256GB'),
(@storage_id, '512GB'),
(@storage_id, '1TB');

-- RAM values
INSERT IGNORE INTO attribute_values (attribute_id, value) VALUES
(@ram_id, '4GB'),
(@ram_id, '6GB'),
(@ram_id, '8GB'),
(@ram_id, '12GB'),
(@ram_id, '16GB');

-- Wattage values
INSERT IGNORE INTO attribute_values (attribute_id, value) VALUES
(@wattage_id, '5W'),
(@wattage_id, '10W'),
(@wattage_id, '20W'),
(@wattage_id, '30W'),
(@wattage_id, '65W');

-- Verify
SELECT a.name, GROUP_CONCAT(av.value SEPARATOR ', ') as attr_values
FROM attributes a
LEFT JOIN attribute_values av ON a.id = av.attribute_id
GROUP BY a.id, a.name
ORDER BY a.name;
