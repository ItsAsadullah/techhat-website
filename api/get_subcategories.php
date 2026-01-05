<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$parent_id = $_GET['parent_id'] ?? null;

if (!$parent_id) {
    echo json_encode(['success' => false, 'message' => 'Parent ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, slug FROM categories WHERE parent_id = ? ORDER BY name ASC");
    $stmt->execute([$parent_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
