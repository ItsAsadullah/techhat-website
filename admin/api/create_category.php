<?php
/**
 * API: Create Category
 * Endpoint: /admin/api/create_category.php
 * Method: POST
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
require_once '../../core/auth.php';
require_once '../../core/db.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    $name = trim($data['name'] ?? '');
    $parent_id = isset($data['parent_id']) && !empty($data['parent_id']) ? (int)$data['parent_id'] : null;

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name required']);
        exit;
    }

    // Check which columns exist
    $checkColumns = $pdo->query("SHOW COLUMNS FROM categories");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
    
    $hasSlug = in_array('slug', $columns);
    $hasLevel = in_array('level', $columns);
    $hasIsActive = in_array('is_active', $columns);
    $hasCreatedAt = in_array('created_at', $columns);

    // Check if category already exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id " . ($parent_id ? "= ?" : "IS NULL"));
    if ($parent_id) {
        $stmt->execute([$name, $parent_id]);
    } else {
        $stmt->execute([$name]);
    }

    if ($stmt->rowCount() > 0) {
        $existingId = $stmt->fetchColumn();
        echo json_encode([
            'success' => true,
            'exists' => true,
            'category' => [
                'id' => (int)$existingId,
                'name' => $name,
                'parent_id' => $parent_id
            ],
            'message' => 'Category already exists'
        ]);
        exit;
    }

    // Prepare values
    $slug = $hasSlug ? strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-')) : null;
    
    $level = 0;
    if ($hasLevel && $parent_id) {
        $levelStmt = $pdo->prepare("SELECT " . ($hasLevel ? "level" : "0 as level") . " FROM categories WHERE id = ?");
        $levelStmt->execute([$parent_id]);
        $result = $levelStmt->fetch(PDO::FETCH_ASSOC);
        $level = ($result && isset($result['level'])) ? (int)$result['level'] + 1 : 1;
    }

    // Build INSERT query based on available columns
    $insertFields = ['name', 'parent_id'];
    $insertValues = [$name, $parent_id];
    $placeholders = ['?', '?'];
    
    if ($hasSlug) {
        $insertFields[] = 'slug';
        $insertValues[] = $slug;
        $placeholders[] = '?';
    }
    
    if ($hasLevel) {
        $insertFields[] = 'level';
        $insertValues[] = $level;
        $placeholders[] = '?';
    }
    
    if ($hasIsActive) {
        $insertFields[] = 'is_active';
        $insertValues[] = 1;
        $placeholders[] = '?';
    }
    
    if ($hasCreatedAt) {
        $insertFields[] = 'created_at';
        $insertValues[] = date('Y-m-d H:i:s');
        $placeholders[] = '?';
    }

    $sql = "INSERT INTO categories (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insert = $pdo->prepare($sql);
    $insert->execute($insertValues);
    
    $newId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'category' => [
            'id' => (int)$newId,
            'name' => $name,
            'slug' => $slug,
            'level' => $level,
            'parent_id' => $parent_id
        ],
        'message' => 'Category created successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
?>