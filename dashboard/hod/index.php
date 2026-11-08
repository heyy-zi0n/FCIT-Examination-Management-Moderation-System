<?php
/**
 * Head of Department (HOD) Dashboard Overview
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);

$pdo = getDb();
$deptId = (int)$user['department_id'];

// Departmental Metrics (Consolidated & Parameterized)
$stmtCourses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = :dept_id");
$stmtCourses->execute(['dept_id' => $deptId]);
$totalCourses = (int)$stmtCourses->fetchColumn();

$stmtCounts = $pdo->prepare("
    SELECT
        COUNT(*) AS started_count,
        SUM(CASE WHEN ep.status = 'Draft' THEN 1 ELSE 0 END) AS drafts_count,
        SUM(CASE WHEN ep.status != 'Draft' THEN 1 ELSE 0 END) AS submitted_workflow_count,
        SUM(CASE WHEN ep.status = 'Submitted' THEN 1 ELSE 0 END) AS awaiting_mod_count,
        SUM(CASE WHEN ep.status = 'Returned' THEN 1 ELSE 0 END) AS revision_required_count,
        SUM(CASE WHEN ep.status = 'Moderated' AND ep.hod_status = 'pending' THEN 1 ELSE 0 END) AS pending_hod_count,
        SUM(CASE WHEN ep.eo_status = 'pending' AND ep.status IN ('Moderated', 'Approved') THEN 1 ELSE 0 END) AS pending_eo_count,
        SUM(CASE WHEN ep.status = 'Printed' THEN 1 ELSE 0 END) AS printed_count
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    WHERE c.department_id = :dept_id
");
$stmtCounts->execute(['dept_id' => $deptId]);
$metrics = $stmtCounts->fetch() ?: [];

$startedCount = (int)($metrics['started_count'] ?? 0);
$notStartedCount = max(0, $totalCourses - $startedCount);
$draftsCount = (int)($metrics['drafts_count'] ?? 0);
$missingCount = $notStartedCount + $draftsCount;
$submittedIntoWorkflowCount = (int)($metrics['submitted_workflow_count'] ?? 0);
$awaitingModerationCount = (int)($metrics['awaiting_mod_count'] ?? 0);
$revisionRequiredCount = (int)($metrics['revision_required_count'] ?? 0);
$pendingHodCount = (int)($metrics['pending_hod_count'] ?? 0);
$pendingEoCount = (int)($metrics['pending_eo_count'] ?? 0);
$printedCount = (int)($metrics['printed_count'] ?? 0);

require_once __DIR__ . '/../../services/PrintingService.php';
$stmtLocked = $pdo->prepare("
    SELECT ep.id, ep.status, es.exam_date
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    LEFT JOIN exam_schedules es ON ep.course_id = es.course_id
    WHERE c.department_id = :dept_id AND ep.status IN ('Approved', 'Locked', 'Queued')
");
$stmtLocked->execute(['dept_id' => $deptId]);
$lockedPapers = $stmtLocked->fetchAll();

$lockedCount = 0;
$readyForPrintingCount = 0;

foreach ($lockedPapers as $p) {
    if (!empty($p['exam_date'])) {
        $eligibility = PrintingService::checkPrintEligibility($p['exam_date']);
        if ($eligibility['eligible']) {
            $readyForPrintingCount++;
        } else {
            $lockedCount++;
        }
    } else {
        $lockedCount++;
    }
}

// Fetch Pending Approvals
$stmtPending = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, al.level_name,
           u_lec.first_name AS lec_first, u_lec.last_name AS lec_last,
           u_mod.first_name AS mod_first, u_mod.last_name AS mod_last,
           pv.version_number
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN users u_lec ON ep.lecturer_id = u_lec.id
    LEFT JOIN users u_mod ON ep.moderator_id = u_mod.id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE c.department_id = :dept_id AND ep.status = 'Moderated' AND ep.hod_status = 'pending'
    ORDER BY ep.updated_at ASC
");
$stmtPending->execute(['dept_id' => $deptId]);
$pendingPapers = $stmtPending->fetchAll();

$pageTitle = "HOD Dashboard — Department of " . ($user['department_name'] ?? 'Computing') . " — LASU FCIT CMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-lasu-navy via-slate-900 to-lasu-navy-light rounded-2xl p-6 text-white shadow-md mb-8 border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Departmental Examination Readiness Portal</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold"><?= formatUserName($user) ?></h1>
                <p class="text-sm text-slate-300 mt-1">Department of <span class="font-semibold text-white"><?= htmlspecialchars($user['department_name'] ?? 'Computing', ENT_QUOTES, 'UTF-8') ?></span></p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="<?= url('dashboard/hod/schedules.php') ?>" class="min-h-[40px] px-4 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold rounded-xl text-xs transition inline-flex items-center gap-2">
                    <span>Exam Schedules</span>
                </a>
                <a href="<?= url('dashboard/hod/pending_approvals.php') ?>" class="min-h-[40px] px-4 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                    <span>Pending Approvals</span>
                    <span class="min-w-[20px] h-5 px-1.5 bg-lasu-navy text-white rounded-full inline-flex items-center justify-center text-[11px] tabular-nums"><?= $pendingHodCount ?></span>
                </a>
            </div>
        </div>

        <!-- Departmental Readiness Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Expected Papers</div>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalCourses ?></div>
                <div class="text-xs text-slate-500 mt-1 tabular-nums"><?= $notStartedCount ?> not started &bull; <?= $draftsCount ?> draft</div>
            </div>
            <div class="rounded-xl p-4 sm:p-5 border shadow-sm <?= $missingCount > 0 ? 'bg-rose-50/70 border-rose-300' : 'bg-white border-slate-200' ?>">
                <div class="text-xs font-semibold uppercase tracking-wide <?= $missingCount > 0 ? 'text-rose-800' : 'text-slate-500' ?>">Not Yet Submitted</div>
                <div class="text-2xl sm:text-3xl font-black mt-1.5 tabular-nums <?= $missingCount > 0 ? 'text-rose-700' : 'text-slate-900' ?>"><?= $missingCount ?></div>
                <div class="text-xs <?= $missingCount > 0 ? 'text-rose-700' : 'text-slate-500' ?> mt-1 tabular-nums"><?= $submittedIntoWorkflowCount ?> in workflow</div>
            </div>
            <div class="rounded-xl p-4 sm:p-5 border shadow-sm <?= $pendingHodCount > 0 ? 'bg-amber-50/70 border-amber-300' : 'bg-white border-slate-200' ?>">
                <div class="text-xs font-semibold uppercase tracking-wide <?= $pendingHodCount > 0 ? 'text-amber-800' : 'text-slate-500' ?>">Pending HOD Sign-Off</div>
                <div class="text-2xl sm:text-3xl font-black mt-1.5 tabular-nums <?= $pendingHodCount > 0 ? 'text-amber-900' : 'text-slate-900' ?>"><?= $pendingHodCount ?></div>
                <div class="text-xs <?= $pendingHodCount > 0 ? 'text-amber-800' : 'text-slate-500' ?> mt-1 tabular-nums"><?= $awaitingModerationCount ?> in moderation</div>
            </div>
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Blind Locked</div>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $lockedCount ?></div>
                <div class="text-xs text-slate-500 mt-1 tabular-nums"><?= $readyForPrintingCount ?> window open</div>
            </div>
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-slate-200 shadow-sm col-span-2 sm:col-span-1">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Printed Papers</div>
                <div class="text-2xl sm:text-3xl font-black text-emerald-700 mt-1.5 tabular-nums"><?= $printedCount ?></div>
                <div class="text-xs text-slate-500 mt-1">Completed lifecycle</div>
            </div>
        </div>

        <!-- Pending Papers Awaiting HOD Sign-off -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Moderated Papers Awaiting HOD Approval</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Papers vetted by departmental moderators requiring your official endorsement</p>
                </div>
                <a href="<?= url('dashboard/hod/pending_approvals.php') ?>" class="text-xs text-lasu-navy font-bold hover:underline shrink-0">View All &rarr;</a>
            </div>

            <div tabindex="0" role="region" aria-label="Moderated papers awaiting HOD approval" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Title</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Lecturer</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Moderator</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Version &amp; Wait</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">EO Sign-Off</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($pendingPapers)): ?>
                            <tr>
                                <td colspan="6" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No moderated papers awaiting your approval</div>
                                    <div class="text-xs text-slate-500 mt-1">All moderated departmental papers have been reviewed.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingPapers as $p): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($p['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($p['level_name'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-slate-800">
                                        <?= htmlspecialchars($p['lec_first'] . ' ' . $p['lec_last'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-700">
                                        <?= htmlspecialchars(trim(($p['mod_first'] ?? '—') . ' ' . ($p['mod_last'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-blue-800 font-mono text-xs font-bold tabular-nums">
                                            V<?= (int)($p['version_number'] ?: 1) ?>
                                        </span>
                                        <div class="text-xs font-semibold text-amber-700 mt-1">
                                            Pending <?= htmlspecialchars(getPaperTimeInStage($p['updated_at']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border <?= $p['eo_status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>">
                                            EO: <?= ucfirst(htmlspecialchars($p['eo_status'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <a href="<?= url('dashboard/hod/review_paper.php?id=' . $p['id']) ?>" class="min-h-[36px] px-4 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-white font-bold rounded-xl shadow-sm transition text-xs inline-flex items-center gap-1.5">
                                            <span>Inspect &amp; Sign-Off</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
