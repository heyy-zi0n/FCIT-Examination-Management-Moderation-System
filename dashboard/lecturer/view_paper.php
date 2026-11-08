<?php
/**
 * Lecturer Paper View & Lifecycle Inspector
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_LECTURER);

$paperId = (int)($_GET['id'] ?? 0);
$paper = requirePaperAccess($paperId, 'view');

$pdo = getDb();
$userId = (int)$user['id'];

// Handle Submit Draft action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit_paper'])) {
    requireCsrf();
    try {
        WorkflowService::submitPaper($paperId, $userId);
        setFlash('success', "Examination paper {$paper['paper_code']} has been submitted for moderation.");
        redirect("dashboard/lecturer/view_paper.php?id={$paperId}");
        exit;
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
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

// Fetch moderation comments
$stmtComments = $pdo->prepare("
    SELECT mc.*, u.first_name, u.last_name, u.title, u.role
    FROM moderation_comments mc
    JOIN users u ON mc.author_id = u.id
    WHERE mc.paper_id = :paper_id
    ORDER BY mc.created_at DESC
");
$stmtComments->execute(['paper_id' => $paperId]);
$comments = $stmtComments->fetchAll();

$commentsByVersion = [];
foreach ($comments as $c) {
    $commentsByVersion[$c['version_id']][] = $c;
}

// Fetch approvals history
$stmtAppr = $pdo->prepare("
    SELECT pa.*, u.first_name, u.last_name, u.title, u.role
    FROM paper_approvals pa
    JOIN users u ON pa.approver_id = u.id
    WHERE pa.paper_id = :paper_id
    ORDER BY pa.created_at DESC
");
$stmtAppr->execute(['paper_id' => $paperId]);
$approvals = $stmtAppr->fetchAll();

// Fetch canonical event timeline
$timeline = getPaperTimeline($paperId, $pdo);

// Fetch schedule details if linked
$schedule = null;
if (!empty($paper['exam_schedule_id'])) {
    $stmtSched = $pdo->prepare("SELECT * FROM exam_schedules WHERE id = :id");
    $stmtSched->execute(['id' => (int)$paper['exam_schedule_id']]);
    $schedule = $stmtSched->fetch();
}

$pageTitle = "Examination Paper: {$paper['paper_code']} — LASU FCIT EMMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Breadcrumb & Top Bar -->
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 mb-6">
            <div>
                <a href="<?= url('dashboard/lecturer/papers.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-lasu-navy mb-1.5 transition">
                    <span aria-hidden="true">&larr;</span>
                    <span>Back to Examination Repository</span>
                </a>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($paper['course_code']) ?>: <?= htmlspecialchars($paper['course_title']) ?></h1>
                    <?= renderStatusBadge($paper['status']) ?>
                </div>
                <div class="text-xs text-slate-600 font-mono mt-1 tabular-nums">Paper Code: <?= htmlspecialchars($paper['paper_code']) ?> &bull; Dept: <?= htmlspecialchars($paper['department_name']) ?></div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <?php if ($paper['status'] === STATUS_DRAFT): ?>
                    <form method="POST" action="<?= url('dashboard/lecturer/view_paper.php?id=' . $paperId) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action_submit_paper" value="1">
                        <button
                            type="submit"
                            data-confirm-title="Submit Paper for Moderation"
                            data-confirm-message="Submit examination paper <?= htmlspecialchars($paper['paper_code'], ENT_QUOTES, 'UTF-8') ?> for departmental moderation?"
                            data-confirm-label="Submit for Moderation"
                            data-confirm-variant="primary"
                            data-loading-text="Submitting..."
                            class="min-h-[40px] px-5 py-2.5 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2"
                        >
                            <span>Submit for Moderation</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </form>
                <?php elseif ($paper['status'] === STATUS_RETURNED): ?>
                    <a href="<?= url('dashboard/lecturer/revise_paper.php?id=' . $paperId) ?>" class="min-h-[40px] px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Create Revision (Upload V<?= (int)(($versions[0]['version_number'] ?? 1) + 1) ?>)</span>
                    </a>
                <?php endif; ?>

                <?php if (!empty($paper['current_version_id'])): ?>
                    <button type="button" onclick="openPreviewModal(<?= (int)$paperId ?>, <?= (int)$paper['current_version_id'] ?>)" class="min-h-[40px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Preview Paper</span>
                    </button>
                    <a
                        href="<?= url('download.php?paper_id=' . $paperId . '&version_id=' . $paper['current_version_id']) ?>"
                        aria-label="Download active question document"
                        class="min-h-[40px] px-3.5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs transition border border-slate-300 inline-flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Schedule & Version History Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 mb-8">
            <!-- Left: Schedule & Meta -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 border-b border-slate-100 pb-2.5">
                    Official Examination Schedule
                </h2>

                <?php if ($schedule): ?>
                    <dl class="space-y-3.5 text-sm">
                        <div>
                            <dt class="text-slate-500 text-xs font-medium">Examination Date</dt>
                            <dd class="font-bold text-slate-900 mt-0.5 tabular-nums"><?= formatDate($schedule['exam_date'], 'l, d F Y') ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs font-medium">Examination Time &amp; Duration</dt>
                            <dd class="font-medium text-slate-800 mt-0.5 tabular-nums"><?= htmlspecialchars($schedule['exam_time']) ?> (<?= (int)$schedule['duration_minutes'] ?> Minutes)</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs font-medium">Examination Venue</dt>
                            <dd class="font-medium text-slate-800 mt-0.5"><?= htmlspecialchars($schedule['venue']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs font-medium">Expected Student Cohort</dt>
                            <dd class="font-medium text-slate-800 mt-0.5 tabular-nums"><?= (int)$schedule['expected_students'] ?> Students</dd>
                        </div>
                    </dl>
                <?php else: ?>
                    <div class="p-4 rounded-xl bg-amber-50 text-amber-900 text-xs border border-amber-200 leading-relaxed">
                        Exam schedule has not yet been published by the Head of Department.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Version History Timeline -->
            <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 border-b border-slate-100 pb-2.5 mb-4">
                    Immutable Version History &amp; Checksums
                </h2>

                <div class="space-y-4">
                    <?php foreach ($versions as $v):
                        $vComments = $commentsByVersion[$v['id']] ?? [];
                        $isCurrent = ((int)$v['id'] === (int)$paper['current_version_id']);
                    ?>
                        <div class="p-4 rounded-xl border <?= $isCurrent ? 'bg-blue-50/40 border-blue-200' : 'bg-slate-50/60 border-slate-200' ?>">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-full font-mono text-xs font-bold bg-lasu-navy text-lasu-gold tabular-nums">
                                        Version <?= (int)$v['version_number'] ?>
                                    </span>
                                    <?php if ($isCurrent): ?>
                                        <span class="text-[11px] bg-blue-100 text-blue-900 font-bold px-2.5 py-0.5 rounded-full">Current Active Version</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-slate-500 tabular-nums"><?= formatDate($v['created_at']) ?></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs mb-2">
                                <div>
                                    <span class="text-slate-500 block">Question Document</span>
                                    <span class="font-semibold text-slate-900 break-all"><?= htmlspecialchars($v['question_file_name']) ?></span>
                                    <span class="text-slate-500 block tabular-nums"><?= formatBytes((int)$v['question_file_size']) ?></span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">SHA-256 Integrity Hash</span>
                                    <span class="font-mono text-[11px] text-slate-700 truncate block" title="<?= htmlspecialchars($v['question_file_hash']) ?>"><?= htmlspecialchars($v['question_file_hash']) ?></span>
                                </div>
                            </div>

                            <?php if (!empty($v['changes_summary'])): ?>
                                <div class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-slate-200 mt-2">
                                    <span class="font-semibold text-slate-900">Revision Summary:</span> <?= htmlspecialchars($v['changes_summary']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Inline Moderation Comments for this version -->
                            <?php if (!empty($vComments)): ?>
                                <div class="mt-4 pt-3 border-t border-slate-200">
                                    <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Moderator Feedback on V<?= (int)$v['version_number'] ?></h3>
                                    <div class="space-y-2">
                                        <?php foreach ($vComments as $c): ?>
                                            <div class="bg-white rounded-lg border border-rose-200 p-3 text-xs">
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 mb-1">
                                                    <span class="font-bold text-slate-900">
                                                        <?= htmlspecialchars(($c['title'] ? $c['title'] . ' ' : '') . $c['first_name'] . ' ' . $c['last_name']) ?>
                                                    </span>
                                                    <?php if (!empty($c['line_reference'])): ?>
                                                        <span class="inline-block self-start sm:self-auto px-2 py-0.5 bg-rose-50 text-rose-800 border border-rose-200 rounded text-[11px] font-mono font-bold">Ref: <?= htmlspecialchars($c['line_reference']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-slate-700 leading-relaxed">
                                                    <?= htmlspecialchars($c['comment_text']) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3 pt-2.5 border-t border-slate-200/80 flex flex-wrap items-center justify-end gap-2">
                                <button type="button" onclick="openPreviewModal(<?= (int)$paperId ?>, <?= (int)$v['id'] ?>)" class="min-h-[34px] px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-800 border border-slate-300 rounded-lg text-xs font-semibold transition">
                                    Preview V<?= (int)$v['version_number'] ?>
                                </button>
                                <a href="<?= url('download.php?paper_id=' . $paperId . '&version_id=' . $v['id']) ?>" class="min-h-[34px] px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-800 border border-slate-300 rounded-lg text-xs font-semibold transition inline-flex items-center">
                                    Download V<?= (int)$v['version_number'] ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Paper Lifecycle Tracking -->
        <?php require __DIR__ . '/../../includes/components/paper_tracking.php'; ?>

    </main>
</div>

<!-- Native Top-Layer Document Preview Dialog -->
<dialog id="preview-modal" closedby="any" aria-labelledby="modal-doc-title" class="rounded-2xl shadow-2xl border border-slate-300 bg-white text-slate-800 w-[min(64rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="flex flex-col h-[min(85dvh,52rem)]">
        <!-- Dialog Header -->
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between gap-3 bg-lasu-navy text-white">
            <div>
                <h2 class="text-sm font-bold" id="modal-doc-title">Document Preview &mdash; <?= htmlspecialchars($paper['course_code']) ?></h2>
                <div class="text-[11px] text-lasu-gold font-mono">CONFIDENTIAL &bull; LASU FCIT EXAMINATION QUESTION SECURITY</div>
            </div>
            <div class="flex items-center gap-2">
                <a id="preview-external-link" href="#" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition">
                    Open in New Tab
                </a>
                <button type="button" onclick="closeModal('preview-modal')" aria-label="Close document preview" class="w-8 h-8 inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Dialog Body (Iframe Secure Stream) -->
        <div class="flex-1 bg-slate-900 relative">
            <iframe id="preview-frame" src="about:blank" class="w-full h-full border-0" title="Examination Document Viewer"></iframe>
        </div>

        <!-- Dialog Footer -->
        <div class="px-5 py-3 border-t border-slate-200 flex items-center justify-between text-xs text-slate-600 bg-slate-50">
            <span>Watermark: Confidential Examination Content</span>
            <button type="button" onclick="closeModal('preview-modal')" class="min-h-[36px] px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg font-semibold transition">
                Close Preview
            </button>
        </div>
    </div>
</dialog>

<script>
function openPreviewModal(paperId, versionId) {
    const streamUrl = `<?= url('preview.php') ?>?paper_id=${encodeURIComponent(paperId)}&version_id=${encodeURIComponent(versionId)}&type=question`;
    const frame = document.getElementById('preview-frame');
    const extLink = document.getElementById('preview-external-link');
    if (frame) frame.src = streamUrl;
    if (extLink) extLink.href = streamUrl;
    openModal('preview-modal');
}

document.getElementById('preview-modal')?.addEventListener('close', function () {
    const frame = document.getElementById('preview-frame');
    if (frame) frame.src = 'about:blank';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
