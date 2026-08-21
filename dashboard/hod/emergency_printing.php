<?php
/**
 * HOD Emergency Printing Approvals
 */
require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);
$hodId = $user['id'];
$pdo = getDb();

// Fetch HOD's department
$stmtHod = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
$stmtHod->execute([$hodId]);
$deptId = $stmtHod->fetchColumn();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_decision'])) {
    requireCsrf();
    $requestId = (int)$_POST['request_id'];
    $decision = $_POST['decision']; // 'approved' or 'rejected'
    
    try {
        PrintingService::authorizeEmergencyPrint($requestId, $hodId, $decision);
        setFlash('success', "Emergency print request successfully {$decision}.");
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
    header("Location: /FOCITECMS/dashboard/hod/emergency_printing.php");
    exit;
}

// Fetch pending requests
$stmt = $pdo->prepare("
    SELECT epr.*, ep.paper_code, c.course_code, c.course_title, pv.version_number,
           u_eo.first_name AS eo_first, u_eo.last_name AS eo_last,
           es.exam_date, es.exam_time
    FROM emergency_print_requests epr
    JOIN examination_papers ep ON epr.paper_id = ep.id
    JOIN courses c ON ep.course_id = c.id
    JOIN paper_versions pv ON epr.version_id = pv.id
    JOIN users u_eo ON epr.exam_officer_id = u_eo.id
    LEFT JOIN exam_schedules es ON ep.exam_schedule_id = es.id
    WHERE c.department_id = :dept_id AND epr.status = 'pending'
    ORDER BY epr.created_at ASC
");
$stmt->execute(['dept_id' => $deptId]);
$requests = $stmt->fetchAll();

$pageTitle = 'Emergency Printing Authorizations — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Emergency Printing Authorizations</h1>
            <p class="text-slate-500 text-sm mt-1">Review and approve requests from Exam Officers to print papers outside of the strict calendar-day window.</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4 font-semibold">Course & Exam Date</th>
                            <th class="py-3 px-4 font-semibold">Paper Version</th>
                            <th class="py-3 px-4 font-semibold">Requested By</th>
                            <th class="py-3 px-4 font-semibold">Emergency Justification</th>
                            <th class="py-3 px-4 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    No pending emergency print requests found for your department.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono"><?= htmlspecialchars($req['course_code']) ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($req['course_title']) ?></div>
                                        <div class="text-xs font-semibold text-cyan-600 dark:text-cyan-400 mt-1">
                                            Exam: <?= $req['exam_date'] ? formatDate($req['exam_date'], 'd M Y') : 'N/A' ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                                            Version <?= $req['version_number'] ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($req['eo_first'] . ' ' . $req['eo_last']) ?></div>
                                        <div class="text-xs text-slate-400"><?= formatDate($req['created_at'], 'd M Y H:i') ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <p class="text-xs bg-amber-50 dark:bg-amber-900/20 text-amber-900 dark:text-amber-200 p-2.5 rounded-lg border border-amber-200 dark:border-amber-800/50">
                                            <?= nl2br(htmlspecialchars($req['reason'])) ?>
                                        </p>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <form method="POST" action="" class="flex items-center justify-end gap-2">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action_decision" value="1">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            
                                            <button type="submit" name="decision" value="approved" class="px-3 py-1.5 bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-semibold rounded-lg text-xs transition shadow-sm">Approve</button>
                                            <button type="submit" name="decision" value="rejected" class="px-3 py-1.5 bg-rose-100 text-rose-700 hover:bg-rose-200 font-semibold rounded-lg text-xs transition shadow-sm" onclick="return confirm('Are you sure you want to reject this print request?');">Reject</button>
                                        </form>
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
