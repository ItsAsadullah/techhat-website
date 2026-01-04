<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/stock.php';

require_admin();

// Clean output buffer
ob_start();

header('Content-Type: application/json');

// Validate items
if (empty($_POST['items']) || !is_array($_POST['items'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get customer info
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'cash');
    $commission = (float)($_POST['commission'] ?? 0);

    // Validate payment method
    if (!in_array($paymentMethod, ['cash', 'card', 'mobile'], true)) {
        $paymentMethod = 'cash';
    }

    // 1️⃣ Create POS Sale
    $stmt = $pdo->prepare("
        INSERT INTO pos_sales (
            staff_user_id, 
            customer_name, 
            customer_phone, 
            total_amount, 
            commission,
            payment_method
        )
        VALUES (?, ?, ?, 0, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $customerName ?: null,
        $customerPhone ?: null,
        $commission,
        $paymentMethod
    ]);
    $pos_sale_id = $pdo->lastInsertId();

    $total = 0;

    // 2️⃣ Process each item
    foreach ($_POST['items'] as $itemJson) {
        $item = json_decode($itemJson, true);
        
        if (!$item || !isset($item['quantity'], $item['price'])) {
            continue;
        }

        $qty = (int)$item['quantity'];
        $price = (float)$item['price'];
        $original_price = (float)($item['original_price'] ?? $price);

        if ($qty <= 0) continue;
        
        // Check if this is a service or product
        if (isset($item['service_id'])) {
            // Handle SERVICE
            $service_id = (int)$item['service_id'];
            $service_name = $item['service_name'] ?? 'Unknown Service';
            $subtotal = $price * $qty;
            
            // Insert service item
            $stmt = $pdo->prepare("
                INSERT INTO service_items (sale_id, service_id, service_name, price, quantity, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$pos_sale_id, $service_id, $service_name, $price, $qty, $subtotal]);
            
            $total += $subtotal;
            
        } elseif (isset($item['variant_id'])) {
            // Handle PRODUCT
            $variant_id = (int)$item['variant_id'];

            // Verify variant exists and has stock
            $stmt = $pdo->prepare("
                SELECT stock_quantity 
                FROM product_variants 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$variant_id]);
            $variant = $stmt->fetch();

        if (!$variant) {
            throw new Exception('Variant #' . $variant_id . ' not found');
        }

        if ($variant['stock_quantity'] < $qty) {
            throw new Exception('Insufficient stock for variant #' . $variant_id);
        }

        $line_total = $price * $qty;
        $total += $line_total;

        // Insert sale item
        $stmt = $pdo->prepare("
            INSERT INTO pos_sale_items 
            (pos_sale_id, variant_id, quantity, price, original_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $pos_sale_id,
            $variant_id,
            $qty,
            $price,
            $original_price
        ]);

        // 🔥 Reduce stock using unified stock system
        if (!adjustStock(
            $variant_id,
            $qty,
            'out',
            'pos',
            $pos_sale_id,
            'POS Sale #' . $pos_sale_id
        )) {
            throw new Exception('Stock adjustment failed for variant #' . $variant_id);
        }
        }
    }

    if ($total <= 0) {
        throw new Exception('Invalid sale total');
    }

    // 3️⃣ Update total
    $stmt = $pdo->prepare("
        UPDATE pos_sales 
        SET total_amount = ? 
        WHERE id = ?
    ");
    $stmt->execute([$total, $pos_sale_id]);

    // 4️⃣ Record income in accounting
    $stmt = $pdo->prepare("
        INSERT INTO accounts_income 
        (source, amount, note, txn_date)
        VALUES ('POS', ?, ?, CURDATE())
    ");
    $stmt->execute([
        $total,
        'POS Sale #' . $pos_sale_id . ($customerName ? ' - ' . $customerName : '')
    ]);

    $pdo->commit();

    ob_clean();
    echo json_encode([
        'success' => true, 
        'sale_id' => $pos_sale_id,
        'total' => $total,
        'message' => 'Sale completed successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
