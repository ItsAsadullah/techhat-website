<?php
require_once 'core/db.php';

echo "Adding default attribute values...\n";

try {
    // Check if we have any attribute values
    $count = $pdo->query("SELECT COUNT(*) FROM attribute_values")->fetchColumn();
    
    if ($count == 0) {
        // Get attribute IDs
        $colorId = $pdo->query("SELECT id FROM attributes WHERE slug = 'color'")->fetchColumn();
        $sizeId = $pdo->query("SELECT id FROM attributes WHERE slug = 'size'")->fetchColumn();
        $ramId = $pdo->query("SELECT id FROM attributes WHERE slug = 'ram'")->fetchColumn();
        $storageId = $pdo->query("SELECT id FROM attributes WHERE slug = 'storage'")->fetchColumn();
        
        // Add Color values
        if ($colorId) {
            $colors = ['Black', 'White', 'Red', 'Blue', 'Green', 'Gold', 'Silver', 'Purple', 'Pink', 'Gray'];
            foreach ($colors as $i => $color) {
                $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES ($colorId, '$color', $i)");
            }
            echo "✓ Added color values\n";
        }
        
        // Add Size values
        if ($sizeId) {
            $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '38', '40', '42', '44'];
            foreach ($sizes as $i => $size) {
                $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES ($sizeId, '$size', $i)");
            }
            echo "✓ Added size values\n";
        }
        
        // Add RAM values
        if ($ramId) {
            $rams = ['2GB', '3GB', '4GB', '6GB', '8GB', '12GB', '16GB', '32GB'];
            foreach ($rams as $i => $ram) {
                $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES ($ramId, '$ram', $i)");
            }
            echo "✓ Added RAM values\n";
        }
        
        // Add Storage values
        if ($storageId) {
            $storages = ['16GB', '32GB', '64GB', '128GB', '256GB', '512GB', '1TB', '2TB'];
            foreach ($storages as $i => $storage) {
                $pdo->exec("INSERT IGNORE INTO attribute_values (attribute_id, value, display_order) VALUES ($storageId, '$storage', $i)");
            }
            echo "✓ Added storage values\n";
        }
        
        echo "\n✅ All attribute values added!\n";
    } else {
        echo "✓ Attribute values already exist ($count values found)\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
