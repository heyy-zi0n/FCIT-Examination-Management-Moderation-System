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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Master Faculty Security Audit Trail</h1>
            <p class="text-sm text-slate-600 mt-0.5">Immutable forensic audit trail capturing submissions, reviews, dual approvals, emergency unlocks, and print operations</p>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 mb-6">
            <form method="GET" action="<?= url('dashboard/admin/audit_logs.php') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="audit_action_filter" class="block text-xs font-semibold text-slate-700 mb-1.5">Action Event</label>
                    <select id="audit_action_filter" name="action_name" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                        <option value="">All Action Events</option>
                        <?php foreach ($actionsList as $act): ?>
                            <option value="<?= htmlspecialchars($act, ENT_QUOTES, 'UTF-8') ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= htmlspecialchars($act, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="audit_user_filter" class="block text-xs font-semibold text-slate-700 mb-1.5">System User</label>
                    <select id="audit_user_filter" name="user_id" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                        <option value="0">All System Users</option>
                        <?php foreach ($usersList as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= $userFilter === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="audit_entity_filter" class="block text-xs font-semibold text-slate-700 mb-1.5">Entity Type</label>
                    <input id="audit_entity_filter" type="text" name="entity_type" value="<?= htmlspecialchars($entityFilter, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. examination_paper, user..." class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="min-h-[42px] flex-1 py-2.5 px-4 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition">Apply Filter</button>
                    <a href="<?= url('dashboard/admin/audit_logs.php') ?>" class="min-h-[42px] inline-flex items-center justify-center px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl border border-slate-200 text-xs transition">Reset</a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Master faculty security audit trail" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">When (Timestamp)</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Who (Actor)</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">What (Action)</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Which (Target Entity)</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Result (Context / IP)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="py-12 px-5 text-center text-sm text-slate-500">
                                    No audit logs match the selected filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-5 text-slate-600 font-mono text-xs tabular-nums whitespace-nowrap">
                                        <?= formatDate($l['created_at'], 'd M Y H:i:s') ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php if (!empty($l['first_name'])): ?>
                                            <div class="font-medium text-slate-900 text-sm"><?= htmlspecialchars(($l['title'] ? $l['title'] . ' ' : '') . $l['first_name'] . ' ' . $l['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-[11px] text-slate-500 font-bold tracking-wide uppercase"><?= htmlspecialchars(str_replace('_', ' ', (string)$l['role']), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500 italic">System Auto-Process</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-md bg-slate-100 text-lasu-navy font-bold font-mono text-xs">
                                            <?= htmlspecialchars($l['action'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="text-xs font-bold text-slate-800 uppercase tracking-wider"><?= htmlspecialchars(str_replace('_', ' ', (string)$l['entity_type']), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 font-mono mt-0.5 tabular-nums">ID: <?= $l['entity_id'] ?: 'System-Wide' ?></div>
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <div class="text-slate-700 text-xs max-w-sm truncate font-mono" title="<?= htmlspecialchars((string)($l['details'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($l['details'] ?: 'Routine operation completed.', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="text-xs text-slate-500 font-mono tabular-nums mt-1 flex items-center gap-1">
                                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                            <span><?= htmlspecialchars($l['ip_address'], ENT_QUOTES, 'UTF-8') ?></span>
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
