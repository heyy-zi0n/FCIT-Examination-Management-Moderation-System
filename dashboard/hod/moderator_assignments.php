<?php
/**
 * Departmental Moderator Allocations Manager (HOD)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);

$pdo = getDb();
$deptId = (int)$user['department_id'];

// Handle Add Moderator Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign_moderator'])) {
    requireCsrf();

    $moderatorId = (int)($_POST['moderator_id'] ?? 0);
    $levelId = (int)($_POST['level_id'] ?? 0);
    $sessionId = (int)($_POST['academic_session_id'] ?? 0);

    try {
        if ($moderatorId <= 0 || $levelId <= 0 || $sessionId <= 0) {
            throw new InvalidArgumentException("Please select a moderator, level, and academic session.");
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO moderator_assignments (moderator_id, department_id, level_id, academic_session_id, assigned_by, assigned_at)
            VALUES (:mod_id, :dept_id, :lvl_id, :sid, :uid, NOW())
            ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = NOW()
        ");
        $stmtIns->execute([
            'mod_id' => $moderatorId,
            'dept_id' => $deptId,
            'lvl_id' => $levelId,
            'sid' => $sessionId,
            'uid' => (int)$user['id']
        ]);

        logAudit((int)$user['id'], 'assign_moderator', 'moderator_assignment', (int)$pdo->lastInsertId(), [
            'moderator_id' => $moderatorId,
            'level_id' => $levelId,
            'session_id' => $sessionId
        ]);

        setFlash('success', "Moderator assigned successfully to the selected academic level.");
        redirect('dashboard/hod/moderator_assignments.php');
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

// Fetch department staff eligible for moderation
$stmtMods = $pdo->prepare("SELECT id, title, first_name, last_name, email FROM users WHERE department_id = :dept_id AND role IN ('moderator', 'lecturer') AND status = 'active' ORDER BY first_name ASC");
$stmtMods->execute(['dept_id' => $deptId]);
$moderators = $stmtMods->fetchAll();

$levels = $pdo->query("SELECT * FROM academic_levels ORDER BY level_code ASC")->fetchAll();
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY is_current DESC")->fetchAll();

// Fetch active assignments
$stmtActive = $pdo->prepare("
    SELECT ma.*, u.first_name, u.last_name, u.title, u.email, al.level_name, s.session_name,
           u_assign.first_name AS assigner_first, u_assign.last_name AS assigner_last
    FROM moderator_assignments ma
    JOIN users u ON ma.moderator_id = u.id
    JOIN academic_levels al ON ma.level_id = al.id
    JOIN academic_sessions s ON ma.academic_session_id = s.id
    LEFT JOIN users u_assign ON ma.assigned_by = u_assign.id
    WHERE ma.department_id = :dept_id
    ORDER BY al.level_code ASC, ma.assigned_at DESC
");
$stmtActive->execute(['dept_id' => $deptId]);
$assignments = $stmtActive->fetchAll();

$pageTitle = 'Moderator Allocations — HOD Portal — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Departmental Moderator Allocations</h1>
                <p class="text-sm text-slate-600 mt-0.5">Designate peer reviewers and moderators across academic class levels</p>
            </div>

            <button type="button" onclick="openModal('assign-modal')" class="min-h-[40px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Allocate Moderator</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Departmental moderator allocations" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Academic Level</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Assigned Moderator</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Academic Session</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Assigned By</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Date Allocated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="5" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No moderator allocations configured</div>
                                    <div class="text-xs text-slate-500 mt-1">Select &ldquo;Allocate Moderator&rdquo; to designate academic level moderators.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $a): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5 font-bold text-slate-900">
                                        <span class="px-2.5 py-1 rounded-md bg-slate-100 font-mono text-xs"><?= htmlspecialchars($a['level_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="text-slate-900 font-bold text-sm"><?= htmlspecialchars(($a['title'] ? $a['title'] . ' ' : '') . $a['first_name'] . ' ' . $a['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="py-4 px-4 font-medium tabular-nums">
                                        <?= htmlspecialchars($a['session_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-600">
                                        <?= htmlspecialchars(trim(($a['assigner_first'] ?? 'HOD') . ' ' . ($a['assigner_last'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-4 px-5 text-slate-600 text-xs tabular-nums whitespace-nowrap">
                                        <?= formatDate($a['assigned_at']) ?>
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

<!-- Native Dialog: Allocate Moderator -->
<dialog id="assign-modal" closedby="any" aria-labelledby="assign-modal-title" class="rounded-2xl shadow-2xl border border-slate-200 bg-white text-slate-800 w-[min(28rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
        <h2 id="assign-modal-title" class="text-sm font-bold">Allocate Departmental Moderator</h2>
        <button type="button" onclick="closeModal('assign-modal')" aria-label="Close modal" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition">&times;</button>
    </div>
    <form method="POST" action="<?= url('dashboard/hod/moderator_assignments.php') ?>" class="p-6 space-y-4">
        <?= csrfField() ?>
        <input type="hidden" name="action_assign_moderator" value="1">

        <div>
            <label for="mod_staff_id" class="block text-xs font-semibold text-slate-700 mb-1">Select Academic Staff <span class="text-rose-600">*</span></label>
            <select id="mod_staff_id" name="moderator_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <option value="">Select Staff Member</option>
                <?php foreach ($moderators as $m): ?>
                    <option value="<?= (int)$m['id'] ?>">
                        <?= htmlspecialchars(($m['title'] ? $m['title'] . ' ' : '') . $m['first_name'] . ' ' . $m['last_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($m['email'], ENT_QUOTES, 'UTF-8') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="mod_level_id" class="block text-xs font-semibold text-slate-700 mb-1">Academic Class Level <span class="text-rose-600">*</span></label>
            <select id="mod_level_id" name="level_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <?php foreach ($levels as $l): ?>
                    <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['level_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="mod_session_id" class="block text-xs font-semibold text-slate-700 mb-1">Academic Session <span class="text-rose-600">*</span></label>
            <select id="mod_session_id" name="academic_session_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <?php foreach ($sessions as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['session_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="pt-4 flex items-center justify-end gap-2.5 border-t border-slate-100">
            <button type="button" onclick="closeModal('assign-modal')" class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">Cancel</button>
            <button type="submit" data-loading-text="Saving..." class="min-h-[38px] px-5 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition">Save Allocation</button>
        </div>
    </form>
</dialog>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
