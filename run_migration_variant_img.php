<?php
require_once 'core/db.php';

try {
    $sql = file_get_contents('add_variant_image.sql');
    $pdo->exec($sql);
    echo "Migration successful: Added image_path to product_variants.";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>
