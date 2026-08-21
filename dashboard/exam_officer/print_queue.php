<?php
/**
 * Controlled Examination Print Queue (Exam Officer)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$pdo = getDb();

// Fetch all approved/locked/printed papers
$stmt = $pdo->query("
    SELECT ep.*, c.course_code, c.course_title, d.name AS dept_name, d.code AS dept_code, al.level_name,
           es.exam_date, es.exam_time, es.venue, es.expected_students,
           bl.id AS lockdown_id, bl.checksum_sha256, bl.locked_at, bl.is_unlocked,
           pv.version_number, pv.question_file_name, pv.question_file_size,
           (SELECT COUNT(*) FROM print_logs pl WHERE pl.paper_id = ep.id AND pl.print_status = 'successful') AS print_count
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN exam_schedules es ON ep.exam_schedule_id = es.id
    JOIN blind_lockdowns bl ON ep.id = bl.paper_id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE ep.status IN ('Approved', 'Locked', 'Queued', 'Printed')
    ORDER BY es.exam_date ASC, ep.id ASC
");
$queue = $stmt->fetchAll();

$pageTitle = 'Controlled Print Queue — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Controlled Examination Print Queue</h1>
                <p class="text-xs text-slate-500">Live cryptographic release queue enforcing server-side Africa/Lagos calendar day window (Exam Date - 1 Calendar Day)</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="/FOCITECMS/dashboard/exam_officer/print_history.php" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Print Logs & Reprints</span>
                </a>
            </div>
        </div>

        <!-- System Time & Rule Notice Banner -->
        <div class="bg-cyan-950/40 border border-cyan-800/60 rounded-2xl p-5 mb-8 text-cyan-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-900/60 text-cyan-400 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-cyan-300">Server Calendar Date & Time</div>
                    <div class="text-sm font-semibold text-white">
                        <?= (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('l, d F Y — h:i:s A (T)') ?>
                    </div>
                </div>
            </div>
            <div class="text-xs text-cyan-300 font-medium">
                Enforcement: Calendar Date == Exam Date - 1 Day (Timezone: <span class="font-mono font-bold">Africa/Lagos</span>)
            </div>
        </div>

        <!-- Print Queue Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Dept</th>
                            <th class="py-3.5 px-4 font-semibold">Scheduled Exam Date</th>
                            <th class="py-3.5 px-4 font-semibold">Permitted Printing Day</th>
                            <th class="py-3.5 px-4 font-semibold">Expected Cohort</th>
                            <th class="py-3.5 px-4 font-semibold">Printing Eligibility Gate</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Production Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($queue)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    No approved examination papers are currently in the print queue.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queue as $qp): 
                                $elig = PrintingService::checkPrintEligibility($qp['exam_date']);
                                
                                $stmtEpr = $pdo->prepare("SELECT status FROM emergency_print_requests WHERE paper_id = ? AND version_id = ? ORDER BY id DESC LIMIT 1");
                                $stmtEpr->execute([$qp['id'], $qp['current_version_id']]);
                                $eprStatus = $stmtEpr->fetchColumn();
                                
                                if ($eprStatus === 'approved') {
                                    $elig['eligible'] = true;
                                    $elig['status'] = 'emergency_approved';
                                }
                            ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($qp['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($qp['course_title']) ?> (<?= htmlspecialchars($qp['dept_code']) ?> • <?= htmlspecialchars($qp['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                                        <?= formatDate($qp['exam_date'], 'D, d M Y') ?>
                                        <div class="text-[10px] text-slate-400 font-normal">Time: <?= htmlspecialchars($qp['exam_time']) ?></div>
                                    </td>
                                    <td class="py-4 px-4 font-semibold text-cyan-600 dark:text-cyan-400">
                                        <?= formatDate($elig['allowed_date'], 'D, d M Y') ?>
                                        <span class="block text-[10px] text-slate-400 font-normal font-mono">Strict 24h Window</span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-800 dark:text-slate-200"><?= $qp['expected_students'] ?> Copies</div>
                                        <div class="text-[10px] text-slate-400">Venue: <?= htmlspecialchars($qp['venue']) ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if ($qp['status'] === STATUS_PRINTED): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-teal-100 text-teal-800">
                                                <svg class="w-3.5 h-3.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <span>Printed (<?= $qp['print_count'] ?> batch)</span>
                                            </span>
                                        <?php elseif ($elig['status'] === 'emergency_approved'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800 border border-purple-300 animate-pulse">
                                                <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                                                <span>Emergency Print Approved</span>
                                            </span>
                                        <?php elseif ($elig['eligible']): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 animate-pulse">
                                                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                                                <span>Eligible for Print Today</span>
                                            </span>
                                        <?php elseif ($eprStatus === 'pending'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                <svg class="w-3.5 h-3.5 text-amber-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                <span>Emergency Request Pending</span>
                                            </span>
                                        <?php elseif ($elig['status'] === 'early'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                <span>Early — Locked Until <?= formatDate($elig['allowed_date'], 'd M') ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span>Window Expired</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <?php if ($elig['eligible'] || $qp['status'] === STATUS_PRINTED): ?>
                                            <a href="/FOCITECMS/dashboard/exam_officer/controlled_print.php?id=<?= $qp['id'] ?>" class="px-4 py-2 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow transition inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                <span><?= $qp['status'] === STATUS_PRINTED ? 'Reprint Paper' : 'Execute Print' ?></span>
                                            </a>
                                        <?php elseif ($eprStatus === 'pending'): ?>
                                            <button disabled class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-400 rounded-lg text-xs font-semibold cursor-not-allowed border border-slate-200 dark:border-slate-700">
                                                Awaiting HOD
                                            </button>
                                        <?php else: ?>
                                            <a href="/FOCITECMS/dashboard/exam_officer/request_emergency_print.php?id=<?= $qp['id'] ?>" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold border border-rose-200 transition">
                                                Request Emergency Print
                                            </a>
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
