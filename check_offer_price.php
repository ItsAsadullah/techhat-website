<?php
require_once 'core/db.php';

$stmt = $pdo->query("
    SELECT id, name, price, offer_price, storage 
    FROM product_variants 
    WHERE product_id = (SELECT id FROM products WHERE slug = 'electronics-ultrabook-pro-14')
    ORDER BY id
");

echo "Variant Data:\n";
echo "ID | Name | Price | Offer Price | Storage\n";
echo "-------------------------------------------\n";

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("%d | %s | %.2f | %s | %s\n", 
        $row['id'], 
        $row['name'], 
        $row['price'], 
        $row['offer_price'] ?? 'NULL', 
        $row['storage'] ?? 'N/A'
    );
}
?>
