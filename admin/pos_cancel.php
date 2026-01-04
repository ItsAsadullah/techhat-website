<?php
require_once __DIR__ . '/../core/auth.php';
require_admin();

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/stock.php';

$pos_id = (int)($_GET['id'] ?? 0);

try {
    $pdo->beginTransaction();

    $items = $pdo->prepare("
        SELECT variant_id, quantity
        FROM pos_sale_items
        WHERE pos_sale_id = ?
    ");
    $items->execute([$pos_id]);

    foreach ($items as $item) {
        adjustStock(
            $item['variant_id'],
            $item['quantity'],
            'in',
            'adjustment',
            $pos_id,
            'POS cancelled'
        );
    }

    $pdo->prepare("DELETE FROM pos_sales WHERE id=?")->execute([$pos_id]);

    $pdo->commit();
    header("Location: pos.php?cancelled=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: pos.php?error=cancel");
    exit;
}
