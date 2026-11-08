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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-lasu-navy via-slate-900 to-lasu-navy-light rounded-2xl p-6 text-white shadow-md mb-8 border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-lasu-gold uppercase tracking-wider mb-1">Faculty Master Administration</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold"><?= formatUserName($user) ?></h1>
                <p class="text-sm text-slate-300 mt-1">Manage user accounts, departmental course structures, cryptographic diagnostics, and faculty-wide audit trails.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= url('dashboard/admin/users.php') ?>" class="min-h-[42px] px-4 py-2.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>Manage Users</span>
                </a>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">System Users</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalUsers ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Departments</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalDepartments ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Courses</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalCourses ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Exam Papers</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalPapers ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Sealed Lockdowns</div>
                <div class="text-2xl font-black text-lasu-navy mt-1.5 tabular-nums"><?= $activeLockdowns ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Security Audits</div>
                <div class="text-2xl font-black text-slate-900 mt-1.5 tabular-nums"><?= $totalAudits ?></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Quick Administration Tools -->
            <section class="lg:col-span-1 bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">Core Administration Panels</h2>

                <div class="space-y-2.5">
                    <a href="<?= url('dashboard/admin/users.php') ?>" class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-100 hover:border-slate-300 transition flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-lasu-navy/10 text-lasu-navy flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-900">User Accounts &amp; RBAC</div>
                                <div class="text-xs text-slate-500 truncate">Manage credentials, roles &amp; permissions</div>
                            </div>
                        </div>
                        <span class="text-slate-400 shrink-0" aria-hidden="true">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/academic_structure.php') ?>" class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-100 hover:border-slate-300 transition flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-900">Academic Curriculum &amp; Depts</div>
                                <div class="text-xs text-slate-500 truncate">Sessions, courses, levels &amp; departments</div>
                            </div>
                        </div>
                        <span class="text-slate-400 shrink-0" aria-hidden="true">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/assignments.php') ?>" class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-100 hover:border-slate-300 transition flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-900">Course &amp; Staff Allocations</div>
                                <div class="text-xs text-slate-500 truncate">Course lecturers and moderator mappings</div>
                            </div>
                        </div>
                        <span class="text-slate-400 shrink-0" aria-hidden="true">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/audit_logs.php') ?>" class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-100 hover:border-slate-300 transition flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-900">Audit Trail Explorer</div>
                                <div class="text-xs text-slate-500 truncate">Faculty security and action records</div>
                            </div>
                        </div>
                        <span class="text-slate-400 shrink-0" aria-hidden="true">&rarr;</span>
                    </a>

                    <a href="<?= url('dashboard/admin/system_settings.php') ?>" class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-100 hover:border-slate-300 transition flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-900">System Health &amp; Storage</div>
                                <div class="text-xs text-slate-500 truncate">Environment and encryption status</div>
                            </div>
                        </div>
                        <span class="text-slate-400 shrink-0" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </section>

            <!-- Recent Faculty Audit Log -->
            <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Live System Security Audit Feed
                    </h2>
                    <a href="<?= url('dashboard/admin/audit_logs.php') ?>" class="text-xs text-lasu-navy font-bold hover:underline">View All Audits &rarr;</a>
                </div>

                <div tabindex="0" role="region" aria-label="Live system security audit feed" class="table-scroll-region overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th scope="col" class="py-3.5 px-5 font-semibold">When (Time)</th>
                                <th scope="col" class="py-3.5 px-4 font-semibold">Who (User)</th>
                                <th scope="col" class="py-3.5 px-4 font-semibold">What (Event)</th>
                                <th scope="col" class="py-3.5 px-5 font-semibold">Which (Entity)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php if (empty($recentAudits)): ?>
                                <tr>
                                    <td colspan="4" class="py-10 px-5 text-center text-sm text-slate-500">No security audit events recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAudits as $ra): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-3.5 px-5 text-slate-600 text-xs tabular-nums whitespace-nowrap">
                                            <?= formatDate($ra['created_at'], 'd M Y H:i') ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-medium text-slate-900 text-sm"><?= htmlspecialchars(($ra['first_name'] ? $ra['first_name'] . ' ' . $ra['last_name'] : 'System'), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-[11px] text-slate-500 uppercase font-semibold tracking-wide"><?= htmlspecialchars(str_replace('_', ' ', (string)($ra['role'] ?? 'SYSTEM')), ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-md bg-slate-100 text-lasu-navy font-mono font-bold text-xs">
                                                <?= htmlspecialchars($ra['action'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-5 font-mono text-xs text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($ra['entity_type'], ENT_QUOTES, 'UTF-8') ?> #<?= $ra['entity_id'] ?: '—' ?>
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
