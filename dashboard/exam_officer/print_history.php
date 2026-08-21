<?php
/**
 * Controlled Printing History & Verification Logs (Exam Officer)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$pdo = getDb();

// Search by verification code if provided
$codeSearch = sanitizeInput($_GET['verification_code'] ?? '');
$sql = "
    SELECT pl.*, ep.paper_code, c.course_code, c.course_title, d.name AS dept_name,
           u_eo.first_name AS eo_first, u_eo.last_name AS eo_last, u_eo.title AS eo_title
    FROM print_logs pl
    JOIN examination_papers ep ON pl.paper_id = ep.id
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u_eo ON pl.exam_officer_id = u_eo.id
";
$params = [];
if (!empty($codeSearch)) {
    $sql .= " WHERE pl.verification_code LIKE :code";
    $params['code'] = "%{$codeSearch}%";
}
$sql .= " ORDER BY pl.printed_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Print Logs & Verification Trail — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Controlled Printing Audit Logs</h1>
                <p class="text-xs text-slate-500">Immutable ledger of all examination papers released and printed with cryptographic serial codes</p>
            </div>

            <!-- Search by Verification Code -->
            <form method="GET" action="/FOCITECMS/dashboard/exam_officer/print_history.php" class="flex items-center gap-2">
                <input type="text" name="verification_code" placeholder="Search Serial / Code..." value="<?= htmlspecialchars($codeSearch) ?>" class="px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                <button type="submit" class="px-4 py-2 bg-lasu-navy text-lasu-gold font-bold rounded-xl text-xs shadow transition">Search</button>
            </form>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Verification Serial Code</th>
                            <th class="py-3.5 px-4 font-semibold">Course & Department</th>
                            <th class="py-3.5 px-4 font-semibold">Authorized By</th>
                            <th class="py-3.5 px-4 font-semibold">Copies</th>
                            <th class="py-3.5 px-4 font-semibold">Batch Type</th>
                            <th class="py-3.5 px-4 font-semibold">Client IP</th>
                            <th class="py-3.5 px-4 font-semibold">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    No controlled print logs recorded.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                        <span class="bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-cyan-600 dark:text-cyan-400"><?= htmlspecialchars($l['verification_code']) ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono"><?= htmlspecialchars($l['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($l['course_title']) ?> (<?= htmlspecialchars($l['dept_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-medium">
                                        <?= htmlspecialchars(($l['eo_title'] ? $l['eo_title'] . ' ' : '') . $l['eo_first'] . ' ' . $l['eo_last']) ?>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                                        <?= $l['copies_printed'] ?> Copies
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php if ($l['is_reprint']): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800" title="<?= htmlspecialchars($l['reprint_reason'] ?? '') ?>">Reprint</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Initial Print</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 font-mono text-[11px] text-slate-500">
                                        <?= htmlspecialchars($l['client_ip']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-500 text-[11px]">
                                        <?= formatDate($l['printed_at']) ?>
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
