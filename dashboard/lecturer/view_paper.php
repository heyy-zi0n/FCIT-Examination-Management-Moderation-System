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


$pageTitle = "Examination Paper: {$paper['paper_code']} — LASU FCIT CMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Breadcrumb & Top Bar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <a href="<?= url('dashboard/lecturer/papers.php') ?>" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1 mb-1">
                    &larr; Back to Examination Repository
                </a>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($paper['course_code']) ?>: <?= htmlspecialchars($paper['course_title']) ?></h1>
                    <?= renderStatusBadge($paper['status']) ?>
                </div>
                <div class="text-xs text-slate-500 font-mono mt-1">Paper Code: <?= htmlspecialchars($paper['paper_code']) ?> • Dept: <?= htmlspecialchars($paper['department_name']) ?></div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3 flex-wrap">
                <?php if ($paper['status'] === STATUS_DRAFT): ?>
                    <form method="POST" action="<?= url('dashboard/lecturer/view_paper.php?id=' . $paperId) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action_submit_paper" value="1">
                        <button type="submit" onclick="return confirm('Are you sure you want to submit this examination paper for moderation?')" class="px-5 py-2.5 bg-blue-900 hover:bg-blue-800 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-2">
                            <span>Submit for Moderation</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </form>
                <?php elseif ($paper['status'] === STATUS_RETURNED): ?>
                    <a href="<?= url('dashboard/lecturer/revise_paper.php?id=' . $paperId) ?>" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Create Revision (Upload V<?= ($versions[0]['version_number'] ?? 1) + 1 ?>)</span>
                    </a>
                <?php endif; ?>

                <?php if (!empty($paper['current_version_id'])): ?>
                    <button type="button" onclick="openPreviewModal(<?= $paperId ?>, <?= $paper['current_version_id'] ?>)" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Preview Question Paper</span>
                    </button>
                    <a href="<?= url('download.php?paper_id=' . $paperId . '&version_id=' . $paper['current_version_id']) ?>" class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-semibold rounded-xl text-xs transition border border-slate-300 dark:border-slate-700" title="Download Document">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>


        <!-- Schedule & Version History Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            
            <!-- Left: Schedule & Meta -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 pb-2">
                    Official Examination Schedule
                </h3>

                <?php if ($schedule): ?>
                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Examination Date</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 text-sm"><?= formatDate($schedule['exam_date'], 'l, d F Y') ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Examination Time & Duration</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($schedule['exam_time']) ?> (<?= $schedule['duration_minutes'] ?> Minutes)</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Examination Venue</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($schedule['venue']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Expected Student Cohort</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200"><?= $schedule['expected_students'] ?> Students</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-4 rounded-lg bg-amber-50 text-amber-800 text-xs border border-amber-200">
                        Exam schedule has not yet been published by the Head of Department.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Version History Timeline -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 pb-2 mb-4">
                    Immutable Version History & Checksums
                </h3>

                <div class="space-y-4">
                    <?php foreach ($versions as $v): 
                        $vComments = $commentsByVersion[$v['id']] ?? [];
                    ?>
                        <div class="p-4 rounded-xl border <?= (int)$v['id'] === (int)$paper['current_version_id'] ? 'bg-blue-50/30 border-blue-200 dark:bg-blue-950/20 dark:border-blue-900/50' : 'bg-slate-50/50 border-slate-200 dark:bg-slate-800/30 dark:border-slate-800' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-full font-mono text-xs font-bold bg-lasu-navy text-lasu-gold">
                                        Version <?= $v['version_number'] ?>
                                    </span>
                                    <?php if ((int)$v['id'] === (int)$paper['current_version_id']): ?>
                                        <span class="text-[10px] bg-blue-100 text-blue-800 font-bold px-2 py-0.5 rounded-full uppercase">Current Active Version</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[11px] text-slate-400"><?= formatDate($v['created_at']) ?></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs mb-2">
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Question Document</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($v['question_file_name']) ?></span>
                                    <span class="text-slate-400 text-[10px] block"><?= formatBytes($v['question_file_size']) ?></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">SHA-256 Integrity Hash</span>
                                    <span class="font-mono text-[10px] text-slate-600 dark:text-slate-400 truncate block"><?= htmlspecialchars($v['question_file_hash']) ?></span>
                                </div>
                            </div>

                            <?php if (!empty($v['changes_summary'])): ?>
                                <div class="text-xs text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-900 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800 mt-2">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">Revision Summary:</span> <?= htmlspecialchars($v['changes_summary']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Inline Moderation Comments for this version -->
                            <?php if (!empty($vComments)): ?>
                                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Moderator Feedback on V<?= $v['version_number'] ?></h4>
                                    <div class="space-y-2">
                                        <?php foreach ($vComments as $c): ?>
                                            <div class="bg-white dark:bg-slate-900 rounded border border-rose-100 dark:border-rose-900/30 p-2 text-xs">
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 mb-1">
                                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                                        <?= htmlspecialchars(($c['title'] ? $c['title'] . ' ' : '') . $c['first_name'] . ' ' . $c['last_name']) ?>
                                                    </span>
                                                    <?php if (!empty($c['line_reference'])): ?>
                                                        <span class="inline-block self-start sm:self-auto px-1.5 py-0.5 bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 rounded text-[9px] font-mono font-bold">Line: <?= htmlspecialchars($c['line_reference']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-slate-600 dark:text-slate-400 leading-snug">
                                                    <?= htmlspecialchars($c['comment_text']) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3 pt-2 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-end gap-2">
                                <button type="button" onclick="openPreviewModal(<?= $paperId ?>, <?= $v['id'] ?>)" class="px-3 py-1 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 rounded text-xs font-medium transition">
                                    Preview V<?= $v['version_number'] ?>
                                </button>
                                <a href="<?= url('download.php?paper_id=' . $paperId . '&version_id=' . $v['id']) ?>" class="px-3 py-1 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 rounded text-xs font-medium transition">
                                    Download V<?= $v['version_number'] ?>
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

<!-- Interactive Document Preview Modal -->
<div id="preview-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-5xl h-[90vh] flex flex-col overflow-hidden">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-lasu-navy text-white">
            <div>
                <h3 class="text-sm font-bold" id="modal-doc-title">Document Preview — <?= htmlspecialchars($paper['course_code']) ?></h3>
                <div class="text-[11px] text-lasu-gold font-mono">CONFIDENTIAL • LASU FCIT EXAMINATION QUESTION SECURITY</div>
            </div>
            <button type="button" onclick="closeModal('preview-modal')" class="p-1.5 text-slate-300 hover:text-white rounded-lg transition focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Modal Body (Iframe Secure Stream) -->
        <div class="flex-1 bg-slate-950 relative">
            <iframe id="preview-frame" src="" class="w-full h-full border-0" title="Examination Document Viewer"></iframe>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 bg-slate-50 dark:bg-slate-900">
            <span>Watermark: Confidential Examination Content</span>
            <button type="button" onclick="closeModal('preview-modal')" class="px-4 py-2 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-800 dark:text-slate-200 rounded-lg font-medium transition">
                Close Preview
            </button>
        </div>

    </div>
</div>

<script>
function openPreviewModal(paperId, versionId) {
    const frame = document.getElementById('preview-frame');
    frame.src = `<?= url('preview.php') ?>?paper_id=${paperId}&version_id=${versionId}&type=question`;
    openModal('preview-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
