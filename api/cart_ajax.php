<?php
require_once '../core/auth.php';
require_once '../core/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $variant_id = (int) ($_POST['variant_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        
        if ($variant_id > 0) {
            $_SESSION['cart'][$variant_id] = ($_SESSION['cart'][$variant_id] ?? 0) + $qty;
            
            // Get cart count
            $cartCount = array_sum($_SESSION['cart']);
            
            // Calculate total
            $total = 0;
            if (!empty($_SESSION['cart'])) {
                $variantIds = array_keys($_SESSION['cart']);
                $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
                $stmt = $pdo->prepare("SELECT id, price, offer_price FROM product_variants WHERE id IN ($placeholders)");
                $stmt->execute($variantIds);
                $variants = $stmt->fetchAll();
                foreach ($variants as $v) {
                    $price = $v['offer_price'] > 0 ? $v['offer_price'] : $v['price'];
                    $total += $price * $_SESSION['cart'][$v['id']];
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart',
                'cartCount' => $cartCount,
                'total' => $total,
                'inCart' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid product variant'
            ]);
        }
        exit;
    }
    
    if ($action === 'remove') {
        $variant_id = (int) ($_POST['variant_id'] ?? 0);
        
        if (isset($_SESSION['cart'][$variant_id])) {
            unset($_SESSION['cart'][$variant_id]);
            
            $cartCount = array_sum($_SESSION['cart']);
            
            // Calculate new total
            $total = 0;
            if (!empty($_SESSION['cart'])) {
                $variantIds = array_keys($_SESSION['cart']);
                $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
                $stmt = $pdo->prepare("SELECT id, price, offer_price FROM product_variants WHERE id IN ($placeholders)");
                $stmt->execute($variantIds);
                $variants = $stmt->fetchAll();
                foreach ($variants as $v) {
                    $price = $v['offer_price'] > 0 ? $v['offer_price'] : $v['price'];
                    $total += $price * $_SESSION['cart'][$v['id']];
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Product removed from cart',
                'cartCount' => $cartCount,
                'total' => $total,
                'inCart' => false
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Product not in cart'
            ]);
        }
        exit;
    }
    
    if ($action === 'get_cart') {
        $cartItems = [];
        $total = 0;
        
        if (!empty($_SESSION['cart'])) {
            $variantIds = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
            
            $stmt = $pdo->prepare("
                SELECT pv.*, p.title, p.slug, p.badge_text
                FROM product_variants pv
                JOIN products p ON p.id = pv.product_id
                WHERE pv.id IN ($placeholders) AND pv.status = 1 AND p.is_active = 1
            ");
            $stmt->execute($variantIds);
            $variants = $stmt->fetchAll();
            
            foreach ($variants as $variant) {
                $qty = $_SESSION['cart'][$variant['id']];
                $price = $variant['offer_price'] > 0 ? $variant['offer_price'] : $variant['price'];
                $subtotal = $price * $qty;
                $total += $subtotal;
                
                $cartItems[] = [
                    'id' => $variant['id'],
                    'product_id' => $variant['product_id'],
                    'title' => $variant['title'],
                    'slug' => $variant['slug'],
                    'variant_name' => $variant['name'],
                    'color' => $variant['color'],
                    'size' => $variant['size'],
                    'storage' => $variant['storage'],
                    'image' => $variant['image_path'],
                    'price' => $price,
                    'quantity' => $qty,
                    'subtotal' => $subtotal,
                    'stock' => $variant['stock_quantity']
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'items' => $cartItems,
            'total' => $total,
            'count' => array_sum($_SESSION['cart'])
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
