<?php
/**
 * Migration Script: Add is_active column to courses table
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden: CLI execution only.');
}

require_once __DIR__ . '/../bootstrap.php';
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
