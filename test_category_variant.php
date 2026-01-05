<?php
require_once 'core/db.php';

// Add some test categories if not exist
$testCategories = [
    ['name' => 'Mobile', 'slug' => 'mobile'],
    ['name' => 'Laptop', 'slug' => 'laptop'],
    ['name' => 'Headphone', 'slug' => 'headphone'],
    ['name' => 'Router', 'slug' => 'router'],
    ['name' => 'Watch', 'slug' => 'watch'],
    ['name' => 'Charger', 'slug' => 'charger']
];

foreach ($testCategories as $cat) {
    $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $check->execute([$cat['slug']]);
    
    if (!$check->fetch()) {
        $insert = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, NULL)");
        $insert->execute([$cat['name'], $cat['slug']]);
        echo "✓ Added category: {$cat['name']}<br>";
    } else {
        echo "- Category exists: {$cat['name']}<br>";
    }
}

echo "<br><strong>All categories:</strong><br>";
$all = $pdo->query("SELECT * FROM categories ORDER BY name");
foreach ($all as $row) {
    echo "ID: {$row['id']}, Name: {$row['name']}, Slug: {$row['slug']}<br>";
}

echo "<br><a href='admin/product_add.php'>Go to Product Add Page</a>";
?>
