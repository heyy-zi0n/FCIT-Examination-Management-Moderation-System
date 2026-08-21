<?php
/**
 * Create Examination Paper / Upload Initial Draft (Lecturer)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_LECTURER);

$pdo = getDb();
$userId = (int)$user['id'];
$preselectedCourseId = (int)($_GET['course_id'] ?? 0);

// Fetch allocated courses for this lecturer
$stmtCourses = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_title, c.semester_id, d.name AS dept_name, al.level_name, lca.academic_session_id
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    WHERE lca.lecturer_id = :user_id
");
$stmtCourses->execute(['user_id' => $userId]);
$assignedCourses = $stmtCourses->fetchAll();

// Fetch sessions & semesters
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY is_current DESC, session_name DESC")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $courseId = (int)($_POST['course_id'] ?? 0);
    $sessionId = (int)($_POST['academic_session_id'] ?? 0);
    $semesterId = (int)($_POST['semester_id'] ?? 0);
    $notes = sanitizeInput($_POST['submission_notes'] ?? '');
    $submitImmediately = isset($_POST['action_submit']);

    try {
        if ($courseId <= 0 || $sessionId <= 0 || $semesterId <= 0) {
            throw new InvalidArgumentException("Please select a valid course, academic session, and semester.");
        }

        if (empty($_FILES['question_file']['name'])) {
            throw new InvalidArgumentException("Please upload the examination question paper (.docx format).");
        }

        // Validate Question File (must be .docx)
        $qUpload = validateUploadedDocument($_FILES['question_file'], ['docx']);

        // Validate Marking Guide (optional, .docx or .pdf)
        $gUpload = null;
        if (!empty($_FILES['guide_file']['name'])) {
            $gUpload = validateUploadedDocument($_FILES['guide_file'], ['docx', 'pdf']);
        }

        // Find matching exam schedule if present
        $stmtSched = $pdo->prepare("
            SELECT id FROM exam_schedules 
            WHERE course_id = :cid AND academic_session_id = :sid AND semester_id = :sem_id
            LIMIT 1
        ");
        $stmtSched->execute(['cid' => $courseId, 'sid' => $sessionId, 'sem_id' => $semesterId]);
        $scheduleRow = $stmtSched->fetch();
        $scheduleId = $scheduleRow ? (int)$scheduleRow['id'] : null;

        // Create Draft Paper in Database
        $res = WorkflowService::createDraftPaper($userId, $courseId, $sessionId, $semesterId, $scheduleId, $qUpload, $gUpload, $notes);
        $paperId = (int)$res['paper_id'];

        if ($submitImmediately) {
            WorkflowService::submitPaper($paperId, $userId);
            setFlash('success', "Examination paper {$res['paper_code']} created and submitted successfully for moderation!");
        } else {
            setFlash('success', "Examination paper {$res['paper_code']} saved as Draft. You can review and submit when ready.");
        }

        header("Location: /FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

$pageTitle = 'Upload Examination Paper — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-4xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <a href="/FOCITECMS/dashboard/lecturer/index.php" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1 mb-2">
                &larr; Back to Dashboard
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Create Examination Question Paper</h1>
            <p class="text-xs text-slate-500">Upload initial question paper (DOCX) and marking guide for departmental moderation</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 sm:p-8">
            
            <form method="POST" action="/FOCITECMS/dashboard/lecturer/create_paper.php" enctype="multipart/form-data" class="space-y-6">
                <?= csrfField() ?>

                <!-- Step 1: Course Selection -->
                <div class="space-y-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-blue-900 dark:text-lasu-gold border-b border-slate-100 dark:border-slate-800 pb-2">
                        1. Academic Course Allocation
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Course *</label>
                            <select name="course_id" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                <option value="">Select Allocated Course</option>
                                <?php foreach ($assignedCourses as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($preselectedCourseId === (int)$c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_title']) ?> (<?= htmlspecialchars($c['level_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Academic Session *</label>
                            <select name="academic_session_id" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['is_current'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['session_name']) ?> <?= $s['is_current'] ? '(Current)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Semester *</label>
                            <select name="semester_id" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= $sem['id'] ?>">
                                        <?= htmlspecialchars($sem['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Document Uploads -->
                <div class="space-y-4 pt-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-blue-900 dark:text-lasu-gold border-b border-slate-100 dark:border-slate-800 pb-2">
                        2. Document Uploads (DOCX / Integrity Protected)
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        
                        <!-- Question Paper File -->
                        <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-5 text-center hover:border-lasu-navy dark:hover:border-lasu-gold transition bg-slate-50/50 dark:bg-slate-800/30">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 rounded-xl flex items-center justify-center mx-auto mb-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                                Question Paper (.DOCX) <span class="text-rose-500">*</span>
                            </label>
                            <p class="text-[11px] text-slate-500 mb-3">Authoritative Microsoft Word document (max 20MB)</p>
                            <input type="file" name="question_file" accept=".docx" required class="block w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-lasu-navy file:text-white hover:file:bg-lasu-navy-light cursor-pointer">
                        </div>

                        <!-- Marking Guide File -->
                        <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-5 text-center hover:border-lasu-navy dark:hover:border-lasu-gold transition bg-slate-50/50 dark:bg-slate-800/30">
                            <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-400 rounded-xl flex items-center justify-center mx-auto mb-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                                Marking Guide / Solutions (Optional)
                            </label>
                            <p class="text-[11px] text-slate-500 mb-3">Rubric / solutions in .docx or .pdf (max 20MB)</p>
                            <input type="file" name="guide_file" accept=".docx,.pdf" class="block w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 cursor-pointer">
                        </div>

                    </div>
                </div>

                <!-- Step 3: Notes -->
                <div class="space-y-4 pt-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-blue-900 dark:text-lasu-gold border-b border-slate-100 dark:border-slate-800 pb-2">
                        3. Submission Notes & Syllabus Coverage
                    </h2>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Notes to Moderator</label>
                        <textarea name="submission_notes" rows="3" placeholder="Provide syllabus coverage, formula sheet requirements, calculator permissions, or specific instructions for the moderator..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-lasu-navy focus:outline-none"></textarea>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="submit" name="action_draft" value="1" class="w-full sm:w-auto px-6 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition border border-slate-300 dark:border-slate-700">
                        Save as Draft
                    </button>
                    <button type="submit" name="action_submit" value="1" class="w-full sm:w-auto px-6 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                        <span>Save & Submit for Moderation</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>

            </form>

        </div>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
