<?php
/**
 * Test Suite: Security IDOR, RBAC & Department Boundary Isolation
 */

require_once __DIR__ . '/../bootstrap.php';

function testSecurityIdor(): bool {
    echo "--- Testing IDOR Protection & Department Isolation ---\n";
    $pdo = getDb();
    $allPassed = true;

    // Test 1: Lecturer attempting to submit or view another lecturer's paper
    try {
        $lecCsc = $pdo->query("SELECT id FROM users WHERE email = 'lecturer.csc@lasu.edu.ng'")->fetchColumn();
        $lecCyb = $pdo->query("SELECT id FROM users WHERE email = 'lecturer.cyb@lasu.edu.ng'")->fetchColumn();

        // Find a paper authored by CSC lecturer
        $paper = $pdo->query("SELECT id FROM examination_papers WHERE lecturer_id = {$lecCsc} LIMIT 1")->fetch();
        if ($paper) {
            $paperId = (int)$paper['id'];
            try {
                // CYB lecturer attempting to submit CSC paper
                WorkflowService::submitPaper($paperId, (int)$lecCyb);
                echo "  [FAIL] Cross-lecturer paper submission was not blocked!\n";
                $allPassed = false;
            } catch (RuntimeException $e) {
                echo "  [PASS] Cross-lecturer unauthorized operation rejected: " . $e->getMessage() . "\n";
            }
        }
    } catch (Exception $e) {
        echo "  [FAIL] Test 1 error: " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    // Test 2: Non-Exam Officer attempting controlled print
    try {
        $lecId = (int)$pdo->query("SELECT id FROM users WHERE role = 'lecturer' LIMIT 1")->fetchColumn();
        $paperId = (int)$pdo->query("SELECT id FROM examination_papers WHERE status = 'Approved' OR status = 'Printed' LIMIT 1")->fetchColumn();
        
        if ($paperId) {
            try {
                PrintingService::executeControlledPrint($paperId, $lecId, 50);
                echo "  [FAIL] Lecturer executing controlled print was not blocked!\n";
                $allPassed = false;
            } catch (RuntimeException $e) {
                echo "  [PASS] Lecturer printing attempt rejected by server-side authorization: " . $e->getMessage() . "\n";
            }
        }
    } catch (Exception $e) {
        echo "  [FAIL] Test 2 error: " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    return $allPassed;
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $ok = testSecurityIdor();
    exit($ok ? 0 : 1);
}
