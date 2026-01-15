<?php
require_once 'core/db.php';

try {
    // Add color_code
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN color_code VARCHAR(20) DEFAULT NULL AFTER color");
    echo "Added color_code to product_variants.\n";
} catch (PDOException $e) {
    echo "color_code error: " . $e->getMessage() . "\n";
}

try {
    // Add storage
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN storage VARCHAR(50) DEFAULT NULL AFTER size");
    echo "Added storage to product_variants.\n";
} catch (PDOException $e) {
    echo "storage error: " . $e->getMessage() . "\n";
}

try {
    // Add sim_type
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN sim_type VARCHAR(50) DEFAULT NULL AFTER storage");
    echo "Added sim_type to product_variants.\n";
} catch (PDOException $e) {
    echo "sim_type error: " . $e->getMessage() . "\n";
}

try {
    // Add ram
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN ram VARCHAR(50) DEFAULT NULL AFTER sim_type");
    echo "Added ram to product_variants.\n";
} catch (PDOException $e) {
    echo "ram error: " . $e->getMessage() . "\n";
}

try {
    // Add display_type
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN display_type VARCHAR(100) DEFAULT NULL AFTER ram");
    echo "Added display_type to product_variants.\n";
} catch (PDOException $e) {
    echo "display_type error: " . $e->getMessage() . "\n";
}

try {
    // Add processor
    $pdo->exec("ALTER TABLE product_variants ADD COLUMN processor VARCHAR(100) DEFAULT NULL AFTER display_type");
    echo "Added processor to product_variants.\n";
} catch (PDOException $e) {
    echo "processor error: " . $e->getMessage() . "\n";
}
?>