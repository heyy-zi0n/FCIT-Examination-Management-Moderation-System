<?php
/**
 * Departmental Audit Trail & Governance Logs (HOD)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);

$pdo = getDb();
$deptId = (int)$user['department_id'];

// Fetch departmental audit logs
$stmt = $pdo->prepare("
    SELECT al.*, u.first_name, u.last_name, u.title, u.role, u.email
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE u.department_id = :d1
       OR al.entity_id IN (SELECT ep.id FROM examination_papers ep JOIN courses c ON ep.course_id = c.id WHERE c.department_id = :d2)
    ORDER BY al.created_at DESC
    LIMIT 100
");
$stmt->execute(['d1' => $deptId, 'd2' => $deptId]);
$logs = $stmt->fetchAll();

$pageTitle = 'Department Audit Trail — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Departmental Security & Audit Trail</h1>
            <p class="text-xs text-slate-500">Department of <?= htmlspecialchars($user['department_name']) ?> • Immutable chronological log of all examination actions</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Action / Event</th>
                            <th class="py-3.5 px-4 font-semibold">User & Role</th>
                            <th class="py-3.5 px-4 font-semibold">Entity Type</th>
                            <th class="py-3.5 px-4 font-semibold">Details / Parameters</th>
                            <th class="py-3.5 px-4 font-semibold">IP Address</th>
                            <th class="py-3.5 px-4 font-semibold">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    No audit logs recorded for this department yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-3.5 px-4 font-mono font-bold text-blue-600 dark:text-blue-400">
                                        <?= htmlspecialchars($l['action']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-medium">
                                        <?php if (!empty($l['first_name'])): ?>
                                            <div><?= htmlspecialchars(($l['title'] ? $l['title'] . ' ' : '') . $l['first_name'] . ' ' . $l['last_name']) ?></div>
                                            <div class="text-[10px] text-slate-400"><?= ucfirst($l['role']) ?></div>
                                        <?php else: ?>
                                            <span class="text-slate-400">System / Anonymous</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 font-mono text-[11px]">
                                        <?= htmlspecialchars($l['entity_type']) ?> #<?= $l['entity_id'] ?: '—' ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 max-w-xs truncate text-[11px]">
                                        <?= htmlspecialchars($l['details'] ?: '—') ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500">
                                        <?= htmlspecialchars($l['ip_address']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                        <?= formatDate($l['created_at']) ?>
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
