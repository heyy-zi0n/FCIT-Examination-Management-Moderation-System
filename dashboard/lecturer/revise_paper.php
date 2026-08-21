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
    header("Location: /FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $changesSummary = sanitizeInput($_POST['changes_summary'] ?? '');
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

        header("Location: /FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

$pageTitle = "Upload Revision V{$nextVersionNumber} — LASU FCIT CMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-4xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <a href="/FOCITECMS/dashboard/lecturer/view_paper.php?id=<?= $paperId ?>" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1 mb-2">
                &larr; Back to Paper Timeline
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Submit Revision (Version <?= $nextVersionNumber ?>)</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-rose-100 text-rose-800">Returned for Correction</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Address moderation feedback and upload your revised examination documents</p>
        </div>

        <!-- Moderation Feedback Callout -->
        <div class="bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50 rounded-2xl p-5 mb-8">
            <h2 class="text-xs font-bold uppercase tracking-wider text-rose-800 dark:text-rose-300 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Moderator & Governance Feedback to Address</span>
            </h2>

            <div class="space-y-2.5">
                <?php if (empty($recentComments)): ?>
                    <div class="text-xs text-rose-700 dark:text-rose-400">Paper was returned. Please check all questions according to departmental guidelines.</div>
                <?php else: ?>
                    <?php foreach ($recentComments as $c): ?>
                        <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-rose-100 dark:border-slate-800 text-xs">
                            <div class="flex items-center justify-between text-[11px] text-slate-500 mb-1">
                                <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars(($c['title'] ? $c['title'] . ' ' : '') . $c['first_name'] . ' ' . $c['last_name']) ?> (<?= ucfirst($c['role']) ?>)</span>
                                <span><?= formatDate($c['created_at']) ?></span>
                            </div>
                            <div class="text-slate-700 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($c['comment_text']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Revision Upload Form -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 sm:p-8">
            <form method="POST" action="/FOCITECMS/dashboard/lecturer/revise_paper.php?id=<?= $paperId ?>" enctype="multipart/form-data" class="space-y-6">
                <?= csrfField() ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Revised Question Paper -->
                    <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-5 text-center hover:border-lasu-navy dark:hover:border-lasu-gold transition bg-slate-50/50 dark:bg-slate-800/30">
                        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 rounded-xl flex items-center justify-center mx-auto mb-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Revised Question Paper (.DOCX) <span class="text-rose-500">*</span>
                        </label>
                        <p class="text-[11px] text-slate-500 mb-3">Upload corrected DOCX document (will become Version <?= $nextVersionNumber ?>)</p>
                        <input type="file" name="question_file" accept=".docx" required class="block w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-lasu-navy file:text-white hover:file:bg-lasu-navy-light cursor-pointer">
                    </div>

                    <!-- Revised Marking Guide -->
                    <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-5 text-center hover:border-lasu-navy dark:hover:border-lasu-gold transition bg-slate-50/50 dark:bg-slate-800/30">
                        <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-400 rounded-xl flex items-center justify-center mx-auto mb-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Updated Marking Guide (Optional)
                        </label>
                        <p class="text-[11px] text-slate-500 mb-3">Upload updated solutions / rubric if modified</p>
                        <input type="file" name="guide_file" accept=".docx,.pdf" class="block w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 cursor-pointer">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Revision Summary & Changelog *</label>
                    <textarea name="changes_summary" rows="3" required placeholder="Explain specifically what corrections and modifications were made in response to the feedback..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-lasu-navy focus:outline-none"></textarea>
                </div>

                <div class="pt-4 flex flex-col sm:flex-row items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="submit" name="action_draft" value="1" class="w-full sm:w-auto px-6 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-semibold rounded-xl text-xs transition border border-slate-300 dark:border-slate-700">
                        Save Revision as Draft
                    </button>
                    <button type="submit" name="action_submit" value="1" class="w-full sm:w-auto px-6 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                        <span>Save & Submit Version <?= $nextVersionNumber ?></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>

            </form>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
