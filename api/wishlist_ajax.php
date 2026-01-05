<?php
require_once '../core/auth.php';
require_once '../core/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please login first', 'requireLogin' => true]);
    exit;
}

$user_id = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        
        if ($product_id > 0) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
                
                // Get wishlist count
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
                $countStmt->execute([$user_id]);
                $wishlistCount = $countStmt->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Added to wishlist',
                    'wishlistCount' => $wishlistCount,
                    'inWishlist' => true
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
        }
        exit;
    }
    
    if ($action === 'remove') {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        
        if ($product_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                
                // Get wishlist count
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
                $countStmt->execute([$user_id]);
                $wishlistCount = $countStmt->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Removed from wishlist',
                    'wishlistCount' => $wishlistCount,
                    'inWishlist' => false
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
        }
        exit;
    }
    
    if ($action === 'get_wishlist') {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    w.id as wishlist_id,
                    w.product_id,
                    p.title,
                    p.slug,
                    p.badge_text,
                    pv.id as variant_id,
                    pv.price,
                    pv.offer_price,
                    pv.stock_quantity,
                    pv.image_path
                FROM wishlist w
                JOIN products p ON p.id = w.product_id
                LEFT JOIN product_variants pv ON pv.product_id = p.id
                WHERE w.user_id = ? AND p.is_active = 1
                GROUP BY w.id
                ORDER BY w.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $wishlistItems = [];
            foreach ($items as $item) {
                $price = $item['offer_price'] > 0 ? $item['offer_price'] : $item['price'];
                $wishlistItems[] = [
                    'wishlist_id' => $item['wishlist_id'],
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'badge' => $item['badge_text'],
                    'price' => $price,
                    'stock' => $item['stock_quantity'],
                    'image' => $item['image_path'] ?? 'assets/images/placeholder.png'
                ];
            }
            
            echo json_encode([
                'success' => true,
                'items' => $wishlistItems,
                'count' => count($wishlistItems)
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }
    
    if ($action === 'check') {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        
        if ($product_id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $exists = $stmt->fetchColumn() > 0;
                
                echo json_encode([
                    'success' => true,
                    'inWishlist' => $exists
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'inWishlist' => false]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
