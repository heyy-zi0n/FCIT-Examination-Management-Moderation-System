<?php
/**
 * Create Revision for Returned Examination Paper (Lecturer)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_LECTURER);

$paperId = (int)($_GET['id'] ?? 0);
$paper = requirePaperAccess($paperId, 'edit');

if ($paper['status'] !== STATUS_RETURNED) {
    setFlash('error', "Only papers in 'Returned' status can have revisions submitted (current status: {$paper['status']}).");
    redirect("dashboard/lecturer/view_paper.php?id={$paperId}");
    exit;
}

$pdo = getDb();
$userId = (int)$user['id'];

// Get latest version number
$stmtVer = $pdo->prepare("SELECT MAX(version_number) AS max_v FROM paper_versions WHERE paper_id = :paper_id");
$stmtVer->execute(['paper_id' => $paperId]);
$nextVersionNumber = ((int)$stmtVer->fetchColumn()) + 1;

// Fetch latest return comments
$stmtComments = $pdo->prepare("
    SELECT mc.*, u.first_name, u.last_name, u.title, u.role
    FROM moderation_comments mc
    JOIN users u ON mc.author_id = u.id
    WHERE mc.paper_id = :paper_id
    ORDER BY mc.created_at DESC
    LIMIT 5
");
$stmtComments->execute(['paper_id' => $paperId]);
$recentComments = $stmtComments->fetchAll();

$submittedSummary = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $submittedSummary = $_POST['changes_summary'] ?? '';
    $changesSummary = sanitizeInput($submittedSummary);
    $submitImmediately = isset($_POST['action_submit']);

    try {
        if (empty($_FILES['question_file']['name'])) {
            throw new InvalidArgumentException("Please upload the revised question paper (.docx format).");
        }

        $qUpload = validateUploadedDocument($_FILES['question_file'], ['docx']);

        $gUpload = null;
        if (!empty($_FILES['guide_file']['name'])) {
            $gUpload = validateUploadedDocument($_FILES['guide_file'], ['docx', 'pdf']);
        }

        // Create new revision version
        $rev = WorkflowService::createRevisionVersion($paperId, $userId, $qUpload, $gUpload, $changesSummary);

        if ($submitImmediately) {
            WorkflowService::submitPaper($paperId, $userId);
            setFlash('success', "Revision V{$rev['version_number']} created and resubmitted for moderation!");
        } else {
            setFlash('success', "Revision V{$rev['version_number']} saved. You can submit when ready.");
        }

        redirect("dashboard/lecturer/view_paper.php?id={$paperId}");
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

$pageTitle = "Upload Revision V{$nextVersionNumber} — LASU FCIT EMMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $paperId) ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-lasu-navy mb-2 transition">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Paper Timeline</span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Submit Revision (Version <?= (int)$nextVersionNumber ?>)</h1>
                <?= renderStatusBadge($paper['status']) ?>
            </div>
            <p class="text-sm text-slate-600 mt-1">Address moderation feedback for <span class="font-mono font-semibold text-slate-800"><?= htmlspecialchars($paper['course_code']) ?></span> and upload your revised examination documents.</p>
        </div>

        <!-- Moderation Feedback Callout -->
        <section aria-labelledby="feedback-heading" class="bg-rose-50/80 border border-rose-200 rounded-2xl p-5 mb-7">
            <h2 id="feedback-heading" class="text-xs font-bold uppercase tracking-wider text-rose-800 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Moderator &amp; Governance Feedback to Address</span>
            </h2>

            <div class="space-y-2.5">
                <?php if (empty($recentComments)): ?>
                    <p class="text-sm text-rose-800">Paper was returned for correction. Please review all questions and rubric alignments against departmental guidelines.</p>
                <?php else: ?>
                    <?php foreach ($recentComments as $c): ?>
                        <div class="p-3.5 bg-white rounded-xl border border-rose-200/80">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600 mb-1.5">
                                <span class="font-bold text-slate-900"><?= htmlspecialchars(($c['title'] ? $c['title'] . ' ' : '') . $c['first_name'] . ' ' . $c['last_name']) ?> (<?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['role']))) ?>)</span>
                                <span class="tabular-nums"><?= formatDate($c['created_at']) ?></span>
                            </div>
                            <p class="text-sm text-slate-800 leading-relaxed"><?= htmlspecialchars($c['comment_text']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Revision Upload Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-8">
            <form method="POST" action="<?= url('dashboard/lecturer/revise_paper.php?id=' . $paperId) ?>" enctype="multipart/form-data" class="space-y-6">
                <?= csrfField() ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Revised Question Paper -->
                    <div class="border-2 border-dashed border-slate-300 rounded-2xl p-5 text-center hover:border-lasu-navy transition bg-slate-50/70">
                        <div class="w-10 h-10 bg-blue-100 text-lasu-navy rounded-xl flex items-center justify-center mx-auto mb-2.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <label for="rev-question-file" class="block text-sm font-bold text-slate-900 mb-1 cursor-pointer">
                            Revised Question Paper (.DOCX) <span class="text-rose-600">*</span>
                        </label>
                        <p id="rev-question-hint" class="text-xs text-slate-600 mb-3">Upload corrected DOCX document (Version <?= (int)$nextVersionNumber ?>, max 20 MB)</p>
                        <input
                            id="rev-question-file"
                            type="file"
                            name="question_file"
                            accept=".docx"
                            required
                            aria-describedby="rev-question-hint rev-question-feedback"
                            data-file-feedback="rev-question-feedback"
                            data-max-mb="20"
                            class="block w-full text-xs text-slate-600 file:mr-2.5 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-lasu-navy file:text-white hover:file:bg-lasu-navy-light cursor-pointer"
                        >
                        <div id="rev-question-feedback" class="hidden" aria-live="polite"></div>
                    </div>

                    <!-- Revised Marking Guide -->
                    <div class="border-2 border-dashed border-slate-300 rounded-2xl p-5 text-center hover:border-lasu-navy transition bg-slate-50/70">
                        <div class="w-10 h-10 bg-emerald-100 text-emerald-800 rounded-xl flex items-center justify-center mx-auto mb-2.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <label for="rev-guide-file" class="block text-sm font-bold text-slate-900 mb-1 cursor-pointer">
                            Updated Marking Guide (Optional)
                        </label>
                        <p id="rev-guide-hint" class="text-xs text-slate-600 mb-3">Upload updated solutions or rubric if modified (.docx or .pdf)</p>
                        <input
                            id="rev-guide-file"
                            type="file"
                            name="guide_file"
                            accept=".docx,.pdf"
                            aria-describedby="rev-guide-hint rev-guide-feedback"
                            data-file-feedback="rev-guide-feedback"
                            data-max-mb="20"
                            class="block w-full text-xs text-slate-600 file:mr-2.5 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 cursor-pointer"
                        >
                        <div id="rev-guide-feedback" class="hidden" aria-live="polite"></div>
                    </div>
                </div>

                <div>
                    <label for="changes-summary" class="block text-xs font-semibold text-slate-700 mb-1.5">Revision Summary &amp; Changelog <span class="text-rose-600">*</span></label>
                    <textarea
                        id="changes-summary"
                        name="changes_summary"
                        rows="3"
                        required
                        placeholder="Explain specifically what corrections and modifications were made in response to the feedback..."
                        class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none"
                    ><?= htmlspecialchars($submittedSummary) ?></textarea>
                </div>

                <div class="pt-4 flex flex-col sm:flex-row items-center justify-end gap-3 border-t border-slate-200">
                    <button
                        type="submit"
                        name="action_draft"
                        value="1"
                        data-loading-text="Saving Draft..."
                        class="min-h-[42px] w-full sm:w-auto px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-xl text-xs transition border border-slate-300"
                    >
                        Save Revision as Draft
                    </button>
                    <button
                        type="submit"
                        name="action_submit"
                        value="1"
                        data-loading-text="Submitting Revision..."
                        class="min-h-[42px] w-full sm:w-auto px-6 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center justify-center gap-2"
                    >
                        <span>Save &amp; Submit Version <?= (int)$nextVersionNumber ?></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>

            </form>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
