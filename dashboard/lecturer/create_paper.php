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

$selectedCourseId = $preselectedCourseId;
$selectedSessionId = 0;
$selectedSemesterId = 0;
$submittedNotes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $courseId = (int)($_POST['course_id'] ?? 0);
    $sessionId = (int)($_POST['academic_session_id'] ?? 0);
    $semesterId = (int)($_POST['semester_id'] ?? 0);
    $notes = sanitizeInput($_POST['submission_notes'] ?? '');
    $submitImmediately = isset($_POST['action_submit']);

    $selectedCourseId = $courseId;
    $selectedSessionId = $sessionId;
    $selectedSemesterId = $semesterId;
    $submittedNotes = $_POST['submission_notes'] ?? '';

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

        redirect("dashboard/lecturer/view_paper.php?id={$paperId}");
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

$pageTitle = 'Upload Examination Paper — LASU FCIT EMMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <a href="<?= url('dashboard/lecturer/index.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-lasu-navy mb-2 transition">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Dashboard</span>
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Create Examination Question Paper</h1>
            <p class="text-sm text-slate-600 mt-1">Upload initial question paper (DOCX) and marking guide for departmental moderation.</p>
        </div>

        <?php if (empty($assignedCourses)): ?>
            <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm flex items-start gap-3" role="status">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="font-bold">No Course Allocations Found</p>
                    <p class="text-xs text-amber-800 mt-0.5">You do not currently have an active course assigned. Contact the System Administrator or your Head of Department to allocate courses to your staff profile.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8">
            <form method="POST" action="<?= url('dashboard/lecturer/create_paper.php') ?>" enctype="multipart/form-data" class="space-y-7">
                <?= csrfField() ?>

                <!-- Step 1: Course Selection -->
                <fieldset class="space-y-4">
                    <legend class="w-full text-xs font-bold uppercase tracking-wider text-lasu-navy border-b border-slate-200 pb-2">
                        1. Academic Course Allocation
                    </legend>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
                        <div>
                            <label for="course_id" class="block text-xs font-semibold text-slate-700 mb-1.5">Course <span class="text-rose-600">*</span></label>
                            <select id="course_id" name="course_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                <option value="">Select Allocated Course</option>
                                <?php foreach ($assignedCourses as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= ($selectedCourseId === (int)$c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['course_code']) ?> &mdash; <?= htmlspecialchars($c['course_title']) ?> (<?= htmlspecialchars($c['level_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="academic_session_id" class="block text-xs font-semibold text-slate-700 mb-1.5">Academic Session <span class="text-rose-600">*</span></label>
                            <select id="academic_session_id" name="academic_session_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                <?php foreach ($sessions as $s):
                                    $isSelected = $selectedSessionId > 0 ? ($selectedSessionId === (int)$s['id']) : (bool)$s['is_current'];
                                ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['session_name']) ?> <?= $s['is_current'] ? '(Current)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="semester_id" class="block text-xs font-semibold text-slate-700 mb-1.5">Semester <span class="text-rose-600">*</span></label>
                            <select id="semester_id" name="semester_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= (int)$sem['id'] ?>" <?= ($selectedSemesterId === (int)$sem['id']) ? 'selected' : '' ?>>
                                        <?= formatSemester($sem['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- Step 2: Document Uploads -->
                <fieldset class="space-y-4">
                    <legend class="w-full text-xs font-bold uppercase tracking-wider text-lasu-navy border-b border-slate-200 pb-2">
                        2. Document Uploads (DOCX / Integrity Protected)
                    </legend>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-1">
                        <!-- Question Paper File -->
                        <div class="border-2 border-dashed border-slate-300 rounded-2xl p-5 text-center hover:border-lasu-navy transition bg-slate-50/70">
                            <div class="w-10 h-10 bg-blue-100 text-lasu-navy rounded-xl flex items-center justify-center mx-auto mb-2.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <label for="question_file" class="block text-sm font-bold text-slate-900 mb-1 cursor-pointer">
                                Question Paper (.DOCX) <span class="text-rose-600">*</span>
                            </label>
                            <p id="question-file-hint" class="text-xs text-slate-600 mb-3">Authoritative Microsoft Word document (max 20 MB)</p>
                            <input
                                id="question_file"
                                type="file"
                                name="question_file"
                                accept=".docx"
                                required
                                aria-describedby="question-file-hint question-file-feedback"
                                data-file-feedback="question-file-feedback"
                                data-max-mb="20"
                                class="block w-full text-xs text-slate-600 file:mr-2.5 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-lasu-navy file:text-white hover:file:bg-lasu-navy-light cursor-pointer"
                            >
                            <div id="question-file-feedback" class="hidden" aria-live="polite"></div>
                        </div>

                        <!-- Marking Guide File -->
                        <div class="border-2 border-dashed border-slate-300 rounded-2xl p-5 text-center hover:border-lasu-navy transition bg-slate-50/70">
                            <div class="w-10 h-10 bg-emerald-100 text-emerald-800 rounded-xl flex items-center justify-center mx-auto mb-2.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                            <label for="guide_file" class="block text-sm font-bold text-slate-900 mb-1 cursor-pointer">
                                Marking Guide / Solutions (Optional)
                            </label>
                            <p id="guide-file-hint" class="text-xs text-slate-600 mb-3">Rubric or solutions in .docx or .pdf (max 20 MB)</p>
                            <input
                                id="guide_file"
                                type="file"
                                name="guide_file"
                                accept=".docx,.pdf"
                                aria-describedby="guide-file-hint guide-file-feedback"
                                data-file-feedback="guide-file-feedback"
                                data-max-mb="20"
                                class="block w-full text-xs text-slate-600 file:mr-2.5 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 cursor-pointer"
                            >
                            <div id="guide-file-feedback" class="hidden" aria-live="polite"></div>
                        </div>
                    </div>
                </fieldset>

                <!-- Step 3: Notes -->
                <fieldset class="space-y-4">
                    <legend class="w-full text-xs font-bold uppercase tracking-wider text-lasu-navy border-b border-slate-200 pb-2">
                        3. Submission Notes &amp; Syllabus Coverage
                    </legend>

                    <div class="pt-1">
                        <label for="submission_notes" class="block text-xs font-semibold text-slate-700 mb-1.5">Notes to Moderator</label>
                        <textarea
                            id="submission_notes"
                            name="submission_notes"
                            rows="3"
                            placeholder="Provide syllabus coverage, formula sheet requirements, calculator permissions, or specific instructions for the moderator..."
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none"
                        ><?= htmlspecialchars($submittedNotes) ?></textarea>
                    </div>
                </fieldset>

                <!-- Submit Buttons -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-end gap-3 border-t border-slate-200">
                    <button
                        type="submit"
                        name="action_draft"
                        value="1"
                        data-loading-text="Saving Draft..."
                        class="min-h-[42px] w-full sm:w-auto px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-xl text-xs transition border border-slate-300"
                    >
                        Save as Draft
                    </button>
                    <button
                        type="submit"
                        name="action_submit"
                        value="1"
                        data-loading-text="Submitting Paper..."
                        class="min-h-[42px] w-full sm:w-auto px-6 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center justify-center gap-2"
                    >
                        <span>Save &amp; Submit for Moderation</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>

            </form>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
