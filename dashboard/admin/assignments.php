<?php
/**
 * Course & Staff Allocations (Administrator)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

// Handle Assign Lecturer to Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign_lecturer'])) {
    requireCsrf();

    $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $sessionId = (int)($_POST['academic_session_id'] ?? 0);

    try {
        if ($lecturerId <= 0 || $courseId <= 0 || $sessionId <= 0) {
            throw new InvalidArgumentException("Please select a lecturer, course, and academic session.");
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO lecturer_course_assignments (lecturer_id, course_id, academic_session_id, assigned_by, assigned_at)
            VALUES (:lid, :cid, :sid, :uid, NOW())
            ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = NOW()
        ");
        $stmtIns->execute([
            'lid' => $lecturerId,
            'cid' => $courseId,
            'sid' => $sessionId,
            'uid' => (int)$user['id']
        ]);

        logAudit((int)$user['id'], 'assign_lecturer_course', 'lecturer_course_assignment', (int)$pdo->lastInsertId(), [
            'lecturer_id' => $lecturerId,
            'course_id' => $courseId,
            'session_id' => $sessionId
        ]);

        setFlash('success', "Lecturer allocated to course successfully.");
        header("Location: /FOCITECMS/dashboard/admin/assignments.php");
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

// Fetch lecturers, courses, sessions
$lecturers = $pdo->query("SELECT id, title, first_name, last_name, email, department_id FROM users WHERE role IN ('lecturer', 'hod', 'moderator') ORDER BY first_name ASC")->fetchAll();
$courses = $pdo->query("SELECT c.*, d.code AS dept_code FROM courses c JOIN departments d ON c.department_id = d.id ORDER BY d.code ASC, c.course_code ASC")->fetchAll();
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY is_current DESC")->fetchAll();

// Fetch active allocations
$assignments = $pdo->query("
    SELECT lca.*, u.first_name, u.last_name, u.title, u.email,
           c.course_code, c.course_title, d.code AS dept_code, al.level_name,
           s.session_name
    FROM lecturer_course_assignments lca
    JOIN users u ON lca.lecturer_id = u.id
    JOIN courses c ON lca.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN academic_sessions s ON lca.academic_session_id = s.id
    ORDER BY d.code ASC, c.course_code ASC
")->fetchAll();

$pageTitle = 'Lecturer Course Allocations — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Lecturer Course Allocations</h1>
                <p class="text-xs text-slate-500">Map academic teaching staff to courses for examination creation privileges</p>
            </div>
            
            <button type="button" onclick="openModal('assign-modal')" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Allocate Course</span>
            </button>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Department & Level</th>
                            <th class="py-3.5 px-4 font-semibold">Allocated Lecturer</th>
                            <th class="py-3.5 px-4 font-semibold">Session</th>
                            <th class="py-3.5 px-4 font-semibold">Date Assigned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-400">
                                    No course allocations found. Click "Allocate Course" to assign lecturers to courses.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $a): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($a['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($a['course_title']) ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="font-bold text-blue-900 dark:text-blue-400"><?= htmlspecialchars($a['dept_code']) ?></span>
                                        <span class="text-slate-400 ml-1">• <?= htmlspecialchars($a['level_name']) ?></span>
                                    </td>
                                    <td class="py-4 px-4 font-medium">
                                        <div class="text-slate-900 dark:text-white font-bold"><?= htmlspecialchars(($a['title'] ? $a['title'] . ' ' : '') . $a['first_name'] . ' ' . $a['last_name']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($a['email']) ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?= htmlspecialchars($a['session_name']) ?>
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

<!-- Modal: Allocate Course -->
<div id="assign-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
            <h3 class="text-sm font-bold">Allocate Course to Lecturer</h3>
            <button type="button" onclick="closeModal('assign-modal')" class="text-slate-300 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="/FOCITECMS/dashboard/admin/assignments.php" class="p-6 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action_assign_lecturer" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Lecturer *</label>
                <select name="lecturer_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <option value="">Select Academic Staff</option>
                    <?php foreach ($lecturers as $l): ?>
                        <option value="<?= $l['id'] ?>">
                            <?= htmlspecialchars(($l['title'] ? $l['title'] . ' ' : '') . $l['first_name'] . ' ' . $l['last_name']) ?> (<?= htmlspecialchars($l['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Course *</label>
                <select name="course_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            [<?= htmlspecialchars($c['dept_code']) ?>] <?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Academic Session *</label>
                <select name="academic_session_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['is_current'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['session_name']) ?>
                        </option>
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
