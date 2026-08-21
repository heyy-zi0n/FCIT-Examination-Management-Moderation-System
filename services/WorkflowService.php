<?php
/**
 * Workflow State Machine Service
 * Authoritative business logic governing the entire examination lifecycle
 */

class WorkflowService {

    /**
     * Create initial examination paper draft
     */
    public static function createDraftPaper(int $lecturerId, int $courseId, int $sessionId, int $semesterId, ?int $scheduleId, array $qUpload, ?array $gUpload, ?string $submissionNotes): array {
        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            // Verify lecturer is assigned to this course
            $stmtCheck = $pdo->prepare("
                SELECT id FROM lecturer_course_assignments 
                WHERE lecturer_id = :lec_id AND course_id = :course_id AND academic_session_id = :session_id
            ");
            $stmtCheck->execute([
                'lec_id' => $lecturerId,
                'course_id' => $courseId,
                'session_id' => $sessionId
            ]);
            if (!$stmtCheck->fetch()) {
                throw new RuntimeException("You are not authorized or assigned to create examination papers for this course in the selected session.");
            }

            // Get Course Details
            $stmtCourse = $pdo->prepare("SELECT course_code, department_id, level_id FROM courses WHERE id = :id");
            $stmtCourse->execute(['id' => $courseId]);
            $course = $stmtCourse->fetch();
            if (!$course) {
                throw new RuntimeException("Selected course not found.");
            }

            // Auto-detect moderator for this department & level
            $stmtMod = $pdo->prepare("
                SELECT moderator_id FROM moderator_assignments
                WHERE department_id = :dept_id AND level_id = :level_id AND academic_session_id = :session_id
                LIMIT 1
            ");
            $stmtMod->execute([
                'dept_id' => $course['department_id'],
                'level_id' => $course['level_id'],
                'session_id' => $sessionId
            ]);
            $modRow = $stmtMod->fetch();
            $moderatorId = $modRow ? (int)$modRow['moderator_id'] : null;

            // Generate unique Paper Code
            $paperCode = sprintf('LASU-%s-%s-SEM%d-%04d', $course['course_code'], str_replace('/', '-', $sessionId), $semesterId, rand(1000, 9999));

            // Insert Examination Paper row
            $stmtPaper = $pdo->prepare("
                INSERT INTO examination_papers (
                    paper_code, course_id, academic_session_id, semester_id, exam_schedule_id, 
                    lecturer_id, status, moderator_id, moderator_decision, hod_status, eo_status, created_at
                ) VALUES (
                    :code, :course_id, :session_id, :semester_id, :schedule_id,
                    :lec_id, 'Draft', :mod_id, 'pending', 'pending', 'pending', NOW()
                )
            ");
            $stmtPaper->execute([
                'code' => $paperCode,
                'course_id' => $courseId,
                'session_id' => $sessionId,
                'semester_id' => $semesterId,
                'schedule_id' => $scheduleId,
                'lec_id' => $lecturerId,
                'mod_id' => $moderatorId
            ]);
            $paperId = (int)$pdo->lastInsertId();

            // Store files for Version 1
            $storedFiles = DocumentService::storeVersionFiles($paperId, 1, $qUpload, $gUpload);

            // Insert Paper Version row
            $stmtVer = $pdo->prepare("
                INSERT INTO paper_versions (
                    paper_id, version_number, question_file_path, question_file_name, question_file_hash, question_file_size,
                    guide_file_path, guide_file_name, guide_file_hash, guide_file_size, submission_notes, created_by, created_at
                ) VALUES (
                    :paper_id, 1, :q_path, :q_name, :q_hash, :q_size,
                    :g_path, :g_name, :g_hash, :g_size, :notes, :created_by, NOW()
                )
            ");
            $stmtVer->execute([
                'paper_id' => $paperId,
                'q_path' => $storedFiles['question_path'],
                'q_name' => $storedFiles['question_name'],
                'q_hash' => $storedFiles['question_hash'],
                'q_size' => $storedFiles['question_size'],
                'g_path' => $storedFiles['guide_path'],
                'g_name' => $storedFiles['guide_name'],
                'g_hash' => $storedFiles['guide_hash'],
                'g_size' => $storedFiles['guide_size'],
                'notes' => $submissionNotes,
                'created_by' => $lecturerId
            ]);
            $versionId = (int)$pdo->lastInsertId();

            // Update current_version_id on paper
            $stmtUpd = $pdo->prepare("UPDATE examination_papers SET current_version_id = :ver_id WHERE id = :id");
            $stmtUpd->execute(['ver_id' => $versionId, 'id' => $paperId]);

            // Audit
            logAudit($lecturerId, 'create_draft_paper', 'examination_paper', $paperId, [
                'paper_code' => $paperCode,
                'course_code' => $course['course_code'],
                'version' => 1
            ]);

            $pdo->commit();
            return ['paper_id' => $paperId, 'version_id' => $versionId, 'paper_code' => $paperCode];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Submit paper for Moderation (Draft -> Submitted or Returned -> Submitted)
     */
    public static function submitPaper(int $paperId, int $lecturerId): bool {
        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, c.course_code, c.department_id, c.level_id
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                WHERE ep.id = :id AND ep.lecturer_id = :lec_id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId, 'lec_id' => $lecturerId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Examination paper not found or access denied.");
            }

            if (!in_array($paper['status'], [STATUS_DRAFT, STATUS_RETURNED], true)) {
                throw new RuntimeException("Paper cannot be submitted from status: '{$paper['status']}'. Must be Draft or Returned.");
            }

            if (empty($paper['current_version_id'])) {
                throw new RuntimeException("No valid document version associated with this paper.");
            }

            // Ensure moderator is assigned
            $moderatorId = $paper['moderator_id'];
            if (!$moderatorId) {
                $stmtMod = $pdo->prepare("
                    SELECT moderator_id FROM moderator_assignments
                    WHERE department_id = :dept_id AND level_id = :level_id AND academic_session_id = :session_id
                    LIMIT 1
                ");
                $stmtMod->execute([
                    'dept_id' => $paper['department_id'],
                    'level_id' => $paper['level_id'],
                    'session_id' => $paper['academic_session_id']
                ]);
                $modRow = $stmtMod->fetch();
                $moderatorId = $modRow ? (int)$modRow['moderator_id'] : null;
            }

            // Update status to Submitted
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET status = 'Submitted', moderator_id = :mod_id, moderator_decision = 'pending',
                    hod_status = 'pending', eo_status = 'pending', updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['mod_id' => $moderatorId, 'id' => $paperId]);

            // Audit
            logAudit($lecturerId, 'submit_paper', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'status' => STATUS_SUBMITTED,
                'version_id' => $paper['current_version_id']
            ]);

            // Notification to Lecturer
            createNotification($lecturerId, 'Paper Submitted Successfully', "Your examination paper {$paper['paper_code']} for {$paper['course_code']} has been submitted for moderation.", "/FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");

            // Notification to Moderator
            if ($moderatorId) {
                createNotification($moderatorId, 'New Examination Paper for Moderation', "Examination paper {$paper['paper_code']} ({$paper['course_code']}) is waiting for your review.", "/FOCITECMS/dashboard/moderator/review_paper.php?id={$paperId}");
            }

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Moderator approves paper (Submitted -> Moderated)
     * Triggers the final approval stage (awaiting HOD + EO dual approval)
     */
    public static function moderatorApprovePaper(int $paperId, int $moderatorId, ?string $comments = null): bool {
        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, c.course_code, c.department_id, c.level_id
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                WHERE ep.id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Paper not found.");
            }

            if ($paper['status'] !== STATUS_SUBMITTED) {
                throw new RuntimeException("Paper is not in 'Submitted' state for moderation (current status: {$paper['status']}).");
            }

            // Verify moderator assignment
            $stmtAuth = $pdo->prepare("
                SELECT id FROM moderator_assignments
                WHERE moderator_id = :mod_id AND department_id = :dept_id AND level_id = :lvl_id AND academic_session_id = :session_id
            ");
            $stmtAuth->execute([
                'mod_id' => $moderatorId,
                'dept_id' => $paper['department_id'],
                'lvl_id' => $paper['level_id'],
                'session_id' => $paper['academic_session_id']
            ]);
            if (!$stmtAuth->fetch() && (int)$paper['moderator_id'] !== $moderatorId) {
                throw new RuntimeException("You are not assigned as moderator for this department and level.");
            }

            // Update paper to Moderated
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET status = 'Moderated', moderator_id = :mod_id, moderator_decision = 'approved',
                    moderator_decision_at = NOW(), hod_status = 'pending', eo_status = 'pending', updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['mod_id' => $moderatorId, 'id' => $paperId]);

            // Record in paper_approvals
            $stmtAppr = $pdo->prepare("
                INSERT INTO paper_approvals (paper_id, version_id, stage, approver_id, decision, comments, ip_address, created_at)
                VALUES (:paper_id, :version_id, 'moderator', :approver_id, 'approved', :comments, :ip, NOW())
            ");
            $stmtAppr->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'approver_id' => $moderatorId,
                'comments' => $comments,
                'ip' => getClientIp()
            ]);

            // Audit
            logAudit($moderatorId, 'moderator_approve', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'version_id' => $paper['current_version_id'],
                'decision' => 'approved'
            ]);

            // Notify Lecturer
            createNotification((int)$paper['lecturer_id'], 'Moderation Approved', "Your examination paper {$paper['paper_code']} ({$paper['course_code']}) was approved by the moderator and is now pending HOD and Exam Officer approval.", "/FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");

            // Notify HOD
            notifyRoleInDepartment(ROLE_HOD, (int)$paper['department_id'], 'Paper Awaiting HOD Approval', "Moderated paper {$paper['paper_code']} ({$paper['course_code']}) is awaiting your final HOD sign-off.", "/FOCITECMS/dashboard/hod/pending_approvals.php");

            // Notify Exam Officers
            notifyAllExamOfficers('Paper Awaiting Exam Officer Approval', "Moderated paper {$paper['paper_code']} ({$paper['course_code']}) is awaiting your Exam Officer review.", "/FOCITECMS/dashboard/exam_officer/pending_approvals.php");

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Moderator returns paper for revision (Submitted -> Returned)
     */
    public static function moderatorReturnPaper(int $paperId, int $moderatorId, string $feedback): bool {
        if (empty(trim($feedback))) {
            throw new InvalidArgumentException("Feedback is required when returning an examination paper.");
        }

        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, c.course_code, c.department_id, c.level_id
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                WHERE ep.id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Paper not found.");
            }

            if ($paper['status'] !== STATUS_SUBMITTED) {
                throw new RuntimeException("Only papers in 'Submitted' state can be returned by moderator.");
            }

            // Update status to Returned
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET status = 'Returned', moderator_decision = 'returned', moderator_decision_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['id' => $paperId]);

            // Record in paper_approvals
            $stmtAppr = $pdo->prepare("
                INSERT INTO paper_approvals (paper_id, version_id, stage, approver_id, decision, comments, ip_address, created_at)
                VALUES (:paper_id, :version_id, 'moderator', :approver_id, 'returned', :comments, :ip, NOW())
            ");
            $stmtAppr->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'approver_id' => $moderatorId,
                'comments' => $feedback,
                'ip' => getClientIp()
            ]);

