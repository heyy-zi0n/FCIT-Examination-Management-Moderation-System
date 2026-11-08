<?php
/**
 * Lecturer Dashboard Overview
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_LECTURER);

$pdo = getDb();
$userId = (int)$user['id'];

// Statistics (Consolidated & Parameterized)
$stmtAssignedCount = $pdo->prepare("SELECT COUNT(*) FROM lecturer_course_assignments WHERE lecturer_id = :uid");
$stmtAssignedCount->execute(['uid' => $userId]);
$totalAssignedCourses = (int)$stmtAssignedCount->fetchColumn();

$stmtStats = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) AS awaiting_mod_count,
        SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) AS returned_count,
        SUM(CASE WHEN status IN ('Moderated', 'Approved') THEN 1 ELSE 0 END) AS approved_lockdown_count,
        SUM(CASE WHEN status IN ('Locked', 'Queued') THEN 1 ELSE 0 END) AS locked_count,
        SUM(CASE WHEN status = 'Printed' THEN 1 ELSE 0 END) AS printed_count
    FROM examination_papers
    WHERE lecturer_id = :uid
");
$stmtStats->execute(['uid' => $userId]);
$stats = $stmtStats->fetch() ?: [];

$draftCount = (int)($stats['draft_count'] ?? 0);
$awaitingModerationCount = (int)($stats['awaiting_mod_count'] ?? 0);
$returnedCount = (int)($stats['returned_count'] ?? 0);
$approvedLockdownCount = (int)($stats['approved_lockdown_count'] ?? 0);
$lockedCount = (int)($stats['locked_count'] ?? 0);
$printedCount = (int)($stats['printed_count'] ?? 0);

// Fetch Action Required Papers
$stmtAction = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, pv.version_number
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE ep.lecturer_id = :user_id AND ep.status = 'Returned'
    ORDER BY ep.updated_at DESC
");
$stmtAction->execute(['user_id' => $userId]);
$actionPapers = $stmtAction->fetchAll();

// Fetch Assigned Courses
$stmtCourses = $pdo->prepare("
    SELECT c.*, d.code AS dept_code, al.level_name, s.name AS semester_name,
           (SELECT COUNT(*) FROM examination_papers ep WHERE ep.course_id = c.id AND ep.lecturer_id = :uid1) AS paper_count
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN semesters s ON c.semester_id = s.id
    WHERE lca.lecturer_id = :uid2
");
$stmtCourses->execute(['uid1' => $userId, 'uid2' => $userId]);
$assignedCourses = $stmtCourses->fetchAll();

// Fetch Recent Submissions
$stmtPapers = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, d.code AS dept_code, pv.version_number
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE ep.lecturer_id = :user_id
    ORDER BY ep.updated_at DESC
    LIMIT 6
");
$stmtPapers->execute(['user_id' => $userId]);
$recentPapers = $stmtPapers->fetchAll();

$pageTitle = 'Lecturer Dashboard — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-lasu-navy via-slate-900 to-lasu-navy-light rounded-2xl p-6 text-white shadow-md mb-8 border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Academic Staff Portal</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold">Welcome, <?= formatUserName($user) ?></h1>
                <p class="text-sm text-slate-300 mt-1">Manage your examination question submissions, review moderation feedback, and track lifecycle progress.</p>
            </div>
            <a href="<?= url('dashboard/lecturer/create_paper.php') ?>" class="min-h-[42px] px-5 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl shadow-md transition text-sm inline-flex items-center gap-2 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Upload New Exam Paper</span>
            </a>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Drafts</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $draftCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Awaiting Mod.</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $awaitingModerationCount ?></div>
            </div>
            <div class="rounded-xl p-4 border shadow-sm <?= $returnedCount > 0 ? 'bg-rose-50/70 border-rose-300' : 'bg-white border-slate-200' ?>">
                <div class="text-xs font-semibold uppercase tracking-wide <?= $returnedCount > 0 ? 'text-rose-800' : 'text-slate-500' ?>">Returned</div>
                <div class="text-2xl font-black mt-1.5 tabular-nums <?= $returnedCount > 0 ? 'text-rose-700' : 'text-slate-900' ?>"><?= $returnedCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">In Sign-Off</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $approvedLockdownCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Blind Locked</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $lockedCount ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Printed</div>
                <div class="text-2xl font-black text-emerald-700 mt-1.5 tabular-nums"><?= $printedCount ?></div>
            </div>
        </div>

        <?php if (!empty($actionPapers)): ?>
        <!-- Action Required Section -->
        <section aria-label="Papers returned for correction" class="mb-8 bg-rose-50/80 border border-rose-200 rounded-2xl p-5 shadow-sm">
            <h2 class="text-sm font-bold text-rose-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Action Required: Papers Returned for Correction</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($actionPapers as $p): ?>
                    <div class="bg-white border border-rose-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-1.5">
                                <span class="font-bold text-rose-950 font-mono text-sm"><?= htmlspecialchars($p['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="px-2 py-0.5 rounded-md bg-rose-100 font-mono text-xs text-rose-800 font-bold tabular-nums">V<?= (int)($p['version_number'] ?: 1) ?></span>
                            </div>
                            <div class="text-sm font-medium text-slate-800 mb-3 line-clamp-1"><?= htmlspecialchars($p['course_title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="flex items-center gap-1.5 text-xs text-rose-700 mb-4 font-semibold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Returned <?= htmlspecialchars(getPaperTimeInStage($p['updated_at']), ENT_QUOTES, 'UTF-8') ?> ago</span>
                            </div>
                        </div>
                        <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $p['id']) ?>" class="min-h-[38px] inline-flex items-center justify-center w-full px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-semibold transition text-xs">
                            View Comments &amp; Revise
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Left: Assigned Courses -->
            <section class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center justify-between">
                    <span>Allocated Courses</span>
                    <span class="text-xs text-slate-500 font-semibold tabular-nums"><?= count($assignedCourses) ?> <?= count($assignedCourses) === 1 ? 'Course' : 'Courses' ?></span>
                </h2>

                <div class="space-y-3">
                    <?php if (empty($assignedCourses)): ?>
                        <div class="text-sm text-slate-500 text-center py-8">No courses are currently allocated to your profile.</div>
                    <?php else: ?>
                        <?php foreach ($assignedCourses as $course): ?>
                            <div class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-sm text-lasu-navy font-mono"><?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-xs bg-slate-200/80 text-slate-700 px-2 py-0.5 rounded-md font-medium"><?= htmlspecialchars($course['level_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="text-sm font-medium text-slate-800 line-clamp-1 mb-2.5"><?= htmlspecialchars($course['course_title'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="flex items-center justify-between text-xs text-slate-600 pt-2.5 border-t border-slate-200/70">
                                    <span><?= (int)$course['credit_units'] ?> Units &bull; <?= htmlspecialchars($course['dept_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <a href="<?= url('dashboard/lecturer/create_paper.php?course_id=' . $course['id']) ?>" class="text-lasu-navy font-bold hover:underline">Submit Paper &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Right: Recent Submissions -->
            <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                        Recent Paper Lifecycle Activity
                    </h2>
                    <a href="<?= url('dashboard/lecturer/papers.php') ?>" class="text-xs text-lasu-navy font-bold hover:underline">View All Papers &rarr;</a>
                </div>

                <div tabindex="0" role="region" aria-label="Recent paper lifecycle activity" class="table-scroll-region overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th scope="col" class="py-3.5 px-5 font-semibold">Course / Code</th>
                                <th scope="col" class="py-3.5 px-4 font-semibold">Version</th>
                                <th scope="col" class="py-3.5 px-4 font-semibold">Status</th>
                                <th scope="col" class="py-3.5 px-4 font-semibold">Updated</th>
                                <th scope="col" class="py-3.5 px-5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php if (empty($recentPapers)): ?>
                                <tr>
                                    <td colspan="5" class="py-10 px-5 text-center text-sm text-slate-500">
                                        You have not created any examination papers yet. Select <a href="<?= url('dashboard/lecturer/create_paper.php') ?>" class="font-bold text-lasu-navy hover:underline">Upload New Exam Paper</a> to begin.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentPapers as $p): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-3.5 px-5">
                                            <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($p['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($p['paper_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 font-mono text-xs font-bold text-slate-700 tabular-nums">V<?= (int)($p['version_number'] ?: 1) ?></span>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?= renderStatusBadge($p['status']) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 text-xs tabular-nums whitespace-nowrap">
                                            <?= formatDate($p['updated_at'], 'd M Y') ?>
                                        </td>
                                        <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                            <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $p['id']) ?>" class="min-h-[34px] inline-flex items-center px-3 py-1.5 bg-lasu-navy text-white hover:bg-lasu-navy-light rounded-lg font-semibold transition text-xs">
                                                View &amp; Timeline
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
