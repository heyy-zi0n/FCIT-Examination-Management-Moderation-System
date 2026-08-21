<?php
/**
 * Test Suite: Database Schema & Relationships
 */

require_once __DIR__ . '/../bootstrap.php';

function testDatabase(): bool {
    echo "--- Testing Database Connection & Schema ---\n";
    $pdo = getDb();
    
    // Check tables exist
    $tables = [
        'departments', 'academic_sessions', 'semesters', 'academic_levels',
        'users', 'courses', 'lecturer_course_assignments', 'moderator_assignments',
        'exam_schedules', 'delegations', 'examination_papers', 'paper_versions',
        'paper_approvals', 'moderation_comments', 'blind_lockdowns', 'print_logs',
        'notifications', 'audit_logs'
    ];

    $allPassed = true;
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->fetch()) {
            echo "  [PASS] Table `{$table}` exists.\n";
        } else {
            echo "  [FAIL] Table `{$table}` is missing!\n";
            $allPassed = false;
        }
    }

    // Verify Foreign Key Constraints and seeded rows
    $deptCount = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    if ($deptCount === 5) {
        echo "  [PASS] Seeded departments count is 5.\n";
    } else {
        echo "  [FAIL] Seeded departments count mismatch (got {$deptCount}).\n";
        $allPassed = false;
    }

    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount >= 9) {
        echo "  [PASS] Seeded users count is {$userCount}.\n";
    } else {
        echo "  [FAIL] Seeded users count mismatch (got {$userCount}).\n";
        $allPassed = false;
    }

    return $allPassed;
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $ok = testDatabase();
    exit($ok ? 0 : 1);
}
