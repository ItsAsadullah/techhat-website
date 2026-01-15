<?php
require_once '../core/auth.php';
require_admin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$parent_id = $input['parent_id'] ?? null;

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit;
}

try {
    // Generate slug
    $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug .= '-' . time();
    }
    
    // Insert
    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
    $stmt->execute([$name, $slug, $parent_id]);
    
    $newId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $newId,
            'name' => $name,
            'slug' => $slug
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
