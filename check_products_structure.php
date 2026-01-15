<?php
require_once 'core/db.php';

echo "Products Table Structure:\n";
echo "========================================\n";

try {
    $result = $pdo->query("DESCRIBE products");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
    
    echo "\n========================================\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
