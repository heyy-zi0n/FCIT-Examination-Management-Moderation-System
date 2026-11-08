<?php
/**
 * Formal Exam Officer Role Delegation
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$pdo = getDb();
$deptId = (int)($user['department_id'] ?? 0);

// Handle Add Delegation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_delegation'])) {
    requireCsrf();

    $delegateId = (int)($_POST['delegate_id'] ?? 0);
    $startDate = sanitizeInput($_POST['start_date'] ?? '');
    $endDate = sanitizeInput($_POST['end_date'] ?? '');
    $reason = sanitizeInput($_POST['reason'] ?? '');

    try {
        if ($deptId <= 0) {
            throw new RuntimeException("Cannot create delegation: Your account is not assigned to a valid department.");
        }

        if ($delegateId <= 0 || empty($startDate) || empty($endDate) || empty(trim($reason))) {
            throw new InvalidArgumentException("All fields are required to create an Exam Officer delegation.");
        }

        if ($delegateId === (int)$user['id']) {
            throw new InvalidArgumentException("You cannot delegate authority to yourself.");
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            throw new InvalidArgumentException("End date cannot be earlier than start date.");
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO delegations (delegator_id, delegate_id, role_delegated, department_id, start_date, end_date, reason, is_active, created_at)
            VALUES (:uid, :did, 'exam_officer', :dept_id, :s_dt, :e_dt, :reason, 1, NOW())
        ");
        $stmtIns->execute([
            'uid' => (int)$user['id'],
            'did' => $delegateId,
            'dept_id' => $deptId,
            's_dt' => $startDate,
            'e_dt' => $endDate,
            'reason' => $reason
        ]);

        logAudit((int)$user['id'], 'create_eo_delegation', 'delegation', (int)$pdo->lastInsertId(), [
            'delegate_id' => $delegateId,
            'department_id' => $deptId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        setFlash('success', "Exam Officer authority delegated successfully for the specified period.");
        redirect('dashboard/exam_officer/delegations.php');
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

// Fetch active academic staff in this department eligible for EO delegation
$stmtStaff = $pdo->prepare("
    SELECT id, title, first_name, last_name, email, role
    FROM users
    WHERE id != :uid AND department_id = :dept_id AND status = 'active'
    ORDER BY first_name ASC
");
$stmtStaff->execute(['uid' => (int)$user['id'], 'dept_id' => $deptId]);
$staff = $stmtStaff->fetchAll();

// Fetch active & past EO delegations
$stmtDel = $pdo->prepare("
    SELECT d.*, u.first_name AS del_first, u.last_name AS del_last, u.title AS del_title, u.email AS del_email
    FROM delegations d
    JOIN users u ON d.delegate_id = u.id
    WHERE d.delegator_id = :uid AND d.role_delegated = 'exam_officer'
    ORDER BY d.created_at DESC
");
$stmtDel->execute(['uid' => (int)$user['id']]);
$delegations = $stmtDel->fetchAll();

$pageTitle = 'Exam Officer Delegations — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Exam Officer Authority Delegation</h1>
                <p class="text-sm text-slate-600 mt-0.5">Temporarily authorize a colleague to act as Examination Officer with audited governance</p>
            </div>

            <button type="button" onclick="openModal('delegation-modal')" class="min-h-[40px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New EO Delegation</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Exam Officer delegations" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Designated Delegate</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Start Date</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">End Date</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Reason / Justification</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($delegations)): ?>
                            <tr>
                                <td colspan="5" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No active or historical Exam Officer delegations found</div>
                                    <div class="text-xs text-slate-500 mt-1">Select &ldquo;New EO Delegation&rdquo; to record a time-bound acting authorization.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($delegations as $d):
                                $isActive = ($d['is_active'] && date('Y-m-d') >= $d['start_date'] && date('Y-m-d') <= $d['end_date']);
                            ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars(($d['del_title'] ? $d['del_title'] . ' ' : '') . $d['del_first'] . ' ' . $d['del_last'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($d['del_email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="py-4 px-4 tabular-nums whitespace-nowrap"><?= formatDate($d['start_date'], 'd M Y') ?></td>
                                    <td class="py-4 px-4 tabular-nums whitespace-nowrap"><?= formatDate($d['end_date'], 'd M Y') ?></td>
                                    <td class="py-4 px-4 text-slate-600 text-xs max-w-md leading-relaxed"><?= htmlspecialchars($d['reason'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-4 px-5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border <?= $isActive ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>">
                                            <?= $isActive ? 'Currently Active' : 'Inactive / Expired' ?>
                                        </span>
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

<!-- Native Dialog: New EO Delegation -->
<dialog id="delegation-modal" closedby="any" aria-labelledby="delegation-modal-title" class="rounded-2xl shadow-2xl border border-slate-200 bg-white text-slate-800 w-[min(28rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
        <h2 id="delegation-modal-title" class="text-sm font-bold">Formal Exam Officer Delegation</h2>
        <button type="button" onclick="closeModal('delegation-modal')" aria-label="Close modal" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition">&times;</button>
    </div>
    <form method="POST" action="<?= url('dashboard/exam_officer/delegations.php') ?>" class="p-6 space-y-4">
        <?= csrfField() ?>
        <input type="hidden" name="action_create_delegation" value="1">

        <div>
            <label for="eo_del_staff_id" class="block text-xs font-semibold text-slate-700 mb-1">Select Delegate Staff <span class="text-rose-600">*</span></label>
            <select id="eo_del_staff_id" name="delegate_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <option value="">Select Departmental Academic Staff</option>
                <?php foreach ($staff as $s): ?>
                    <option value="<?= (int)$s['id'] ?>">
                        <?= htmlspecialchars(($s['title'] ? $s['title'] . ' ' : '') . $s['first_name'] . ' ' . $s['last_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?> &mdash; <?= ucfirst(htmlspecialchars(str_replace('_', ' ', (string)$s['role']), ENT_QUOTES, 'UTF-8')) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <label for="eo_del_start" class="block text-xs font-semibold text-slate-700 mb-1">Start Date <span class="text-rose-600">*</span></label>
                <input id="eo_del_start" type="date" name="start_date" required value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
            </div>
            <div>
                <label for="eo_del_end" class="block text-xs font-semibold text-slate-700 mb-1">End Date <span class="text-rose-600">*</span></label>
                <input id="eo_del_end" type="date" name="end_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
            </div>
        </div>

        <div>
            <label for="eo_del_reason" class="block text-xs font-semibold text-slate-700 mb-1">Delegation Reason <span class="text-rose-600">*</span></label>
            <textarea id="eo_del_reason" name="reason" rows="3" required placeholder="Official justification for Exam Officer delegation..." class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy"></textarea>
        </div>

        <div class="pt-4 flex items-center justify-end gap-2.5 border-t border-slate-100">
            <button type="button" onclick="closeModal('delegation-modal')" class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">Cancel</button>
            <button type="submit" data-loading-text="Saving..." class="min-h-[38px] px-5 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition">Save Delegation</button>
        </div>
    </form>
</dialog>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
