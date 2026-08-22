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
        header("Location: /FOCITECMS/dashboard/lecturer/view_paper.php?id={$paperId}");
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

// Fetch schedule details if linked
$schedule = null;
if (!empty($paper['exam_schedule_id'])) {
    $stmtSched = $pdo->prepare("SELECT * FROM exam_schedules WHERE id = :id");
    $stmtSched->execute(['id' => (int)$paper['exam_schedule_id']]);
    $schedule = $stmtSched->fetch();
}

// Determine next action
$nextActionText = '';
$nextActionActor = '';
switch ($paper['status']) {
    case STATUS_DRAFT:
        $nextActionText = "Upload question document and submit for moderation.";
        $nextActionActor = "Lecturer";
        break;
    case STATUS_SUBMITTED:
        $nextActionText = "Review and moderate the submitted paper.";
        $nextActionActor = "Moderator";
        break;
    case STATUS_RETURNED:
        $nextActionText = "Address moderator comments and upload a new revision.";
        $nextActionActor = "Lecturer";
        break;
    case STATUS_MODERATED:
        if ($paper['hod_status'] === 'pending') {
            $nextActionText = "Review and sign-off on the moderated paper.";
            $nextActionActor = "Head of Department (HOD)";
        } else if ($paper['eo_status'] === 'pending') {
            $nextActionText = "Review and sign-off on the moderated paper.";
            $nextActionActor = "Exam Officer";
        }
        break;
    case STATUS_APPROVED:
        $nextActionText = "Paper is approved and ready for blind lockdown.";
        $nextActionActor = "System / Exam Officer";
        break;
    case STATUS_LOCKED:
        $nextActionText = "Paper is locked securely. Awaiting print eligibility window.";
        $nextActionActor = "Exam Officer";
        break;
    case STATUS_QUEUED:
        $nextActionText = "Execute controlled printing of the paper.";
        $nextActionActor = "Exam Officer";
        break;
    case STATUS_PRINTED:
        $nextActionText = "Lifecycle complete. Paper has been printed successfully.";
        $nextActionActor = "None";
        break;
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
                <a href="/FOCITECMS/dashboard/lecturer/papers.php" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1 mb-1">
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
                    <form method="POST" action="/FOCITECMS/dashboard/lecturer/view_paper.php?id=<?= $paperId ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action_submit_paper" value="1">
                        <button type="submit" onclick="return confirm('Are you sure you want to submit this examination paper for moderation?')" class="px-5 py-2.5 bg-blue-900 hover:bg-blue-800 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-2">
                            <span>Submit for Moderation</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </form>
                <?php elseif ($paper['status'] === STATUS_RETURNED): ?>
                    <a href="/FOCITECMS/dashboard/lecturer/revise_paper.php?id=<?= $paperId ?>" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Create Revision (Upload V<?= ($versions[0]['version_number'] ?? 1) + 1 ?>)</span>
                    </a>
                <?php endif; ?>

                <?php if (!empty($paper['current_version_id'])): ?>
                    <button type="button" onclick="openPreviewModal(<?= $paperId ?>, <?= $paper['current_version_id'] ?>)" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Preview Question Paper</span>
                    </button>
                    <a href="/FOCITECMS/download.php?paper_id=<?= $paperId ?>&version_id=<?= $paper['current_version_id'] ?>" class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-semibold rounded-xl text-xs transition border border-slate-300 dark:border-slate-700" title="Download Document">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Next Required Action Banner -->
        <div class="mb-6 p-4 rounded-xl border <?= $nextActionActor === 'Lecturer' ? 'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200' : 'bg-slate-50 border-slate-200 text-slate-800 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' ?>">
            <div class="flex items-start sm:items-center gap-3">
                <div class="p-2 bg-white dark:bg-slate-900 rounded-lg shadow-sm">
                    <svg class="w-5 h-5 <?= $nextActionActor === 'Lecturer' ? 'text-amber-600' : 'text-slate-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider <?= $nextActionActor === 'Lecturer' ? 'text-amber-700 dark:text-amber-500' : 'text-slate-500' ?>">
                        Next Action Required By: <?= htmlspecialchars($nextActionActor) ?>
                    </div>
                    <div class="text-sm font-medium mt-0.5">
                        <?= htmlspecialchars($nextActionText) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canonical Lifecycle Timeline -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 mb-8 overflow-hidden">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-6">Examination Lifecycle Progress</h2>
            
            <div class="relative flex flex-col md:flex-row justify-between w-full">
                <!-- Line background -->
                <div class="absolute top-4 left-4 md:left-0 md:right-0 bottom-4 md:bottom-auto md:top-4 w-1 md:w-full md:h-1 bg-slate-100 dark:bg-slate-800 rounded"></div>
                
                <?php
                $stages = [
                    ['label' => 'Draft', 'status' => STATUS_DRAFT, 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                    ['label' => 'Submitted', 'status' => STATUS_SUBMITTED, 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['label' => 'Moderated', 'status' => STATUS_MODERATED, 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                    ['label' => 'HOD Appr.', 'status' => 'hod_approved', 'icon' => 'M5 13l4 4L19 7'],
                    ['label' => 'EO Appr.', 'status' => 'eo_approved', 'icon' => 'M5 13l4 4L19 7'],
                    ['label' => 'Locked', 'status' => STATUS_LOCKED, 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                    ['label' => 'Ready to Print', 'status' => STATUS_QUEUED, 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
                    ['label' => 'Printed', 'status' => STATUS_PRINTED, 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z']
                ];

                $currentStatus = $paper['status'];
                $hodStatus = $paper['hod_status'];
                $eoStatus = $paper['eo_status'];
                
                $statusScores = [
                    STATUS_DRAFT => 0,
                    STATUS_SUBMITTED => 1,
                    STATUS_RETURNED => 1, // Logically returned goes back to draft/submitted state loop
                    STATUS_MODERATED => 2,
                    'hod_approved' => 3,
                    'eo_approved' => 4,
                    STATUS_APPROVED => 4,
                    STATUS_LOCKED => 5,
                    STATUS_QUEUED => 6,
                    STATUS_PRINTED => 7
                ];

                $currentScore = $statusScores[$currentStatus] ?? 0;
                if ($currentStatus === STATUS_MODERATED) {
                    if ($hodStatus === 'approved') $currentScore = 3;
                    if ($eoStatus === 'approved') $currentScore = 4;
                }

                foreach ($stages as $idx => $stage):
                    $stageScore = $statusScores[$stage['status']];
                    
                    $isCompleted = $currentScore > $stageScore;
                    $isActive = $currentScore === $stageScore;
                    
                    // Specific logic for HOD/EO intermediate states
                    if ($stage['status'] === 'hod_approved' && $hodStatus === 'approved') {
                        $isCompleted = true;
                    }
                    if ($stage['status'] === 'eo_approved' && $eoStatus === 'approved') {
                        $isCompleted = true;
                    }

                    $bgColor = $isCompleted ? 'bg-emerald-500' : ($isActive ? 'bg-blue-500 ring-4 ring-blue-500/20' : 'bg-slate-200 dark:bg-slate-700');
                    $textColor = $isCompleted ? 'text-white' : ($isActive ? 'text-white' : 'text-slate-400');
                    $labelColor = $isCompleted ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ($isActive ? 'text-blue-600 dark:text-blue-400 font-bold' : 'text-slate-400 font-medium');
                ?>
                    <div class="relative z-10 flex md:flex-col items-center gap-4 md:gap-2 mb-6 md:mb-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center transition-colors <?= $bgColor ?> <?= $textColor ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $stage['icon'] ?>"/></svg>
                        </div>
                        <div class="text-[10px] uppercase tracking-wider text-left md:text-center <?= $labelColor ?> w-24">
                            <?= $stage['label'] ?>
                            <?php if ($isActive && $currentStatus === STATUS_RETURNED && $stage['status'] === STATUS_SUBMITTED): ?>
                                <br><span class="text-rose-500">(Returned)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                                                <div class="flex justify-between items-start mb-1">
                                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                                        <?= htmlspecialchars(($c['title'] ? $c['title'] . ' ' : '') . $c['first_name'] . ' ' . $c['last_name']) ?>
                                                    </span>
                                                    <?php if (!empty($c['line_reference'])): ?>
                                                        <span class="px-1.5 py-0.5 bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 rounded text-[9px] font-mono font-bold">Line: <?= htmlspecialchars($c['line_reference']) ?></span>
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
                                <a href="/FOCITECMS/download.php?paper_id=<?= $paperId ?>&version_id=<?= $v['id'] ?>" class="px-3 py-1 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 rounded text-xs font-medium transition">
                                    Download V<?= $v['version_number'] ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

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
    frame.src = `/FOCITECMS/preview.php?paper_id=${paperId}&version_id=${versionId}&type=question`;
    openModal('preview-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
