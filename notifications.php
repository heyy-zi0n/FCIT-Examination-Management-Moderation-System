<?php
/**
 * Notifications Management Center
 */

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth();
$pdo = getDb();

// Handle Mark All Read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    requireCsrf();
    markAllNotificationsRead((int)$user['id']);
    setFlash('success', 'All notifications marked as read.');
    header('Location: /FOCITECMS/notifications.php');
    exit;
}

// Handle Mark Single Read action
if (isset($_GET['mark_read'])) {
    $notifId = (int)$_GET['mark_read'];
    markNotificationRead($notifId, (int)$user['id']);
    header('Location: /FOCITECMS/notifications.php');
    exit;
}

$pageTitle = 'Notifications — LASU FCIT CMS';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-5xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Notifications Center</h1>
                <p class="text-xs text-slate-500">System alerts, submission updates, and workflow reviews</p>
            </div>
            
            <form method="POST" action="/FOCITECMS/notifications.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition border border-slate-300 dark:border-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Mark All as Read</span>
                </button>
            </form>
        </div>

        <?php
        $notifs = getUserNotifications((int)$user['id'], 50);
        ?>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
            <?php if (empty($notifs)): ?>
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto text-slate-400 mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div class="text-sm font-medium text-slate-700 dark:text-slate-300">No notifications found</div>
                    <div class="text-xs text-slate-400 mt-1">You are all caught up!</div>
                </div>
            <?php else: ?>
                <?php foreach ($notifs as $n): ?>
                    <div class="p-4 flex items-start justify-between gap-4 transition <?= $n['is_read'] ? 'bg-white dark:bg-slate-900 opacity-80' : 'bg-blue-50/50 dark:bg-blue-950/20' ?>">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 <?= $n['is_read'] ? 'bg-slate-100 dark:bg-slate-800 text-slate-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span><?= htmlspecialchars($n['title']) ?></span>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 leading-relaxed"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="text-[10px] text-slate-400 mt-1.5"><?= formatDate($n['created_at']) ?></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            <?php if (!empty($n['link'])): ?>
                                <a href="<?= htmlspecialchars($n['link']) ?>" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-medium transition">
                                    View
                                </a>
                            <?php endif; ?>
                            <?php if (!$n['is_read']): ?>
                                <a href="/FOCITECMS/notifications.php?mark_read=<?= $n['id'] ?>" class="px-2.5 py-1 text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 text-xs border border-slate-200 dark:border-slate-700 rounded transition" title="Mark Read">
                                    &check;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
