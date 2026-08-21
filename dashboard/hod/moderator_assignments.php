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
        header("Location: /FOCITECMS/dashboard/hod/moderator_assignments.php");
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

// Fetch department staff eligible for moderation
$moderators = $pdo->query("SELECT id, title, first_name, last_name, email FROM users WHERE department_id = {$deptId} AND role IN ('moderator', 'lecturer') ORDER BY first_name ASC")->fetchAll();
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

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Departmental Moderator Allocations</h1>
                <p class="text-xs text-slate-500">Designate peer reviewers and moderators across academic class levels</p>
            </div>
            
            <button type="button" onclick="openModal('assign-modal')" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Allocate Moderator</span>
            </button>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Academic Level</th>
                            <th class="py-3.5 px-4 font-semibold">Assigned Moderator</th>
                            <th class="py-3.5 px-4 font-semibold">Academic Session</th>
                            <th class="py-3.5 px-4 font-semibold">Assigned By</th>
                            <th class="py-3.5 px-4 font-semibold">Date Allocated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-400">
                                    No moderator assignments configured. Click "Allocate Moderator" to designate level moderators.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $a): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                                        <span class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 font-mono text-xs"><?= htmlspecialchars($a['level_name']) ?></span>
                                    </td>
                                    <td class="py-4 px-4 font-medium">
                                        <div class="text-slate-900 dark:text-white font-bold"><?= htmlspecialchars(($a['title'] ? $a['title'] . ' ' : '') . $a['first_name'] . ' ' . $a['last_name']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($a['email']) ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= htmlspecialchars($a['session_name']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-500">
                                        <?= htmlspecialchars(($a['assigner_first'] ?? 'HOD') . ' ' . ($a['assigner_last'] ?? '')) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-500 text-[11px]">
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

<!-- Modal: Allocate Moderator -->
<div id="assign-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
            <h3 class="text-sm font-bold">Allocate Departmental Moderator</h3>
            <button type="button" onclick="closeModal('assign-modal')" class="text-slate-300 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="/FOCITECMS/dashboard/hod/moderator_assignments.php" class="p-6 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action_assign_moderator" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Academic Staff *</label>
                <select name="moderator_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <option value="">Select Staff Member</option>
                    <?php foreach ($moderators as $m): ?>
                        <option value="<?= $m['id'] ?>">
                            <?= htmlspecialchars(($m['title'] ? $m['title'] . ' ' : '') . $m['first_name'] . ' ' . $m['last_name']) ?> (<?= htmlspecialchars($m['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Academic Class Level *</label>
                <select name="level_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['level_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Academic Session *</label>
                <select name="academic_session_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-4 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeModal('assign-modal')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-lasu-navy text-lasu-gold font-bold rounded-lg text-xs shadow">Save Allocation</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
