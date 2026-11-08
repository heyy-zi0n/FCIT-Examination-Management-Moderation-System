<?php
/**
 * Faculty Examination Lifecycle & Compliance Reports (Administrator)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

$activeSessionName = (string)($pdo->query("SELECT session_name FROM academic_sessions WHERE is_current = 1 LIMIT 1")->fetchColumn() ?: '2025/2026');

// Department-wise Examination Progress Matrix
$deptStats = $pdo->query("
    SELECT d.id, d.name AS dept_name, d.code AS dept_code,
           COUNT(DISTINCT c.id) AS total_courses,
           COUNT(DISTINCT ep.id) AS total_papers,
           COUNT(DISTINCT CASE WHEN ep.status = 'Draft' THEN ep.id END) AS draft_count,
           COUNT(DISTINCT CASE WHEN ep.status IN ('Submitted', 'Returned', 'Moderated', 'Approved', 'Locked', 'Queued', 'Printed') THEN ep.id END) AS submitted_or_beyond_count,
           COUNT(DISTINCT CASE WHEN ep.status = 'Submitted' THEN ep.id END) AS submitted_count,
           COUNT(DISTINCT CASE WHEN ep.status = 'Returned' THEN ep.id END) AS returned_count,
           COUNT(DISTINCT CASE WHEN ep.status = 'Moderated' THEN ep.id END) AS moderated_count,
           COUNT(DISTINCT CASE WHEN ep.status IN ('Approved', 'Locked', 'Queued') THEN ep.id END) AS approved_count,
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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Faculty Examination Progress &amp; Governance Report</h1>
                <p class="text-sm text-slate-600 mt-0.5">Departmental breakdown of submission readiness, moderation velocity, and print production</p>
            </div>

            <button type="button" onclick="window.print()" class="min-h-[40px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2 no-print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print Executive Report</span>
            </button>
        </div>

        <!-- Departmental Breakdown Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                <h2 class="text-base font-bold text-slate-900">Departmental Examination Readiness Matrix</h2>
                <span class="text-xs text-slate-600 font-semibold bg-slate-100 px-3 py-1 rounded-full tabular-nums">Session: <?= htmlspecialchars($activeSessionName, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div tabindex="0" role="region" aria-label="Departmental examination readiness matrix" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Department</th>
                            <th scope="col" class="py-3.5 px-3 font-semibold text-center">Courses</th>
                            <th scope="col" class="py-3.5 px-3 font-semibold text-center">Missing</th>
                            <th scope="col" class="py-3.5 px-3 font-semibold text-center">Submitted</th>
                            <th scope="col" class="py-3.5 px-3 font-semibold text-center">Moderated</th>
                            <th scope="col" class="py-3.5 px-3 font-semibold text-center">Returned</th>
                            <th scope="col" class="py-3.5 px-3 font-semibold text-center">Sealed</th>
                            <th scope="col" class="py-3.5 px-3 font-semibold text-center">Printed</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Readiness</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($deptStats as $ds):
                            $readiness = $ds['total_courses'] > 0 ? round(($ds['approved_count'] / $ds['total_courses']) * 100) : 0;
                            $missing = max(0, (int)$ds['total_courses'] - (int)$ds['submitted_or_beyond_count']);
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-5 font-bold text-slate-900">
                                    <span><?= htmlspecialchars($ds['dept_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="font-mono text-lasu-navy text-xs font-bold ml-1">(<?= htmlspecialchars($ds['dept_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                </td>
                                <td class="py-4 px-3 text-center font-bold font-mono tabular-nums text-slate-900"><?= (int)$ds['total_courses'] ?></td>
                                <td class="py-4 px-3 text-center font-mono tabular-nums font-bold <?= $missing > 0 ? 'text-rose-700 bg-rose-50/50' : 'text-slate-400 bg-slate-50/50' ?>"><?= $missing ?></td>
                                <td class="py-4 px-3 text-center font-mono tabular-nums text-slate-800 font-semibold"><?= (int)$ds['submitted_count'] ?></td>
                                <td class="py-4 px-3 text-center font-mono tabular-nums text-amber-800 font-semibold"><?= (int)$ds['moderated_count'] ?></td>
                                <td class="py-4 px-3 text-center font-mono tabular-nums <?= (int)$ds['returned_count'] > 0 ? 'text-rose-700 font-bold' : 'text-slate-500' ?>"><?= (int)$ds['returned_count'] ?></td>
                                <td class="py-4 px-3 text-center font-mono tabular-nums text-purple-800 font-semibold"><?= (int)$ds['approved_count'] ?></td>
                                <td class="py-4 px-3 text-center font-mono tabular-nums text-emerald-700 font-semibold"><?= (int)$ds['printed_count'] ?></td>
                                <td class="py-4 px-5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2.5">
                                        <div class="w-20 bg-slate-200 rounded-full h-2 overflow-hidden" role="progressbar" aria-valuenow="<?= (int)$readiness ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= htmlspecialchars($ds['dept_name'], ENT_QUOTES, 'UTF-8') ?> readiness">
                                            <div class="bg-emerald-600 h-2 rounded-full transition-all" style="width: <?= (int)$readiness ?>%"></div>
                                        </div>
                                        <span class="font-bold text-slate-900 tabular-nums w-9 text-right text-xs"><?= (int)$readiness ?>%</span>
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
