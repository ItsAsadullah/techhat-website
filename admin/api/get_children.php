<?php
/**
 * API: Get Child Categories
 * Endpoint: /admin/api/get_children.php
 * Method: GET
 * Parameters: parent_id (optional, null for root categories)
 */

require_once '../core/auth.php';
require_once '../core/db.php';

header('Content-Type: application/json');

try {
    $parent_id = !empty($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

    if ($parent_id === null) {
        // Fetch root categories (parent_id IS NULL)
        $stmt = $pdo->prepare("
            SELECT id, name, slug, level, parent_id
            FROM categories
            WHERE parent_id IS NULL AND is_active = 1
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute();
    } else {
        // Fetch children of specific parent
        $stmt = $pdo->prepare("
            SELECT id, name, slug, level, parent_id
            FROM categories
            WHERE parent_id = ? AND is_active = 1
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute([$parent_id]);
    }

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $categories,
        'count' => count($categories)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
