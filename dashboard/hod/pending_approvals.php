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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Moderated Papers Pending HOD Sign-Off</h1>
            <p class="text-sm text-slate-600 mt-0.5">Department of <?= htmlspecialchars($user['department_name'] ?? 'Computing', ENT_QUOTES, 'UTF-8') ?> &bull; Review and endorse moderated examination questions</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Moderated papers pending HOD sign-off" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Title</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Course Lecturer</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Moderator</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Version</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">HOD Decision</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">EO Decision</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($papers)): ?>
                            <tr>
                                <td colspan="7" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No moderated papers awaiting sign-off</div>
                                    <div class="text-xs text-slate-500 mt-1">All moderated examination papers in your department have been processed.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($papers as $p): ?>
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
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-blue-800 font-mono text-xs font-bold tabular-nums">
                                            V<?= (int)($p['version_number'] ?: 1) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full font-bold text-xs border <?= $p['hod_status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200' ?>">
                                            <?= ucfirst(htmlspecialchars($p['hod_status'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full font-bold text-xs border <?= $p['eo_status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200' ?>">
                                            <?= ucfirst(htmlspecialchars($p['eo_status'], ENT_QUOTES, 'UTF-8')) ?>
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
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
