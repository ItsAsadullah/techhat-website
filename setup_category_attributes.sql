-- Setup category attributes for testing
-- Mobile category (id=6)
INSERT IGNORE INTO category_attributes (category_id, attribute_id, sort_order)
SELECT 6, id, 
  CASE WHEN name = 'Color' THEN 1
       WHEN name = 'Storage' THEN 2
       WHEN name = 'RAM' THEN 3
       ELSE 10 END
FROM attributes WHERE name IN ('Color', 'Storage', 'RAM');

-- Charger category (id=11)
INSERT IGNORE INTO category_attributes (category_id, attribute_id, sort_order)
SELECT 11, id, 
  CASE WHEN name = 'Wattage' THEN 1
       WHEN name = 'Color' THEN 2
       ELSE 10 END
FROM attributes WHERE name IN ('Wattage', 'Color');

-- Headphone category (id=8)
INSERT IGNORE INTO category_attributes (category_id, attribute_id, sort_order)
SELECT 8, id, 
  CASE WHEN name = 'Color' THEN 1
       WHEN name = 'Driver Size' THEN 2
       ELSE 10 END
FROM attributes WHERE name IN ('Color', 'Driver Size');

-- Verify
SELECT ca.category_id, c.name as category, a.name as attribute, ca.sort_order
FROM category_attributes ca
JOIN attributes a ON ca.attribute_id = a.id
JOIN categories c ON ca.category_id = c.id
ORDER BY ca.category_id, ca.sort_order;
