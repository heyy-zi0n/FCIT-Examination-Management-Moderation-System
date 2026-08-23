<?php
require __DIR__ . '/../bootstrap.php';
$pdo = getDb();
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER credit_units");
    echo "Successfully added is_active column to courses table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column is_active already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
