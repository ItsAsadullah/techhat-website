<?php
require_once '../core/auth.php';
require_once '../core/stock.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pos_sales.php');
    exit;
}

$sale_id = (int)($_POST['sale_id'] ?? 0);
$return_reason = trim($_POST['return_reason'] ?? '');
$return_quantities = $_POST['return_qty'] ?? [];

if (!$sale_id || empty($return_reason)) {
    die('Invalid request data');
}

try {
    $pdo->beginTransaction();

    // Verify sale exists
    $stmt = $pdo->prepare("SELECT * FROM pos_sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();

    if (!$sale) {
        throw new Exception('Sale not found');
    }

    $total_return_amount = 0;
    $return_items = [];

    // Process each item
    foreach ($return_quantities as $sale_item_id => $return_qty) {
        $return_qty = (int)$return_qty;
        if ($return_qty <= 0) continue;

        // Get sale item details and already returned quantity
        $stmt = $pdo->prepare("
            SELECT 
                psi.*, 
                pv.product_id,
                COALESCE(SUM(pri.quantity), 0) as already_returned
            FROM pos_sale_items psi
            INNER JOIN product_variants pv ON psi.variant_id = pv.id
            LEFT JOIN pos_return_items pri ON psi.id = pri.pos_sale_item_id
            WHERE psi.id = ? AND psi.pos_sale_id = ?
            GROUP BY psi.id
        ");
        $stmt->execute([$sale_item_id, $sale_id]);
        $sale_item = $stmt->fetch();

        if (!$sale_item) {
            throw new Exception('Sale item not found');
        }

        // Check available quantity (sold - already returned)
        $available_qty = $sale_item['quantity'] - $sale_item['already_returned'];
        
        if ($return_qty > $available_qty) {
            throw new Exception('Return quantity exceeds available quantity. Already returned: ' . $sale_item['already_returned']);
        }

        $return_amount = $return_qty * $sale_item['price'];
        $total_return_amount += $return_amount;

        $return_items[] = [
            'sale_item_id' => $sale_item_id,
            'product_id' => $sale_item['product_id'],
            'variant_id' => $sale_item['variant_id'],
            'quantity' => $return_qty,
            'price' => $sale_item['price']
        ];
    }

    if ($total_return_amount <= 0) {
        throw new Exception('No items selected for return');
    }

    // Create return record
    $stmt = $pdo->prepare("
        INSERT INTO pos_returns 
        (pos_sale_id, return_amount, return_reason, returned_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $sale_id,
        $total_return_amount,
        $return_reason,
        $_SESSION['user_id']
    ]);
    $return_id = $pdo->lastInsertId();

    // Insert return items and restore stock
    foreach ($return_items as $item) {
        // Insert return item
        $stmt = $pdo->prepare("
            INSERT INTO pos_return_items 
            (pos_return_id, pos_sale_item_id, product_id, variant_id, quantity, price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $return_id,
            $item['sale_item_id'],
            $item['product_id'],
            $item['variant_id'],
            $item['quantity'],
            $item['price']
        ]);

        // 🔥 Add stock back using unified stock system
        if (!adjustStock(
            $item['variant_id'],
            $item['quantity'],
            'in',
            'pos_return',
            $return_id,
            'POS Return #' . $return_id . ' (from Sale #' . $sale_id . ')'
        )) {
            throw new Exception('Failed to restore stock for variant #' . $item['variant_id']);
        }
    }

    // Record in accounts (negative income)
    $stmt = $pdo->prepare("
        INSERT INTO accounts_income 
        (source, amount, reference_table, reference_id, note, txn_date)
        VALUES (?, ?, ?, ?, ?, CURDATE())
    ");
    $stmt->execute([
        'POS Return',
        -$total_return_amount, // Negative amount
        'pos_returns',
        $return_id,
        'POS Return #' . $return_id . ' (Invoice #' . $sale_id . ') - ' . $return_reason
    ]);

    $pdo->commit();

    // Redirect with success message
    header('Location: pos_sales.php?return_success=1&return_id=' . $return_id . '&amount=' . $total_return_amount);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die('Error processing return: ' . $e->getMessage());
}
