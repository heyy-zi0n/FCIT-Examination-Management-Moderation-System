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
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My Examination Papers</h1>
                <p class="text-xs text-slate-500">Repository of all examination questions and version history created by you</p>
            </div>
            
            <a href="/FOCITECMS/dashboard/lecturer/create_paper.php" class="px-4 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Paper</span>
            </a>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white dark:bg-slate-900 rounded-xl p-4 shadow-sm border border-slate-200 dark:border-slate-800 mb-6 flex flex-wrap items-center gap-2 text-xs">
            <span class="font-semibold text-slate-500 mr-2">Filter by Status:</span>
            <a href="/FOCITECMS/dashboard/lecturer/papers.php" class="px-3 py-1 rounded-full border transition <?= empty($statusFilter) ? 'bg-lasu-navy text-white border-lasu-navy' : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700' ?>">All Papers</a>
            <a href="/FOCITECMS/dashboard/lecturer/papers.php?status=Draft" class="px-3 py-1 rounded-full border transition <?= $statusFilter === 'Draft' ? 'bg-slate-800 text-white' : 'bg-slate-50 dark:bg-slate-800 text-slate-600 border-slate-200 dark:border-slate-700' ?>">Drafts</a>
            <a href="/FOCITECMS/dashboard/lecturer/papers.php?status=Submitted" class="px-3 py-1 rounded-full border transition <?= $statusFilter === 'Submitted' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 border-blue-200' ?>">Submitted</a>
            <a href="/FOCITECMS/dashboard/lecturer/papers.php?status=Returned" class="px-3 py-1 rounded-full border transition <?= $statusFilter === 'Returned' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-700 border-rose-200' ?>">Returned</a>
            <a href="/FOCITECMS/dashboard/lecturer/papers.php?status=Moderated" class="px-3 py-1 rounded-full border transition <?= $statusFilter === 'Moderated' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 border-amber-200' ?>">Moderated</a>
            <a href="/FOCITECMS/dashboard/lecturer/papers.php?status=Approved" class="px-3 py-1 rounded-full border transition <?= $statusFilter === 'Approved' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 border-emerald-200' ?>">Approved</a>
        </div>

        <!-- Papers Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Paper ID / Code</th>
                            <th class="py-3.5 px-4 font-semibold">Version</th>
                            <th class="py-3.5 px-4 font-semibold">Assigned Moderator</th>
                            <th class="py-3.5 px-4 font-semibold">Status</th>
                            <th class="py-3.5 px-4 font-semibold">Last Updated</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($papers)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    No examination papers found matching this criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($papers as $p): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($p['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title']) ?> (<?= htmlspecialchars($p['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-mono text-slate-600 dark:text-slate-400 text-[11px]">
                                        <?= htmlspecialchars($p['paper_code']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-xs font-bold text-slate-700 dark:text-slate-300">
                                            V<?= $p['version_number'] ?: 1 ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if (!empty($p['mod_first'])): ?>
                                            <div class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars(($p['mod_title'] ? $p['mod_title'] . ' ' : '') . $p['mod_first'] . ' ' . $p['mod_last']) ?></div>
                                            <div class="text-[10px] text-slate-400">Department Moderator</div>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= renderStatusBadge($p['status']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-500 text-[11px]">
                                        <?= formatDate($p['updated_at']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <a href="/FOCITECMS/dashboard/lecturer/view_paper.php?id=<?= $p['id'] ?>" class="px-3 py-1.5 bg-lasu-navy hover:bg-lasu-navy-light text-white rounded-lg font-medium transition text-xs">
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
