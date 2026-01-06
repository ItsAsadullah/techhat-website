<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$category_id = $_GET['category_id'] ?? 0;

if (!$category_id) {
    echo json_encode(['success' => false, 'attributes' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.id, a.name, a.slug, a.input_type
    FROM category_attributes ca
    JOIN attributes a ON ca.attribute_id = a.id
    WHERE ca.category_id = ?
    ORDER BY ca.sort_order, a.name
");
$stmt->execute([$category_id]);
$attributes = $stmt->fetchAll();

echo json_encode(['success' => true, 'attributes' => $attributes]);
