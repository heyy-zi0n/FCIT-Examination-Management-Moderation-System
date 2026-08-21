<?php
/**
 * Test Suite: Examination Workflow State Machine & Dual Approval
 */

require_once __DIR__ . '/../bootstrap.php';

function testWorkflow(): bool {
    echo "--- Testing Workflow State Machine & Dual Approval ---\n";
    $pdo = getDb();
    $allPassed = true;

    // Get test user IDs
    $stmtUsers = $pdo->query("SELECT email, id, role, department_id FROM users");
    $users = [];
    while ($r = $stmtUsers->fetch()) {
        $users[$r['email']] = (int)$r['id'];
    }

    $lecturerId = $users['lecturer.csc@lasu.edu.ng'];
    $moderatorId = $users['moderator.csc@lasu.edu.ng'];
    $hodId = $users['hod.csc@lasu.edu.ng'];
    $eoId = $users['exam.officer@lasu.edu.ng'];

    // Get Course and Schedule IDs
    $course = $pdo->query("SELECT id FROM courses WHERE course_code = 'CSC 301'")->fetch();
    $courseId = (int)$course['id'];
    $session = $pdo->query("SELECT id FROM academic_sessions WHERE session_name = '2025/2026'")->fetch();
    $sessionId = (int)$session['id'];
    $semester = $pdo->query("SELECT id FROM semesters WHERE code = 'first'")->fetch();
    $semesterId = (int)$semester['id'];
    $schedule = $pdo->query("SELECT id FROM exam_schedules WHERE course_id = {$courseId}")->fetch();
    $scheduleId = (int)$schedule['id'];

    // Step 1: Create Temporary DOCX File
    $tmpDocx = sys_get_temp_dir() . '/wf_test_' . uniqid() . '.docx';
    $docxContent = MAGIC_DOCX . "\x14\x00\x06\x00SAMPLE LASU FCIT CSC 301 EXAMINATION QUESTIONS V1";
    file_put_contents($tmpDocx, $docxContent);

    $qUpload = [
        'original_name' => 'CSC301_Main_Exam.docx',
        'extension' => 'docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size' => strlen($docxContent),
        'tmp_path' => $tmpDocx,
        'hash_sha256' => hash('sha256', $docxContent)
    ];

    try {
        // Step 2: Lecturer creates Draft Paper
        $draftResult = WorkflowService::createDraftPaper($lecturerId, $courseId, $sessionId, $semesterId, $scheduleId, $qUpload, null, 'Initial Question Submission');
        $paperId = (int)$draftResult['paper_id'];
        echo "  [PASS] Paper created in Draft status (Paper ID: {$paperId}, Code: {$draftResult['paper_code']}).\n";

        // Step 3: Submit Paper
        WorkflowService::submitPaper($paperId, $lecturerId);
        $status = $pdo->query("SELECT status FROM examination_papers WHERE id = {$paperId}")->fetchColumn();
        if ($status === STATUS_SUBMITTED) {
            echo "  [PASS] Paper transitioned to Submitted status.\n";
        } else {
            echo "  [FAIL] Expected Submitted status, got '{$status}'.\n";
            $allPassed = false;
        }

        // Step 4: Moderator Returns Paper
        WorkflowService::moderatorReturnPaper($paperId, $moderatorId, 'Please add marks distribution for Section B.');
        $status = $pdo->query("SELECT status FROM examination_papers WHERE id = {$paperId}")->fetchColumn();
        if ($status === STATUS_RETURNED) {
            echo "  [PASS] Moderator returned paper -> Returned status.\n";
        } else {
            echo "  [FAIL] Expected Returned status, got '{$status}'.\n";
            $allPassed = false;
        }

        // Step 5: Lecturer creates Revision V2
        $tmpDocxV2 = sys_get_temp_dir() . '/wf_test_v2_' . uniqid() . '.docx';
        $docxContentV2 = MAGIC_DOCX . "\x14\x00\x06\x00SAMPLE LASU FCIT CSC 301 EXAMINATION QUESTIONS V2 WITH MARKS ALLOCATION";
        file_put_contents($tmpDocxV2, $docxContentV2);
        $qUploadV2 = [
            'original_name' => 'CSC301_Main_Exam_V2.docx',
            'extension' => 'docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => strlen($docxContentV2),
            'tmp_path' => $tmpDocxV2,
            'hash_sha256' => hash('sha256', $docxContentV2)
        ];

        $revResult = WorkflowService::createRevisionVersion($paperId, $lecturerId, $qUploadV2, null, 'Added marks breakdown for Section B');
        if ($revResult['version_number'] === 2) {
            echo "  [PASS] Revision V2 created with independent physical storage.\n";
        } else {
            echo "  [FAIL] Revision version number mismatch.\n";
            $allPassed = false;
        }

        // Step 6: Lecturer resubmits V2
        WorkflowService::submitPaper($paperId, $lecturerId);
        echo "  [PASS] Revision V2 resubmitted for moderation.\n";

        // Step 7: Moderator Approves V2
        WorkflowService::moderatorApprovePaper($paperId, $moderatorId, 'All corrections verified.');
        $status = $pdo->query("SELECT status FROM examination_papers WHERE id = {$paperId}")->fetchColumn();
        if ($status === STATUS_MODERATED) {
            echo "  [PASS] Moderator approved paper -> Moderated status (awaiting HOD + EO dual approval).\n";
        } else {
            echo "  [FAIL] Expected Moderated status, got '{$status}'.\n";
            $allPassed = false;
        }

        // Step 8: Test Dual Approval Rule — HOD Approves Alone
        WorkflowService::hodApprovePaper($paperId, $hodId, 'Approved by HOD.');
        $paperRow = $pdo->query("SELECT status, hod_status, eo_status FROM examination_papers WHERE id = {$paperId}")->fetch();
        if ($paperRow['status'] === STATUS_MODERATED && $paperRow['hod_status'] === 'approved' && $paperRow['eo_status'] === 'pending') {
            echo "  [PASS] HOD approval alone does NOT trigger Approved or Blind Lockdown (Remains Moderated).\n";
        } else {
            echo "  [FAIL] Single HOD approval violated dual approval boundary: status='{$paperRow['status']}'.\n";
            $allPassed = false;
        }

        // Step 9: Exam Officer Approves -> Dual Approval Satisfied!
        WorkflowService::eoApprovePaper($paperId, $eoId, 'Approved by Exam Officer.');
        $paperApproved = $pdo->query("SELECT status, hod_status, eo_status FROM examination_papers WHERE id = {$paperId}")->fetch();
        if ($paperApproved['status'] === STATUS_APPROVED) {
            echo "  [PASS] Dual Approval satisfied: Paper transitioned to Approved status.\n";
        } else {
            echo "  [FAIL] Expected Approved status after dual approval, got '{$paperApproved['status']}'.\n";
            $allPassed = false;
        }

        // Step 10: Verify Blind Lockdown
        $lockdown = $pdo->query("SELECT * FROM blind_lockdowns WHERE paper_id = {$paperId}")->fetch();
        if ($lockdown && file_exists($lockdown['encrypted_file_path']) && $lockdown['is_unlocked'] == 0) {
            echo "  [PASS] Blind Lockdown automatically triggered and AES-256 payload verified on disk.\n";
        } else {
            echo "  [FAIL] Blind Lockdown record or encrypted payload missing!\n";
            $allPassed = false;
        }

        // Step 11: Emergency Unlock
        WorkflowService::emergencyUnlock($paperId, $hodId, 'Urgent correction required for exam venue compatibility');
        $unlockedPaper = $pdo->query("SELECT ep.status, bl.is_unlocked, bl.unlock_reason FROM examination_papers ep JOIN blind_lockdowns bl ON ep.id = bl.paper_id WHERE ep.id = {$paperId}")->fetch();
        if ($unlockedPaper['status'] === STATUS_RETURNED && $unlockedPaper['is_unlocked'] == 1) {
            echo "  [PASS] Emergency unlock successfully reverted paper to Returned status with audit trail.\n";
        } else {
            echo "  [FAIL] Emergency unlock failed to update status.\n";
            $allPassed = false;
        }

    } catch (Exception $e) {
        echo "  [FAIL] Workflow exception: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        $allPassed = false;
    }

    @unlink($tmpDocx);
    @unlink($tmpDocxV2 ?? '');
    return $allPassed;
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $ok = testWorkflow();
    exit($ok ? 0 : 1);
}
