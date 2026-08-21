<?php
/**
 * Faculty Examination Lifecycle & Compliance Reports (Administrator)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

// Department-wise Examination Progress Matrix
$deptStats = $pdo->query("
    SELECT d.id, d.name AS dept_name, d.code AS dept_code,
           COUNT(DISTINCT c.id) AS total_courses,
           COUNT(DISTINCT ep.id) AS total_papers,
           COUNT(DISTINCT CASE WHEN ep.status = 'Draft' THEN ep.id END) AS draft_count,
           COUNT(DISTINCT CASE WHEN ep.status = 'Submitted' THEN ep.id END) AS submitted_count,
           COUNT(DISTINCT CASE WHEN ep.status = 'Returned' THEN ep.id END) AS returned_count,
           COUNT(DISTINCT CASE WHEN ep.status = 'Moderated' THEN ep.id END) AS moderated_count,
           COUNT(DISTINCT CASE WHEN ep.status IN ('Approved', 'Locked', 'Queued', 'Printed') THEN ep.id END) AS approved_count,
           COUNT(DISTINCT CASE WHEN ep.status = 'Printed' THEN ep.id END) AS printed_count
    FROM departments d
    LEFT JOIN courses c ON d.id = c.department_id
    LEFT JOIN examination_papers ep ON c.id = ep.course_id
    GROUP BY d.id, d.name, d.code
    ORDER BY d.code ASC
")->fetchAll();

// Overall Status Summary
$statusSummary = $pdo->query("
    SELECT status, COUNT(*) AS count 
    FROM examination_papers 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Faculty Compliance & Lifecycle Reports — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Faculty Examination Progress & Governance Report</h1>
                <p class="text-xs text-slate-500">Departmental breakdown of examination submission readiness, moderation velocity, and print production</p>
            </div>
            
            <button type="button" onclick="window.print()" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2 no-print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print Executive Report</span>
            </button>
        </div>

        <!-- Departmental Breakdown Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Departmental Examination Readiness Matrix</h2>
                <span class="text-xs text-slate-400 font-mono">Current Session: 2025/2026</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Department</th>
                            <th class="py-3.5 px-4 font-semibold text-center">Courses</th>
                            <th class="py-3.5 px-4 font-semibold text-center">Submitted</th>
                            <th class="py-3.5 px-4 font-semibold text-center">In Moderation</th>
                            <th class="py-3.5 px-4 font-semibold text-center">Returned</th>
                            <th class="py-3.5 px-4 font-semibold text-center">Dual Approved & Sealed</th>
                            <th class="py-3.5 px-4 font-semibold text-center">Printed</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Readiness %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php foreach ($deptStats as $ds): 
                            $readiness = $ds['total_courses'] > 0 ? round(($ds['approved_count'] / $ds['total_courses']) * 100) : 0;
                        ?>
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                                    <span><?= htmlspecialchars($ds['dept_name']) ?></span>
                                    <span class="font-mono text-blue-600 dark:text-blue-400 font-bold ml-1">(<?= htmlspecialchars($ds['dept_code']) ?>)</span>
                                </td>
                                <td class="py-4 px-4 text-center font-bold font-mono"><?= $ds['total_courses'] ?></td>
                                <td class="py-4 px-4 text-center font-mono text-blue-600 font-bold"><?= $ds['submitted_count'] ?></td>
                                <td class="py-4 px-4 text-center font-mono text-amber-600 font-bold"><?= $ds['moderated_count'] ?></td>
                                <td class="py-4 px-4 text-center font-mono text-rose-600 font-bold"><?= $ds['returned_count'] ?></td>
                                <td class="py-4 px-4 text-center font-mono text-purple-600 font-bold"><?= $ds['approved_count'] ?></td>
                                <td class="py-4 px-4 text-center font-mono text-teal-600 font-bold"><?= $ds['printed_count'] ?></td>
                                <td class="py-4 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 bg-slate-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= $readiness ?>%"></div>
                                        </div>
                                        <span class="font-bold text-slate-900 dark:text-white w-8 text-right"><?= $readiness ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
