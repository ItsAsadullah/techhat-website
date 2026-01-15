<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$parent_id = $input['parent_id'] ?? null;

if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Category name is required']);
    exit;
}

try {
    // Create slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))");
    $stmt->execute([$name, $parent_id, $parent_id]);
    
    if ($row = $stmt->fetch()) {
        echo json_encode(['status' => 'success', 'message' => 'Category already exists', 'data' => ['id' => $row['id'], 'name' => $name]]);
        exit;
    }
    
    // Insert
    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
    $stmt->execute([$name, $slug, $parent_id]);
    $newId = $pdo->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $newId,
            'name' => $name,
            'slug' => $slug
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
