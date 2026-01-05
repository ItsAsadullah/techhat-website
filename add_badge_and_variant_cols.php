<?php
require_once 'core/db.php';

try {
    $pdo->exec("ALTER TABLE products ADD COLUMN badge_text VARCHAR(50) DEFAULT NULL AFTER is_flash_sale");
    echo "Added badge_text to products.\n";
} catch (PDOException $e) {
    echo "badge_text might already exist or error: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN color VARCHAR(50) DEFAULT NULL AFTER name");
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN size VARCHAR(50) DEFAULT NULL AFTER color");
    echo "Added color and size to product_variants.\n";
} catch (PDOException $e) {
    echo "color/size might already exist or error: " . $e->getMessage() . "\n";
}
?>