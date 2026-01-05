<?php
require_once 'core/db.php';

$slug = 'electronics-ultrabook-pro-14';

// Fetch Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE slug = ?");
$stmt->execute([$slug]);
$product = $stmt->fetch();

// Fetch Variants
$stmtVar = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND status = 1");
$stmtVar->execute([$product['id']]);
$variants = $stmtVar->fetchAll();

// Process like in product.php
$unique_colors = [];
foreach ($variants as $vv) {
    $color = isset($vv['color']) ? trim((string) $vv['color']) : '';
    $color_code = isset($vv['color_code']) ? trim((string) $vv['color_code']) : '';
    
    if (empty($color_code) && !empty($color)) {
        $color_code = $color;
    }
    
    $color_identifier = !empty($color) ? $color : $color_code;
    
    $v_data = [
        'color' => $color_identifier !== '' ? $color_identifier : null,
        'color_display' => $color !== '' ? $color : $color_code,
        'color_code' => $color_code !== '' ? $color_code : null,
    ];
    
    if ($v_data['color']) {
        $unique_colors[$v_data['color']] = [
            'name' => $v_data['color_display'],
            'code' => $v_data['color_code'],
        ];
    }
}

echo "Unique Colors Array:\n";
print_r($unique_colors);
?>
