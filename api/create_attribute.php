<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$attribute_id = $_POST['attribute_id'] ?? $_GET['attribute_id'] ?? null;
$value = trim($_POST['value'] ?? $_GET['value'] ?? '');

if (!$attribute_id || empty($value)) {
    echo json_encode(['status' => 'error', 'message' => 'Attribute ID and value are required']);
    exit;
}

try {
    // Check if exists
    $stmt = $pdo->prepare("SELECT id, value FROM attribute_values WHERE attribute_id = ? AND value = ?");
    $stmt->execute([$attribute_id, $value]);
    
    if ($row = $stmt->fetch()) {
        echo json_encode(['status' => 'success', 'message' => 'Already exists', 'data' => $row]);
        exit;
    }
    
    // Insert
    $stmt = $pdo->prepare("INSERT INTO attribute_values (attribute_id, value) VALUES (?, ?)");
    $stmt->execute([$attribute_id, $value]);
    $newId = $pdo->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $newId,
            'value' => $value
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
