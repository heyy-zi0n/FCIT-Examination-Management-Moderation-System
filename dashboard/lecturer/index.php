<?php
/**
 * Lecturer Dashboard Overview
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_LECTURER);

$pdo = getDb();
$userId = (int)$user['id'];

// Statistics
$totalAssignedCourses = (int)$pdo->query("SELECT COUNT(*) FROM lecturer_course_assignments WHERE lecturer_id = {$userId}")->fetchColumn();
$draftCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = {$userId} AND status = 'Draft'")->fetchColumn();
$awaitingModerationCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = {$userId} AND status = 'Submitted'")->fetchColumn();
$returnedCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = {$userId} AND status = 'Returned'")->fetchColumn();
$approvedLockdownCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = {$userId} AND status IN ('Moderated', 'Approved')")->fetchColumn();
$lockedCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = {$userId} AND status IN ('Locked', 'Queued')")->fetchColumn();
$printedCount = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = {$userId} AND status = 'Printed'")->fetchColumn();

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

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-lasu-navy via-slate-900 to-blue-950 rounded-2xl p-6 text-white shadow-lg mb-8 border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Academic Staff Portal</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold">Welcome, <?= formatUserName($user) ?></h1>
                <p class="text-xs text-slate-300 mt-1">Manage examination lifecycle, view moderation comments, and track paper progress.</p>
            </div>
            <a href="<?= url('dashboard/lecturer/create_paper.php') ?>" class="px-5 py-3 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl shadow-lg transition text-xs flex items-center gap-2 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Upload New Exam Paper</span>
            </a>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase">Drafts</div>
                <div class="text-2xl font-black text-slate-700 dark:text-slate-300 mt-1"><?= $draftCount ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-blue-200 dark:border-blue-900/50 shadow-sm bg-blue-50/20">
                <div class="text-xs font-semibold text-blue-600 uppercase">Awaiting Moderation</div>
                <div class="text-2xl font-black text-blue-600 mt-1"><?= $awaitingModerationCount ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-rose-200 dark:border-rose-900/50 shadow-sm bg-rose-50/20">
                <div class="text-xs font-semibold text-rose-600 uppercase">Returned for Correction</div>
                <div class="text-2xl font-black text-rose-600 mt-1"><?= $returnedCount ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-amber-200 dark:border-amber-900/50 shadow-sm bg-amber-50/20">
                <div class="text-xs font-semibold text-amber-600 uppercase">Approved for Lockdown</div>
                <div class="text-2xl font-black text-amber-600 mt-1"><?= $approvedLockdownCount ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-emerald-200 dark:border-emerald-900/50 shadow-sm bg-emerald-50/20">
                <div class="text-xs font-semibold text-emerald-600 uppercase">Locked Papers</div>
                <div class="text-2xl font-black text-emerald-600 mt-1"><?= $lockedCount ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-indigo-200 dark:border-indigo-900/50 shadow-sm bg-indigo-50/20">
                <div class="text-xs font-semibold text-indigo-600 uppercase">Printed Papers</div>
                <div class="text-2xl font-black text-indigo-600 mt-1"><?= $printedCount ?></div>
            </div>
        </div>

        <?php if (!empty($actionPapers)): ?>
        <!-- Action Required Section -->
        <div class="mb-8 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-900/50 rounded-xl p-5 shadow-sm">
            <h2 class="text-sm font-bold text-rose-900 dark:text-rose-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Action Required: Papers Returned for Correction
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($actionPapers as $p): ?>
                    <div class="bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-800 rounded-lg p-4 shadow-sm">
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-bold text-rose-900 dark:text-rose-400 font-mono text-sm"><?= htmlspecialchars($p['course_code']) ?></span>
                            <span class="px-2 py-0.5 rounded bg-rose-100 dark:bg-rose-900/50 font-mono text-[10px] text-rose-700 dark:text-rose-300 font-bold">V<?= $p['version_number'] ?: 1 ?></span>
                        </div>
                        <div class="text-xs font-medium text-slate-800 dark:text-slate-200 mb-3 line-clamp-1"><?= htmlspecialchars($p['course_title']) ?></div>
                        <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $p['id']) ?>" class="block w-full text-center px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-medium transition text-xs">
                            View Comments & Revise
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left: Assigned Courses -->
            <div class="lg:col-span-1 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-5">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center justify-between">
                    <span>Allocated Courses</span>
                    <span class="text-xs text-slate-400 font-normal"><?= count($assignedCourses) ?> Courses</span>
                </h2>

                <div class="space-y-3">
                    <?php if (empty($assignedCourses)): ?>
                        <div class="text-xs text-slate-500 text-center py-6">No courses currently allocated.</div>
                    <?php else: ?>
                        <?php foreach ($assignedCourses as $course): ?>
                            <div class="p-3.5 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-xs text-blue-900 dark:text-blue-400 font-mono"><?= htmlspecialchars($course['course_code']) ?></span>
                                    <span class="text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 px-1.5 py-0.5 rounded"><?= htmlspecialchars($course['level_name']) ?></span>
                                </div>
                                <div class="text-xs font-medium text-slate-800 dark:text-slate-200 line-clamp-1 mb-2"><?= htmlspecialchars($course['course_title']) ?></div>
                                <div class="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-200/50 dark:border-slate-700/50">
                                    <span><?= $course['credit_units'] ?> Units • <?= htmlspecialchars($course['dept_code']) ?></span>
                                    <a href="<?= url('dashboard/lecturer/create_paper.php?course_id=' . $course['id']) ?>" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">Submit Paper &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Recent Submissions -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                        Recent Paper Lifecycle Activity
                    </h2>
                    <a href="<?= url('dashboard/lecturer/papers.php') ?>" class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline">View All Papers</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                <th class="pb-3 font-semibold">Course / Code</th>
                                <th class="pb-3 font-semibold">Version</th>
                                <th class="pb-3 font-semibold">Status</th>
                                <th class="pb-3 font-semibold">Updated</th>
                                <th class="pb-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <?php if (empty($recentPapers)): ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400">
                                        You have not created any examination papers yet. Click "Upload New Exam Paper" to begin.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentPapers as $p): ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                        <td class="py-3">
                                            <div class="font-bold text-slate-900 dark:text-white font-mono"><?= htmlspecialchars($p['course_code']) ?></div>
                                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($p['paper_code']) ?></div>
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-[11px] font-bold">V<?= $p['version_number'] ?: 1 ?></span>
                                        </td>
                                        <td class="py-3">
                                            <?= renderStatusBadge($p['status']) ?>
                                        </td>
                                        <td class="py-3 text-slate-500 text-[11px]">
                                            <?= formatDate($p['updated_at'], 'd M Y') ?>
                                        </td>
                                        <td class="py-3 text-right">
                                            <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $p['id']) ?>" class="px-3 py-1.5 bg-lasu-navy text-white hover:bg-lasu-navy-light rounded-lg font-medium transition text-[11px]">
                                                View & Timeline
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
