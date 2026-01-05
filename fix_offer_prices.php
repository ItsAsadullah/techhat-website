<?php
require_once 'core/db.php';

try {
    // Update variant 3: Price 1599, Offer Price 1299
    $pdo->exec("UPDATE product_variants SET price = 1599.00, offer_price = 1299.00 WHERE id = 3");
    echo "Updated variant 3: Price 1599, Offer Price 1299\n";
    
    // Update variant 4: Price 1788, Offer Price 1699
    $pdo->exec("UPDATE product_variants SET price = 1788.00, offer_price = 1699.00 WHERE id = 4");
    echo "Updated variant 4: Price 1788, Offer Price 1699\n";
    
    echo "\nDone! Refresh the product page.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
