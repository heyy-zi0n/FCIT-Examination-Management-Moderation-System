<?php
/**
 * Test Suite 8: Emergency Printing Authorization
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../services/PrintingService.php';
require_once __DIR__ . '/../services/WorkflowService.php';

function testEmergencyPrinting(): bool {
    echo "--- Testing Emergency Printing Authorization ---\n";

    $pdo = getDb();
    $allPassed = true;

    try {

        // 1. Setup Data
        $deptCode = 'CSC';
        $stmtDept = $pdo->prepare("SELECT id FROM departments WHERE code = :code");
        $stmtDept->execute(['code' => $deptCode]);
        $deptId = (int)$stmtDept->fetchColumn();

        $stmtCourse = $pdo->prepare("SELECT id FROM courses WHERE department_id = :dept_id LIMIT 1");
        $stmtCourse->execute(['dept_id' => $deptId]);
        $courseId = (int)$stmtCourse->fetchColumn();

        $stmtLecturer = $pdo->prepare("SELECT id FROM users WHERE department_id = :dept_id AND role = 'lecturer' LIMIT 1");
        $stmtLecturer->execute(['dept_id' => $deptId]);
        $lecturerId = (int)$stmtLecturer->fetchColumn();

        $stmtHod = $pdo->prepare("SELECT id FROM users WHERE department_id = :dept_id AND role = 'hod' LIMIT 1");
        $stmtHod->execute(['dept_id' => $deptId]);
        $hodId = (int)$stmtHod->fetchColumn();

        $stmtEo = $pdo->prepare("SELECT id FROM users WHERE department_id = :dept_id AND role = 'exam_officer' LIMIT 1");
        $stmtEo->execute(['dept_id' => $deptId]);
        $eoId = (int)$stmtEo->fetchColumn();

        $stmtSched = $pdo->prepare("SELECT id FROM exam_schedules WHERE course_id = :course_id LIMIT 1");
        $stmtSched->execute(['course_id' => $courseId]);
        $schedId = (int)$stmtSched->fetchColumn();

        if (!$schedId) {
            $stmtInsSched = $pdo->prepare("INSERT INTO exam_schedules (course_id, academic_session_id, semester_id, exam_date, exam_time, venue, expected_students, duration_minutes, status, created_by, created_at) VALUES (:course_id, 1, 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY), '09:00:00', 'Hall A', 100, 120, 'published', :creator, NOW())");
            $stmtInsSched->execute(['course_id' => $courseId, 'creator' => $hodId]);
            $schedId = (int)$pdo->lastInsertId();
        }

        $sessionId = 1;
        $semesterId = 1;
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'exam_');
        file_put_contents($tmpFile, "PK\x03\x04\x14\x00\x06\x00Fake DOCX Content inside emergency test");
        $qUpload = [
            'original_name' => 'CSC301_Emergency.docx',
            'extension' => 'docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => filesize($tmpFile),
            'tmp_path' => $tmpFile,
            'hash_sha256' => hash_file('sha256', $tmpFile)
        ];

        // 2. Create and lock a paper
        $draftResult = WorkflowService::createDraftPaper($lecturerId, $courseId, $sessionId, $semesterId, $schedId, $qUpload, null, 'Initial draft');
        $paperId = (int)$draftResult['paper_id'];
        
        $versionId = (int)$pdo->query("SELECT id FROM paper_versions WHERE paper_id = {$paperId} ORDER BY id DESC LIMIT 1")->fetchColumn();

        WorkflowService::submitPaper($paperId, $lecturerId);
        $pdo->exec("UPDATE examination_papers SET status = 'Moderated' WHERE id = {$paperId}"); // fast forward
        WorkflowService::hodApprovePaper($paperId, $hodId, 'Approved by HOD');
        WorkflowService::eoApprovePaper($paperId, $eoId, 'Approved by EO');

        // 3. Test: EO requests Emergency Print
        try {
            PrintingService::requestEmergencyPrint($paperId, $versionId, $eoId, 'Printing machine broke down yesterday.');
            echo "  [PASS] Exam Officer successfully requested emergency print.\n";
        } catch (Exception $e) {
            echo "  [FAIL] Failed to request emergency print: " . $e->getMessage() . "\n";
            $allPassed = false;
        }

        // 4. Test: Mandatory reason required
        try {
            PrintingService::requestEmergencyPrint($paperId, $versionId, $eoId, '   ');
            echo "  [FAIL] Empty reason was allowed.\n";
            $allPassed = false;
        } catch (InvalidArgumentException $e) {
            echo "  [PASS] Empty reason was correctly rejected.\n";
        }

        // 5. Test: Pending requests cannot authorize printing
        $isEmergencyPending = PrintingService::hasApprovedEmergencyPrint($paperId, $versionId);
        if ($isEmergencyPending) {
            echo "  [FAIL] Pending request should not authorize printing.\n";
            $allPassed = false;
        } else {
            echo "  [PASS] Pending request does not authorize printing.\n";
        }

        // 6. Test: HOD approves request
        $reqId = (int)$pdo->query("SELECT id FROM emergency_print_requests WHERE paper_id = {$paperId} ORDER BY id DESC LIMIT 1")->fetchColumn();
        try {
            PrintingService::authorizeEmergencyPrint($reqId, $hodId, 'approved');
            echo "  [PASS] HOD successfully approved the request.\n";
        } catch (Exception $e) {
            echo "  [FAIL] HOD failed to approve request: " . $e->getMessage() . "\n";
            $allPassed = false;
        }

        // 7. Test: Approved request authorizes printing
        $isEmergencyApproved = PrintingService::hasApprovedEmergencyPrint($paperId, $versionId);
        if ($isEmergencyApproved) {
            echo "  [PASS] Approved request correctly authorizes printing.\n";
        } else {
            echo "  [FAIL] Approved request failed to authorize printing.\n";
            $allPassed = false;
        }

        // 8. Test: Controlled printing execution respects the approved request
        try {
            PrintingService::executeControlledPrint($paperId, $eoId, 50, null);
            echo "  [PASS] Controlled print execution succeeded via emergency approval.\n";
        } catch (Exception $e) {
            echo "  [FAIL] Controlled print execution failed: " . $e->getMessage() . "\n";
            $allPassed = false;
        }

    } catch (Exception $e) {
        echo "  [FAIL] Unexpected Exception: " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    return $allPassed;
}
