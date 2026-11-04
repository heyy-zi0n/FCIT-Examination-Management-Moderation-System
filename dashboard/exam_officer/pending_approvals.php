<?php
/**
 * Exam Officer Pending Approvals
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$pdo = getDb();
$deptId = !empty($user['department_id']) ? (int)$user['department_id'] : null;

$whereDept = $deptId !== null ? "c.department_id = :dept_id AND " : "";
$stmt = $pdo->prepare("
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
    WHERE {$whereDept} ep.status = 'Moderated' AND ep.eo_status = 'pending'
    ORDER BY ep.updated_at ASC
");
$params = $deptId !== null ? ['dept_id' => $deptId] : [];
$stmt->execute($params);
$papers = $stmt->fetchAll();

$pageTitle = 'Faculty Moderated Papers — Exam Officer Portal — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Faculty Moderation Endorsement Queue</h1>
            <p class="text-xs text-slate-500">Review moderated examination questions across all departments and execute Exam Officer sign-off</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Department</th>
                            <th class="py-3.5 px-4 font-semibold">Lecturer</th>
                            <th class="py-3.5 px-4 font-semibold">Moderator</th>
                            <th class="py-3.5 px-4 font-semibold">HOD Endorsement</th>
                            <th class="py-3.5 px-4 font-semibold">EO Endorsement</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($papers)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    No papers are currently awaiting Exam Officer endorsement.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($papers as $p): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($p['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title']) ?> (<?= htmlspecialchars($p['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-slate-800 dark:text-slate-200">
                                        <?= htmlspecialchars($p['dept_name']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= htmlspecialchars($p['lec_first'] . ' ' . $p['lec_last']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= htmlspecialchars(($p['mod_first'] ?? '—') . ' ' . ($p['mod_last'] ?? '')) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-1 rounded-full font-bold text-[10px] <?= $p['hod_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                            HOD: <?= ucfirst($p['hod_status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-1 rounded-full font-bold text-[10px] <?= $p['eo_status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                            EO: <?= ucfirst($p['eo_status']) ?>
                                        </span>
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

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
