<?php
/**
 * Moderator Dashboard & Moderation Queue
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_MODERATOR);

$pdo = getDb();
$userId = (int)$user['id'];

// Get moderator's assigned departments and levels
$stmtAssigned = $pdo->prepare("
    SELECT ma.*, d.name AS dept_name, d.code AS dept_code, al.level_name, al.level_code
    FROM moderator_assignments ma
    JOIN departments d ON ma.department_id = d.id
    JOIN academic_levels al ON ma.level_id = al.id
    WHERE ma.moderator_id = :user_id
");
$stmtAssigned->execute(['user_id' => $userId]);
$assignments = $stmtAssigned->fetchAll();

// Fetch Pending Papers in Moderation Queue (status = 'Submitted')
$stmtQueue = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, d.code AS dept_code, al.level_name,
           u_lec.first_name AS lec_first, u_lec.last_name AS lec_last, u_lec.title AS lec_title,
           pv.version_number, pv.question_file_name, pv.created_at AS version_created_at
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN users u_lec ON ep.lecturer_id = u_lec.id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE ep.status = 'Submitted'
      AND (
          ep.moderator_id = :user_id1
          OR (c.department_id IN (SELECT department_id FROM moderator_assignments WHERE moderator_id = :user_id2)
              AND c.level_id IN (SELECT level_id FROM moderator_assignments WHERE moderator_id = :user_id3))
      )
    ORDER BY ep.updated_at ASC
");
$stmtQueue->execute([
    'user_id1' => $userId,
    'user_id2' => $userId,
    'user_id3' => $userId
]);
$pendingPapers = $stmtQueue->fetchAll();

// Moderation Metrics
$pendingReviewCount = count($pendingPapers);
$revisionsRequestedCount = (int)$pdo->query("
    SELECT COUNT(DISTINCT paper_id) FROM paper_approvals 
    WHERE approver_id = {$userId} AND stage = 'moderator' AND decision = 'returned'
")->fetchColumn();
$recentlyApprovedCount = (int)$pdo->query("
    SELECT COUNT(DISTINCT paper_id) FROM paper_approvals 
    WHERE approver_id = {$userId} AND stage = 'moderator' AND decision = 'approved'
")->fetchColumn();

$pageTitle = 'Moderation Queue — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-amber-950 via-slate-900 to-lasu-navy rounded-2xl p-6 text-white shadow-lg mb-8 border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Academic Quality & Moderation Panel</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold">Welcome, <?= formatUserName($user) ?></h1>
                <p class="text-xs text-slate-300 mt-1">Manage examination question lifecycle, review submissions, and track moderation progress.</p>
            </div>
            <div class="px-4 py-2 bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-xl text-xs font-semibold">
                <?= count($pendingPapers) ?> Paper(s) Awaiting Your Review
            </div>
        </div>

        <!-- Assignments Scope Badges -->
        <div class="bg-white dark:bg-slate-900 rounded-xl p-4 shadow-sm border border-slate-200 dark:border-slate-800 mb-8">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Your Assigned Moderation Portfolios:</h2>
            <div class="flex flex-wrap gap-2">
                <?php if (empty($assignments)): ?>
                    <span class="text-xs text-slate-400">No active moderator allocations found.</span>
                <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                        <span class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <span class="font-bold text-amber-600 dark:text-amber-400"><?= htmlspecialchars($a['dept_code']) ?></span>
                            <span>•</span>
                            <span><?= htmlspecialchars($a['level_name']) ?></span>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-800 shadow-sm">
                <div class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Pending Reviews</div>
                <div class="text-3xl font-black text-blue-700 dark:text-blue-400 mt-2"><?= $pendingReviewCount ?></div>
            </div>
            <div class="bg-rose-50 dark:bg-rose-900/20 rounded-xl p-5 border border-rose-200 dark:border-rose-800 shadow-sm">
                <div class="text-xs font-semibold text-rose-600 uppercase tracking-wider">Revisions Requested</div>
                <div class="text-3xl font-black text-rose-700 dark:text-rose-400 mt-2"><?= $revisionsRequestedCount ?></div>
            </div>
            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-5 border border-emerald-200 dark:border-emerald-800 shadow-sm">
                <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Recently Approved</div>
                <div class="text-3xl font-black text-emerald-700 dark:text-emerald-400 mt-2"><?= $recentlyApprovedCount ?></div>
            </div>
        </div>

        <!-- Moderation Queue -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Active Moderation Queue</h2>
                    <p class="text-xs text-slate-500">Submitted papers awaiting your review and decision</p>
                </div>
                <span class="px-2.5 py-1 bg-blue-100 text-blue-800 font-bold rounded-full text-xs"><?= count($pendingPapers) ?> Pending</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course / Code</th>
                            <th class="py-3.5 px-4 font-semibold">Course Lecturer</th>
                            <th class="py-3.5 px-4 font-semibold">Version</th>
                            <th class="py-3.5 px-4 font-semibold">Submitted On</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Review Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($pendingPapers)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="font-medium text-slate-700 dark:text-slate-300">Queue is Clear!</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">No submitted papers are currently awaiting moderation.</div>
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
                                        <div class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars(($p['lec_title'] ? $p['lec_title'] . ' ' : '') . $p['lec_first'] . ' ' . $p['lec_last']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($p['dept_code']) ?> Lecturer</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-mono text-xs font-bold">
                                            V<?= $p['version_number'] ?: 1 ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-slate-500 text-[11px]">
                                        <?= formatDate($p['version_created_at'] ?? $p['updated_at']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <a href="<?= url('dashboard/moderator/review_paper.php?id=' . $p['id']) ?>" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-xl shadow transition text-xs inline-flex items-center gap-1.5">
                                            <span>Review & Moderate</span>
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
