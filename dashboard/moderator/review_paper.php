<?php
/**
 * Moderator Examination Review & Vetting Portal
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_MODERATOR);

$paperId = (int)($_GET['id'] ?? 0);
$paper = requirePaperAccess($paperId, 'moderate');

$pdo = getDb();
$userId = (int)$user['id'];

// Handle Moderation Decisions & Adding Comments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // 1. Add Annotation Comment
    if (isset($_POST['action_add_comment'])) {
        $lineRef = sanitizeInput($_POST['line_reference'] ?? '');
        $commentType = sanitizeInput($_POST['comment_type'] ?? 'general');
        $commentText = sanitizeInput($_POST['comment_text'] ?? '');

        if (empty(trim($commentText))) {
            setFlash('error', 'Comment text cannot be blank.');
        } else {
            $stmtComm = $pdo->prepare("
                INSERT INTO moderation_comments (paper_id, version_id, author_id, line_reference, comment_type, comment_text, status, created_at)
                VALUES (:paper_id, :version_id, :author_id, :line_ref, :type, :text, 'open', NOW())
            ");
            $stmtComm->execute([
                'paper_id' => $paperId,
                'version_id' => (int)$paper['current_version_id'],
                'author_id' => $userId,
                'line_ref' => $lineRef,
                'type' => $commentType,
                'text' => $commentText
            ]);

            logAudit($userId, 'add_moderation_comment', 'moderation_comment', (int)$pdo->lastInsertId(), [
                'paper_id' => $paperId,
                'type' => $commentType,
                'line' => $lineRef
            ]);

            setFlash('success', 'Annotation comment recorded.');
        }
        redirect("dashboard/moderator/review_paper.php?id={$paperId}");
        exit;
    }

    // 2. Approve Moderation
    if (isset($_POST['action_approve_moderation'])) {
        $approvalNotes = sanitizeInput($_POST['moderation_notes'] ?? '');
        try {
            WorkflowService::moderatorApprovePaper($paperId, $userId, $approvalNotes);
            setFlash('success', "Examination paper {$paper['paper_code']} approved for moderation! It has now entered the final approval pipeline awaiting HOD and Exam Officer dual sign-off.");
            redirect('dashboard/moderator/index.php');
            exit;
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }

    // 3. Return Paper for Correction
    if (isset($_POST['action_return_paper'])) {
        $returnFeedback = sanitizeInput($_POST['return_feedback'] ?? '');
        try {
            if (empty(trim($returnFeedback))) {
                throw new InvalidArgumentException("Please provide feedback explaining the corrections required by the lecturer.");
            }
            WorkflowService::moderatorReturnPaper($paperId, $userId, $returnFeedback);
            setFlash('info', "Examination paper {$paper['paper_code']} has been returned to the lecturer with your feedback for revision.");
            redirect('dashboard/moderator/index.php');
            exit;
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }
}

// Fetch all versions for this paper
$stmtVersions = $pdo->prepare("
    SELECT pv.*, u.first_name, u.last_name, u.title
    FROM paper_versions pv
    JOIN users u ON pv.created_by = u.id
    WHERE pv.paper_id = :paper_id
    ORDER BY pv.version_number DESC
");
$stmtVersions->execute(['paper_id' => $paperId]);
$versions = $stmtVersions->fetchAll();

// Fetch comments
$stmtComments = $pdo->prepare("
    SELECT mc.*, u.first_name, u.last_name, u.title, u.role
    FROM moderation_comments mc
    JOIN users u ON mc.author_id = u.id
    WHERE mc.paper_id = :paper_id
    ORDER BY mc.created_at DESC
");
$stmtComments->execute(['paper_id' => $paperId]);
$comments = $stmtComments->fetchAll();

// Fetch lecturer details
$stmtLec = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmtLec->execute(['id' => (int)$paper['lecturer_id']]);
$lecturer = $stmtLec->fetch();

// Fetch canonical event timeline
$timeline = getPaperTimeline($paperId, $pdo);

$previewUrl = url('preview.php?paper_id=' . $paperId . '&version_id=' . $paper['current_version_id'] . '&type=question');

$pageTitle = "Moderate Paper: {$paper['paper_code']} — LASU FCIT EMMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <a href="<?= url('dashboard/moderator/index.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-lasu-navy mb-1.5 transition">
                    <span aria-hidden="true">&larr;</span>
                    <span>Back to Moderation Queue</span>
                </a>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($paper['course_code']) ?>: <?= htmlspecialchars($paper['course_title']) ?></h1>
                    <?= renderStatusBadge($paper['status']) ?>
                </div>
                <div class="text-xs text-slate-600 font-mono mt-1 tabular-nums">Paper Code: <?= htmlspecialchars($paper['paper_code']) ?> &bull; Lecturer: <?= $lecturer ? formatUserName($lecturer) : 'Assigned Lecturer' ?></div>
            </div>

            <div class="flex items-center gap-2">
                <a href="<?= url('download.php?paper_id=' . $paperId . '&version_id=' . $paper['current_version_id']) ?>" class="min-h-[40px] px-4 py-2 bg-white hover:bg-slate-50 text-slate-800 rounded-xl text-xs font-semibold border border-slate-300 transition inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download DOCX</span>
                </a>
            </div>
        </div>

        <!-- Paper Lifecycle Tracking -->
        <?php require __DIR__ . '/../../includes/components/paper_tracking.php'; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Left: Document Viewer (2 Columns) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Live Embedded PDF Preview -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-[420px] sm:h-[600px] lg:h-[680px]">
                    <div class="px-5 py-3 bg-lasu-navy text-white flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-xs">Live Question Paper Document</span>
                            <span class="text-[11px] bg-lasu-gold/20 text-lasu-gold px-2 py-0.5 rounded font-mono tabular-nums">Version <?= (int)($paper['version_number'] ?: 1) ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hidden sm:inline text-[11px] text-slate-300 uppercase tracking-wider font-mono">Watermarked Stream</span>
                            <a href="<?= $previewUrl ?>" target="_blank" rel="noopener" class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition">
                                Open PDF in New Tab
                            </a>
                        </div>
                    </div>

                    <div class="flex-1 bg-slate-900">
                        <iframe src="<?= $previewUrl ?>" class="w-full h-full border-0" title="Question Document Preview"></iframe>
                    </div>
                </div>

                <!-- Version History -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3">Version History for this Submission</h2>
                    <div class="space-y-2.5 text-xs">
                        <?php foreach ($versions as $v): ?>
                            <div class="p-3.5 rounded-xl border border-slate-200 flex flex-wrap items-center justify-between gap-2 bg-slate-50/70">
                                <div>
                                    <span class="font-bold font-mono text-lasu-navy tabular-nums">Version <?= (int)$v['version_number'] ?></span>
                                    <span class="text-slate-600 ml-2 break-all"><?= htmlspecialchars($v['question_file_name']) ?> (<?= formatBytes((int)$v['question_file_size']) ?>)</span>
                                    <?php if (!empty($v['changes_summary'])): ?>
                                        <div class="text-xs text-slate-600 mt-1">Summary: <?= htmlspecialchars($v['changes_summary']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-slate-500 tabular-nums"><?= formatDate($v['created_at']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Moderation Controls & Annotations (1 Column) -->
            <div class="space-y-6">
                <!-- Decision Action Panel -->
                <?php if ($paper['status'] === STATUS_SUBMITTED): ?>
                    <div class="bg-white rounded-2xl p-6 shadow-sm border-2 border-amber-300/70">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                            <span>Moderator Decision</span>
                        </h2>

                        <!-- Action Form 1: Approve -->
                        <form method="POST" action="<?= url('dashboard/moderator/review_paper.php?id=' . $paperId) ?>" class="space-y-4 mb-6">
                            <?= csrfField() ?>
                            <div>
                                <label for="moderation_notes" class="block text-xs font-semibold text-slate-700 mb-1.5">Approval Commendations / Notes (Optional)</label>
                                <textarea id="moderation_notes" name="moderation_notes" rows="2" placeholder="e.g. Exam paper fully vetted and meets syllabus standards..." class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-emerald-600 focus:outline-none"></textarea>
                            </div>
                            <button
                                type="submit"
                                name="action_approve_moderation"
                                value="1"
                                data-confirm-title="Approve Moderation"
                                data-confirm-message="Confirm moderation approval for <?= htmlspecialchars($paper['paper_code'], ENT_QUOTES, 'UTF-8') ?>? It will immediately proceed to HOD and Exam Officer dual approval."
                                data-confirm-label="Approve Moderation"
                                data-confirm-variant="success"
                                data-loading-text="Approving..."
                                class="min-h-[44px] w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Approve Moderation</span>
                            </button>
                        </form>

                        <div class="border-t border-slate-200 my-4"></div>

                        <!-- Action Form 2: Return with Feedback -->
                        <form method="POST" action="<?= url('dashboard/moderator/review_paper.php?id=' . $paperId) ?>" class="space-y-4">
                            <?= csrfField() ?>
                            <div>
                                <label for="return_feedback" class="block text-xs font-semibold text-rose-800 mb-1.5">Return Feedback for Revision <span class="text-rose-600">*</span></label>
                                <textarea id="return_feedback" name="return_feedback" rows="3" required placeholder="Specify what corrections or question revisions are required before approval..." class="w-full px-3.5 py-2.5 bg-white border border-rose-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-rose-600 focus:outline-none"></textarea>
                            </div>
                            <button
                                type="submit"
                                name="action_return_paper"
                                value="1"
                                data-confirm-title="Return Paper for Revision"
                                data-confirm-message="Return examination paper <?= htmlspecialchars($paper['paper_code'], ENT_QUOTES, 'UTF-8') ?> to the lecturer for correction?"
                                data-confirm-label="Return for Revision"
                                data-confirm-variant="danger"
                                data-loading-text="Returning Paper..."
                                class="min-h-[42px] w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 0118 0z"/></svg>
                                <span>Return for Revision</span>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200 text-center">
                        <div class="text-xs font-semibold text-slate-600">Moderation Status</div>
                        <div class="mt-2"><?= renderStatusBadge($paper['status']) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Add Section/Line Annotation Form -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-lasu-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        <span>Add Section / Line Annotation</span>
                    </h2>

                    <form method="POST" action="<?= url('dashboard/moderator/review_paper.php?id=' . $paperId) ?>" class="space-y-3.5">
                        <?= csrfField() ?>
                        <input type="hidden" name="action_add_comment" value="1">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="line_reference" class="block text-xs font-semibold text-slate-700 mb-1">Reference</label>
                                <input id="line_reference" type="text" name="line_reference" placeholder="e.g. Q2(b), Page 1" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                            </div>
                            <div>
                                <label for="comment_type" class="block text-xs font-semibold text-slate-700 mb-1">Feedback Category</label>
                                <select id="comment_type" name="comment_type" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                    <option value="correction">Correction Needed</option>
                                    <option value="clarity">Clarity / Ambiguity</option>
                                    <option value="marks_allocation">Marks Breakdown</option>
                                    <option value="rubric">Marking Scheme Rubric</option>
                                    <option value="general">General Commendation</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="comment_text" class="block text-xs font-semibold text-slate-700 mb-1">Annotation / Note <span class="text-rose-600">*</span></label>
                            <textarea id="comment_text" name="comment_text" rows="3" required placeholder="Type feedback or suggestion for this question/section..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none"></textarea>
                        </div>

                        <button type="submit" data-loading-text="Posting..." class="min-h-[40px] w-full py-2.5 bg-lasu-navy hover:bg-lasu-navy-light text-white font-bold rounded-xl text-xs transition">
                            Post Annotation
                        </button>
                    </form>
                </div>

                <!-- Existing Comments Feed -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center justify-between">
                        <span>Recorded Annotations</span>
                        <span class="text-xs font-semibold text-slate-500 tabular-nums"><?= count($comments) ?> Total</span>
                    </h2>

                    <div class="space-y-3 max-h-72 overflow-y-auto">
                        <?php if (empty($comments)): ?>
                            <div class="text-sm text-slate-500 text-center py-4">No annotations posted yet.</div>
                        <?php else: ?>
                            <?php foreach ($comments as $c): ?>
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                                    <div class="flex items-center justify-between gap-2 text-xs text-slate-600 mb-1">
                                        <span class="font-bold text-slate-900"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></span>
                                        <?php if (!empty($c['line_reference'])): ?>
                                            <span class="bg-slate-200 text-slate-800 px-2 py-0.5 rounded font-mono text-[11px] font-semibold"><?= htmlspecialchars($c['line_reference']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-slate-700 leading-relaxed"><?= htmlspecialchars($c['comment_text']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
