<?php
/**
 * Faculty Examination Officer (EO) Dashboard Overview
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$pdo = getDb();
$userId = (int)$user['id'];

// Department-scoped statistics
$deptId = (int)$user['department_id'];

$stmtSchedCount = $pdo->prepare("SELECT COUNT(*) FROM exam_schedules es JOIN courses c ON es.course_id = c.id WHERE c.department_id = :dept_id");
$stmtSchedCount->execute(['dept_id' => $deptId]);
$totalActiveSchedules = (int)$stmtSchedCount->fetchColumn();

$stmtPendingEo = $pdo->prepare("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = :dept_id AND ep.status IN ('Moderated', 'Approved') AND ep.eo_status = 'pending'");
$stmtPendingEo->execute(['dept_id' => $deptId]);
$pendingEoCount = (int)$stmtPendingEo->fetchColumn();

$stmtPrinted = $pdo->prepare("SELECT COUNT(*) FROM print_logs pl JOIN examination_papers ep ON pl.paper_id = ep.id JOIN courses c ON ep.course_id = c.id WHERE c.department_id = :dept_id AND pl.print_status = 'successful'");
$stmtPrinted->execute(['dept_id' => $deptId]);
$totalPrintedCount = (int)$stmtPrinted->fetchColumn();

$stmtModCount = $pdo->prepare("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = :dept_id AND ep.status = 'Submitted'");
$stmtModCount->execute(['dept_id' => $deptId]);
$modCount = (int)$stmtModCount->fetchColumn();

// Calculate actual Locked vs Ready for Printing based on eligibility
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

// Fetch Pending EO Approvals
$stmtPending = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, d.name AS dept_name, al.level_name,
           u_lec.first_name AS lec_first, u_lec.last_name AS lec_last,
           u_mod.first_name AS mod_first, u_mod.last_name AS mod_last,
           pv.version_number
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN users u_lec ON ep.lecturer_id = u_lec.id
    LEFT JOIN users u_mod ON ep.moderator_id = u_mod.id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE c.department_id = :dept_id AND ep.status = 'Moderated' AND ep.eo_status = 'pending'
    ORDER BY ep.updated_at ASC
");
$stmtPending->execute(['dept_id' => $deptId]);
$pendingPapers = $stmtPending->fetchAll();

// Fetch upcoming locked papers eligible or soon-to-be eligible for print
$stmtQueue = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, d.code AS dept_code,
           es.exam_date, es.exam_time, es.venue, es.expected_students,
           bl.checksum_sha256, bl.locked_at
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN exam_schedules es ON ep.exam_schedule_id = es.id
    JOIN blind_lockdowns bl ON ep.id = bl.paper_id
    WHERE c.department_id = :dept_id AND ep.status IN ('Approved', 'Locked', 'Queued', 'Printed')
    ORDER BY es.exam_date ASC
");
$stmtQueue->execute(['dept_id' => $deptId]);
$queuePapers = $stmtQueue->fetchAll();

$pageTitle = 'Examination Officer Dashboard — LASU FCIT CMS';
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
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Examination Office Pipeline</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold"><?= formatUserName($user) ?></h1>
                <p class="text-sm text-slate-300 mt-1">Dual-authorization and controlled print release for <span class="font-semibold text-white"><?= htmlspecialchars($user['department_name'] ?? 'Department', ENT_QUOTES, 'UTF-8') ?></span></p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="min-h-[42px] px-5 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Controlled Print Queue</span>
                </a>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Scheduled Exams</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalActiveSchedules ?></div>
            </div>
            <div class="rounded-xl p-4 border shadow-sm <?= $pendingEoCount > 0 ? 'bg-amber-50/70 border-amber-300' : 'bg-white border-slate-200' ?>">
                <div class="text-xs font-semibold uppercase tracking-wide <?= $pendingEoCount > 0 ? 'text-amber-800' : 'text-slate-500' ?>">Awaiting EO Sign-Off</div>
                <div class="text-2xl font-black mt-1.5 tabular-nums <?= $pendingEoCount > 0 ? 'text-amber-900' : 'text-slate-900' ?>"><?= $pendingEoCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">In Moderation</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $modCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Blind Locked</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $lockedCount ?></div>
            </div>
            <div class="rounded-xl p-4 border shadow-sm <?= $readyForPrintingCount > 0 ? 'bg-emerald-50/70 border-emerald-300' : 'bg-white border-slate-200' ?>">
                <div class="text-xs font-semibold uppercase tracking-wide <?= $readyForPrintingCount > 0 ? 'text-emerald-800' : 'text-slate-500' ?>">Ready to Print</div>
                <div class="text-2xl font-black mt-1.5 tabular-nums <?= $readyForPrintingCount > 0 ? 'text-emerald-800' : 'text-slate-900' ?>"><?= $readyForPrintingCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Printed Batches</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalPrintedCount ?></div>
            </div>
        </div>

        <!-- Section 1: Moderated Papers Awaiting EO Approval -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Moderated Papers Pending EO Sign-Off</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Moderated examination questions requiring Examination Officer sign-off for Dual Approval</p>
                </div>
                <span class="px-3 py-1 bg-slate-100 text-slate-800 font-bold rounded-full text-xs tabular-nums"><?= count($pendingPapers) ?> Pending</span>
            </div>

            <div tabindex="0" role="region" aria-label="Moderated papers pending Exam Officer sign-off" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Title</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Department</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Lecturer</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">HOD Status</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($pendingPapers)): ?>
                            <tr>
                                <td colspan="5" class="py-10 px-5 text-center text-sm text-slate-500">
                                    No papers are currently awaiting Examination Officer sign-off.
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
                                        <?= htmlspecialchars($p['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-slate-800">
                                        <?= htmlspecialchars($p['lec_first'] . ' ' . $p['lec_last'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full font-bold text-xs border <?= $p['hod_status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200' ?>">
                                            HOD: <?= ucfirst(htmlspecialchars($p['hod_status'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                        <div class="text-xs font-semibold text-amber-700 mt-1">
                                            Pending <?= htmlspecialchars(getPaperTimeInStage($p['updated_at']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <a href="<?= url('dashboard/exam_officer/review_paper.php?id=' . $p['id']) ?>" class="min-h-[36px] px-4 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-white font-bold rounded-xl shadow-sm transition text-xs inline-flex items-center gap-1.5">
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

        <!-- Section 2: Controlled Print Queue Overview -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Controlled Printing Status &amp; Date Gates</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Live eligibility calculated against Africa/Lagos calendar day (Exam Date &minus; 1 day)</p>
                </div>
                <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="text-xs text-lasu-navy font-bold hover:underline shrink-0">Full Print Queue &rarr;</a>
            </div>

            <div tabindex="0" role="region" aria-label="Controlled printing status and date gates" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Dept</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Exam Date</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Permitted Printing Day</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Status / Gate</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Print Control</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($queuePapers)): ?>
                            <tr>
                                <td colspan="5" class="py-10 px-5 text-center text-sm text-slate-500">
                                    No dual-approved examination papers are currently in the print queue.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queuePapers as $qp):
                                $elig = PrintingService::checkPrintEligibility($qp['exam_date']);
                            ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5 font-bold font-mono text-slate-900">
                                        <?= htmlspecialchars($qp['course_code'], ENT_QUOTES, 'UTF-8') ?>
                                        <span class="text-slate-500 font-normal text-xs ml-1">(<?= htmlspecialchars($qp['dept_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                    </td>
                                    <td class="py-4 px-4 font-medium tabular-nums whitespace-nowrap">
                                        <?= formatDate($qp['exam_date'], 'D, d M Y') ?>
                                    </td>
                                    <td class="py-4 px-4 font-semibold text-cyan-800 tabular-nums whitespace-nowrap">
                                        <?= formatDate($elig['allowed_date'], 'd M Y') ?> ONLY
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <?php if ($qp['status'] === STATUS_PRINTED): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200">Printed</span>
                                        <?php elseif ($elig['eligible']): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                <span class="w-2 h-2 rounded-full bg-emerald-600" aria-hidden="true"></span>
                                                <span>Eligible Today</span>
                                            </span>
                                        <?php elseif ($elig['status'] === 'early'): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">Early &mdash; Locked</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">Window Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <?php if ($elig['eligible'] || $qp['status'] === STATUS_PRINTED): ?>
                                            <a href="<?= url('dashboard/exam_officer/controlled_print.php?id=' . $qp['id']) ?>" class="min-h-[34px] inline-flex items-center px-3.5 py-1.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-lg text-xs shadow-sm transition">
                                                <?= $qp['status'] === STATUS_PRINTED ? 'Reprint Paper' : 'Execute Print' ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">Gate Locked</span>
                                        <?php endif; ?>
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
