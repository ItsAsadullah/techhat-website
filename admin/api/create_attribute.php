<?php
/**
 * API: Create New Attribute Value
 * Endpoint: /admin/api/create_attribute.php
 * Method: POST
 * Parameters: attribute_id, value, color_code (optional)
 */

require_once '../../core/auth.php';
require_once '../../core/db.php';

header('Content-Type: application/json');

// Validate admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$attribute_id = !empty($_POST['attribute_id']) ? (int)$_POST['attribute_id'] : 0;
$value = trim($_POST['value'] ?? '');
$color_code = !empty($_POST['color_code']) ? trim($_POST['color_code']) : null;

if ($attribute_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Attribute ID is required']);
    exit;
}

if (empty($value)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Value is required']);
    exit;
}

if (strlen($value) > 255) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Value too long (max 255 chars)']);
    exit;
}

try {
    // Verify attribute exists
    $stmt = $pdo->prepare("SELECT id FROM attributes WHERE id = ?");
    $stmt->execute([$attribute_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Attribute not found']);
        exit;
    }

    // Check if value already exists for this attribute
    $stmt = $pdo->prepare("SELECT id FROM attribute_values WHERE attribute_id = ? AND value = ?");
    $stmt->execute([$attribute_id, $value]);
    if ($existing = $stmt->fetch()) {
        // Return existing value
        echo json_encode([
            'status' => 'success',
            'data' => [
                'id' => (int)$existing['id'],
                'value' => $value,
                'existing' => true
            ],
            'message' => 'Value already exists'
        ]);
        exit;
    }

    // Insert new attribute value
    $stmt = $pdo->prepare("
        INSERT INTO attribute_values (attribute_id, value, color_code)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$attribute_id, $value, $color_code]);
    
    $id = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => (int)$id,
            'value' => $value,
            'color_code' => $color_code,
            'existing' => false
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
