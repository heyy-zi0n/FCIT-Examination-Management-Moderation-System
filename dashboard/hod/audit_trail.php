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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Departmental Security &amp; Audit Trail</h1>
            <p class="text-sm text-slate-600 mt-0.5">Department of <?= htmlspecialchars($user['department_name'] ?? 'Computing', ENT_QUOTES, 'UTF-8') ?> &bull; Immutable chronological log of examination actions</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Departmental security and audit trail" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Action / Event</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">User &amp; Role</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Entity</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Details / Parameters</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">IP Address</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="py-12 px-5 text-center text-sm text-slate-500">
                                    No audit logs recorded for this department yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-5 whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-md bg-slate-100 text-lasu-navy font-mono text-xs font-bold">
                                            <?= htmlspecialchars($l['action'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php if (!empty($l['first_name'])): ?>
                                            <div class="font-medium text-slate-900 text-sm"><?= htmlspecialchars(($l['title'] ? $l['title'] . ' ' : '') . $l['first_name'] . ' ' . $l['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-xs text-slate-500 uppercase tracking-wide font-semibold"><?= htmlspecialchars(str_replace('_', ' ', (string)$l['role']), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500">System / Anonymous</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 font-mono text-xs whitespace-nowrap">
                                        <?= htmlspecialchars($l['entity_type'], ENT_QUOTES, 'UTF-8') ?> #<?= $l['entity_id'] ?: '—' ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate font-mono text-xs" title="<?= htmlspecialchars((string)($l['details'] ?: ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($l['details'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-xs text-slate-600 tabular-nums whitespace-nowrap">
                                        <?= htmlspecialchars($l['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3.5 px-5 text-slate-600 text-xs tabular-nums whitespace-nowrap">
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
