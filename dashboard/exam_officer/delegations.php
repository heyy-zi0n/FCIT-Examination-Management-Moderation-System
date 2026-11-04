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

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Exam Officer Authority Delegation</h1>
                <p class="text-xs text-slate-500">Temporarily authorize a colleague to act as Faculty Examination Officer with audited governance</p>
            </div>
            
            <button type="button" onclick="openModal('delegation-modal')" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New EO Delegation</span>
            </button>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Designated Delegate</th>
                            <th class="py-3.5 px-4 font-semibold">Start Date</th>
                            <th class="py-3.5 px-4 font-semibold">End Date</th>
                            <th class="py-3.5 px-4 font-semibold">Reason / Justification</th>
                            <th class="py-3.5 px-4 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($delegations)): ?>
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-400">
                                    No active or historical Exam Officer delegations found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($delegations as $d): 
                                $isActive = ($d['is_active'] && date('Y-m-d') >= $d['start_date'] && date('Y-m-d') <= $d['end_date']);
                            ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4 font-medium">
                                        <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(($d['del_title'] ? $d['del_title'] . ' ' : '') . $d['del_first'] . ' ' . $d['del_last']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($d['del_email']) ?></div>
                                    </td>
                                    <td class="py-4 px-4"><?= formatDate($d['start_date'], 'd M Y') ?></td>
                                    <td class="py-4 px-4"><?= formatDate($d['end_date'], 'd M Y') ?></td>
                                    <td class="py-4 px-4 text-slate-600 dark:text-slate-400 text-xs"><?= htmlspecialchars($d['reason']) ?></td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
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

<!-- Modal: New EO Delegation -->
<div id="delegation-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
            <h3 class="text-sm font-bold">Formal Exam Officer Delegation</h3>
            <button type="button" onclick="closeModal('delegation-modal')" class="text-slate-300 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="<?= url('dashboard/exam_officer/delegations.php') ?>" class="p-6 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action_create_delegation" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Delegate Staff *</label>
                <select name="delegate_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <option value="">Select Departmental Academic Staff</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars(($s['title'] ? $s['title'] . ' ' : '') . $s['first_name'] . ' ' . $s['last_name']) ?> (<?= htmlspecialchars($s['email']) ?> - <?= ucfirst($s['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Start Date *</label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">End Date *</label>
                    <input type="date" name="end_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Delegation Reason *</label>
                <textarea name="reason" rows="3" required placeholder="Official justification for Exam Officer delegation..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeModal('delegation-modal')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-lasu-navy text-lasu-gold font-bold rounded-lg text-xs shadow">Save Delegation</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
