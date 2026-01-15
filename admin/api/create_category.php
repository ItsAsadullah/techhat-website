<?php
// api/create_category.php
header('Content-Type: application/json');
require_once '../../core/auth.php';
require_once '../../core/db.php'; // আপনার ডাটাবেস ফাইল লিংক করুন

$data = json_decode(file_get_contents("php://input"), true);
$name = trim($data['name']);
$parent_id = isset($data['parent_id']) && !empty($data['parent_id']) ? $data['parent_id'] : NULL;

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Name required']);
    exit;
}

// চেক করুন আগে থেকেই আছে কিনা
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
            'id' => $existingId,
            'name' => $name
        ],
        'message' => 'Category already exists'
    ]);
} else {
    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    
    // Calculate level
    $level = 0;
    if ($parent_id) {
        $levelStmt = $pdo->prepare("SELECT level FROM categories WHERE id = ?");
        $levelStmt->execute([$parent_id]);
        $parentLevel = $levelStmt->fetchColumn();
        $level = $parentLevel + 1;
    }
    
    // Insert new category
    $insert = $pdo->prepare("INSERT INTO categories (name, slug, parent_id, level, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $insert->execute([$name, $slug, $parent_id, $level]);
    $newId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'category' => [
            'id' => $newId,
            'name' => $name,
            'slug' => $slug,
            'level' => $level,
            'parent_id' => $parent_id
        ],
        'message' => 'Category created successfully'
    ]);
}
?>