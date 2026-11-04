<?php

/**
 * Academic Structure Management (Admin)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

// Handle Add / Edit Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_course'])) {
    requireCsrf();

    $courseId = (int)($_POST['course_id'] ?? 0);
    $deptId = (int)($_POST['department_id'] ?? 0);
    $levelId = (int)($_POST['level_id'] ?? 0);
    $semesterId = (int)($_POST['semester_id'] ?? 0);
    $courseCode = sanitizeInput($_POST['course_code'] ?? '');
    $courseTitle = sanitizeInput($_POST['course_title'] ?? '');
    $credits = (int)($_POST['credit_units'] ?? 3);

    try {
        if ($deptId <= 0 || $levelId <= 0 || $semesterId <= 0 || empty($courseCode) || empty($courseTitle)) {
            throw new InvalidArgumentException("All course parameters marked with * are required.");
        }

        if ($courseId > 0) {
            $stmtUpd = $pdo->prepare("
                UPDATE courses
                SET department_id = :dept_id, level_id = :lvl_id, semester_id = :sem_id,
                    course_code = :code, course_title = :title, credit_units = :credits
                WHERE id = :id
            ");
            $stmtUpd->execute([
                'dept_id' => $deptId,
                'lvl_id' => $levelId,
                'sem_id' => $semesterId,
                'code' => strtoupper($courseCode),
                'title' => $courseTitle,
                'credits' => $credits,
                'id' => $courseId
            ]);
            logAudit((int)$user['id'], 'update_course', 'course', $courseId, ['code' => $courseCode]);
            setFlash('success', "Course {$courseCode} updated successfully.");
        } else {
            $stmtIns = $pdo->prepare("
                INSERT INTO courses (department_id, level_id, semester_id, course_code, course_title, credit_units, is_active, created_at)
                VALUES (:dept_id, :lvl_id, :sem_id, :code, :title, :credits, 1, NOW())
            ");
            $stmtIns->execute([
                'dept_id' => $deptId,
                'lvl_id' => $levelId,
                'sem_id' => $semesterId,
                'code' => strtoupper($courseCode),
                'title' => $courseTitle,
                'credits' => $credits
            ]);
            $newId = (int)$pdo->lastInsertId();
            logAudit((int)$user['id'], 'create_course', 'course', $newId, ['code' => $courseCode]);
            setFlash('success', "New course {$courseCode} registered successfully.");
        }

        redirect('dashboard/admin/academic_structure.php');
        exit;
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

// Fetch structure data
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$levels = $pdo->query("SELECT * FROM academic_levels ORDER BY level_code ASC")->fetchAll();
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY is_current DESC, session_name DESC")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters")->fetchAll();

// Fetch all courses
$courses = $pdo->query("
    SELECT c.*, d.name AS dept_name, d.code AS dept_code, al.level_name, s.name AS semester_name
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN semesters s ON c.semester_id = s.id
    ORDER BY d.code ASC, c.course_code ASC
")->fetchAll();

$pageTitle = 'Academic Structure & Courses — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Academic Curriculum & Structure</h1>
                <p class="text-xs text-slate-500">Configure faculty departments, academic sessions, and course catalogs</p>
            </div>

            <button type="button" onclick="openCreateCourseModal()" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add New Course</span>
            </button>
        </div>

        <!-- Academic Structure Summaries Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

            <!-- Departments Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center justify-between">
                    <span>Faculty Departments</span>
                    <span class="text-xs text-blue-600 font-bold"><?= count($departments) ?></span>
                </h2>
                <div class="space-y-2">
                    <?php foreach ($departments as $d): ?>
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($d['name']) ?></span>
                            <span class="font-mono text-[10px] bg-slate-200 dark:bg-slate-700 px-1.5 py-0.5 rounded font-bold"><?= htmlspecialchars($d['code']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sessions Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center justify-between">
                    <span>Academic Sessions</span>
                    <span class="text-xs text-blue-600 font-bold"><?= count($sessions) ?></span>
                </h2>
                <div class="space-y-2">
                    <?php foreach ($sessions as $s): ?>
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($s['session_name']) ?></span>
                            <?php if ($s['is_current']): ?>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full uppercase">Current</span>
                            <?php else: ?>
                                <span class="text-[10px] text-slate-400">Past</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Levels Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center justify-between">
                    <span>Academic Levels</span>
                    <span class="text-xs text-blue-600 font-bold"><?= count($levels) ?></span>
                </h2>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($levels as $l): ?>
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 text-xs text-center font-bold text-slate-800 dark:text-slate-200">
                            <?= htmlspecialchars($l['level_name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Courses Master Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Faculty Course Catalog</h2>
                    <p class="text-xs text-slate-500">Registered undergraduate and postgraduate courses</p>
                </div>
                <span class="text-xs text-slate-400 font-mono"><?= count($courses) ?> Total Courses</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code</th>
                            <th class="py-3.5 px-4 font-semibold">Course Title</th>
                            <th class="py-3.5 px-4 font-semibold">Department</th>
                            <th class="py-3.5 px-4 font-semibold">Level & Semester</th>
                            <th class="py-3.5 px-4 font-semibold">Units</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php foreach ($courses as $c):
                            $safeCoursePayload = [
                                'id' => (int)$c['id'],
                                'course_code' => $c['course_code'],
                                'course_title' => $c['course_title'],
                                'credit_units' => (int)$c['credit_units'],
                                'department_id' => (int)$c['department_id'],
                                'level_id' => (int)$c['level_id'],
                                'semester_id' => (int)$c['semester_id']
                            ];
                        ?>
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="py-4 px-4 font-bold font-mono text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($c['course_code']) ?>
                                </td>
                                <td class="py-4 px-4 font-medium text-slate-800 dark:text-slate-200">
                                    <?= htmlspecialchars($c['course_title']) ?>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="font-bold text-blue-900 dark:text-blue-400"><?= htmlspecialchars($c['dept_code']) ?></span>
                                    <span class="text-slate-400 text-[10px] ml-1">(<?= htmlspecialchars($c['dept_name']) ?>)</span>
                                </td>
                                <td class="py-4 px-4">
                                    <div><?= htmlspecialchars($c['level_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= formatSemester($c['semester_name']) ?></div>
                                </td>
                                <td class="py-4 px-4 font-mono font-bold">
                                    <?= $c['credit_units'] ?>
                                </td>
                                <td class="py-4 px-4 text-right">
                                    <button type="button"
                                        data-course="<?= htmlspecialchars(json_encode($safeCoursePayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="openEditCourseModal(this)"
                                        class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition border border-slate-300 dark:border-slate-700">
                                        Edit Course
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- Modal: Course Form -->
<div id="course-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
            <h3 class="text-sm font-bold" id="course-modal-title">Register Course</h3>
            <button type="button" onclick="closeModal('course-modal')" class="text-slate-300 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="<?= url('dashboard/admin/academic_structure.php') ?>" class="p-6 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action_save_course" value="1">
            <input type="hidden" name="course_id" id="form-course-id" value="0">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Course Code *</label>
                    <input type="text" name="course_code" id="form-course-code" required placeholder="e.g. CSC401" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Credit Units *</label>
                    <input type="number" name="credit_units" id="form-credit-units" min="1" max="6" value="3" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Course Title *</label>
                <input type="text" name="course_title" id="form-course-title" required placeholder="e.g. Software Engineering & Architecture" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department *</label>
                    <select name="department_id" id="form-course-dept" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Level *</label>
                    <select name="level_id" id="form-course-level" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                        <?php foreach ($levels as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['level_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Semester *</label>
                    <select name="semester_id" id="form-course-semester" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= $sem['id'] ?>"><?= htmlspecialchars($sem['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeModal('course-modal')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-lasu-navy text-lasu-gold font-bold rounded-lg text-xs shadow">Save Course</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateCourseModal() {
        document.getElementById('course-modal-title').textContent = 'Register New Course';
        document.getElementById('form-course-id').value = '0';
        document.getElementById('form-course-code').value = '';
        document.getElementById('form-course-title').value = '';
        document.getElementById('form-credit-units').value = '3';
        openModal('course-modal');
    }

    function openEditCourseModal(btn) {
        const c = JSON.parse(btn.getAttribute('data-course'));
        document.getElementById('course-modal-title').textContent = 'Edit Course: ' + (c.course_code || '');
        document.getElementById('form-course-id').value = c.id;
        document.getElementById('form-course-code').value = c.course_code || '';
        document.getElementById('form-course-title').value = c.course_title || '';
        document.getElementById('form-credit-units').value = c.credit_units || '3';
        document.getElementById('form-course-dept').value = c.department_id || '';
        document.getElementById('form-course-level').value = c.level_id || '';
        document.getElementById('form-course-semester').value = c.semester_id || '';
        openModal('course-modal');
    }
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
