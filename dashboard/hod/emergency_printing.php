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
    redirect('dashboard/hod/emergency_printing.php');
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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Emergency Printing Authorizations</h1>
            <p class="text-slate-600 text-sm mt-0.5">Review and approve requests from Examination Officers to print sealed papers outside the standard day-before window.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Emergency printing authorization requests" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course &amp; Exam Date</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Paper Version</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Requested By</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Emergency Justification</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="5" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No pending emergency print requests</div>
                                    <div class="text-xs text-slate-500 mt-1">All departmental printing follows the standard scheduled window.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($req['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($req['course_title'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs font-semibold text-lasu-navy mt-1 tabular-nums">
                                            Exam: <?= $req['exam_date'] ? formatDate($req['exam_date'], 'd M Y') : 'Unscheduled' ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-slate-100 text-slate-800 tabular-nums">
                                            V<?= (int)$req['version_number'] ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="font-medium text-slate-900"><?= htmlspecialchars($req['eo_first'] . ' ' . $req['eo_last'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 tabular-nums"><?= formatDate($req['created_at'], 'd M Y H:i') ?></div>
                                    </td>
                                    <td class="py-4 px-4 max-w-md">
                                        <p class="text-xs bg-amber-50 text-amber-950 p-3 rounded-xl border border-amber-200 leading-relaxed">
                                            <?= nl2br(htmlspecialchars($req['reason'], ENT_QUOTES, 'UTF-8')) ?>
                                        </p>
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <form method="POST" action="" class="inline-flex items-center justify-end gap-2">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action_decision" value="1">
                                            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">

                                            <button type="submit" name="decision" value="approved"
                                                data-confirm-title="Approve Emergency Print"
                                                data-confirm-message="Authorize out-of-window emergency printing for <?= htmlspecialchars($req['course_code'], ENT_QUOTES, 'UTF-8') ?>? This override is permanently recorded in the audit trail."
                                                data-confirm-label="Approve Override"
                                                data-confirm-variant="primary"
                                                data-loading-text="Approving..."
                                                class="min-h-[36px] px-3.5 py-1.5 bg-emerald-600 text-white hover:bg-emerald-700 font-bold rounded-lg text-xs transition shadow-sm">
                                                Approve
                                            </button>
                                            <button type="submit" name="decision" value="rejected"
                                                data-confirm-title="Reject Emergency Print Request"
                                                data-confirm-message="Reject this emergency print request for <?= htmlspecialchars($req['course_code'], ENT_QUOTES, 'UTF-8') ?>? The Examination Officer will be notified."
                                                data-confirm-label="Reject Request"
                                                data-confirm-variant="danger"
                                                data-loading-text="Rejecting..."
                                                class="min-h-[36px] px-3.5 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 font-bold rounded-lg text-xs transition">
                                                Reject
                                            </button>
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
