<?php
/**
 * API: Get Category Attributes
 * Endpoint: /admin/api/get_attributes.php
 * Method: GET
 * Parameters: category_id
 */

require_once '../core/auth.php';
require_once '../core/db.php';

header('Content-Type: application/json');

try {
    $category_id = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

    if ($category_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Category ID required']);
        exit;
    }

    // Get attributes linked to this category
    $stmt = $pdo->prepare("
        SELECT a.id, a.name, a.slug, a.type, ca.is_required, ca.display_order
        FROM attributes a
        JOIN category_attributes ca ON a.id = ca.attribute_id
        WHERE ca.category_id = ? AND a.is_active = 1
        ORDER BY ca.display_order ASC
    ");
    $stmt->execute([$category_id]);
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each attribute, fetch its values
    foreach ($attributes as &$attr) {
        $valStmt = $pdo->prepare("
            SELECT id, value, color_code
            FROM attribute_values
            WHERE attribute_id = ? AND is_active = 1
            ORDER BY display_order ASC
        ");
        $valStmt->execute([$attr['id']]);
        $attr['values'] = $valStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $attributes,
        'count' => count($attributes)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
