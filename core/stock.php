<?php
require_once __DIR__ . '/db.php';

/**
 * Central stock adjustment engine
 *
 * @param int         $variant_id
 * @param int         $quantity
 * @param string      $type      in | out
 * @param string      $source    online | pos | adjustment | return
 * @param int|null    $reference_id
 * @param string|null $note
 * @return bool
 */
function adjustStock(
    int $variant_id,
    int $quantity,
    string $type,
    string $source,
    ?int $reference_id = null,
    ?string $note = null
): bool {
    global $pdo;

    if ($quantity <= 0 || !in_array($type, ['in', 'out'], true)) {
        return false;
    }

    try {
        // Check if transaction is already active
        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $stmt = $pdo->prepare("
            SELECT stock_quantity, product_id
            FROM product_variants
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$variant_id]);
        $variant = $stmt->fetch();

        if (!$variant) {
            throw new Exception('Variant not found');
        }

        $currentStock = (int)$variant['stock_quantity'];

        if ($type === 'out' && $currentStock < $quantity) {
            throw new Exception('Insufficient stock');
        }

        $newStock = ($type === 'in')
            ? $currentStock + $quantity
            : $currentStock - $quantity;

        $pdo->prepare("
            UPDATE product_variants
            SET stock_quantity = ?
            WHERE id = ?
        ")->execute([$newStock, $variant_id]);

        $pdo->prepare("
            INSERT INTO stock_movements
            (product_id, variant_id, quantity, movement_type, source, reference_id, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $variant['product_id'],
            $variant_id,
            $quantity,
            $type,
            $source,
            $reference_id,
            $note
        ]);

        // Only commit if we started the transaction
        if ($startedTransaction) {
            $pdo->commit();
        }
        return true;

    } catch (Exception $e) {
        // Only rollback if we started the transaction
        if (isset($startedTransaction) && $startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}
