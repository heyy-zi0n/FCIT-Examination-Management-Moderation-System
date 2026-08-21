<?php
/**
 * HOD Pending Approvals List
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);

$pdo = getDb();
$deptId = (int)$user['department_id'];

$stmt = $pdo->prepare("
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
    WHERE c.department_id = :dept_id AND ep.status = 'Moderated'
    ORDER BY ep.updated_at ASC
");
$stmt->execute(['dept_id' => $deptId]);
$papers = $stmt->fetchAll();

$pageTitle = 'Pending HOD Approvals — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Moderated Papers Pending HOD Sign-Off</h1>
            <p class="text-xs text-slate-500">Department of <?= htmlspecialchars($user['department_name']) ?> • Review and endorse moderated examination questions</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Course Lecturer</th>
                            <th class="py-3.5 px-4 font-semibold">Moderator</th>
                            <th class="py-3.5 px-4 font-semibold">Version</th>
                            <th class="py-3.5 px-4 font-semibold">HOD Decision</th>
                            <th class="py-3.5 px-4 font-semibold">EO Decision</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($papers)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    No moderated papers are currently waiting for your review.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($papers as $p): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($p['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title']) ?> (<?= htmlspecialchars($p['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-medium">
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
                                        <span class="px-2.5 py-1 rounded-full font-bold text-[11px] <?= $p['hod_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                            <?= ucfirst($p['hod_status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-1 rounded-full font-bold text-[11px] <?= $p['eo_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                                            <?= ucfirst($p['eo_status']) ?>
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
