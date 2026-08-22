<?php
/**
 * Head of Department (HOD) Dashboard Overview
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);

$pdo = getDb();
$deptId = (int)$user['department_id'];

// Departmental Metrics
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE department_id = {$deptId}")->fetchColumn();
$totalSchedules = (int)$pdo->query("SELECT COUNT(*) FROM exam_schedules es JOIN courses c ON es.course_id = c.id WHERE c.department_id = {$deptId}")->fetchColumn();
$awaitingModerationCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status = 'Submitted'")->fetchColumn();
$revisionRequiredCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status = 'Returned'")->fetchColumn();
$pendingHodCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status = 'Moderated' AND ep.hod_status = 'pending'")->fetchColumn();
$pendingEoCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.eo_status = 'pending' AND ep.status IN ('Moderated', 'Approved')")->fetchColumn();
$readyForLockdownCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status = 'Approved'")->fetchColumn();
$lockedCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status = 'Locked'")->fetchColumn();
$readyForPrintingCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status = 'Queued'")->fetchColumn();
$printedCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = {$deptId} AND ep.status = 'Printed'")->fetchColumn();

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

$pageTitle = "HOD Dashboard — Department of {$user['department_name']} — LASU FCIT CMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-950 via-slate-900 to-lasu-navy rounded-2xl p-6 text-white shadow-lg mb-8 border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Departmental Examination Readiness Portal</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold"><?= formatUserName($user) ?></h1>
                <p class="text-xs text-slate-300 mt-1">Department: <span class="font-semibold text-white"><?= htmlspecialchars($user['department_name'] ?? 'Computing') ?></span></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="/FOCITECMS/dashboard/hod/pending_approvals.php" class="px-4 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                    <span>Pending Approvals</span>
                    <span class="w-5 h-5 bg-lasu-navy text-white rounded-full flex items-center justify-center text-[10px]"><?= $pendingHodCount ?></span>
                </a>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-slate-900 rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase">Department Courses</div>
                <div class="text-3xl font-black text-slate-900 dark:text-white mt-1"><?= $totalCourses ?></div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-900/50 shadow-sm">
                <div class="text-xs font-semibold text-blue-600 uppercase">Awaiting Moderation</div>
                <div class="text-3xl font-black text-blue-600 mt-1"><?= $awaitingModerationCount ?></div>
            </div>
            <div class="bg-rose-50 dark:bg-rose-900/20 rounded-xl p-5 border border-rose-200 dark:border-rose-900/50 shadow-sm">
                <div class="text-xs font-semibold text-rose-600 uppercase">Revision Required</div>
                <div class="text-3xl font-black text-rose-600 mt-1"><?= $revisionRequiredCount ?></div>
            </div>
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-5 border border-amber-200 dark:border-amber-900/50 shadow-sm">
                <div class="text-xs font-semibold text-amber-600 uppercase">Pending HOD Sign-Off</div>
                <div class="text-3xl font-black text-amber-600 mt-1"><?= $pendingHodCount ?></div>
            </div>
            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-5 border border-indigo-200 dark:border-indigo-900/50 shadow-sm">
                <div class="text-xs font-semibold text-indigo-600 uppercase">Pending EO Sign-Off</div>
                <div class="text-3xl font-black text-indigo-600 mt-1"><?= $pendingEoCount ?></div>
            </div>
            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-5 border border-emerald-200 dark:border-emerald-900/50 shadow-sm">
                <div class="text-xs font-semibold text-emerald-600 uppercase">Ready for Lockdown</div>
                <div class="text-3xl font-black text-emerald-600 mt-1"><?= $readyForLockdownCount ?></div>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-5 border border-purple-200 dark:border-purple-900/50 shadow-sm">
                <div class="text-xs font-semibold text-purple-600 uppercase">Locked / Ready to Print</div>
                <div class="text-3xl font-black text-purple-600 mt-1"><?= $lockedCount + $readyForPrintingCount ?></div>
            </div>
            <div class="bg-slate-100 dark:bg-slate-800 rounded-xl p-5 border border-slate-300 dark:border-slate-700 shadow-sm">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Printed Papers</div>
                <div class="text-3xl font-black text-slate-700 dark:text-slate-200 mt-1"><?= $printedCount ?></div>
            </div>
        </div>

        <!-- Pending Papers Awaiting HOD Sign-off -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Moderated Papers Awaiting HOD Approval</h2>
                    <p class="text-xs text-slate-500">Papers vetted by departmental moderators requiring your official approval</p>
                </div>
                <a href="/FOCITECMS/dashboard/hod/pending_approvals.php" class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline">View All &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Lecturer</th>
                            <th class="py-3.5 px-4 font-semibold">Moderator</th>
                            <th class="py-3.5 px-4 font-semibold">Version</th>
                            <th class="py-3.5 px-4 font-semibold">EO Approval State</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($pendingPapers)): ?>
                            <tr>
                                <td colspan="6" class="py-10 text-center text-slate-400">
                                    No moderated papers are currently pending your approval.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingPapers as $p): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($p['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title']) ?> (<?= htmlspecialchars($p['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= htmlspecialchars($p['lec_first'] . ' ' . $p['lec_last']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= htmlspecialchars(($p['mod_first'] ?? '—') . ' ' . ($p['mod_last'] ?? '')) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-mono text-xs font-bold">
                                            V<?= $p['version_number'] ?: 1 ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $p['eo_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                                            EO: <?= ucfirst($p['eo_status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <a href="/FOCITECMS/dashboard/hod/review_paper.php?id=<?= $p['id'] ?>" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow transition text-xs inline-flex items-center gap-1.5">
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

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
