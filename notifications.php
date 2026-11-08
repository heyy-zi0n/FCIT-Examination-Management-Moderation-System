<?php
/**
 * Notifications Management Center
 */

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth();
$pdo = getDb();

// Handle POST actions (Mark All Read / Mark Single Read)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        markAllNotificationsRead((int)$user['id']);
        setFlash('success', 'All notifications marked as read.');
        redirect('notifications.php');
        exit;
    }

    if ($action === 'mark_single_read' && !empty($_POST['notif_id'])) {
        $notifId = (int)$_POST['notif_id'];
        markNotificationRead($notifId, (int)$user['id']);
        redirect('notifications.php');
        exit;
    }
}

$pageTitle = 'Notifications — LASU FCIT CMS';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Notifications Center</h1>
                <p class="text-sm text-slate-600 mt-0.5">System alerts, submission updates, and examination workflow reviews</p>
            </div>

            <form method="POST" action="<?= url('notifications.php') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" data-loading-text="Updating..." class="min-h-[40px] px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl transition border border-slate-200 shadow-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Mark All as Read</span>
                </button>
            </form>
        </div>

        <?php
        $notifs = getUserNotifications((int)$user['id'], 50);
        ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden divide-y divide-slate-100">
            <?php if (empty($notifs)): ?>
                <div class="p-12 text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-slate-400 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">No notifications found</div>
                    <div class="text-xs text-slate-500 mt-1">You are all caught up on examination workflow events.</div>
                </div>
            <?php else: ?>
                <?php foreach ($notifs as $n): ?>
                    <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4 transition <?= $n['is_read'] ? 'bg-white' : 'bg-blue-50/50' ?>">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 <?= $n['is_read'] ? 'bg-slate-100 text-slate-500' : 'bg-blue-100 text-blue-700' ?>" aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                                    <span><?= htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="w-2 h-2 rounded-full bg-blue-600" aria-label="Unread"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-slate-600 mt-0.5 leading-relaxed"><?= htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-slate-500 mt-1.5 tabular-nums"><?= formatDate($n['created_at']) ?></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0 self-end sm:self-center">
                            <?php if (!empty($n['link'])):
                                $btnText = 'View';
                                if (str_starts_with($n['title'], 'Action Required')) $btnText = 'Take Action';
                                elseif (str_starts_with($n['title'], 'Action Complete')) $btnText = 'View Details';
                                elseif (str_starts_with($n['title'], 'Workflow Update') || str_starts_with($n['title'], 'Workflow Complete')) $btnText = 'View Status';
                            ?>
                                <a href="<?= htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8') ?>" class="min-h-[36px] px-3.5 py-1.5 <?= str_starts_with($n['title'], 'Action Required') ? 'bg-amber-500 hover:bg-amber-600 text-slate-950 shadow-sm' : 'bg-lasu-navy hover:bg-lasu-navy-light text-white' ?> rounded-lg text-xs font-bold transition inline-flex items-center gap-1.5">
                                    <?= $btnText ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!$n['is_read']): ?>
                                <form method="POST" action="<?= url('notifications.php') ?>" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="mark_single_read">
                                    <input type="hidden" name="notif_id" value="<?= (int)$n['id'] ?>">
                                    <button type="submit" aria-label="Mark notification as read" class="w-9 h-9 inline-flex items-center justify-center text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition" title="Mark as Read">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
