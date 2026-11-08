<?php
/**
 * Controlled Examination Print Queue (Exam Officer)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$pdo = getDb();
$deptId = !empty($user['department_id']) ? (int)$user['department_id'] : null;

// Fetch all approved/locked/printed papers with correlated emergency print request status (no N+1 query)
$whereDept = $deptId !== null ? "c.department_id = :dept_id AND " : "";
$stmt = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, d.name AS dept_name, d.code AS dept_code, al.level_name,
           es.exam_date, es.exam_time, es.venue, es.expected_students,
           bl.id AS lockdown_id, bl.checksum_sha256, bl.locked_at, bl.is_unlocked,
           pv.version_number, pv.question_file_name, pv.question_file_size,
           (SELECT COUNT(*) FROM print_logs pl WHERE pl.paper_id = ep.id AND pl.print_status = 'successful') AS print_count,
           (SELECT epr.status FROM emergency_print_requests epr WHERE epr.paper_id = ep.id AND epr.version_id = ep.current_version_id ORDER BY epr.id DESC LIMIT 1) AS epr_status
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN exam_schedules es ON ep.exam_schedule_id = es.id
    JOIN blind_lockdowns bl ON ep.id = bl.paper_id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE {$whereDept} ep.status IN ('Approved', 'Locked', 'Queued', 'Printed')
    ORDER BY es.exam_date ASC, ep.id ASC
");
$params = $deptId !== null ? ['dept_id' => $deptId] : [];
$stmt->execute($params);
$queue = $stmt->fetchAll();

$pageTitle = 'Controlled Print Queue — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Controlled Examination Print Queue</h1>
                <p class="text-sm text-slate-600 mt-0.5">Cryptographic release queue enforcing server-side Africa/Lagos calendar-day window (Exam Date &minus; 1 Day)</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="<?= url('dashboard/exam_officer/print_history.php') ?>" class="min-h-[40px] px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold border border-slate-200 shadow-sm transition inline-flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Print Logs &amp; Reprints</span>
                </a>
            </div>
        </div>

        <!-- High-Contrast Server Time & Date-Gate Rule Banner -->
        <div class="bg-lasu-navy border border-lasu-navy-light rounded-2xl p-5 mb-8 text-white shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-white/10 text-lasu-gold border border-white/15 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-lasu-gold">Authoritative Server Date &amp; Time</div>
                    <div class="text-sm sm:text-base font-bold text-white tabular-nums mt-0.5">
                        <?= (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('l, d F Y — h:i:s A (T)') ?>
                    </div>
                </div>
            </div>
            <div class="text-xs text-slate-200 font-medium bg-white/10 border border-white/15 px-3.5 py-2 rounded-xl">
                Release Gate: <span class="font-bold text-white">Today == Exam Date &minus; 1 Day</span> (<span class="font-mono text-lasu-gold font-bold">Africa/Lagos</span>)
            </div>
        </div>

        <!-- Print Queue Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Controlled examination print queue" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Dept</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Scheduled Exam Date</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Permitted Printing Day</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Expected Cohort</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Printing Eligibility Gate</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Production Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($queue)): ?>
                            <tr>
                                <td colspan="6" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No approved examination papers in the print queue</div>
                                    <div class="text-xs text-slate-500 mt-1">Papers enter this queue automatically once Dual Approval and Blind Lockdown are completed.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($queue as $qp):
                                $elig = PrintingService::checkPrintEligibility($qp['exam_date']);
                                $eprStatus = $qp['epr_status'] ?? null;

                                if ($eprStatus === 'approved') {
                                    $elig['eligible'] = true;
                                    $elig['status'] = 'emergency_approved';
                                }
                            ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($qp['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($qp['course_title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($qp['dept_code'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($qp['level_name'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap tabular-nums">
                                        <div class="font-bold text-slate-900"><?= formatDate($qp['exam_date'], 'D, d M Y') ?></div>
                                        <div class="text-xs text-slate-500">Time: <?= htmlspecialchars($qp['exam_time'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap tabular-nums">
                                        <div class="font-semibold text-cyan-800"><?= formatDate($elig['allowed_date'], 'D, d M Y') ?></div>
                                        <span class="block text-xs text-slate-500 font-mono">Strict 24h Window</span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 tabular-nums"><?= (int)$qp['expected_students'] ?> Copies</div>
                                        <div class="text-xs text-slate-500 line-clamp-1">Venue: <?= htmlspecialchars($qp['venue'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <?php if ($qp['status'] === STATUS_PRINTED): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200">
                                                <svg class="w-3.5 h-3.5 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <span class="tabular-nums">Printed (<?= (int)$qp['print_count'] ?> <?= (int)$qp['print_count'] === 1 ? 'batch' : 'batches' ?>)</span>
                                            </span>
                                        <?php elseif ($elig['status'] === 'emergency_approved'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-800 border border-purple-200">
                                                <span class="w-2 h-2 rounded-full bg-purple-600" aria-hidden="true"></span>
                                                <span>Emergency Print Approved</span>
                                            </span>
                                        <?php elseif ($elig['eligible']): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                <span class="w-2 h-2 rounded-full bg-emerald-600" aria-hidden="true"></span>
                                                <span>Eligible for Print Today</span>
                                            </span>
                                        <?php elseif ($eprStatus === 'pending'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                <svg class="w-3.5 h-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span>Emergency Request Pending</span>
                                            </span>
                                        <?php elseif ($elig['status'] === 'early'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                <svg class="w-3.5 h-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                <span>Locked Until <?= formatDate($elig['allowed_date'], 'd M') ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                                <svg class="w-3.5 h-3.5 text-rose-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span>Window Expired</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <?php if ($elig['eligible'] || $qp['status'] === STATUS_PRINTED): ?>
                                            <a href="<?= url('dashboard/exam_officer/controlled_print.php?id=' . $qp['id']) ?>" class="min-h-[36px] px-4 py-2 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                <span><?= $qp['status'] === STATUS_PRINTED ? 'Reprint Paper' : 'Execute Print' ?></span>
                                            </a>
                                        <?php elseif ($eprStatus === 'pending'): ?>
                                            <span class="inline-flex items-center px-3 py-1.5 bg-slate-100 text-slate-500 rounded-lg text-xs font-semibold border border-slate-200">
                                                Awaiting HOD
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= url('dashboard/exam_officer/request_emergency_print.php?id=' . $qp['id']) ?>" class="min-h-[36px] inline-flex items-center px-3.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-800 rounded-lg text-xs font-bold border border-rose-200 transition">
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
