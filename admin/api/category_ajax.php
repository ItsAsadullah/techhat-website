<?php
require_once '../../core/db.php';
require_once '../../core/auth.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'search') {
    $term = $_GET['term'] ?? '';
    $parentId = isset($_GET['parent_id']) && $_GET['parent_id'] !== '' ? $_GET['parent_id'] : null;

    $sql = "SELECT id, name FROM categories WHERE name LIKE ?";
    $params = ["%$term%"];

    if ($parentId !== null) {
        $sql .= " AND parent_id = ?";
        $params[] = $parentId;
    } else {
        $sql .= " AND parent_id IS NULL";
    }

    $sql .= " LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

if ($action === 'add') {
    $name = $_POST['name'] ?? '';
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? $_POST['parent_id'] : null;

    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit;
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    // Ensure unique slug
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug .= '-' . time();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $slug, $parentId]);
        $id = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>