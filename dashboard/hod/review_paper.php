<?php
/**
 * HOD Examination Review & Dual Approval Stage
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);

$paperId = (int)($_GET['id'] ?? 0);
$paper = requirePaperAccess($paperId, 'hod_review');

$pdo = getDb();
$userId = (int)$user['id'];

// Handle HOD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // 1. HOD Approve
    if (isset($_POST['action_hod_approve'])) {
        $comments = sanitizeInput($_POST['hod_comments'] ?? '');
        try {
            WorkflowService::hodApprovePaper($paperId, $userId, $comments);
            
            // Check if Dual approval triggered Approved status
            $stmtStatus = $pdo->prepare("SELECT status FROM examination_papers WHERE id = :id");
            $stmtStatus->execute(['id' => $paperId]);
            $statusCheck = $stmtStatus->fetchColumn();
            if ($statusCheck === STATUS_APPROVED) {
                setFlash('success', "Dual Approval Complete! Both HOD and Exam Officer have approved. Examination paper {$paper['paper_code']} is now securely locked under Cryptographic Blind Lockdown.");
            } else {
                setFlash('success', "HOD Sign-Off Recorded! Paper is currently awaiting Exam Officer sign-off to complete dual approval.");
            }
            
            redirect('dashboard/hod/pending_approvals.php');
            exit;
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }

    // 2. HOD Return
    if (isset($_POST['action_hod_return'])) {
        $feedback = sanitizeInput($_POST['return_feedback'] ?? '');
        try {
            if (empty(trim($feedback))) {
                throw new InvalidArgumentException("Please provide feedback explaining why the paper is being returned for correction.");
            }
            WorkflowService::hodReturnPaper($paperId, $userId, $feedback);
            setFlash('info', "Examination paper {$paper['paper_code']} has been returned to the lecturer for revision.");
            redirect('dashboard/hod/pending_approvals.php');
            exit;
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }
}

// Fetch versions, comments, approvals, schedule, lecturer
$stmtVersions = $pdo->prepare("SELECT * FROM paper_versions WHERE paper_id = :paper_id ORDER BY version_number DESC");
$stmtVersions->execute(['paper_id' => $paperId]);
$versions = $stmtVersions->fetchAll();

$stmtComments = $pdo->prepare("
    SELECT mc.*, u.first_name, u.last_name, u.title, u.role
    FROM moderation_comments mc
    JOIN users u ON mc.author_id = u.id
    WHERE mc.paper_id = :paper_id
    ORDER BY mc.created_at DESC
");
$stmtComments->execute(['paper_id' => $paperId]);
$comments = $stmtComments->fetchAll();

$stmtAppr = $pdo->prepare("
    SELECT pa.*, u.first_name, u.last_name, u.title, u.role
    FROM paper_approvals pa
    JOIN users u ON pa.approver_id = u.id
    WHERE pa.paper_id = :paper_id
    ORDER BY pa.created_at DESC
");
$stmtAppr->execute(['paper_id' => $paperId]);
$approvals = $stmtAppr->fetchAll();

$schedule = null;
if (!empty($paper['exam_schedule_id'])) {
    $stmtSched = $pdo->prepare("SELECT * FROM exam_schedules WHERE id = :id");
    $stmtSched->execute(['id' => (int)$paper['exam_schedule_id']]);
    $schedule = $stmtSched->fetch();
}

// Fetch canonical event timeline
$timeline = getPaperTimeline($paperId, $pdo);

$pageTitle = "HOD Review: {$paper['paper_code']} — LASU FCIT CMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <a href="<?= url('dashboard/hod/pending_approvals.php') ?>" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1 mb-1">
                    &larr; Back to Pending Approvals
                </a>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($paper['course_code']) ?>: <?= htmlspecialchars($paper['course_title']) ?></h1>
                    <?= renderStatusBadge($paper['status']) ?>
                </div>
                <div class="text-xs text-slate-500 font-mono mt-1">Paper Code: <?= htmlspecialchars($paper['paper_code']) ?> • Department: <?= htmlspecialchars($paper['department_name']) ?></div>
            </div>

            <div class="flex items-center gap-2">
                <a href="<?= url('download.php?paper_id=' . $paperId . '&version_id=' . $paper['current_version_id']) ?>" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download DOCX</span>
                </a>
            </div>
        </div>

        <!-- Paper Lifecycle Tracking -->
        <?php require __DIR__ . '/../../includes/components/paper_tracking.php'; ?>


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left: Document Preview & Schedule (2 Columns) -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Live Embedded PDF Preview -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col h-[650px]">
                    <div class="px-6 py-3 bg-lasu-navy text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-xs">Vetted Question Paper Document</span>
                            <span class="text-[10px] bg-lasu-gold/20 text-lasu-gold px-2 py-0.5 rounded font-mono">Version <?= $paper['version_number'] ?: 1 ?></span>
                        </div>
                        <span class="text-[10px] text-slate-300 uppercase tracking-widest font-mono">WATERMARKED PREVIEW</span>
                    </div>

                    <div class="flex-1 bg-slate-950">
                        <iframe src="<?= url('preview.php?paper_id=' . $paperId . '&version_id=' . $paper['current_version_id'] . '&type=question') ?>" class="w-full h-full border-0" title="Question Document Preview"></iframe>
                    </div>
                </div>

                <!-- Moderation & Annotations Summary -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4 flex items-center justify-between">
                        <span>Moderator Vetting & Annotations Log</span>
                        <span class="text-xs text-slate-400 font-normal"><?= count($comments) ?> Comment(s)</span>
                    </h3>

                    <?php if (empty($comments)): ?>
                        <div class="text-xs text-slate-400 text-center py-4">No specific line annotations recorded by the moderator.</div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($comments as $c): ?>
                                <div class="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-100 dark:border-slate-800 text-xs">
                                    <div class="flex items-center justify-between text-[10px] text-slate-500 mb-1">
                                        <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?> (<?= ucfirst($c['role']) ?>)</span>
                                        <span><?= formatDate($c['created_at']) ?></span>
                                    </div>
                                    <div class="text-slate-700 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($c['comment_text']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Right: HOD Governance Decision Panel (1 Column) -->
            <div class="space-y-6">
                
                <!-- Dual Approval Status Badge -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">Dual Approval Status</h2>
                    
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-200">Head of Department (HOD)</div>
                                <div class="text-[10px] text-slate-400">Department Sign-off</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full font-bold text-[11px] <?= $paper['hod_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                <?= ucfirst($paper['hod_status']) ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-200">Faculty Exam Officer (EO)</div>
                                <div class="text-[10px] text-slate-400">Faculty Print Authorization</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full font-bold text-[11px] <?= $paper['eo_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                                <?= ucfirst($paper['eo_status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 p-3 rounded-lg bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30 text-[11px] text-blue-800 dark:text-blue-300 leading-relaxed">
                        Dual Approval Principle: Blind lockdown and printing clearance trigger automatically ONLY when both HOD and Exam Officer approvals are complete.
                    </div>
                </div>

                <!-- HOD Sign-off Action Panel -->
                <?php if ($paper['status'] === STATUS_MODERATED): ?>
                    <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border-2 border-indigo-500/30">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                            <span>HOD Executive Action</span>
                        </h2>

                        <!-- Action Form 1: HOD Approve -->
                        <form method="POST" action="<?= url('dashboard/hod/review_paper.php?id=' . $paperId) ?>" class="space-y-4 mb-6">
                            <?= csrfField() ?>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Official HOD Comments / Endorsement</label>
                                <textarea name="hod_comments" rows="2" placeholder="e.g. Endorsed for examination conduct following departmental quality review..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                            </div>
                            <button type="submit" name="action_hod_approve" value="1" onclick="return confirm('Authorize and sign-off as Head of Department?')" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Endorse & Approve HOD Stage</span>
                            </button>
                        </form>

                        <div class="border-t border-slate-200 dark:border-slate-800 my-4"></div>

                        <!-- Action Form 2: HOD Return -->
                        <form method="POST" action="<?= url('dashboard/hod/review_paper.php?id=' . $paperId) ?>" class="space-y-4">
                            <?= csrfField() ?>
                            <div>
                                <label class="block text-xs font-semibold text-rose-700 dark:text-rose-400 mb-1">Return Reason / Required Revisions</label>
                                <textarea name="return_feedback" rows="2" required placeholder="Specify why the paper is being returned to the lecturer..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-rose-300 dark:border-rose-800 rounded-xl text-xs focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>
                            </div>
                            <button type="submit" name="action_hod_return" value="1" onclick="return confirm('Return this paper to the lecturer?')" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 0118 0z"/></svg>
                                <span>Return Paper for Revision</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Examination Schedule Card -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Official Examination Schedule:</h3>
                    <?php if ($schedule): ?>
                        <div class="space-y-2 text-xs">
                            <div>
                                <span class="text-slate-400 block text-[10px]">Date & Time</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200"><?= formatDate($schedule['exam_date'], 'l, d F Y') ?> @ <?= htmlspecialchars($schedule['exam_time']) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px]">Venue</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($schedule['venue']) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-amber-600">No examination schedule configured for this course.</div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
