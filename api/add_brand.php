<?php
require_once '../core/auth.php';
require_admin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Brand name is required']);
    exit;
}

try {
    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
    $stmt->execute([$name]);
    if ($row = $stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Brand already exists', 'id' => $row['id']]);
        exit;
    }
    
    // Insert
    $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (?)");
    $stmt->execute([$name]);
    
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'name' => $name
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
