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
// Enforces paired (department_id, level_id) match and Separation of Duties (lecturer_id != userId)
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
      AND ep.lecturer_id != :uid_sod
      AND (
          ep.moderator_id = :user_id1
          OR EXISTS (
              SELECT 1 FROM moderator_assignments ma
              WHERE ma.moderator_id = :user_id2
                AND ma.department_id = c.department_id
                AND ma.level_id = c.level_id
          )
      )
    ORDER BY ep.updated_at ASC
");
$stmtQueue->execute([
    'uid_sod' => $userId,
    'user_id1' => $userId,
    'user_id2' => $userId
]);
$pendingPapers = $stmtQueue->fetchAll();

// Moderation Metrics (Parameterized)
$pendingReviewCount = count($pendingPapers);

$stmtRev = $pdo->prepare("
    SELECT COUNT(DISTINCT paper_id) FROM paper_approvals
    WHERE approver_id = :uid AND stage = 'moderator' AND decision = 'returned'
");
$stmtRev->execute(['uid' => $userId]);
$revisionsRequestedCount = (int)$stmtRev->fetchColumn();

$stmtAppr = $pdo->prepare("
    SELECT COUNT(DISTINCT paper_id) FROM paper_approvals
    WHERE approver_id = :uid AND stage = 'moderator' AND decision = 'approved'
");
$stmtAppr->execute(['uid' => $userId]);
$recentlyApprovedCount = (int)$stmtAppr->fetchColumn();

$pageTitle = 'Moderation Queue — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-lasu-navy via-slate-900 to-lasu-navy-light rounded-2xl p-6 text-white shadow-md mb-8 border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Academic Quality &amp; Moderation Panel</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold">Welcome, <?= formatUserName($user) ?></h1>
                <p class="text-sm text-slate-300 mt-1">Review submitted examination questions for academic standards, clarity, and syllabus alignment.</p>
            </div>
            <div class="px-4 py-2 bg-white/10 text-lasu-gold border border-white/15 rounded-xl text-xs font-bold tabular-nums shrink-0">
                <?= $pendingReviewCount ?> <?= $pendingReviewCount === 1 ? 'Paper' : 'Papers' ?> Awaiting Review
            </div>
        </div>

        <!-- Assignments Scope Badges -->
        <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 mb-6">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5">Your Assigned Moderation Portfolios</h2>
            <div class="flex flex-wrap gap-2">
                <?php if (empty($assignments)): ?>
                    <span class="text-sm text-slate-500">No active moderator allocations found.</span>
                <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                        <span class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-800 inline-flex items-center gap-2">
                            <span class="font-extrabold text-lasu-navy font-mono"><?= htmlspecialchars($a['dept_code'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-slate-300" aria-hidden="true">&bull;</span>
                            <span><?= htmlspecialchars($a['level_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="rounded-xl p-5 border shadow-sm <?= $pendingReviewCount > 0 ? 'bg-amber-50/70 border-amber-300' : 'bg-white border-slate-200' ?>">
                <div class="text-xs font-semibold uppercase tracking-wider <?= $pendingReviewCount > 0 ? 'text-amber-800' : 'text-slate-500' ?>">Pending Reviews</div>
                <div class="text-3xl font-black mt-1.5 tabular-nums <?= $pendingReviewCount > 0 ? 'text-amber-900' : 'text-slate-900' ?>"><?= $pendingReviewCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Revisions Requested</div>
                <div class="text-3xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $revisionsRequestedCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Papers Approved</div>
                <div class="text-3xl font-black text-emerald-700 mt-1.5 tabular-nums"><?= $recentlyApprovedCount ?></div>
            </div>
        </div>

        <!-- Moderation Queue -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Active Moderation Queue</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Submitted papers awaiting your academic review and decision</p>
                </div>
                <span class="px-3 py-1 bg-slate-100 text-slate-800 font-bold rounded-full text-xs tabular-nums"><?= $pendingReviewCount ?> Pending</span>
            </div>

            <div tabindex="0" role="region" aria-label="Active moderation queue" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course / Code</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Course Lecturer</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Version</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Submitted On</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Review Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($pendingPapers)): ?>
                            <tr>
                                <td colspan="5" class="py-12 px-5 text-center">
                                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2.5">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="text-sm font-semibold text-slate-800">Moderation Queue is Clear</div>
                                    <div class="text-xs text-slate-500 mt-1">No submitted papers are currently awaiting moderation in your assigned portfolios.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingPapers as $p): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($p['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($p['course_title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($p['level_name'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-slate-800 text-sm"><?= htmlspecialchars(($p['lec_title'] ? $p['lec_title'] . ' ' : '') . $p['lec_first'] . ' ' . $p['lec_last'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($p['dept_code'], ENT_QUOTES, 'UTF-8') ?> Lecturer</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-blue-800 font-mono text-xs font-bold tabular-nums">
                                            V<?= (int)($p['version_number'] ?: 1) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="text-xs text-slate-600 tabular-nums mb-0.5">
                                            <?= formatDate($p['version_created_at'] ?? $p['updated_at']) ?>
                                        </div>
                                        <div class="text-xs font-semibold text-amber-700">
                                            Pending <?= htmlspecialchars(getPaperTimeInStage($p['updated_at']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <a href="<?= url('dashboard/moderator/review_paper.php?id=' . $p['id']) ?>" class="min-h-[36px] px-4 py-2 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl shadow-sm transition text-xs inline-flex items-center gap-1.5">
                                            <span>Review &amp; Moderate</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
