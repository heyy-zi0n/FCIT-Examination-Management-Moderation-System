<?php

/**
 * Shared Navbar Layout
 */
$currentUser = currentUser();
$unreadCount = $currentUser ? getUnreadNotificationsCount((int)$currentUser['id']) : 0;
$userNotifs = $currentUser ? getUserNotifications((int)$currentUser['id'], 5) : [];
?>
<header class="bg-lasu-navy text-white shadow-md sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Left: Brand / Logo -->
            <div class="flex items-center gap-3">
                <button id="mobile-menu-btn" type="button" class="md:hidden text-slate-300 hover:text-white p-2 rounded-lg focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <a href="<?= url('') ?>" class="flex items-center gap-3 group">
                    <img src="<?= url('assets/img/lasu-logo.png') ?>" alt="LASU Logo" class="h-12 w-auto object-contain group-hover:scale-105 transition-transform drop-shadow-md">
                    <div class="hidden sm:block">
                        <div class="text-sm font-bold tracking-tight text-white flex items-center gap-2">
                            <span>LASU FCIT</span>
                            <span class="text-xs bg-lasu-gold/20 text-lasu-gold px-1.5 py-0.5 rounded font-mono uppercase tracking-wider">EMMS</span>
                        </div>
                        <div class="text-xs text-slate-300 font-light truncate max-w-xs md:max-w-md">
                            Examination Management & Moderation System
                        </div>
                    </div>
                </a>
            </div>

            <!-- Center: Active Academic Session & Semester Badge -->
            <div class="hidden lg:flex items-center gap-2 px-3 py-1 bg-lasu-navy-light rounded-full text-xs text-slate-200 border border-slate-700/50">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="font-medium">Active Session:</span>
                <span class="font-semibold text-lasu-gold">2025/2026</span>
                <span class="text-slate-400">•</span>
                <span>First Semester</span>
            </div>

            <!-- Right: Notifications & User Profile -->
            <div class="flex items-center gap-4">
                <?php if ($currentUser): ?>
                    <!-- Notifications Dropdown -->
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="notifications-dropdown" class="relative p-2 text-slate-300 hover:text-white hover:bg-slate-800/60 rounded-full transition focus:outline-none" title="Notifications">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <?php if ($unreadCount > 0): ?>
                                <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center animate-pulse">
                                    <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <div id="notifications-dropdown" class="dropdown-menu hidden absolute right-0 mt-2 w-[calc(100vw-2rem)] sm:w-96 max-w-sm origin-top-right bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <span class="font-semibold text-sm text-slate-900 dark:text-white">Notifications</span>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full font-medium"><?= $unreadCount ?> new</span>
                            </div>
                            <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                                <?php if (empty($userNotifs)): ?>
                                    <div class="p-4 text-center text-xs text-slate-500">No new notifications.</div>
                                <?php else: ?>
                                    <?php foreach ($userNotifs as $notif): ?>
                                        <a href="<?= $notif['link'] ? htmlspecialchars($notif['link']) : '#' ?>" class="block p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition <?= $notif['is_read'] ? 'opacity-70' : 'bg-blue-50/40 dark:bg-blue-900/10' ?>">
                                            <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-0.5"><?= htmlspecialchars($notif['title']) ?></div>
                                            <div class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2"><?= htmlspecialchars($notif['message']) ?></div>
                                            <div class="text-[10px] text-slate-400 mt-1"><?= formatDate($notif['created_at']) ?></div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-2 border-t border-slate-100 dark:border-slate-800 text-center">
                                <a href="<?= url('notifications.php') ?>" class="text-xs text-lasu-navy dark:text-blue-400 font-medium hover:underline">View All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="profile-dropdown" class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-slate-800/60 transition focus:outline-none">
                            <?= renderUserAvatar($currentUser, 'w-8 h-8', 'text-xs') ?>
                            <div class="hidden md:block text-left">
                                <div class="text-xs font-semibold text-white leading-tight">
                                    <?= formatUserName($currentUser) ?>
                                </div>
                                <div class="text-[10px] text-lasu-gold uppercase tracking-wider font-mono">
                                    <?= str_replace('_', ' ', $currentUser['role']) ?>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="profile-dropdown" class="dropdown-menu hidden absolute right-0 mt-2 w-56 max-w-[calc(100vw-2rem)] origin-top-right bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-800">
                                <div class="text-xs font-semibold text-slate-900 dark:text-white"><?= formatUserName($currentUser) ?></div>
                                <div class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($currentUser['email']) ?></div>
                                <?php if (!empty($currentUser['department_name'])): ?>
                                    <div class="text-[10px] text-blue-600 dark:text-blue-400 font-medium mt-0.5"><?= htmlspecialchars($currentUser['department_name']) ?></div>
                                <?php endif; ?>
                            </div>
                            <a href="<?= url('dashboard/' . $currentUser['role'] . '/') ?>" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span>My Dashboard</span>
                            </a>
                            <a href="<?= url('profile.php') ?>" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Security & Profile</span>
                            </a>
                            <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
                            <a href="<?= url('logout.php') ?>" class="flex items-center gap-2 px-4 py-2 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span class="font-medium">Sign Out</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= url('login.php') ?>" class="px-4 py-2 bg-lasu-gold text-lasu-navy text-xs font-bold rounded-lg hover:bg-lasu-gold-hover transition shadow">
                        Sign In
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</header>