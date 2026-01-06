<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$attribute_id = $_GET['attribute_id'] ?? 0;

if (!$attribute_id) {
    echo json_encode(['success' => false, 'values' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT av.id, av.value, a.name as attribute_name
    FROM attribute_values av
    JOIN attributes a ON av.attribute_id = a.id
    WHERE av.attribute_id = ?
    ORDER BY av.value
");
$stmt->execute([$attribute_id]);
$values = $stmt->fetchAll();

echo json_encode(['success' => true, 'values' => $values]);
