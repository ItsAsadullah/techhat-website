<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$attribute_id = $data['attribute_id'] ?? 0;
$value = trim($data['value'] ?? '');

if (!$attribute_id || !$value) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO attribute_values (attribute_id, value) VALUES (?, ?)");
    $stmt->execute([$attribute_id, $value]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
