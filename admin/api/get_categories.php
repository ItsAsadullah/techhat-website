<?php
// admin/api/get_categories.php
require_once '../../core/db_connect.php'; // আপনার ডাটাবেস কানেকশন ফাইল পাথ ঠিক করে নিবেন
header('Content-Type: application/json');

$parent_id = isset($_GET['parent_id']) && $_GET['parent_id'] !== '' ? $_GET['parent_id'] : NULL;

try {
    if ($parent_id === NULL) {
        $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE parent_id = ? ORDER BY name ASC");
        $stmt->execute([$parent_id]);
    }
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $categories]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>