            // Add general moderation comment
            $stmtComm = $pdo->prepare("
                INSERT INTO moderation_comments (paper_id, version_id, author_id, comment_type, comment_text, status, created_at)
                VALUES (:paper_id, :version_id, :author_id, 'correction', :text, 'open', NOW())
            ");
            $stmtComm->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'author_id' => $moderatorId,
                'text' => $feedback
            ]);

            // Audit
            logAudit($moderatorId, 'moderator_return', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'decision' => 'returned',
                'feedback' => $feedback
            ]);

            // Notify Lecturer
            createNotification((int)$paper['lecturer_id'], 'Paper Returned for Revision', "Your examination paper {$paper['paper_code']} was returned by the moderator with feedback: {$feedback}", "/FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * HOD approves paper at HOD stage
     */
    public static function hodApprovePaper(int $paperId, int $hodId, ?string $comments = null): bool {
        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, c.course_code, c.department_id
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                WHERE ep.id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Paper not found.");
            }

            if ($paper['status'] !== STATUS_MODERATED) {
                throw new RuntimeException("Paper must be in 'Moderated' status for HOD approval (current status: {$paper['status']}).");
            }

            // Verify HOD department access
            $stmtHod = $pdo->prepare("SELECT department_id FROM users WHERE id = :id AND role = 'hod'");
            $stmtHod->execute(['id' => $hodId]);
            $hodUser = $stmtHod->fetch();

            // Also check for active delegation
            $stmtDel = $pdo->prepare("
                SELECT id FROM delegations
                WHERE delegate_id = :id AND role_delegated = 'hod' AND department_id = :dept_id
                  AND is_active = 1 AND CURRENT_DATE BETWEEN start_date AND end_date
            ");
            $stmtDel->execute(['id' => $hodId, 'dept_id' => $paper['department_id']]);
            $isDelegate = (bool)$stmtDel->fetch();

            if (!$isDelegate && (!$hodUser || (int)$hodUser['department_id'] !== (int)$paper['department_id'])) {
                throw new RuntimeException("You are not authorized as HOD for this department.");
            }

            // Update HOD approval status
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET hod_id = :hod_id, hod_status = 'approved', hod_decision_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['hod_id' => $hodId, 'id' => $paperId]);

            // Record in paper_approvals
            $stmtAppr = $pdo->prepare("
                INSERT INTO paper_approvals (paper_id, version_id, stage, approver_id, decision, comments, ip_address, created_at)
                VALUES (:paper_id, :version_id, 'hod', :approver_id, 'approved', :comments, :ip, NOW())
            ");
            $stmtAppr->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'approver_id' => $hodId,
                'comments' => $comments,
                'ip' => getClientIp()
            ]);

            // Audit
            logAudit($hodId, 'hod_approve', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'stage' => 'hod',
                'decision' => 'approved'
            ]);

            // Evaluate if Dual Approval is now fully satisfied
            self::evaluateDualApproval($paperId, $hodId);

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * HOD returns paper for revision (Moderated -> Returned)
     */
    public static function hodReturnPaper(int $paperId, int $hodId, string $feedback): bool {
        if (empty(trim($feedback))) {
            throw new InvalidArgumentException("Feedback is mandatory when returning a paper.");
        }

        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, c.course_code, c.department_id
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                WHERE ep.id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Paper not found.");
            }

            if ($paper['status'] !== STATUS_MODERATED) {
                throw new RuntimeException("Paper must be in 'Moderated' status to be returned by HOD.");
            }

            // Update to Returned and reset dual approvals
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET status = 'Returned', hod_id = :hod_id, hod_status = 'returned', hod_decision_at = NOW(),
                    eo_status = 'pending', updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['hod_id' => $hodId, 'id' => $paperId]);

            // Record approval decision
            $stmtAppr = $pdo->prepare("
                INSERT INTO paper_approvals (paper_id, version_id, stage, approver_id, decision, comments, ip_address, created_at)
                VALUES (:paper_id, :version_id, 'hod', :approver_id, 'returned', :comments, :ip, NOW())
            ");
            $stmtAppr->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'approver_id' => $hodId,
                'comments' => $feedback,
                'ip' => getClientIp()
            ]);

            // Audit
            logAudit($hodId, 'hod_return', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'decision' => 'returned',
                'feedback' => $feedback
            ]);

            // Notify Lecturer
            createNotification((int)$paper['lecturer_id'], 'Paper Returned by HOD', "Your paper {$paper['paper_code']} was returned by the HOD for correction: {$feedback}", "/FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Exam Officer approves paper at EO stage
     */
    public static function eoApprovePaper(int $paperId, int $eoId, ?string $comments = null): bool {
        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, c.course_code
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                WHERE ep.id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Paper not found.");
            }

            if ($paper['status'] !== STATUS_MODERATED) {
                throw new RuntimeException("Paper must be in 'Moderated' status for Exam Officer approval (current status: {$paper['status']}).");
            }

            // Verify EO role or delegation
            $stmtEo = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmtEo->execute(['id' => $eoId]);
            $eoUser = $stmtEo->fetch();

            $stmtDel = $pdo->prepare("
                SELECT id FROM delegations
                WHERE delegate_id = :id AND role_delegated = 'exam_officer'
                  AND is_active = 1 AND CURRENT_DATE BETWEEN start_date AND end_date
            ");
            $stmtDel->execute(['id' => $eoId]);
            $isDelegate = (bool)$stmtDel->fetch();

            if (!$isDelegate && (!$eoUser || $eoUser['role'] !== ROLE_EXAM_OFFICER)) {
                throw new RuntimeException("You are not authorized as an Exam Officer.");
            }

            // Update EO status
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET eo_id = :eo_id, eo_status = 'approved', eo_decision_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['eo_id' => $eoId, 'id' => $paperId]);

            // Record in paper_approvals
            $stmtAppr = $pdo->prepare("
                INSERT INTO paper_approvals (paper_id, version_id, stage, approver_id, decision, comments, ip_address, created_at)
                VALUES (:paper_id, :version_id, 'exam_officer', :approver_id, 'approved', :comments, :ip, NOW())
            ");
            $stmtAppr->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'approver_id' => $eoId,
                'comments' => $comments,
                'ip' => getClientIp()
            ]);

            // Audit
            logAudit($eoId, 'eo_approve', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'stage' => 'exam_officer',
                'decision' => 'approved'
            ]);

            // Evaluate if Dual Approval is now fully satisfied
            self::evaluateDualApproval($paperId, $eoId);

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Exam Officer returns paper for revision
     */
    public static function eoReturnPaper(int $paperId, int $eoId, string $feedback): bool {
        if (empty(trim($feedback))) {
            throw new InvalidArgumentException("Feedback is mandatory when returning a paper.");
        }

        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, c.course_code
                FROM examination_papers ep
                JOIN courses c ON ep.course_id = c.id
                WHERE ep.id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Paper not found.");
            }

            if ($paper['status'] !== STATUS_MODERATED) {
                throw new RuntimeException("Paper must be in 'Moderated' status to be returned by Exam Officer.");
            }

            // Update to Returned and reset dual approvals
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET status = 'Returned', eo_id = :eo_id, eo_status = 'returned', eo_decision_at = NOW(),
                    hod_status = 'pending', updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['eo_id' => $eoId, 'id' => $paperId]);

            // Record approval decision
            $stmtAppr = $pdo->prepare("
                INSERT INTO paper_approvals (paper_id, version_id, stage, approver_id, decision, comments, ip_address, created_at)
                VALUES (:paper_id, :version_id, 'exam_officer', :approver_id, 'returned', :comments, :ip, NOW())
            ");
            $stmtAppr->execute([
                'paper_id' => $paperId,
                'version_id' => $paper['current_version_id'],
                'approver_id' => $eoId,
                'comments' => $feedback,
                'ip' => getClientIp()
            ]);

            // Audit
            logAudit($eoId, 'eo_return', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'decision' => 'returned',
                'feedback' => $feedback
            ]);

            // Notify Lecturer & HOD
            createNotification((int)$paper['lecturer_id'], 'Paper Returned by Exam Officer', "Your paper {$paper['paper_code']} was returned by the Exam Officer: {$feedback}", "/FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Evaluate Dual Approval condition:
     * Paper becomes Approved AND enters Blind Lockdown ONLY when:
     * hod_status = 'approved' AND eo_status = 'approved'
     */
    public static function evaluateDualApproval(int $paperId, int $actorId): bool {
        $pdo = getDb();
        $stmt = $pdo->prepare("
            SELECT ep.*, c.course_code, pv.question_file_path, pv.question_file_hash, pv.version_number
            FROM examination_papers ep
            JOIN courses c ON ep.course_id = c.id
            JOIN paper_versions pv ON ep.current_version_id = pv.id
            WHERE ep.id = :id
            FOR UPDATE
        ");
        $stmt->execute(['id' => $paperId]);
        $paper = $stmt->fetch();

        if (!$paper) {
            return false;
        }

        // Dual approval check
        if ($paper['hod_status'] === DECISION_APPROVED && $paper['eo_status'] === DECISION_APPROVED) {
            // Both approved! Transition to Approved and immediately execute Blind Lockdown
            $stmtUpd = $pdo->prepare("
                UPDATE examination_papers
                SET status = 'Approved', locked_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute(['id' => $paperId]);

            // Perform Blind Lockdown
            self::activateBlindLockdown($paperId, (int)$paper['current_version_id'], $actorId, $paper);

            // Audit Dual Approval & Lockdown
            logAudit($actorId, 'dual_approval_satisfied', 'examination_paper', $paperId, [
                'paper_code' => $paper['paper_code'],
                'version_id' => $paper['current_version_id'],
                'status' => STATUS_APPROVED
            ]);

            // Notify Lecturer
            createNotification((int)$paper['lecturer_id'], 'Examination Paper Fully Approved & Locked', "Your examination paper {$paper['paper_code']} ({$paper['course_code']}) has received final HOD + Exam Officer approval and is securely locked.", "/FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");

            // Notify Exam Officers that paper is ready for print queue
            notifyAllExamOfficers('Paper Ready for Print Queue', "Paper {$paper['paper_code']} ({$paper['course_code']}) is fully approved and locked under Blind Lockdown.", "/FOCITECMS/dashboard/exam_officer/print_queue.php");

            return true;
        }

        return false;
    }

    /**
     * Activate Blind Lockdown for the exact approved version
     */
    public static function activateBlindLockdown(int $paperId, int $versionId, int $actorId, ?array $paperData = null): bool {
        $pdo = getDb();

        if ($paperData === null) {
            $stmt = $pdo->prepare("
                SELECT ep.*, pv.question_file_path, pv.question_file_hash, pv.version_number
                FROM examination_papers ep
                JOIN paper_versions pv ON ep.current_version_id = pv.id
                WHERE ep.id = :id
            ");
            $stmt->execute(['id' => $paperId]);
            $paperData = $stmt->fetch();
        }

        if (!$paperData || !file_exists($paperData['question_file_path'])) {
            throw new RuntimeException("Cannot lock paper: Approved question file not found on disk.");
        }

        // Verify SHA-256 before encryption
        if (!DocumentService::verifyFileIntegrity($paperData['question_file_path'], $paperData['question_file_hash'])) {
            throw new RuntimeException("Cannot lock paper: File integrity check failed prior to encryption.");
        }

        // Read and encrypt question paper content
        $rawFileContent = file_get_contents($paperData['question_file_path']);
        $encryptedResult = EncryptionService::encryptFileContent($rawFileContent);

        // Store encrypted payload
        $lockDir = STORAGE_PATH . "/examinations/paper-{$paperId}/lockdown";
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }
        $encryptedPath = "{$lockDir}/locked_v{$paperData['version_number']}.enc";
        file_put_contents($encryptedPath, $encryptedResult['ciphertext']);

        // Insert or update blind_lockdowns record
        $stmtLock = $pdo->prepare("
            INSERT INTO blind_lockdowns (
                paper_id, version_id, locked_by, encrypted_file_path, checksum_sha256,
                encryption_algorithm, encryption_iv, encryption_tag, locked_at, is_unlocked
            ) VALUES (
                :paper_id, :version_id, :locked_by, :enc_path, :sha256,
                :algo, :iv, :tag, NOW(), 0
            )
            ON DUPLICATE KEY UPDATE
                version_id = VALUES(version_id),
                locked_by = VALUES(locked_by),
                encrypted_file_path = VALUES(encrypted_file_path),
                checksum_sha256 = VALUES(checksum_sha256),
                encryption_algorithm = VALUES(encryption_algorithm),
                encryption_iv = VALUES(encryption_iv),
                encryption_tag = VALUES(encryption_tag),
                locked_at = VALUES(locked_at),
                is_unlocked = 0,
                unlock_reason = NULL,
                unlocked_by = NULL,
                unlocked_at = NULL
        ");

        $stmtLock->execute([
            'paper_id' => $paperId,
            'version_id' => $versionId,
            'locked_by' => $actorId,
            'enc_path' => $encryptedPath,
            'sha256' => $encryptedResult['checksum_sha256'],
            'algo' => ENCRYPTION_CIPHER,
            'iv' => $encryptedResult['iv'],
            'tag' => $encryptedResult['tag']
        ]);

        // Audit Lockdown
        logAudit($actorId, 'blind_lockdown_activated', 'blind_lockdown', $paperId, [
            'paper_code' => $paperData['paper_code'],
            'version' => $paperData['version_number'],
            'encrypted_path' => $encryptedPath,
            'checksum_sha256' => $encryptedResult['checksum_sha256']
        ]);

        return true;
    }

    /**
     * Create Revision (V2, V3...) after a paper has been returned
     */
    public static function createRevisionVersion(int $paperId, int $lecturerId, array $qUpload, ?array $gUpload, ?string $changesSummary): array {
        $pdo = getDb();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT ep.*, MAX(pv.version_number) AS max_version
                FROM examination_papers ep
                JOIN paper_versions pv ON ep.id = pv.paper_id
                WHERE ep.id = :id AND ep.lecturer_id = :lec_id
                GROUP BY ep.id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $paperId, 'lec_id' => $lecturerId]);
            $paper = $stmt->fetch();

            if (!$paper) {
                throw new RuntimeException("Paper not found or access denied.");
            }

            if ($paper['status'] !== STATUS_RETURNED) {
                throw new RuntimeException("Revisions can only be created for papers in 'Returned' status.");
            }

            $newVersionNumber = (int)$paper['max_version'] + 1;

            // Store files for new version in independent physical directory
            $storedFiles = DocumentService::storeVersionFiles($paperId, $newVersionNumber, $qUpload, $gUpload);

            // Insert new version
            $stmtVer = $pdo->prepare("
                INSERT INTO paper_versions (
                    paper_id, version_number, question_file_path, question_file_name, question_file_hash, question_file_size,
                    guide_file_path, guide_file_name, guide_file_hash, guide_file_size, submission_notes, changes_summary, created_by, created_at
                ) VALUES (
                    :paper_id, :ver_num, :q_path, :q_name, :q_hash, :q_size,
                    :g_path, :g_name, :g_hash, :g_size, :notes, :changes, :created_by, NOW()
                )
            ");
            $stmtVer->execute([
                'paper_id' => $paperId,
                'ver_num' => $newVersionNumber,
                'q_path' => $storedFiles['question_path'],
                'q_name' => $storedFiles['question_name'],
                'q_hash' => $storedFiles['question_hash'],
                'q_size' => $storedFiles['question_size'],
                'g_path' => $storedFiles['guide_path'],
                'g_name' => $storedFiles['guide_name'],
                'g_hash' => $storedFiles['guide_hash'],
                'g_size' => $storedFiles['guide_size'],
                'notes' => 'Revision V' . $newVersionNumber,
                'changes' => $changesSummary,
                'created_by' => $lecturerId
            ]);
            $newVersionId = (int)$pdo->lastInsertId();

            // Point current_version_id to new version
            $stmtUpd = $pdo->prepare("UPDATE examination_papers SET current_version_id = :ver_id, updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['ver_id' => $newVersionId, 'id' => $paperId]);

            // Audit
            logAudit($lecturerId, 'create_revision_version', 'paper_version', $newVersionId, [
                'paper_id' => $paperId,
                'version_number' => $newVersionNumber,
                'changes_summary' => $changesSummary
            ]);

            $pdo->commit();
            return ['version_id' => $newVersionId, 'version_number' => $newVersionNumber];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
