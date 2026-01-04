<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stock.php';

/**
 * Create order with atomic stock cut
 *
 * @param int    $user_id
 * @param array  $cartItems  [
 *   ['product_id'=>int,'variant_id'=>int,'price'=>float,'qty'=>int]
 * ]
 * @param string $payment_method  cod|bkash|nagad|rocket
 * @return int|false  order_id on success
 */
function createOrder(int $user_id, array $cartItems, string $payment_method)
{
    global $pdo;

    if (empty($cartItems)) return false;

    try {
        $pdo->beginTransaction();

        // 1) Calculate total
        $total = 0;
        foreach ($cartItems as $item) {
            $total += ($item['price'] * $item['qty']);
        }

        // 2) Create order (PENDING)
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, total, payment_method, order_status, payment_status)
            VALUES (?, ?, ?, 'pending', 'pending')
        ");
        $stmt->execute([$user_id, $total, $payment_method]);
        $order_id = (int)$pdo->lastInsertId();

        // 3) Insert order items + cut stock
        foreach ($cartItems as $item) {

            // 3.1 Insert item
            $pdo->prepare("
                INSERT INTO order_items
                (order_id, product_id, variant_id, price, qty)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $order_id,
                $item['product_id'],
                $item['variant_id'],
                $item['price'],
                $item['qty']
            ]);

            // 3.2 Cut stock (atomic)
            $ok = adjustStock(
                $item['variant_id'],
                $item['qty'],
                'out',
                'online',
                $order_id,
                'Order placed'
            );

            if (!$ok) {
                throw new Exception('Stock cut failed');
            }
        }

        $pdo->commit();
        return $order_id;

    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
