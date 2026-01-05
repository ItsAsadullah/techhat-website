<?php
require_once 'core/db.php';

try {
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN division VARCHAR(100) NULL AFTER phone,
        ADD COLUMN district VARCHAR(100) NULL AFTER division,
        ADD COLUMN upazila VARCHAR(100) NULL AFTER district,
        ADD COLUMN address TEXT NULL AFTER upazila
    ");
    echo "Address columns added to users table successfully.";
} catch (PDOException $e) {
    echo "Error adding columns: " . $e->getMessage();
}
?>