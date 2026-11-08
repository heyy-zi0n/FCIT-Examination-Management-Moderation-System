<?php
/**
 * Controlled Printing History & Verification Logs (Exam Officer)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$pdo = getDb();
$deptId = !empty($user['department_id']) ? (int)$user['department_id'] : null;

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
$conditions = [];
$params = [];

if ($deptId !== null) {
    $conditions[] = "c.department_id = :dept_id";
    $params['dept_id'] = $deptId;
}
if (!empty($codeSearch)) {
    $conditions[] = "pl.verification_code LIKE :code";
    $params['code'] = "%{$codeSearch}%";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Controlled Printing Audit Logs</h1>
                <p class="text-sm text-slate-600 mt-0.5">Immutable ledger of examination papers released and printed with cryptographic serial codes</p>
            </div>

            <!-- Search by Verification Code -->
            <form method="GET" action="<?= url('dashboard/exam_officer/print_history.php') ?>" class="flex items-center gap-2 w-full sm:w-auto">
                <label for="verification_code_search" class="sr-only">Search Verification Serial Code</label>
                <input id="verification_code_search" type="search" name="verification_code" placeholder="Search Serial / Code..." value="<?= htmlspecialchars($codeSearch, ENT_QUOTES, 'UTF-8') ?>" class="flex-1 sm:w-56 px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                <button type="submit" class="min-h-[38px] px-4 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition">Search</button>
                <?php if (!empty($codeSearch)): ?>
                    <a href="<?= url('dashboard/exam_officer/print_history.php') ?>" class="min-h-[38px] px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs inline-flex items-center transition">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Controlled printing audit logs" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Verification Serial Code</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Course &amp; Department</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Authorized By</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Copies</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Batch Type</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Client IP</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No controlled print logs recorded<?= !empty($codeSearch) ? ' matching "' . htmlspecialchars($codeSearch, ENT_QUOTES, 'UTF-8') . '"' : '' ?></div>
                                    <div class="text-xs text-slate-500 mt-1">Authorized print executions are automatically logged here with unique verification serials.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5 font-mono font-bold whitespace-nowrap">
                                        <span class="bg-cyan-50 border border-cyan-200 px-2.5 py-1 rounded-md text-xs text-cyan-900"><?= htmlspecialchars($l['verification_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($l['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($l['course_title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($l['dept_name'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-slate-800">
                                        <?= htmlspecialchars(($l['eo_title'] ? $l['eo_title'] . ' ' : '') . $l['eo_first'] . ' ' . $l['eo_last'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                        <?= (int)$l['copies_printed'] ?> Copies
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <?php if ($l['is_reprint']): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200" title="<?= htmlspecialchars((string)($l['reprint_reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Reprint</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">Initial Print</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 font-mono text-xs text-slate-600 tabular-nums whitespace-nowrap">
                                        <?= htmlspecialchars($l['client_ip'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-5 text-slate-600 text-xs tabular-nums whitespace-nowrap">
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
