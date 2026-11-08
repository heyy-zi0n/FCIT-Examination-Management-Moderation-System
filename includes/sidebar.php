<?php
/**
 * Shared Role-Aware Sidebar Navigation
 */
$currentUser = currentUser();
if (!$currentUser) return;
$hasAppSidebar = true;

$role = $currentUser['role'];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

if (!function_exists('isSidebarActive')) {
    /**
     * Match exact route suffix(es) safely without substring collisions.
     *
     * @param string|array $paths Single suffix or list of suffixes belonging to a nav section
     * @param string $currentPath Current request URI path
     */
    function isSidebarActive(string|array $paths, string $currentPath): bool {
        $candidates = is_array($paths) ? $paths : [$paths];
        $normalizedCurrent = rtrim(str_replace('\\', '/', $currentPath), '/');
        foreach ($candidates as $candidate) {
            $normalizedCandidate = '/' . ltrim(rtrim($candidate, '/'), '/');
            if ($normalizedCurrent === $normalizedCandidate || str_ends_with($normalizedCurrent, $normalizedCandidate)) {
                return true;
            }
        }
        return false;
    }
}

// Compute lightweight, role-scoped actionable badge counts
$sidebarCounts = [
    'returned'       => 0,
    'mod_queue'      => 0,
    'hod_pending'    => 0,
    'hod_emergency'  => 0,
    'eo_pending'     => 0,
    'print_eligible' => 0,
];

