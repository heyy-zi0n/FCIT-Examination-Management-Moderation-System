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
        redirect('dashboard/admin/assignments.php');
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

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Lecturer Course Allocations</h1>
                <p class="text-sm text-slate-600 mt-0.5">Map academic teaching staff to courses for examination creation privileges</p>
            </div>

            <button type="button" onclick="openModal('assign-modal')" class="min-h-[40px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Allocate Course</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Lecturer course allocations" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Title</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Department &amp; Level</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Allocated Lecturer</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Session</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Date Assigned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="5" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No course allocations found</div>
                                    <div class="text-xs text-slate-500 mt-1">Select &ldquo;Allocate Course&rdquo; to assign lecturers to courses.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $a): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($a['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($a['course_title'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <span class="font-bold text-lasu-navy font-mono text-xs"><?= htmlspecialchars($a['dept_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-slate-400 mx-1" aria-hidden="true">&bull;</span>
                                        <span class="text-xs text-slate-600"><?= htmlspecialchars($a['level_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="text-slate-900 font-bold text-sm"><?= htmlspecialchars(($a['title'] ? $a['title'] . ' ' : '') . $a['first_name'] . ' ' . $a['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="py-4 px-4 font-medium tabular-nums">
                                        <?= htmlspecialchars($a['session_name'], ENT_QUOTES, 'UTF-8') ?>
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

<!-- Native Dialog: Allocate Course -->
<dialog id="assign-modal" closedby="any" aria-labelledby="assign-modal-title" class="rounded-2xl shadow-2xl border border-slate-200 bg-white text-slate-800 w-[min(28rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
        <h2 id="assign-modal-title" class="text-sm font-bold">Allocate Course to Lecturer</h2>
        <button type="button" onclick="closeModal('assign-modal')" aria-label="Close modal" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition">&times;</button>
    </div>
    <form method="POST" action="<?= url('dashboard/admin/assignments.php') ?>" class="p-6 space-y-4">
        <?= csrfField() ?>
        <input type="hidden" name="action_assign_lecturer" value="1">

        <div>
            <label for="alloc_lecturer_id" class="block text-xs font-semibold text-slate-700 mb-1">Select Lecturer <span class="text-rose-600">*</span></label>
            <select id="alloc_lecturer_id" name="lecturer_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <option value="">Select Academic Staff</option>
                <?php foreach ($lecturers as $l): ?>
                    <option value="<?= (int)$l['id'] ?>">
                        <?= htmlspecialchars(($l['title'] ? $l['title'] . ' ' : '') . $l['first_name'] . ' ' . $l['last_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($l['email'], ENT_QUOTES, 'UTF-8') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="alloc_course_id" class="block text-xs font-semibold text-slate-700 mb-1">Select Course <span class="text-rose-600">*</span></label>
            <select id="alloc_course_id" name="course_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <option value="">Select Course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>">
                        [<?= htmlspecialchars($c['dept_code'], ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($c['course_code'], ENT_QUOTES, 'UTF-8') ?> &mdash; <?= htmlspecialchars($c['course_title'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="alloc_session_id" class="block text-xs font-semibold text-slate-700 mb-1">Academic Session <span class="text-rose-600">*</span></label>
            <select id="alloc_session_id" name="academic_session_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <?php foreach ($sessions as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $s['is_current'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['session_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
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
