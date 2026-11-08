<?php

/**
 * Shared Navbar Layout
 */
$currentUser = currentUser();
$unreadCount = $currentUser ? getUnreadNotificationsCount((int)$currentUser['id']) : 0;
$userNotifs = $currentUser ? getUserNotifications((int)$currentUser['id'], 5) : [];

// Dynamically resolve current Academic Session and active Semester from database
$activeSessionName = '2025/2026';
$activeSemesterName = 'First Semester';
try {
    $pdoNav = getDb();
    $sessionRow = $pdoNav->query("SELECT id, session_name FROM academic_sessions WHERE is_current = 1 ORDER BY id DESC LIMIT 1")->fetch();
    if ($sessionRow) {
        $activeSessionName = (string)$sessionRow['session_name'];
        $stmtSem = $pdoNav->prepare("
            SELECT s.name FROM exam_schedules es
            JOIN semesters s ON es.semester_id = s.id
            WHERE es.academic_session_id = :sid
            ORDER BY ABS(DATEDIFF(es.exam_date, CURDATE())) ASC
            LIMIT 1
        ");
        $stmtSem->execute(['sid' => (int)$sessionRow['id']]);
        $semVal = $stmtSem->fetchColumn();
        if (!empty($semVal)) {
            $activeSemesterName = formatSemester((string)$semVal);
        }
    }
} catch (Exception $e) {
    // Fallback to defaults if DB is unavailable
}
?>
<header class="bg-lasu-navy text-white shadow-md sticky top-0 z-40 border-b border-slate-800/80 no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Left: Brand / Logo -->
            <div class="flex items-center gap-3 min-w-0">
                <?php if ($currentUser && empty($isLandingPage)): ?>
                    <button id="mobile-menu-btn" type="button" aria-expanded="false" aria-controls="app-sidebar" aria-label="Toggle workspace navigation" class="md:hidden text-slate-300 hover:text-white hover:bg-slate-800/70 p-2 min-w-[40px] min-h-[40px] inline-flex items-center justify-center rounded-lg transition shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                <?php endif; ?>
                <a href="<?= url('') ?>" class="flex items-center gap-3 group min-w-0">
                    <img src="<?= url('assets/img/lasu-logo.png') ?>" alt="Lagos State University Crest" class="h-11 w-auto object-contain group-hover:scale-105 transition-transform drop-shadow-md shrink-0">
                    <div class="hidden sm:block min-w-0">
                        <div class="text-sm font-bold tracking-tight text-white flex items-center gap-2">
                            <span>LASU FCIT</span>
                            <span class="text-[11px] bg-lasu-gold/20 text-lasu-gold px-1.5 py-0.5 rounded font-mono uppercase tracking-wider">EMMS</span>
                        </div>
                        <div class="text-xs text-slate-300 font-normal truncate max-w-xs md:max-w-md">
                            Examination Management &amp; Moderation System
                        </div>
                    </div>
                </a>
            </div>

            <!-- Center: Active Academic Session & Semester Badge -->
            <div class="hidden lg:flex items-center gap-2 px-3.5 py-1.5 bg-lasu-navy-light/90 rounded-full text-xs text-slate-200 border border-slate-700/60">
                <span class="w-2 h-2 rounded-full bg-emerald-400" aria-hidden="true"></span>
                <span class="font-medium text-slate-300">Active Session:</span>
                <span class="font-bold text-lasu-gold tabular-nums"><?= htmlspecialchars($activeSessionName) ?></span>
                <span class="text-slate-400" aria-hidden="true">&bull;</span>
                <span class="font-medium"><?= htmlspecialchars($activeSemesterName) ?></span>
            </div>

            <!-- Right: Notifications & User Profile -->
            <div class="flex items-center gap-2 sm:gap-3">
                <?php if ($currentUser): ?>
                    <!-- Notifications Dropdown -->
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="notifications-dropdown" aria-expanded="false" aria-haspopup="true" aria-controls="notifications-dropdown" aria-label="Notifications<?= $unreadCount > 0 ? ' (' . (int)$unreadCount . ' unread)' : '' ?>" class="relative p-2 min-w-[40px] min-h-[40px] inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-800/70 rounded-full transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <?php if ($unreadCount > 0): ?>
                                <span class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 bg-rose-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center tabular-nums">
                                    <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <div id="notifications-dropdown" class="dropdown-menu hidden absolute right-0 mt-2 w-[calc(100vw-2rem)] sm:w-96 max-w-sm origin-top-right bg-white rounded-xl shadow-2xl border border-slate-200 py-2 z-50 text-slate-800">
                            <div class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
                                <span class="font-bold text-sm text-slate-900">Notifications</span>
                                <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full font-semibold tabular-nums"><?= $unreadCount ?> unread</span>
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                <?php if (empty($userNotifs)): ?>
                                    <div class="p-6 text-center text-sm text-slate-500">No notifications yet.</div>
                                <?php else: ?>
                                    <?php foreach ($userNotifs as $notif): ?>
                                        <a href="<?= $notif['link'] ? htmlspecialchars($notif['link']) : url('notifications.php') ?>" class="block px-4 py-3 hover:bg-slate-50 transition <?= $notif['is_read'] ? 'opacity-75' : 'bg-blue-50/40' ?>">
                                            <div class="text-xs font-bold text-slate-900 mb-0.5"><?= htmlspecialchars($notif['title']) ?></div>
                                            <div class="text-xs text-slate-600 line-clamp-2 leading-relaxed"><?= htmlspecialchars($notif['message']) ?></div>
                                            <div class="text-[11px] text-slate-500 mt-1 tabular-nums"><?= formatDate($notif['created_at']) ?></div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-2.5 border-t border-slate-100 text-center">
                                <a href="<?= url('notifications.php') ?>" class="text-xs text-lasu-navy font-semibold hover:underline">View All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="profile-dropdown" aria-expanded="false" aria-haspopup="true" aria-controls="profile-dropdown" class="flex items-center gap-2.5 p-1.5 min-h-[40px] rounded-xl hover:bg-slate-800/70 transition">
                            <?= renderUserAvatar($currentUser, 'w-8 h-8', 'text-xs') ?>
                            <div class="hidden md:block text-left">
                                <div class="text-xs font-semibold text-white leading-tight">
                                    <?= formatUserName($currentUser) ?>
                                </div>
                                <div class="text-[11px] text-lasu-gold uppercase tracking-wider font-mono">
                                    <?= htmlspecialchars(str_replace('_', ' ', $currentUser['role'])) ?>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="profile-dropdown" class="dropdown-menu hidden absolute right-0 mt-2 w-60 max-w-[calc(100vw-2rem)] origin-top-right bg-white rounded-xl shadow-2xl border border-slate-200 py-2 z-50 text-slate-800">
                            <div class="px-4 py-2.5 border-b border-slate-100">
                                <div class="text-sm font-bold text-slate-900"><?= formatUserName($currentUser) ?></div>
                                <div class="text-xs text-slate-500 truncate mt-0.5"><?= htmlspecialchars($currentUser['email']) ?></div>
                                <?php if (!empty($currentUser['department_name'])): ?>
                                    <div class="text-xs text-lasu-navy font-semibold mt-1"><?= htmlspecialchars($currentUser['department_name']) ?></div>
                                <?php endif; ?>
                            </div>
                            <a href="<?= url('dashboard/' . $currentUser['role'] . '/index.php') ?>" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span>My Dashboard</span>
                            </a>
                            <a href="<?= url('profile.php') ?>" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Security &amp; Profile</span>
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <form method="POST" action="<?= url('logout.php') ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-700 hover:bg-rose-50 text-left transition">
                                    <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span class="font-semibold">Sign Out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= url('login.php') ?>" class="btn-signin px-4 py-2 min-h-[38px] inline-flex items-center bg-lasu-gold text-lasu-navy text-xs sm:text-sm font-bold rounded-lg hover:bg-lasu-gold-hover transition shadow">
                        Sign In
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</header>