try {
    $pdoSide = getDb();
    $sideUserId = (int)$currentUser['id'];
    $sideDeptId = !empty($currentUser['department_id']) ? (int)$currentUser['department_id'] : null;

    if ($role === ROLE_LECTURER) {
        $stmtSide = $pdoSide->prepare("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = :uid AND status = 'Returned'");
        $stmtSide->execute(['uid' => $sideUserId]);
        $sidebarCounts['returned'] = (int)$stmtSide->fetchColumn();
    } elseif ($role === ROLE_MODERATOR) {
        $stmtSide = $pdoSide->prepare("
            SELECT COUNT(*)
            FROM examination_papers ep
            JOIN courses c ON ep.course_id = c.id
            WHERE ep.status = 'Submitted'
              AND ep.lecturer_id != :uid_sod
              AND (
                  ep.moderator_id = :uid1
                  OR EXISTS (
                      SELECT 1 FROM moderator_assignments ma
                      WHERE ma.moderator_id = :uid2
                        AND ma.department_id = c.department_id
                        AND ma.level_id = c.level_id
                  )
              )
        ");
        $stmtSide->execute(['uid_sod' => $sideUserId, 'uid1' => $sideUserId, 'uid2' => $sideUserId]);
        $sidebarCounts['mod_queue'] = (int)$stmtSide->fetchColumn();
    } elseif ($role === ROLE_HOD && $sideDeptId !== null) {
        $stmtSide = $pdoSide->prepare("
            SELECT COUNT(*) FROM examination_papers ep
            JOIN courses c ON ep.course_id = c.id
            WHERE c.department_id = :dept_id AND ep.status = 'Moderated' AND ep.hod_status = 'pending'
        ");
        $stmtSide->execute(['dept_id' => $sideDeptId]);
        $sidebarCounts['hod_pending'] = (int)$stmtSide->fetchColumn();

        $stmtEmerg = $pdoSide->prepare("
            SELECT COUNT(*) FROM emergency_print_requests epr
            JOIN examination_papers ep ON epr.paper_id = ep.id
            JOIN courses c ON ep.course_id = c.id
            WHERE c.department_id = :dept_id AND epr.status = 'pending'
        ");
        $stmtEmerg->execute(['dept_id' => $sideDeptId]);
        $sidebarCounts['hod_emergency'] = (int)$stmtEmerg->fetchColumn();
    } elseif ($role === ROLE_EXAM_OFFICER) {
        $whereDept = $sideDeptId !== null ? "c.department_id = :dept_id AND " : "";
        $paramsSide = $sideDeptId !== null ? ['dept_id' => $sideDeptId] : [];

        $stmtSide = $pdoSide->prepare("
            SELECT COUNT(*) FROM examination_papers ep
            JOIN courses c ON ep.course_id = c.id
            WHERE {$whereDept} ep.status = 'Moderated' AND ep.eo_status = 'pending'
        ");
        $stmtSide->execute($paramsSide);
        $sidebarCounts['eo_pending'] = (int)$stmtSide->fetchColumn();

        $stmtPrint = $pdoSide->prepare("
            SELECT COUNT(*) FROM examination_papers ep
            JOIN courses c ON ep.course_id = c.id
            JOIN exam_schedules es ON ep.exam_schedule_id = es.id
            JOIN blind_lockdowns bl ON ep.id = bl.paper_id
            WHERE {$whereDept} ep.status IN ('Approved', 'Locked', 'Queued')
              AND (
                  es.exam_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                  OR EXISTS (
                      SELECT 1 FROM emergency_print_requests epr
                      WHERE epr.paper_id = ep.id AND epr.version_id = ep.current_version_id AND epr.status = 'approved'
                  )
              )
        ");
        $stmtPrint->execute($paramsSide);
        $sidebarCounts['print_eligible'] = (int)$stmtPrint->fetchColumn();
    }
} catch (Exception $e) {
    // Non-blocking fallback
}

$navLinkClass = static function (bool $active): string {
    return $active
        ? 'bg-lasu-navy text-lasu-gold border border-lasu-gold/40 shadow-sm font-semibold'
        : 'hover:bg-slate-800/70 text-slate-300 hover:text-white border border-transparent font-medium';
};
?>
<!-- Mobile Backdrop -->
<div id="sidebar-backdrop" class="fixed inset-0 top-16 bg-slate-950/60 backdrop-blur-sm z-20 hidden md:hidden no-print" aria-hidden="true"></div>

<!-- Sidebar Container -->
<aside id="app-sidebar" aria-label="Role workspace sidebar" class="fixed top-16 bottom-0 left-0 z-30 w-64 shrink-0 bg-slate-900 text-slate-300 flex flex-col border-r border-slate-800 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out no-print">
    
    <!-- User Quick Info -->
    <div class="p-4 border-b border-slate-800/80 bg-slate-950/40">
        <div class="flex items-center gap-3">
            <?= renderUserAvatar($currentUser, 'w-10 h-10 shrink-0', 'text-sm') ?>
            <div class="overflow-hidden min-w-0">
                <div class="text-sm font-semibold text-white truncate"><?= formatUserName($currentUser) ?></div>
                <div class="text-[11px] text-lasu-gold font-mono uppercase tracking-wider"><?= htmlspecialchars(str_replace('_', ' ', $role)) ?></div>
            </div>
        </div>
        <?php if (!empty($currentUser['department_name'])): ?>
            <div class="mt-2.5 text-xs text-slate-300 bg-slate-800/70 px-2.5 py-1.5 rounded-lg truncate border border-slate-700/60" title="<?= htmlspecialchars($currentUser['department_name']) ?>">
                <?= htmlspecialchars($currentUser['department_name']) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Items -->
    <nav aria-label="Workspace navigation" class="flex-1 px-3 py-4 space-y-1 overflow-y-auto overscroll-contain">
        
        <?php if ($role === ROLE_LECTURER): ?>
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">Lecturer Workflow</div>
            
            <?php $active = isSidebarActive(['/dashboard/lecturer/index.php', '/dashboard/lecturer'], $currentPath); ?>
            <a href="<?= url('dashboard/lecturer/index.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span>Dashboard</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/lecturer/create_paper.php', $currentPath); ?>
            <a href="<?= url('dashboard/lecturer/create_paper.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-emerald-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Submit Exam Paper</span>
            </a>

            <?php $active = isSidebarActive(['/dashboard/lecturer/papers.php', '/dashboard/lecturer/view_paper.php', '/dashboard/lecturer/revise_paper.php'], $currentPath); ?>
            <a href="<?= url('dashboard/lecturer/papers.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <span class="flex items-center gap-3 min-w-0">
                    <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="truncate">My Paper Lifecycle</span>
                </span>
                <?php if ($sidebarCounts['returned'] > 0): ?>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-rose-500 text-white tabular-nums shrink-0" title="<?= $sidebarCounts['returned'] ?> returned for correction">
                        <?= $sidebarCounts['returned'] ?>
                    </span>
                <?php endif; ?>
            </a>

        <?php elseif ($role === ROLE_MODERATOR): ?>
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">Moderation Workflow</div>

            <?php $active = isSidebarActive(['/dashboard/moderator/index.php', '/dashboard/moderator/review_paper.php', '/dashboard/moderator'], $currentPath); ?>
            <a href="<?= url('dashboard/moderator/index.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <span class="flex items-center gap-3 min-w-0">
                    <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-amber-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <span class="truncate">Moderation Queue</span>
                </span>
                <?php if ($sidebarCounts['mod_queue'] > 0): ?>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-400 text-slate-950 tabular-nums shrink-0">
                        <?= $sidebarCounts['mod_queue'] ?>
                    </span>
                <?php endif; ?>
            </a>

            <?php $active = isSidebarActive('/dashboard/moderator/history.php', $currentPath); ?>
            <a href="<?= url('dashboard/moderator/history.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Reviewed Papers</span>
            </a>

        <?php elseif ($role === ROLE_HOD): ?>
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">Department Readiness</div>

            <?php $active = isSidebarActive(['/dashboard/hod/index.php', '/dashboard/hod'], $currentPath); ?>
            <a href="<?= url('dashboard/hod/index.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Overview</span>
            </a>

            <?php $active = isSidebarActive(['/dashboard/hod/pending_approvals.php', '/dashboard/hod/review_paper.php'], $currentPath); ?>
            <a href="<?= url('dashboard/hod/pending_approvals.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <span class="flex items-center gap-3 min-w-0">
                    <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-amber-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="truncate">Pending HOD Approvals</span>
                </span>
                <?php if ($sidebarCounts['hod_pending'] > 0): ?>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-400 text-slate-950 tabular-nums shrink-0">
                        <?= $sidebarCounts['hod_pending'] ?>
                    </span>
                <?php endif; ?>
            </a>

            <?php $active = isSidebarActive('/dashboard/hod/schedules.php', $currentPath); ?>
            <a href="<?= url('dashboard/hod/schedules.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-blue-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Exam Schedules</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/hod/moderator_assignments.php', $currentPath); ?>
            <a href="<?= url('dashboard/hod/moderator_assignments.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-purple-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Moderator Allocations</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/hod/emergency_printing.php', $currentPath); ?>
            <a href="<?= url('dashboard/hod/emergency_printing.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <span class="flex items-center gap-3 min-w-0">
                    <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-rose-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span class="truncate">Emergency Printing</span>
                </span>
                <?php if ($sidebarCounts['hod_emergency'] > 0): ?>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-rose-500 text-white tabular-nums shrink-0">
                        <?= $sidebarCounts['hod_emergency'] ?>
                    </span>
                <?php endif; ?>
            </a>

            <?php $active = isSidebarActive('/dashboard/hod/delegations.php', $currentPath); ?>
            <a href="<?= url('dashboard/hod/delegations.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Formal Delegation</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/hod/audit_trail.php', $currentPath); ?>
            <a href="<?= url('dashboard/hod/audit_trail.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Department Audit Trail</span>
            </a>

        <?php elseif ($role === ROLE_EXAM_OFFICER): ?>
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">Examination Pipeline</div>

            <?php $active = isSidebarActive(['/dashboard/exam_officer/index.php', '/dashboard/exam_officer'], $currentPath); ?>
            <a href="<?= url('dashboard/exam_officer/index.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Overview</span>
            </a>

            <?php $active = isSidebarActive(['/dashboard/exam_officer/pending_approvals.php', '/dashboard/exam_officer/review_paper.php'], $currentPath); ?>
            <a href="<?= url('dashboard/exam_officer/pending_approvals.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <span class="flex items-center gap-3 min-w-0">
                    <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-amber-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="truncate">EO Approval Queue</span>
                </span>
                <?php if ($sidebarCounts['eo_pending'] > 0): ?>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-400 text-slate-950 tabular-nums shrink-0">
                        <?= $sidebarCounts['eo_pending'] ?>
                    </span>
                <?php endif; ?>
            </a>

            <?php $active = isSidebarActive(['/dashboard/exam_officer/print_queue.php', '/dashboard/exam_officer/controlled_print.php', '/dashboard/exam_officer/request_emergency_print.php'], $currentPath); ?>
            <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <span class="flex items-center gap-3 min-w-0">
                    <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-cyan-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span class="truncate">Controlled Print Queue</span>
                </span>
                <?php if ($sidebarCounts['print_eligible'] > 0): ?>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-400 text-slate-950 tabular-nums shrink-0" title="Eligible for printing today">
                        <?= $sidebarCounts['print_eligible'] ?>
                    </span>
                <?php endif; ?>
            </a>

            <?php $active = isSidebarActive('/dashboard/exam_officer/print_history.php', $currentPath); ?>
            <a href="<?= url('dashboard/exam_officer/print_history.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Print Logs &amp; Reprints</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/exam_officer/delegations.php', $currentPath); ?>
            <a href="<?= url('dashboard/exam_officer/delegations.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>EO Delegations</span>
            </a>

        <?php elseif ($role === ROLE_ADMIN): ?>
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">System Administration</div>

            <?php $active = isSidebarActive(['/dashboard/admin/index.php', '/dashboard/admin'], $currentPath); ?>
            <a href="<?= url('dashboard/admin/index.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span>Admin Console</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/admin/users.php', $currentPath); ?>
            <a href="<?= url('dashboard/admin/users.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-blue-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>User Management</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/admin/academic_structure.php', $currentPath); ?>
            <a href="<?= url('dashboard/admin/academic_structure.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-emerald-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Academic Structure</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/admin/assignments.php', $currentPath); ?>
            <a href="<?= url('dashboard/admin/assignments.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-purple-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Course Allocations</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/admin/audit_logs.php', $currentPath); ?>
            <a href="<?= url('dashboard/admin/audit_logs.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-rose-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Master Audit Logs</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/admin/reports.php', $currentPath); ?>
            <a href="<?= url('dashboard/admin/reports.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-indigo-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Analytics &amp; Reports</span>
            </a>

            <?php $active = isSidebarActive('/dashboard/admin/system_settings.php', $currentPath); ?>
            <a href="<?= url('dashboard/admin/system_settings.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>System Settings</span>
            </a>
        <?php endif; ?>

        <!-- Common Account Links on Mobile -->
        <div class="pt-4 mt-4 border-t border-slate-800/80 space-y-1">
            <div class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">Account</div>
            <?php $active = isSidebarActive('/profile.php', $currentPath); ?>
            <a href="<?= url('profile.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Security &amp; Profile</span>
            </a>
            <?php $active = isSidebarActive('/notifications.php', $currentPath); ?>
            <a href="<?= url('notifications.php') ?>" <?= $active ? 'aria-current="page"' : '' ?> class="flex items-center gap-3 px-3 py-2 text-sm rounded-xl transition <?= $navLinkClass($active) ?>">
                <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-lasu-gold' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span>Notifications</span>
            </a>
        </div>

    </nav>

    <!-- Bottom Footer -->
    <div class="p-4 border-t border-slate-800 text-xs text-slate-400">
        <div class="flex items-center justify-between">
            <span>LASU FCIT EMMS</span>
            <span class="font-mono text-[11px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-300">v<?= htmlspecialchars(APP_VERSION) ?></span>
        </div>
    </div>
</aside>
<!-- Desktop Layout Flow Spacer for Fixed Sidebar -->
<div class="hidden md:block w-64 shrink-0 no-print" aria-hidden="true"></div>
