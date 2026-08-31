<?php
/**
 * Faculty-Wide System Security Audit Trail (Administrator)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

// Filters
$actionFilter = sanitizeInput($_GET['action_name'] ?? '');
$userFilter = (int)($_GET['user_id'] ?? 0);
$entityFilter = sanitizeInput($_GET['entity_type'] ?? '');

$sql = "
    SELECT al.*, u.first_name, u.last_name, u.title, u.role, u.email
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($actionFilter)) {
    $sql .= " AND al.action = :action";
    $params['action'] = $actionFilter;
}
if ($userFilter > 0) {
    $sql .= " AND al.user_id = :uid";
    $params['uid'] = $userFilter;
}
if (!empty($entityFilter)) {
    $sql .= " AND al.entity_type = :entity";
    $params['entity'] = $entityFilter;
}

$sql .= " ORDER BY al.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions & users for filters
$actionsList = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$usersList = $pdo->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name ASC")->fetchAll();

$pageTitle = 'Master Security Audit Trail — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Master Faculty Security Audit Trail</h1>
            <p class="text-xs text-slate-500">Immutable forensic audit trail capturing all examination submissions, reviews, dual approvals, emergency unlocks, and print operations</p>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 mb-6">
            <form method="GET" action="<?= url('dashboard/admin/audit_logs.php') ?>" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Filter by Action Event</label>
                    <select name="action_name" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl">
                        <option value="">All Action Events</option>
                        <?php foreach ($actionsList as $act): ?>
                            <option value="<?= htmlspecialchars($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Filter by User</label>
                    <select name="user_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl">
                        <option value="0">All System Users</option>
                        <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userFilter === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Filter by Entity Type</label>
                    <input type="text" name="entity_type" value="<?= htmlspecialchars($entityFilter) ?>" placeholder="e.g. examination_paper, user..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="w-full py-2 bg-lasu-navy text-lasu-gold font-bold rounded-xl shadow transition">Apply Filter</button>
                    <a href="<?= url('dashboard/admin/audit_logs.php') ?>" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold rounded-xl border border-slate-300 dark:border-slate-700 text-center">Reset</a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-bold text-slate-700 dark:text-slate-300">WHEN (Timestamp)</th>
                            <th class="py-3.5 px-4 font-bold text-slate-700 dark:text-slate-300">WHO (Actor)</th>
                            <th class="py-3.5 px-4 font-bold text-slate-700 dark:text-slate-300">WHAT (Action)</th>
                            <th class="py-3.5 px-4 font-bold text-slate-700 dark:text-slate-300">WHICH (Target Entity)</th>
                            <th class="py-3.5 px-4 font-bold text-slate-700 dark:text-slate-300">RESULT (Context / IP)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    No audit logs matching this filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                        <?= formatDate($l['created_at'], 'd M Y H:i:s') ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-medium">
                                        <?php if (!empty($l['first_name'])): ?>
                                            <div class="text-slate-900 dark:text-white"><?= htmlspecialchars(($l['title'] ? $l['title'] . ' ' : '') . $l['first_name'] . ' ' . $l['last_name']) ?></div>
                                            <div class="text-[10px] text-slate-500 font-bold tracking-wide uppercase"><?= htmlspecialchars($l['role']) ?></div>
                                        <?php else: ?>
                                            <span class="text-slate-500 italic">System Auto-Process</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-bold font-mono text-[10px] border border-blue-100 dark:border-blue-800">
                                            <?= htmlspecialchars($l['action']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider"><?= htmlspecialchars(str_replace('_', ' ', $l['entity_type'])) ?></div>
                                        <div class="text-[10px] text-slate-500 font-mono mt-0.5">ID: <?= $l['entity_id'] ?: 'System-Wide' ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="text-slate-700 dark:text-slate-300 text-[11px] max-w-sm truncate" title="<?= htmlspecialchars($l['details'] ?? '') ?>">
                                            <?= htmlspecialchars($l['details'] ?: 'Routine operation completed.') ?>
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-mono mt-1 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                            <?= htmlspecialchars($l['ip_address']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
