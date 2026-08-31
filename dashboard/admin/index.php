<?php
/**
 * System Administrator Dashboard Overview
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

// System Wide Statistics
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDepartments = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalPapers = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers")->fetchColumn();
$totalAudits = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$activeLockdowns = (int)$pdo->query("SELECT COUNT(*) FROM blind_lockdowns WHERE is_unlocked = 0")->fetchColumn();

// Recent Audit Events
$recentAudits = $pdo->query("
    SELECT al.*, u.first_name, u.last_name, u.role, u.email
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 6
")->fetchAll();

$pageTitle = 'System Administration — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-slate-950 via-lasu-navy to-blue-950 rounded-2xl p-6 text-white shadow-lg mb-8 border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Faculty Master Administration</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold"><?= formatUserName($user) ?></h1>
                <p class="text-xs text-slate-300 mt-1">Manage user security, departmental course structures, cryptographic keys, and system-wide audits.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= url('dashboard/admin/users.php') ?>" class="px-4 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>Manage Users</span>
                </a>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase">System Users</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $totalUsers ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase">Departments</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $totalDepartments ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-blue-200 dark:border-blue-900/50 shadow-sm bg-blue-50/20">
                <div class="text-xs font-semibold text-blue-600 uppercase">Total Courses</div>
                <div class="text-2xl font-black text-blue-600 mt-1"><?= $totalCourses ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-amber-200 dark:border-amber-900/50 shadow-sm bg-amber-50/20">
                <div class="text-xs font-semibold text-amber-600 uppercase">Exam Papers</div>
                <div class="text-2xl font-black text-amber-600 mt-1"><?= $totalPapers ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-purple-200 dark:border-purple-900/50 shadow-sm bg-purple-50/20">
                <div class="text-xs font-semibold text-purple-600 uppercase">Active Lockdowns</div>
                <div class="text-2xl font-black text-purple-600 mt-1"><?= $activeLockdowns ?></div>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-teal-200 dark:border-teal-900/50 shadow-sm bg-teal-50/20">
                <div class="text-xs font-semibold text-teal-600 uppercase">Security Audits</div>
                <div class="text-2xl font-black text-teal-600 mt-1"><?= $totalAudits ?></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Quick Administration Tools -->
            <div class="lg:col-span-1 bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">Core Administration Panels</h2>

                <div class="space-y-2.5">
                    <a href="<?= url('dashboard/admin/users.php') ?>" class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">User Accounts & RBAC</div>
                                <div class="text-[10px] text-slate-400">Manage credentials, roles & permissions</div>
                            </div>
                        </div>
                        <span class="text-slate-400">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/academic_structure.php') ?>" class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Academic Curriculum & Depts</div>
                                <div class="text-[10px] text-slate-400">Sessions, courses, levels & departments</div>
                            </div>
                        </div>
                        <span class="text-slate-400">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/assignments.php') ?>" class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Course & Staff Allocations</div>
                                <div class="text-[10px] text-slate-400">Course lecturers and moderator mappings</div>
                            </div>
                        </div>
                        <span class="text-slate-400">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/audit_logs.php') ?>" class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Audit Trail Explorer</div>
                                <div class="text-[10px] text-slate-400">Faculty security and action records</div>
                            </div>
                        </div>
                        <span class="text-slate-400">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/system_settings.php') ?>" class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">System Health & Storage</div>
                                <div class="text-[10px] text-slate-400">Environment and encryption status</div>
                            </div>
                        </div>
                        <span class="text-slate-400">&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Recent Faculty Audit Log -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Live System Security Audit Feed
                    </h2>
                    <a href="<?= url('dashboard/admin/audit_logs.php') ?>" class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline">View All Audits</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                <th class="pb-3 font-semibold">WHEN (Time)</th>
                                <th class="pb-3 font-semibold">WHO (User)</th>
                                <th class="pb-3 font-semibold">WHAT (Event)</th>
                                <th class="pb-3 font-semibold">WHICH (Entity)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <?php foreach ($recentAudits as $ra): ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-3 text-slate-500 text-[11px] whitespace-nowrap">
                                        <?= formatDate($ra['created_at'], 'd M Y H:i') ?>
                                    </td>
                                    <td class="py-3 font-medium text-[11px]">
                                        <?= htmlspecialchars(($ra['first_name'] ? $ra['first_name'] . ' ' . $ra['last_name'] : 'System')) ?>
                                        <div class="text-[9px] text-slate-400 uppercase"><?= htmlspecialchars($ra['role'] ?? 'SYSTEM') ?></div>
                                    </td>
                                    <td class="py-3 font-mono font-bold text-blue-600 dark:text-blue-400 text-[10px]">
                                        <?= htmlspecialchars($ra['action']) ?>
                                    </td>
                                    <td class="py-3 font-mono text-[10px] text-slate-500">
                                        <?= htmlspecialchars($ra['entity_type']) ?> #<?= $ra['entity_id'] ?: '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
