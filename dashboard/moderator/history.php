<?php
/**
 * Moderator Reviewed Papers History
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_MODERATOR);

$pdo = getDb();
$userId = (int)$user['id'];

$stmt = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, d.code AS dept_code, al.level_name,
           u_lec.first_name AS lec_first, u_lec.last_name AS lec_last,
           pa.decision AS my_decision, pa.created_at AS decided_at
    FROM paper_approvals pa
    JOIN examination_papers ep ON pa.paper_id = ep.id
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN users u_lec ON ep.lecturer_id = u_lec.id
    WHERE pa.approver_id = :user_id AND pa.stage = 'moderator'
    ORDER BY pa.created_at DESC
");
$stmt->execute(['user_id' => $userId]);
$history = $stmt->fetchAll();

$pageTitle = 'Moderation History — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Moderation History & Audit</h1>
            <p class="text-xs text-slate-500">Record of all examination papers vetted and reviewed by you</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Lecturer</th>
                            <th class="py-3.5 px-4 font-semibold">Your Decision</th>
                            <th class="py-3.5 px-4 font-semibold">Current Paper Status</th>
                            <th class="py-3.5 px-4 font-semibold">Date Reviewed</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    No moderation decisions recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono"><?= htmlspecialchars($h['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($h['course_title']) ?> (<?= htmlspecialchars($h['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= htmlspecialchars($h['lec_first'] . ' ' . $h['lec_last']) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-1 rounded-full font-bold text-[11px] <?= $h['my_decision'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                            <?= ucfirst($h['my_decision']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= renderStatusBadge($h['status']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-500 text-[11px]">
                                        <?= formatDate($h['decided_at']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <a href="<?= url('dashboard/moderator/review_paper.php?id=' . $h['id']) ?>" class="px-3 py-1.5 bg-lasu-navy text-white hover:bg-lasu-navy-light rounded-lg text-xs font-medium transition">
                                            View Paper
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
