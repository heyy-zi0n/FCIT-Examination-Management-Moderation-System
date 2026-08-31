<?php
/**
 * Object-Level Authorization & IDOR Protection Middleware
 * Verifies that users only interact with objects within their assigned scope
 */

/**
 * Authorize access to an examination paper
 * @return array The loaded paper record
 */
function requirePaperAccess(int $paperId, string $action = 'view'): array {
    $user = requireAuth();
    $pdo = getDb();

    $stmt = $pdo->prepare("
        SELECT ep.*, c.course_code, c.course_title, c.department_id, c.level_id,
               d.code AS department_code, d.name AS department_name,
               pv.question_file_path, pv.question_file_name, pv.question_file_hash,
               pv.guide_file_path, pv.guide_file_name, pv.guide_file_hash, pv.version_number
        FROM examination_papers ep
        JOIN courses c ON ep.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
        WHERE ep.id = :id
    ");
    $stmt->execute(['id' => $paperId]);
    $paper = $stmt->fetch();

    if (!$paper) {
        http_response_code(404);
        die("Examination paper not found.");
    }

    // 1. Admin has supervisory access
    if ($user['role'] === ROLE_ADMIN) {
        return $paper;
    }

    // 2. Lecturer: Only own submissions
    if ($user['role'] === ROLE_LECTURER) {
        if ((int)$paper['lecturer_id'] === (int)$user['id']) {
            return $paper;
        }
    }

    // 3. Moderator: Only assigned department + level papers
    if ($user['role'] === ROLE_MODERATOR) {
        $stmtMod = $pdo->prepare("
            SELECT id FROM moderator_assignments
            WHERE moderator_id = :mod_id AND department_id = :dept_id AND level_id = :lvl_id AND academic_session_id = :session_id
        ");
        $stmtMod->execute([
            'mod_id' => (int)$user['id'],
            'dept_id' => (int)$paper['department_id'],
            'lvl_id' => (int)$paper['level_id'],
            'session_id' => (int)$paper['academic_session_id']
        ]);
        if ($stmtMod->fetch() || (int)$paper['moderator_id'] === (int)$user['id']) {
            return $paper;
        }
    }

    // 4. HOD: Only departmental papers or active delegate
    if ($user['role'] === ROLE_HOD) {
        if ((int)$user['department_id'] === (int)$paper['department_id']) {
            return $paper;
        }
    }

    // Check HOD delegation
    $stmtDelHod = $pdo->prepare("
        SELECT id FROM delegations
        WHERE delegate_id = :user_id AND role_delegated = 'hod' AND department_id = :dept_id
          AND is_active = 1 AND CURRENT_DATE BETWEEN start_date AND end_date
    ");
    $stmtDelHod->execute(['user_id' => (int)$user['id'], 'dept_id' => (int)$paper['department_id']]);
    if ($stmtDelHod->fetch()) {
        return $paper;
    }

    // 5. Exam Officer: Department-wide examination workflow
    if ($user['role'] === ROLE_EXAM_OFFICER && (int)$user['department_id'] === (int)$paper['department_id']) {
        return $paper;
    }

    // Check Exam Officer delegation
    $stmtDelEo = $pdo->prepare("
        SELECT id FROM delegations
        WHERE delegate_id = :user_id AND role_delegated = 'exam_officer'
          AND is_active = 1 AND CURRENT_DATE BETWEEN start_date AND end_date
    ");
    $stmtDelEo->execute(['user_id' => (int)$user['id']]);
    if ($stmtDelEo->fetch()) {
        return $paper;
    }

    // Unauthorized IDOR attempt: Log and fail closed
    logAudit((int)$user['id'], 'unauthorized_paper_access_attempt', 'examination_paper', $paperId, [
        'user_role' => $user['role'],
        'user_dept' => $user['department_id'],
        'paper_dept' => $paper['department_id'],
        'action' => $action
    ]);

    http_response_code(403);
    die("Access Denied: You are not authorized to access this examination paper.");
}

/**
 * Authorize departmental access
 */
function requireDepartmentAccess(int $departmentId): void {
    $user = requireAuth();

    if ($user['role'] === ROLE_ADMIN) {
        return;
    }

    if ((int)$user['department_id'] === $departmentId) {
        return;
    }

    // Check delegation
    $pdo = getDb();
    $stmtDel = $pdo->prepare("
        SELECT id FROM delegations
        WHERE delegate_id = :user_id AND department_id = :dept_id
          AND is_active = 1 AND CURRENT_DATE BETWEEN start_date AND end_date
    ");
    $stmtDel->execute(['user_id' => (int)$user['id'], 'dept_id' => $departmentId]);
    if ($stmtDel->fetch()) {
        return;
    }

    logAudit((int)$user['id'], 'unauthorized_department_access', 'department', $departmentId);
    http_response_code(403);
    die("Access Denied: You do not have access to this department's resources.");
}
