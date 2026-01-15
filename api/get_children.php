<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$parent_id = $_GET['parent_id'] ?? null;

try {
    if ($parent_id) {
        $stmt = $pdo->prepare("SELECT id, name, slug, parent_id FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY display_order, name ASC");
        $stmt->execute([$parent_id]);
    } else {
        $stmt = $pdo->query("SELECT id, name, slug, parent_id FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY display_order, name ASC");
    }
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if each category has children
    foreach ($categories as &$cat) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
        $checkStmt->execute([$cat['id']]);
        $cat['has_children'] = $checkStmt->fetchColumn() > 0;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $categories
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
