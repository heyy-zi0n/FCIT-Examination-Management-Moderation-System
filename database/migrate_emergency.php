<?php
require_once __DIR__ . '/../bootstrap.php';
$pdo = getDb();

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `emergency_print_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL,
            `version_id` INT NOT NULL,
            `exam_officer_id` INT NOT NULL,
            `reason` TEXT NOT NULL,
            `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            `hod_id` INT NULL,
            `hod_decision_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_epr_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_epr_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_epr_eo` FOREIGN KEY (`exam_officer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_epr_hod` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            INDEX `idx_epr_paper_status` (`paper_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Migration successful: emergency_print_requests created.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
