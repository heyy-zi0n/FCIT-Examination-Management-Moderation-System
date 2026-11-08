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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Moderation History &amp; Audit</h1>
            <p class="text-sm text-slate-600 mt-0.5">Record of all examination papers vetted and reviewed by you</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Moderation decision history" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Title</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Lecturer</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Your Decision</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Current Paper Status</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Date Reviewed</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="6" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No moderation decisions recorded yet</div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        Check your <a href="<?= url('dashboard/moderator/index.php') ?>" class="font-bold text-lasu-navy hover:underline">Moderation Queue</a> for submitted papers awaiting review.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($h['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($h['course_title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($h['level_name'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-slate-800">
                                        <?= htmlspecialchars($h['lec_first'] . ' ' . $h['lec_last'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full font-bold text-xs border <?= $h['my_decision'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
                                            <?= ucfirst(htmlspecialchars($h['my_decision'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= renderStatusBadge($h['status']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-600 text-xs tabular-nums whitespace-nowrap">
                                        <?= formatDate($h['decided_at']) ?>
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <a href="<?= url('dashboard/moderator/review_paper.php?id=' . $h['id']) ?>" class="min-h-[34px] inline-flex items-center px-3.5 py-1.5 bg-lasu-navy text-white hover:bg-lasu-navy-light rounded-lg text-xs font-semibold transition">
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
