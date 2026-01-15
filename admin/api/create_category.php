<?php
// api/create_category.php
header('Content-Type: application/json');
require_once '../../core/auth.php';
require_once '../../core/db.php'; // আপনার ডাটাবেস ফাইল লিংক করুন

$data = json_decode(file_get_contents("php://input"), true);
$name = trim($data['name']);
$parent_id = isset($data['parent_id']) && !empty($data['parent_id']) ? $data['parent_id'] : NULL;

if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Name required']);
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
    echo json_encode(['status' => 'exists', 'id' => $stmt->fetchColumn()]);
} else {
    // নতুন তৈরি করুন
    $insert = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
    $insert->execute([$name, $parent_id]);
    $newId = $pdo->lastInsertId();
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $newId,
            'name' => $name
        ]
    ]);
}
?>