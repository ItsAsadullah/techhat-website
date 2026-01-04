<?php
require_once 'core/db.php';

// Ensure UTF-8
$pdo->exec("SET NAMES utf8mb4");

// Clear and re-insert suppliers with Bengali names
$pdo->exec("DELETE FROM suppliers");

$stmt = $pdo->prepare("INSERT INTO suppliers (name, company_name, phone, email, address) VALUES (?, ?, ?, ?, ?)");

$suppliers = [
    ['আব্দুর রহমান', 'Rahman Electronics', '01711111111', 'rahman@example.com', 'ঢাকা, বাংলাদেশ'],
    ['কামাল উদ্দিন', 'Kamal Trading', '01722222222', 'kamal@example.com', 'চট্টগ্রাম, বাংলাদেশ']
];

foreach ($suppliers as $sup) {
    $stmt->execute($sup);
}

echo "✓ Suppliers inserted successfully with Bengali names!\n";
echo "Please refresh the page.\n";
