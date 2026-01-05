<?php
require_once 'core/db.php';

// Check variant data for the product in screenshot
$stmt = $pdo->query("
    SELECT p.id, p.title, pv.id as variant_id, pv.name, pv.color, pv.color_code, pv.storage, pv.size, pv.sim_type 
    FROM products p 
    JOIN product_variants pv ON p.id = pv.product_id 
    WHERE p.slug = 'electronics-ultrabook-pro-14' 
    ORDER BY pv.id
");

echo "Product Variants for Ultrabook Pro 14:\n";
echo "=====================================\n\n";

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Variant ID: " . $row['variant_id'] . "\n";
    echo "Name: " . $row['name'] . "\n";
    echo "Color: " . ($row['color'] ?? 'NULL') . "\n";
    echo "Color Code: " . ($row['color_code'] ?? 'NULL') . "\n";
    echo "Storage: " . ($row['storage'] ?? 'NULL') . "\n";
    echo "Size: " . ($row['size'] ?? 'NULL') . "\n";
    echo "Sim Type: " . ($row['sim_type'] ?? 'NULL') . "\n";
    echo "---\n";
}
?>
