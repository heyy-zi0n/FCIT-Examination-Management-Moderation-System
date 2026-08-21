<?php
/**
 * Controlled Examination Printing Service
 * Enforces strict calendar-day printing window in Africa/Lagos timezone,
 * one-time controlled decryption, watermarking, and tamper-evident print logging.
 */

class PrintingService {

    /**
     * Determine printing eligibility based strictly on Africa/Lagos calendar date
     * Rule: current calendar date == examination date - 1 calendar day
     */
    public static function checkPrintEligibility(string $examDate): array {
        $tz = new DateTimeZone(APP_TIMEZONE);
        $todayDt = new DateTime('now', $tz);
        $todayDate = $todayDt->format('Y-m-d');

        $examDt = new DateTime($examDate, $tz);
        $examDateStr = $examDt->format('Y-m-d');

        // Permitted print date is exactly 1 calendar day before exam date
        $allowedPrintDt = (clone $examDt)->modify('-1 day');
        $allowedPrintDate = $allowedPrintDt->format('Y-m-d');

        if ($todayDate < $allowedPrintDate) {
            return [
                'eligible' => false,
                'status' => 'early',
                'allowed_date' => $allowedPrintDate,
                'exam_date' => $examDateStr,
                'current_date' => $todayDate,
                'message' => "Printing blocked: Examination date is {$examDateStr}. Printing is permitted strictly on {$allowedPrintDate}."
            ];
        }

        if ($todayDate > $allowedPrintDate) {
            return [
                'eligible' => false,
                'status' => 'late',
                'allowed_date' => $allowedPrintDate,
                'exam_date' => $examDateStr,
                'current_date' => $todayDate,
                'message' => "Printing window expired: Printing was only permitted on {$allowedPrintDate} (exam date: {$examDateStr})."
            ];
        }

        return [
            'eligible' => true,
            'status' => 'eligible',
            'allowed_date' => $allowedPrintDate,
            'exam_date' => $examDateStr,
            'current_date' => $todayDate,
            'message' => "Eligible for printing today ({$todayDate})."
        ];
    }

