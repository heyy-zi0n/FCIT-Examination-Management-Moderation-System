<?php
/**
 * Test Suite: Controlled Printing & Africa/Lagos Calendar-Day Restriction
 */

require_once __DIR__ . '/../bootstrap.php';

function testPrinting(): bool {
    echo "--- Testing Controlled Printing & Calendar-Day Rule ---\n";
    $pdo = getDb();
    $allPassed = true;

    $tz = new DateTimeZone(APP_TIMEZONE);
    $today = new DateTime('now', $tz);

    // Test 1: Date Eligibility Matrix
    // A. Exam is tomorrow -> Eligible today
    $tomorrowDate = (clone $today)->modify('+1 day')->format('Y-m-d');
    $checkTomorrow = PrintingService::checkPrintEligibility($tomorrowDate);
    if ($checkTomorrow['eligible'] === true && $checkTomorrow['status'] === 'eligible') {
        echo "  [PASS] Exam tomorrow -> Eligible for printing today (Date: {$today->format('Y-m-d')}).\n";
    } else {
        echo "  [FAIL] Expected eligible for tomorrow's exam, got: {$checkTomorrow['message']}\n";
        $allPassed = false;
    }

    // B. Exam is in 3 days -> BLOCKED (Early)
    $inThreeDaysDate = (clone $today)->modify('+3 days')->format('Y-m-d');
    $checkEarly = PrintingService::checkPrintEligibility($inThreeDaysDate);
    if ($checkEarly['eligible'] === false && $checkEarly['status'] === 'early') {
        echo "  [PASS] Exam in 3 days -> Blocked with status 'early'.\n";
    } else {
        echo "  [FAIL] Early exam was not blocked!\n";
        $allPassed = false;
    }

    // C. Exam was yesterday -> BLOCKED (Expired/Late)
    $yesterdayDate = (clone $today)->modify('-1 day')->format('Y-m-d');
    $checkLate = PrintingService::checkPrintEligibility($yesterdayDate);
    if ($checkLate['eligible'] === false && $checkLate['status'] === 'late') {
        echo "  [PASS] Exam yesterday -> Blocked with status 'late'.\n";
    } else {
        echo "  [FAIL] Past exam was not blocked!\n";
        $allPassed = false;
    }

    // Test 2: Full Controlled Print Execution for an Approved & Locked Paper
    try {
        $eo = $pdo->query("SELECT id FROM users WHERE role = 'exam_officer' LIMIT 1")->fetch();
        $eoId = (int)$eo['id'];
        $hod = $pdo->query("SELECT id FROM users WHERE role = 'hod' AND department_id = (SELECT id FROM departments WHERE code='CSC') LIMIT 1")->fetch();
        $hodId = (int)$hod['id'];
        $lec = $pdo->query("SELECT id FROM users WHERE role = 'lecturer' AND department_id = (SELECT id FROM departments WHERE code='CSC') LIMIT 1")->fetch();
        $lecId = (int)$lec['id'];
        $mod = $pdo->query("SELECT id FROM users WHERE role = 'moderator' AND department_id = (SELECT id FROM departments WHERE code='CSC') LIMIT 1")->fetch();
        $modId = (int)$mod['id'];

        $course = $pdo->query("SELECT id FROM courses WHERE course_code = 'CSC 301'")->fetch();
        $courseId = (int)$course['id'];
        $session = $pdo->query("SELECT id FROM academic_sessions WHERE session_name = '2025/2026'")->fetch();
        $sessionId = (int)$session['id'];
        $semester = $pdo->query("SELECT id FROM semesters WHERE code = 'first'")->fetch();
        $semesterId = (int)$semester['id'];
        
        // Ensure schedule exam date is tomorrow
        $schedule = $pdo->query("SELECT id FROM exam_schedules WHERE course_id = {$courseId}")->fetch();
        $scheduleId = (int)$schedule['id'];
        $pdo->prepare("UPDATE exam_schedules SET exam_date = :dt WHERE id = :id")->execute(['dt' => $tomorrowDate, 'id' => $scheduleId]);

        // Create, submit, moderate, HOD approve, EO approve
        $tmpDocx = sys_get_temp_dir() . '/print_test_' . uniqid() . '.docx';
        $docxContent = MAGIC_DOCX . "\x14\x00\x06\x00OFFICIAL QUESTION PAPER FOR CONTROLLED PRINTING VERIFICATION";
        file_put_contents($tmpDocx, $docxContent);

        $qUpload = [
            'original_name' => 'CSC301_Final_Printable.docx',
            'extension' => 'docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => strlen($docxContent),
            'tmp_path' => $tmpDocx,
            'hash_sha256' => hash('sha256', $docxContent)
        ];

        $draft = WorkflowService::createDraftPaper($lecId, $courseId, $sessionId, $semesterId, $scheduleId, $qUpload, null, 'Printable Final');
        $paperId = (int)$draft['paper_id'];

        WorkflowService::submitPaper($paperId, $lecId);
        WorkflowService::moderatorApprovePaper($paperId, $modId, 'Approved for print test');
        WorkflowService::hodApprovePaper($paperId, $hodId, 'HOD signoff');
        WorkflowService::eoApprovePaper($paperId, $eoId, 'EO signoff');

        // Paper is now Approved and Locked
        $paperStatus = $pdo->query("SELECT status FROM examination_papers WHERE id = {$paperId}")->fetchColumn();
        if ($paperStatus !== STATUS_APPROVED) {
            throw new RuntimeException("Paper is not in Approved status (status: {$paperStatus})");
        }

        // Execute Controlled Print
        $printResult = PrintingService::executeControlledPrint($paperId, $eoId, 120);

        if ($printResult['success'] && !empty($printResult['verification_code']) && strpos($printResult['decrypted_content'], 'OFFICIAL QUESTION PAPER') !== false) {
            echo "  [PASS] Controlled print successfully executed: Verification Code = {$printResult['verification_code']}.\n";
        } else {
            echo "  [FAIL] Controlled print execution returned invalid output.\n";
            $allPassed = false;
        }

        // Verify status transitioned to Printed
        $finalStatus = $pdo->query("SELECT status FROM examination_papers WHERE id = {$paperId}")->fetchColumn();
        if ($finalStatus === STATUS_PRINTED) {
            echo "  [PASS] Paper status updated to Printed.\n";
        } else {
            echo "  [FAIL] Expected Printed status, got: '{$finalStatus}'.\n";
            $allPassed = false;
        }

        // Verify print_logs entry
        $printLog = $pdo->query("SELECT * FROM print_logs WHERE paper_id = {$paperId}")->fetch();
        if ($printLog && $printLog['copies_printed'] === 120) {
            echo "  [PASS] Print log recorded with copies, timestamp, and IP.\n";
        } else {
            echo "  [FAIL] Print log row missing or incorrect.\n";
            $allPassed = false;
        }

        @unlink($tmpDocx);

    } catch (Exception $e) {
        echo "  [FAIL] Print execution exception: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        $allPassed = false;
    }

    return $allPassed;
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $ok = testPrinting();
    exit($ok ? 0 : 1);
}
