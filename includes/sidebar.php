<?php
/**
 * Shared Role-Aware Sidebar Navigation
 */
$currentUser = currentUser();
if (!$currentUser) return;

$role = $currentUser['role'];
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

function isSidebarActive(string $path, string $currentUri): bool {
    return strpos($currentUri, $path) !== false;
}
?>
<!-- Mobile Backdrop -->
<div id="sidebar-backdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-30 hidden md:hidden"></div>

<!-- Sidebar Container -->
<aside id="app-sidebar" class="fixed md:static inset-y-0 left-0 z-30 w-64 bg-slate-900 text-slate-300 flex flex-col border-r border-slate-800 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    
    <!-- User Quick Info -->
    <div class="p-4 border-b border-slate-800/80 bg-slate-950/40">
        <div class="flex items-center gap-3">
            <?= renderUserAvatar($currentUser, 'w-10 h-10', 'text-sm') ?>
            <div class="overflow-hidden">
                <div class="text-sm font-semibold text-white truncate"><?= formatUserName($currentUser) ?></div>
                <div class="text-xs text-lasu-gold font-mono uppercase tracking-wider"><?= str_replace('_', ' ', $role) ?></div>
            </div>
        </div>
        <?php if (!empty($currentUser['department_name'])): ?>
            <div class="mt-2 text-[11px] text-slate-400 bg-slate-800/60 px-2 py-1 rounded truncate border border-slate-700/50">
                <?= htmlspecialchars($currentUser['department_name']) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Items -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        
        <?php if ($role === ROLE_LECTURER): ?>
            <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Lecturer Workflow</div>
            
            <a href="/FOCITECMS/dashboard/lecturer/index.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('/dashboard/lecturer/index.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span>Dashboard</span>
            </a>

            <a href="/FOCITECMS/dashboard/lecturer/create_paper.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('create_paper.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Submit Exam Paper</span>
            </a>

            <a href="/FOCITECMS/dashboard/lecturer/papers.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('papers.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>My Paper Lifecycle</span>
            </a>

        <?php elseif ($role === ROLE_MODERATOR): ?>
            <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Moderation Workflow</div>

            <a href="/FOCITECMS/dashboard/moderator/index.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('/dashboard/moderator/index.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span>Moderation Queue</span>
            </a>

            <a href="/FOCITECMS/dashboard/moderator/history.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('history.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Reviewed Papers</span>
            </a>

        <?php elseif ($role === ROLE_HOD): ?>
            <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Department Readiness</div>

            <a href="/FOCITECMS/dashboard/hod/index.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('/dashboard/hod/index.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Overview</span>
            </a>

            <a href="/FOCITECMS/dashboard/hod/pending_approvals.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('pending_approvals.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Pending HOD Approvals</span>
            </a>

            <a href="/FOCITECMS/dashboard/hod/schedules.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('schedules.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Exam Schedules</span>
            </a>

            <a href="/FOCITECMS/dashboard/hod/moderator_assignments.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('moderator_assignments.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Moderator Allocations</span>
            </a>

            <a href="/FOCITECMS/dashboard/hod/emergency_printing.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('emergency_printing.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Emergency Printing</span>
            </a>

            <a href="/FOCITECMS/dashboard/hod/delegations.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('delegations.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Formal Delegation</span>
            </a>

            <a href="/FOCITECMS/dashboard/hod/audit_trail.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('audit_trail.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Department Audit Trail</span>
            </a>

        <?php elseif ($role === ROLE_EXAM_OFFICER): ?>
            <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Examination Pipeline</div>

            <a href="/FOCITECMS/dashboard/exam_officer/index.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('/dashboard/exam_officer/index.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Overview</span>
            </a>

            <a href="/FOCITECMS/dashboard/exam_officer/pending_approvals.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('pending_approvals.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>EO Approval Queue</span>
            </a>

            <a href="/FOCITECMS/dashboard/exam_officer/print_queue.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('print_queue.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Controlled Print Queue</span>
            </a>

            <a href="/FOCITECMS/dashboard/exam_officer/print_history.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('print_history.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Print Logs & Reprints</span>
            </a>

            <a href="/FOCITECMS/dashboard/exam_officer/delegations.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('delegations.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>EO Delegations</span>
            </a>

        <?php elseif ($role === ROLE_ADMIN): ?>
            <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">System Administration</div>

            <a href="/FOCITECMS/dashboard/admin/index.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('/dashboard/admin/index.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span>Admin Console</span>
            </a>

            <a href="/FOCITECMS/dashboard/admin/users.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('users.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>User Management</span>
            </a>

            <a href="/FOCITECMS/dashboard/admin/academic_structure.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('academic_structure.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Academic Structure</span>
            </a>

            <a href="/FOCITECMS/dashboard/admin/assignments.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('assignments.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Course Allocations</span>
            </a>

            <a href="/FOCITECMS/dashboard/admin/audit_logs.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('audit_logs.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Master Audit Logs</span>
            </a>

            <a href="/FOCITECMS/dashboard/admin/reports.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('reports.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Analytics & Reports</span>
            </a>

            <a href="/FOCITECMS/dashboard/admin/system_settings.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition <?= isSidebarActive('system_settings.php', $currentUri) ? 'bg-lasu-navy text-lasu-gold border border-slate-700' : 'hover:bg-slate-800/60 text-slate-300 hover:text-white' ?>">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>System Settings</span>
            </a>
        <?php endif; ?>

    </nav>

    <!-- Bottom Footer -->
    <div class="p-4 border-t border-slate-800 text-xs text-slate-400">
        <div class="flex items-center justify-between">
            <span>LASU FCIT CMS</span>
            <span class="font-mono text-[10px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-300">v1.0.0</span>
        </div>
    </div>
</aside>
