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

$totalActiveSchedules = (int)$pdo->query("SELECT COUNT(*) FROM exam_schedules es JOIN courses c ON es.course_id = c.id WHERE c.department_id = {$deptId}")->fetchColumn();
$pendingEoCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status IN ('Moderated', 'Approved') AND ep.eo_status = 'pending'")->fetchColumn();
$totalPrintedCount = (int)$pdo->query("SELECT COUNT(*) FROM print_logs pl JOIN examination_papers ep ON pl.paper_id = ep.id JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND pl.print_status = 'successful'")->fetchColumn();

// Calculate actual Locked vs Ready for Printing based on eligibility
require_once __DIR__ . '/../../services/PrintingService.php';
$stmtLocked = $pdo->query("
    SELECT ep.id, ep.status, es.exam_date 
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    LEFT JOIN exam_schedules es ON ep.course_id = es.course_id
    WHERE c.department_id = {$deptId} AND ep.status IN ('Approved', 'Locked', 'Queued')
");
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
$stmtPending = $pdo->query("
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
$stmtQueue = $pdo->query("
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

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-emerald-950 via-slate-900 to-lasu-navy rounded-2xl p-6 text-white shadow-lg mb-8 border border-slate-800 flex flex-col md:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-emerald-400 uppercase tracking-wider mb-1">Department Examination Office Pipeline</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold"><?= formatUserName($user) ?></h1>
                <p class="text-xs text-slate-300 mt-1">Manage the complete examination pipeline for: <span class="font-semibold text-white"><?= htmlspecialchars($user['department_name'] ?? 'Your Department') ?></span></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="px-5 py-3 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Controlled Print Queue</span>
                </a>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase">Upcoming Exams</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $totalActiveSchedules ?></div>
            </div>
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-200 dark:border-amber-900/50 shadow-sm">
                <div class="text-xs font-semibold text-amber-600 uppercase">Awaiting EO Approval</div>
                <div class="text-2xl font-black text-amber-600 mt-1"><?= $pendingEoCount ?></div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-900/50 shadow-sm col-span-2 sm:col-span-1 lg:col-span-1">
                <div class="text-xs font-semibold text-blue-600 uppercase">Awaiting Moderation</div>
                <?php 
                $modCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers WHERE status = 'Submitted'")->fetchColumn(); 
                ?>
                <div class="text-2xl font-black text-blue-600 mt-1"><?= $modCount ?></div>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 border border-purple-200 dark:border-purple-900/50 shadow-sm">
                <div class="text-xs font-semibold text-purple-600 uppercase">Locked Papers</div>
                <div class="text-2xl font-black text-purple-600 mt-1"><?= $lockedCount ?></div>
            </div>
            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4 border border-emerald-200 dark:border-emerald-900/50 shadow-sm">
                <div class="text-xs font-semibold text-emerald-600 uppercase">Ready for Printing</div>
                <div class="text-2xl font-black text-emerald-600 mt-1"><?= $readyForPrintingCount ?></div>
            </div>
            <div class="bg-slate-100 dark:bg-slate-800 rounded-xl p-4 border border-slate-300 dark:border-slate-700 shadow-sm">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Printed Papers</div>
                <div class="text-2xl font-black text-slate-700 dark:text-slate-200 mt-1"><?= $totalPrintedCount ?></div>
            </div>
        </div>

        <!-- Section 1: Moderated Papers Awaiting EO Approval -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Faculty Moderation Queue Pending EO Sign-Off</h2>
                    <p class="text-xs text-slate-500">Moderated examination questions requiring Exam Officer approval to satisfy Dual Approval</p>
                </div>
                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-bold rounded-full text-xs"><?= count($pendingPapers) ?> Pending</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Department</th>
                            <th class="py-3.5 px-4 font-semibold">Lecturer</th>
                            <th class="py-3.5 px-4 font-semibold">HOD Status</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($pendingPapers)): ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    No papers are currently awaiting Exam Officer sign-off.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingPapers as $p): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($p['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title']) ?> (<?= htmlspecialchars($p['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-slate-800 dark:text-slate-200">
                                        <?= htmlspecialchars($p['dept_name']) ?>
                                    </td>
                                    <td class="py-4 px-4 font-medium">
                                        <?= htmlspecialchars($p['lec_first'] . ' ' . $p['lec_last']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="mb-1">
                                            <span class="px-2.5 py-1 rounded-full font-bold text-[10px] <?= $p['hod_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                                HOD: <?= ucfirst($p['hod_status']) ?>
                                            </span>
                                        </div>
                                        <div class="text-[10px] font-semibold text-amber-600 dark:text-amber-400">
                                            Pending for <?= getPaperTimeInStage($p['updated_at']) ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <a href="<?= url('dashboard/exam_officer/review_paper.php?id=' . $p['id']) ?>" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow transition text-xs inline-flex items-center gap-1.5">
                                            <span>Inspect & Sign-off</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Controlled Print Queue Overview -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Controlled Printing Status & Date Gates</h2>
                    <p class="text-xs text-slate-500">Live eligibility calculated against Africa/Lagos calendar day (Exam Date - 1 day)</p>
                </div>
                <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline">Full Print Queue &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Dept</th>
                            <th class="py-3.5 px-4 font-semibold">Exam Date</th>
                            <th class="py-3.5 px-4 font-semibold">Permitted Printing Day</th>
                            <th class="py-3.5 px-4 font-semibold">Status / Gate</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Print Control</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($queuePapers)): ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    No approved papers are currently in the print queue.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queuePapers as $qp): 
                                $elig = PrintingService::checkPrintEligibility($qp['exam_date']);
                            ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4 font-bold font-mono text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($qp['course_code']) ?>
                                        <span class="text-slate-400 font-normal text-[11px] ml-1">(<?= htmlspecialchars($qp['dept_code']) ?>)</span>
                                    </td>
                                    <td class="py-4 px-4 font-medium">
                                        <?= formatDate($qp['exam_date'], 'D, d M Y') ?>
                                    </td>
                                    <td class="py-4 px-4 font-semibold text-cyan-600 dark:text-cyan-400">
                                        <?= formatDate($elig['allowed_date'], 'd M Y') ?> ONLY
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if ($qp['status'] === STATUS_PRINTED): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800">Printed</span>
                                        <?php elseif ($elig['eligible']): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 animate-pulse">Eligible for Print Today</span>
                                        <?php elseif ($elig['status'] === 'early'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Early — Locked</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">Window Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <?php if ($elig['eligible'] || $qp['status'] === STATUS_PRINTED): ?>
                                            <a href="<?= url('dashboard/exam_officer/controlled_print.php?id=' . $qp['id']) ?>" class="px-3 py-1.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-lg text-xs shadow transition">
                                                <?= $qp['status'] === STATUS_PRINTED ? 'Reprint Paper' : 'Execute Print' ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-400 italic">Gate Locked</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
