<?php
/**
 * Exam Officer Examination Review & Dual Approval Stage
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$paperId = (int)($_GET['id'] ?? 0);
$paper = requirePaperAccess($paperId, 'eo_review');

$pdo = getDb();
$userId = (int)$user['id'];

// Handle EO Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // 1. EO Approve
    if (isset($_POST['action_eo_approve'])) {
        $comments = sanitizeInput($_POST['eo_comments'] ?? '');
        try {
            WorkflowService::eoApprovePaper($paperId, $userId, $comments);
            
            // Check if Dual approval triggered Approved status
            $stmtStatus = $pdo->prepare("SELECT status FROM examination_papers WHERE id = :id");
            $stmtStatus->execute(['id' => $paperId]);
            $statusCheck = $stmtStatus->fetchColumn();
            if ($statusCheck === STATUS_APPROVED) {
                setFlash('success', "Dual Approval Satisfied! Both HOD and Exam Officer have approved. Examination paper {$paper['paper_code']} is now securely sealed under Cryptographic Blind Lockdown and eligible for print queue.");
            } else {
                setFlash('success', "Exam Officer Sign-Off Recorded! Paper is currently awaiting HOD sign-off to complete dual approval.");
            }
            
            redirect('dashboard/exam_officer/pending_approvals.php');
            exit;
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }

    // 2. EO Return
    if (isset($_POST['action_eo_return'])) {
        $feedback = sanitizeInput($_POST['return_feedback'] ?? '');
        try {
            if (empty(trim($feedback))) {
                throw new InvalidArgumentException("Please provide feedback explaining why the paper is being returned for correction.");
            }
            WorkflowService::eoReturnPaper($paperId, $userId, $feedback);
            setFlash('info', "Examination paper {$paper['paper_code']} has been returned to the lecturer for revision.");
            redirect('dashboard/exam_officer/pending_approvals.php');
            exit;
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }
}

// Fetch versions, comments, approvals, schedule
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

$previewUrl = url('preview.php?paper_id=' . $paperId . '&version_id=' . $paper['current_version_id'] . '&type=question');

$pageTitle = "Exam Officer Review: {$paper['paper_code']} — LASU FCIT EMMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <a href="<?= url('dashboard/exam_officer/pending_approvals.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-lasu-navy mb-1.5 transition">
                    <span aria-hidden="true">&larr;</span>
                    <span>Back to Pending Approvals</span>
                </a>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($paper['course_code']) ?>: <?= htmlspecialchars($paper['course_title']) ?></h1>
                    <?= renderStatusBadge($paper['status']) ?>
                </div>
                <div class="text-xs text-slate-600 font-mono mt-1 tabular-nums">Paper Code: <?= htmlspecialchars($paper['paper_code']) ?> &bull; Dept: <?= htmlspecialchars($paper['department_name']) ?></div>
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
            <!-- Left: Document Preview & Annotations (2 Columns) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Live Embedded PDF Preview -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-[420px] sm:h-[600px] lg:h-[680px]">
                    <div class="px-5 py-3 bg-lasu-navy text-white flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-xs">Vetted Question Paper Document</span>
                            <span class="text-[11px] bg-lasu-gold/20 text-lasu-gold px-2 py-0.5 rounded font-mono tabular-nums">Version <?= (int)($paper['version_number'] ?: 1) ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hidden sm:inline text-[11px] text-slate-300 uppercase tracking-wider font-mono">Watermarked Preview</span>
                            <a href="<?= $previewUrl ?>" target="_blank" rel="noopener" class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition">
                                Open PDF in New Tab
                            </a>
                        </div>
                    </div>

                    <div class="flex-1 bg-slate-900">
                        <iframe src="<?= $previewUrl ?>" class="w-full h-full border-0" title="Question Document Preview"></iframe>
                    </div>
                </div>

                <!-- Moderation & Annotations Summary -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center justify-between">
                        <span>Moderator Vetting &amp; Annotations Log</span>
                        <span class="text-xs text-slate-500 font-semibold tabular-nums"><?= count($comments) ?> Comment(s)</span>
                    </h2>

                    <?php if (empty($comments)): ?>
                        <div class="text-sm text-slate-500 text-center py-4">No specific line annotations recorded by the moderator.</div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($comments as $c): ?>
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600 mb-1">
                                        <span class="font-bold text-slate-900"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?> (<?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['role']))) ?>)</span>
                                        <span class="tabular-nums"><?= formatDate($c['created_at']) ?></span>
                                    </div>
                                    <div class="text-sm text-slate-700 leading-relaxed"><?= htmlspecialchars($c['comment_text']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: EO Governance Decision Panel (1 Column) -->
            <div class="space-y-6">
                <!-- Dual Approval Status Card -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4">Dual Approval Status</h2>
                    
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                            <div>
                                <div class="font-bold text-slate-900">Head of Department (HOD)</div>
                                <div class="text-xs text-slate-500">Departmental Quality Endorsement</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full font-bold text-xs border <?= $paper['hod_status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-900 border-amber-300' ?>">
                                <?= htmlspecialchars(ucfirst($paper['hod_status'])) ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                            <div>
                                <div class="font-bold text-slate-900">Faculty Exam Officer (EO)</div>
                                <div class="text-xs text-slate-500">Faculty Print Clearance</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full font-bold text-xs border <?= $paper['eo_status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-900 border-amber-300' ?>">
                                <?= htmlspecialchars(ucfirst($paper['eo_status'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 text-xs text-emerald-900 leading-relaxed">
                        <strong>Separation of Duties Rule:</strong> Approval by Exam Officer must be matched by HOD approval before final status transitions to Approved and Blind Lockdown triggers.
                    </div>
                </div>

                <!-- EO Sign-off Action Panel -->
                <?php if ($paper['status'] === STATUS_MODERATED): ?>
                    <div class="bg-white rounded-2xl p-6 shadow-sm border-2 border-emerald-200">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600" aria-hidden="true"></span>
                            <span>Exam Officer Action</span>
                        </h2>

                        <!-- Action Form 1: EO Approve -->
                        <form method="POST" action="<?= url('dashboard/exam_officer/review_paper.php?id=' . $paperId) ?>" class="space-y-4 mb-6">
                            <?= csrfField() ?>
                            <div>
                                <label for="eo_comments" class="block text-xs font-semibold text-slate-700 mb-1.5">Exam Officer Notes / Remarks</label>
                                <textarea id="eo_comments" name="eo_comments" rows="2" placeholder="e.g. Cleared for print queue allocation upon HOD dual sign-off..." class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-emerald-600 focus:outline-none"></textarea>
                            </div>
                            <button
                                type="submit"
                                name="action_eo_approve"
                                value="1"
                                data-confirm-title="Authorize Exam Officer Sign-Off"
                                data-confirm-message="Authorize and record formal Exam Officer approval for <?= htmlspecialchars($paper['paper_code'], ENT_QUOTES, 'UTF-8') ?>?"
                                data-confirm-label="Endorse & Approve"
                                data-confirm-variant="success"
                                data-loading-text="Recording Sign-Off..."
                                class="min-h-[44px] w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Endorse &amp; Approve EO Stage</span>
                            </button>
                        </form>

                        <div class="border-t border-slate-200 my-4"></div>

                        <!-- Action Form 2: EO Return -->
                        <form method="POST" action="<?= url('dashboard/exam_officer/review_paper.php?id=' . $paperId) ?>" class="space-y-4">
                            <?= csrfField() ?>
                            <div>
                                <label for="eo_return_feedback" class="block text-xs font-semibold text-rose-800 mb-1.5">Return Reason / Non-Compliance <span class="text-rose-600">*</span></label>
                                <textarea id="eo_return_feedback" name="return_feedback" rows="2" required placeholder="Specify why the paper is being returned..." class="w-full px-3.5 py-2.5 bg-white border border-rose-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-rose-600 focus:outline-none"></textarea>
                            </div>
                            <button
                                type="submit"
                                name="action_eo_return"
                                value="1"
                                data-confirm-title="Return Paper for Revision"
                                data-confirm-message="Return examination paper <?= htmlspecialchars($paper['paper_code'], ENT_QUOTES, 'UTF-8') ?> to the lecturer for revision?"
                                data-confirm-label="Return Paper"
                                data-confirm-variant="danger"
                                data-loading-text="Returning Paper..."
                                class="min-h-[42px] w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 0118 0z"/></svg>
                                <span>Return Paper for Revision</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Schedule Card -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3">Examination Schedule</h2>
                    <?php if ($schedule): ?>
                        <dl class="space-y-2.5 text-sm">
                            <div>
                                <dt class="text-slate-500 text-xs">Date &amp; Time</dt>
                                <dd class="font-bold text-slate-900 tabular-nums"><?= formatDate($schedule['exam_date'], 'l, d F Y') ?> @ <?= htmlspecialchars($schedule['exam_time']) ?></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 text-xs">Venue</dt>
                                <dd class="font-medium text-slate-800"><?= htmlspecialchars($schedule['venue']) ?></dd>
                            </div>
                        </dl>
                    <?php else: ?>
                        <div class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">Schedule not yet published.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
