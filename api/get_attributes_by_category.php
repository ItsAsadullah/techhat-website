<?php
require_once '../core/db.php';

header('Content-Type: application/json');

$category_id = $_GET['category_id'] ?? null;

if (!$category_id) {
    echo json_encode(['status' => 'error', 'message' => 'Category ID is required']);
    exit;
}

try {
    // Get category attributes (including inherited from parent categories)
    $attributes = [];
    $current_category_id = $category_id;
    
    // Walk up the category tree and collect all attributes
    while ($current_category_id) {
        $stmt = $pdo->prepare("
            SELECT a.id, a.name, a.slug, a.type, ca.is_required
            FROM category_attributes ca
            JOIN attributes a ON ca.attribute_id = a.id
            WHERE ca.category_id = ? AND a.is_active = 1
            ORDER BY ca.display_order ASC
        ");
        $stmt->execute([$current_category_id]);
        $cat_attrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($cat_attrs as $attr) {
            // Don't duplicate if already added from child category
            if (!isset($attributes[$attr['id']])) {
                // Get attribute values
                $valStmt = $pdo->prepare("SELECT id, value FROM attribute_values WHERE attribute_id = ? AND is_active = 1 ORDER BY display_order, value ASC");
                $valStmt->execute([$attr['id']]);
                $attr['values'] = $valStmt->fetchAll(PDO::FETCH_ASSOC);
                $attributes[$attr['id']] = $attr;
            }
        }
        
        // Get parent category
        $parentStmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
        $parentStmt->execute([$current_category_id]);
        $parent = $parentStmt->fetch();
        $current_category_id = $parent ? $parent['parent_id'] : null;
    }
    
    // If no category-specific attributes, return all global attributes
    if (empty($attributes)) {
        $stmt = $pdo->query("SELECT id, name, slug, type FROM attributes WHERE is_active = 1 ORDER BY name ASC");
        $all_attrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_attrs as $attr) {
            $valStmt = $pdo->prepare("SELECT id, value FROM attribute_values WHERE attribute_id = ? AND is_active = 1 ORDER BY display_order, value ASC");
            $valStmt->execute([$attr['id']]);
            $attr['values'] = $valStmt->fetchAll(PDO::FETCH_ASSOC);
            $attr['is_required'] = 0;
            $attributes[$attr['id']] = $attr;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => array_values($attributes)
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
