<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$category_id = $_GET['category_id'] ?? null;

try {
    // Get all attributes
    $stmt = $pdo->query("
        SELECT a.id, a.name, a.slug, a.type
        FROM attributes a
        WHERE a.is_active = 1
        ORDER BY a.name ASC
    ");
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each attribute, get its values
    foreach ($attributes as &$attr) {
        $valStmt = $pdo->prepare("
            SELECT id, value 
            FROM attribute_values 
            WHERE attribute_id = ? AND is_active = 1 
            ORDER BY display_order, value ASC
        ");
        $valStmt->execute([$attr['id']]);
        $attr['values'] = $valStmt->fetchAll(PDO::FETCH_ASSOC);
        $attr['is_required'] = 0;
    }
    
    // If category is specified, check for category-specific attributes
    if ($category_id) {
        $catStmt = $pdo->prepare("
            SELECT ca.attribute_id, ca.is_required
            FROM category_attributes ca
            WHERE ca.category_id = ?
        ");
        $catStmt->execute([$category_id]);
        $catAttrs = $catStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Update is_required based on category settings
        foreach ($attributes as &$attr) {
            if (isset($catAttrs[$attr['id']])) {
                $attr['is_required'] = $catAttrs[$attr['id']];
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $attributes
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
