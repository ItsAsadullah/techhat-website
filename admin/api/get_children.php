<?php
/**
 * API: Get Child Categories
 * Endpoint: /admin/api/get_children.php
 * Method: GET
 * Parameters: parent_id (optional, null for root categories)
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log
ini_set('log_errors', 1);

require_once '../../core/auth.php';
require_once '../../core/db.php';

header('Content-Type: application/json');

try {
    // Handle parent_id parameter
    $parent_id = null;
    if (isset($_GET['parent_id']) && $_GET['parent_id'] !== 'null' && $_GET['parent_id'] !== '') {
        $parent_id = (int)$_GET['parent_id'];
    }

    // Check which columns exist in the table
    $checkColumns = $pdo->query("SHOW COLUMNS FROM categories");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
    
    $hasDisplayOrder = in_array('display_order', $columns);
    $hasIsActive = in_array('is_active', $columns);
    $hasLevel = in_array('level', $columns);
    $hasSlug = in_array('slug', $columns);

    // Build SELECT clause based on available columns
    $selectFields = "id, name, parent_id";
    if ($hasSlug) $selectFields .= ", slug";
    if ($hasLevel) $selectFields .= ", level";
    
    // Build WHERE clause
    $whereActive = $hasIsActive ? "AND is_active = 1" : "";
    
    // Build ORDER BY clause
    $orderBy = $hasDisplayOrder ? "display_order ASC, name ASC" : "name ASC";

    if ($parent_id === null) {
        // Fetch root categories (parent_id IS NULL)
        $sql = "SELECT $selectFields FROM categories WHERE parent_id IS NULL $whereActive ORDER BY $orderBy";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        // Fetch children of specific parent
        $sql = "SELECT $selectFields FROM categories WHERE parent_id = ? $whereActive ORDER BY $orderBy";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$parent_id]);
    }

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'count' => count($categories),
        'parent_id' => $parent_id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'categories' => [],
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'categories' => []
    ]);
}
?>
