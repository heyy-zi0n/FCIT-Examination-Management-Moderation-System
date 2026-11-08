<?php
/**
 * Lecturer Papers Repository
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_LECTURER);

$pdo = getDb();
$userId = (int)$user['id'];

// Filter by status if requested
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$sql = "
    SELECT ep.*, c.course_code, c.course_title, d.code AS dept_code, al.level_name,
           pv.version_number, pv.question_file_name, pv.question_file_size,
           u_mod.first_name AS mod_first, u_mod.last_name AS mod_last, u_mod.title AS mod_title
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    LEFT JOIN users u_mod ON ep.moderator_id = u_mod.id
    WHERE ep.lecturer_id = :user_id
";
$params = ['user_id' => $userId];

if (!empty($statusFilter)) {
    $sql .= " AND ep.status = :status";
    $params['status'] = $statusFilter;
}
$sql .= " ORDER BY ep.updated_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$papers = $stmt->fetchAll();

$pageTitle = 'My Examination Papers — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';

$filterOptions = [
    ''          => 'All Papers',
    'Draft'     => 'Drafts',
    'Submitted' => 'Submitted',
    'Returned'  => 'Returned',
    'Moderated' => 'Moderated',
    'Approved'  => 'Approved',
    'Locked'    => 'Locked',
    'Printed'   => 'Printed',
];
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">My Examination Papers</h1>
                <p class="text-sm text-slate-600 mt-0.5">Repository of all examination questions and version history created by you</p>
            </div>

            <a href="<?= url('dashboard/lecturer/create_paper.php') ?>" class="min-h-[40px] px-4 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-sm shadow-sm transition inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Upload New Paper</span>
            </a>
        </div>

        <!-- Filter Bar -->
        <nav aria-label="Filter papers by status" class="bg-white rounded-xl p-3.5 shadow-sm border border-slate-200 mb-6 flex flex-wrap items-center gap-2 text-xs">
            <span class="font-semibold text-slate-600 mr-1">Status:</span>
            <?php foreach ($filterOptions as $val => $label):
                $isActive = ($statusFilter === $val);
                $href = $val === '' ? url('dashboard/lecturer/papers.php') : url('dashboard/lecturer/papers.php?status=' . urlencode($val));
            ?>
                <a href="<?= $href ?>" <?= $isActive ? 'aria-current="page"' : '' ?> class="min-h-[32px] inline-flex items-center px-3 py-1.5 rounded-lg border font-semibold transition <?= $isActive ? 'bg-lasu-navy text-white border-lasu-navy shadow-sm' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200' ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Papers Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="My examination papers" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Course Code &amp; Title</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Paper Code</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Version</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Assigned Moderator</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Status</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Last Updated</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($papers)): ?>
                            <tr>
                                <td colspan="7" class="py-12 px-4 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No examination papers found<?= !empty($statusFilter) ? ' for "' . htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') . '"' : '' ?></div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        <?php if (!empty($statusFilter)): ?>
                                            <a href="<?= url('dashboard/lecturer/papers.php') ?>" class="font-bold text-lasu-navy hover:underline">Clear status filter</a> or upload a new examination paper.
                                        <?php else: ?>
                                            <a href="<?= url('dashboard/lecturer/create_paper.php') ?>" class="font-bold text-lasu-navy hover:underline">Upload your first examination paper &rarr;</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($papers as $p): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($p['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($p['level_name'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-mono text-slate-600 text-xs whitespace-nowrap">
                                        <?= htmlspecialchars($p['paper_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 font-mono text-xs font-bold text-slate-700 tabular-nums">
                                            V<?= (int)($p['version_number'] ?: 1) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if (!empty($p['mod_first'])): ?>
                                            <div class="font-medium text-slate-800 text-sm"><?= htmlspecialchars(($p['mod_title'] ? $p['mod_title'] . ' ' : '') . $p['mod_first'] . ' ' . $p['mod_last'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-xs text-slate-500">Department Moderator</div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">Assigned at submission</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= renderStatusBadge($p['status']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-600 text-xs tabular-nums whitespace-nowrap">
                                        <?= formatDate($p['updated_at']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-right whitespace-nowrap">
                                        <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $p['id']) ?>" class="min-h-[34px] inline-flex items-center px-3.5 py-1.5 bg-lasu-navy hover:bg-lasu-navy-light text-white rounded-lg font-semibold transition text-xs">
                                            Inspect &rarr;
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