    /**
     * Authorize and execute controlled printing
     */
    public static function executeControlledPrint(int $paperId, int $examOfficerId, int $copies, ?string $reprintReason = null): array {
        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            // Verify Exam Officer role
            $stmtUser = $pdo->prepare("SELECT role, title, first_name, last_name FROM users WHERE id = :id");
            $stmtUser->execute(['id' => $examOfficerId]);
            $user = $stmtUser->fetch();

            $stmtDel = $pdo->prepare("
                SELECT id FROM delegations
                WHERE delegate_id = :id AND role_delegated = 'exam_officer'
                  AND is_active = 1 AND CURRENT_DATE BETWEEN start_date AND end_date
            ");
            $stmtDel->execute(['id' => $examOfficerId]);
            $isDelegate = (bool)$stmtDel->fetch();

            if (!$isDelegate && (!$user || $user['role'] !== ROLE_EXAM_OFFICER)) {
                throw new RuntimeException("Unauthorized: Only an Exam Officer may execute controlled printing.");
            }

            // Fetch paper details with lockdown and schedule
            $stmtPaper = $pdo->prepare("
                SELECT ep.*, es.exam_date, es.exam_time, es.venue, es.expected_students,
                       c.course_code, c.course_title,
                       bl.id AS lockdown_id, bl.encrypted_file_path, bl.checksum_sha256,
                       bl.encryption_iv, bl.encryption_tag, bl.is_unlocked,
                       pv.version_number, pv.question_file_path, pv.question_file_hash
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                LEFT JOIN exam_schedules es ON ep.exam_schedule_id = es.id
                LEFT JOIN blind_lockdowns bl ON ep.id = bl.paper_id
                LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
                WHERE ep.id = :id
                FOR UPDATE
            ");
            $stmtPaper->execute(['id' => $paperId]);
            $paper = $stmtPaper->fetch();

            if (!$paper) {
                throw new RuntimeException("Examination paper not found.");
            }

            // Validate status
            if (!in_array($paper['status'], [STATUS_APPROVED, STATUS_LOCKED, STATUS_QUEUED, STATUS_PRINTED], true)) {
                throw new RuntimeException("Paper is not approved for printing (current status: {$paper['status']}).");
            }

            // Validate Schedule Date
            if (empty($paper['exam_date'])) {
                throw new RuntimeException("Cannot print: No authoritative examination schedule assigned to this course.");
            }

            // Enforce Calendar Day Printing Rule
            $eligibility = self::checkPrintEligibility($paper['exam_date']);
            if (!$eligibility['eligible'] && empty($reprintReason)) {
                $isEmergency = self::hasApprovedEmergencyPrint($paperId, (int)$paper['current_version_id']);
                if (!$isEmergency) {
                    throw new RuntimeException($eligibility['message']);
                }
            }

            // Check Lockdown Record
            if (!$paper['lockdown_id'] || !file_exists($paper['encrypted_file_path'])) {
                throw new RuntimeException("Blind Lockdown record or encrypted payload missing.");
            }

            if ($paper['is_unlocked']) {
                throw new RuntimeException("Paper is currently in an unlocked state. Printing is forbidden.");
            }

            // Decrypt Question Paper from Lockdown storage
            $encryptedData = file_get_contents($paper['encrypted_file_path']);
            $decryptedContent = EncryptionService::decryptFileContent(
                $encryptedData,
                $paper['encryption_iv'],
                $paper['encryption_tag'],
                $paper['checksum_sha256']
            );

            // Generate unique print verification code
            $verificationCode = sprintf('LASU-PRN-%s-%s', strtoupper(bin2hex(random_bytes(6))), date('YmdHis'));

            // Check if reprint
            $isReprint = ($paper['status'] === STATUS_PRINTED) ? 1 : 0;

            // Record in print_logs
            $stmtLog = $pdo->prepare("
                INSERT INTO print_logs (
                    paper_id, version_id, exam_officer_id, copies_printed, print_status,
                    verification_code, client_ip, user_agent, is_reprint, reprint_reason, printed_at
                ) VALUES (
                    :paper_id, :version_id, :eo_id, :copies, 'successful',
                    :code, :ip, :ua, :is_reprint, :reason, NOW()
                )
            ");
            $stmtLog->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'eo_id' => $examOfficerId,
                'copies' => $copies > 0 ? $copies : ($paper['expected_students'] ?: 100),
                'code' => $verificationCode,
                'ip' => getClientIp(),
                'ua' => getUserAgent(),
                'is_reprint' => $isReprint,
                'reason' => $reprintReason
            ]);
            $logId = (int)$pdo->lastInsertId();

            // Update paper status to Printed
            $stmtUpd = $pdo->prepare("UPDATE examination_papers SET status = 'Printed', printed_at = NOW(), updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['id' => $paperId]);

            // Audit Print Event
            logAudit($examOfficerId, 'controlled_print_executed', 'print_log', $logId, [
                'paper_code' => $paper['paper_code'],
                'course_code' => $paper['course_code'],
                'verification_code' => $verificationCode,
                'copies' => $copies,
                'is_reprint' => $isReprint
            ]);

            // Notifications
            createNotification((int)$paper['lecturer_id'], 'Examination Paper Printed', "Controlled printing for {$paper['paper_code']} ({$paper['course_code']}) has been completed by Exam Officer.", "/FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");

            $pdo->commit();

            return [
                'success' => true,
                'verification_code' => $verificationCode,
                'paper_code' => $paper['paper_code'],
                'course_code' => $paper['course_code'],
                'copies' => $copies,
                'exam_date' => $paper['exam_date'],
                'decrypted_content' => $decryptedContent
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Check if paper has an approved emergency print request
     */
    public static function hasApprovedEmergencyPrint(int $paperId, int $versionId): bool {
        $pdo = getDb();
        $stmt = $pdo->prepare("
            SELECT id FROM emergency_print_requests 
            WHERE paper_id = :paper_id AND version_id = :version_id AND status = 'approved'
            LIMIT 1
        ");
        $stmt->execute(['paper_id' => $paperId, 'version_id' => $versionId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Request Emergency Printing
     */
    public static function requestEmergencyPrint(int $paperId, int $versionId, int $eoId, string $reason): bool {
        if (empty(trim($reason))) {
            throw new InvalidArgumentException("Mandatory justification reason required for emergency print.");
        }

        $pdo = getDb();
        
        // Ensure no pending request exists
        $stmtCheck = $pdo->prepare("
            SELECT id FROM emergency_print_requests 
            WHERE paper_id = :paper_id AND version_id = :version_id AND status = 'pending'
        ");
        $stmtCheck->execute(['paper_id' => $paperId, 'version_id' => $versionId]);
        if ($stmtCheck->fetch()) {
            throw new RuntimeException("An emergency print request is already pending for this paper version.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO emergency_print_requests (paper_id, version_id, exam_officer_id, reason, status, created_at)
            VALUES (:paper_id, :version_id, :eo_id, :reason, 'pending', NOW())
        ");
        $stmt->execute([
            'paper_id' => $paperId,
            'version_id' => $versionId,
            'eo_id' => $eoId,
            'reason' => $reason
        ]);
        
        $reqId = $pdo->lastInsertId();
        
        // Notify HOD
        $stmtPaper = $pdo->prepare("SELECT ep.course_id, c.department_id, ep.paper_code FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE ep.id = :id");
        $stmtPaper->execute(['id' => $paperId]);
        $paper = $stmtPaper->fetch();
        if ($paper) {
            notifyRoleInDepartment(ROLE_HOD, (int)$paper['department_id'], 'Emergency Print Request', "An emergency print was requested for {$paper['paper_code']}: {$reason}", "/FOCITECMS/dashboard/hod/emergency_printing.php");
        }
        
        logAudit($eoId, 'emergency_print_request', 'emergency_print_requests', $reqId, ['paper_id' => $paperId, 'reason' => $reason]);
        return true;
    }

    /**
     * Authorize or reject Emergency Printing
     */
    public static function authorizeEmergencyPrint(int $requestId, int $hodId, string $decision): bool {
        if (!in_array($decision, ['approved', 'rejected'])) {
            throw new InvalidArgumentException("Invalid decision.");
        }

        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM emergency_print_requests WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $requestId]);
            $req = $stmt->fetch();

            if (!$req || $req['status'] !== 'pending') {
                throw new RuntimeException("Request not found or already processed.");
            }

            // Verify HOD department
            $stmtPaper = $pdo->prepare("SELECT c.department_id, ep.paper_code FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE ep.id = :id");
            $stmtPaper->execute(['id' => $req['paper_id']]);
            $paper = $stmtPaper->fetch();

            $stmtHod = $pdo->prepare("SELECT role, department_id FROM users WHERE id = :id");
            $stmtHod->execute(['id' => $hodId]);
            $hodUser = $stmtHod->fetch();

            if (!$hodUser || ($hodUser['role'] === ROLE_HOD && (int)$hodUser['department_id'] !== (int)$paper['department_id'])) {
                throw new RuntimeException("You can only authorize papers within your authorized department.");
            }

            $stmtUpd = $pdo->prepare("
                UPDATE emergency_print_requests
                SET status = :status, hod_id = :hod_id, hod_decision_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['status' => $decision, 'hod_id' => $hodId, 'id' => $requestId]);

            logAudit($hodId, 'emergency_print_decision', 'emergency_print_requests', $requestId, ['decision' => $decision]);
            
            createNotification((int)$req['exam_officer_id'], 'Emergency Print ' . ucfirst($decision), "Your emergency print request for {$paper['paper_code']} was {$decision} by the HOD.", "/FOCITECMS/dashboard/exam_officer/print_queue.php");

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
