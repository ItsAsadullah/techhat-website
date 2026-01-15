<?php
require_once 'core/db.php';

echo "Checking Services Table...\n";
echo "========================================\n";

try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'services'");
    
    if ($checkTable->rowCount() > 0) {
        echo "✓ Services table exists\n";
        
        // Show structure
        $result = $pdo->query("DESCRIBE services");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nTable Structure:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    } else {
        echo "Creating services table...\n";
        
        $sql = "
        CREATE TABLE IF NOT EXISTS `services` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `category` varchar(100) DEFAULT NULL,
            `price` decimal(10,2) NOT NULL DEFAULT 0.00,
            `description` text,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        echo "✓ Services table created\n";
    }
    
    echo "\n========================================\n";
    echo "✅ Check completed!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
