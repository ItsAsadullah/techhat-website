<?php
require_once 'core/db.php';

try {
    // 1. Add Warranty Columns to Products
    $pdo->exec("ALTER TABLE products ADD COLUMN warranty_type VARCHAR(50) DEFAULT 'No Warranty' AFTER badge_text");
    $pdo->exec("ALTER TABLE products ADD COLUMN warranty_period VARCHAR(50) DEFAULT NULL AFTER warranty_type");
    echo "Added warranty columns to products table.\n";
} catch (PDOException $e) {
    echo "Warranty columns might already exist: " . $e->getMessage() . "\n";
}

try {
    // 2. Insert Delivery Settings
    $settings = [
        'home_district' => 'Jhenaidah',
        'delivery_charge_inside' => '70',
        'delivery_charge_outside' => '150'
    ];

    $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    foreach ($settings as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    echo "Inserted/Updated delivery settings.\n";

} catch (PDOException $e) {
    echo "Error updating settings: " . $e->getMessage() . "\n";
}
?>