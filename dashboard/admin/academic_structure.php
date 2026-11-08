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

$pageTitle = 'Academic Structure & Courses — LASU FCIT EMMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Academic Curriculum &amp; Structure</h1>
                <p class="text-sm text-slate-600 mt-0.5">Configure faculty departments, academic sessions, and course catalogs.</p>
            </div>

            <button type="button" onclick="openCreateCourseModal()" class="min-h-[42px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add New Course</span>
            </button>
        </div>

        <!-- Academic Structure Summaries Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Departments Card -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center justify-between">
                    <span>Faculty Departments</span>
                    <span class="text-xs text-lasu-navy bg-slate-100 px-2 py-0.5 rounded-full font-bold tabular-nums"><?= count($departments) ?></span>
                </h2>
                <div class="space-y-2">
                    <?php foreach ($departments as $d): ?>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-2 text-xs">
                            <span class="font-bold text-slate-900"><?= htmlspecialchars($d['name']) ?></span>
                            <span class="font-mono text-[11px] bg-slate-200 text-slate-800 px-2 py-0.5 rounded font-bold shrink-0"><?= htmlspecialchars($d['code']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sessions Card -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center justify-between">
                    <span>Academic Sessions</span>
                    <span class="text-xs text-lasu-navy bg-slate-100 px-2 py-0.5 rounded-full font-bold tabular-nums"><?= count($sessions) ?></span>
                </h2>
                <div class="space-y-2">
                    <?php foreach ($sessions as $s): ?>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-2 text-xs">
                            <span class="font-bold text-slate-900 tabular-nums"><?= htmlspecialchars($s['session_name']) ?></span>
                            <?php if ($s['is_current']): ?>
                                <span class="text-[11px] bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold px-2.5 py-0.5 rounded-full">Current</span>
                            <?php else: ?>
                                <span class="text-xs text-slate-500">Past</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Levels Card -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center justify-between">
                    <span>Academic Levels</span>
                    <span class="text-xs text-lasu-navy bg-slate-100 px-2 py-0.5 rounded-full font-bold tabular-nums"><?= count($levels) ?></span>
                </h2>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($levels as $l): ?>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 text-xs text-center font-bold text-slate-900 tabular-nums">
                            <?= htmlspecialchars($l['level_name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Courses Master Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Faculty Course Catalog</h2>
                    <p class="text-xs text-slate-600">Registered undergraduate and postgraduate courses</p>
                </div>
                <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-3 py-1 rounded-full font-mono tabular-nums"><?= count($courses) ?> Total Courses</span>
            </div>

            <div class="table-scroll-region" tabindex="0" role="region" aria-label="Faculty course catalog">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" class="py-3.5 px-4 font-bold">Course Code</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Course Title</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Department</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Level &amp; Semester</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Units</th>
                            <th scope="col" class="py-3.5 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
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
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-4 font-bold font-mono text-sm text-slate-900 whitespace-nowrap">
                                    <?= htmlspecialchars($c['course_code']) ?>
                                </td>
                                <td class="py-4 px-4 font-medium text-sm text-slate-900">
                                    <?= htmlspecialchars($c['course_title']) ?>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="font-bold text-lasu-navy"><?= htmlspecialchars($c['dept_code']) ?></span>
                                    <span class="text-slate-500 text-xs ml-1">(<?= htmlspecialchars($c['dept_name']) ?>)</span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-slate-800 tabular-nums"><?= htmlspecialchars($c['level_name']) ?></div>
                                    <div class="text-xs text-slate-500"><?= formatSemester($c['semester_name']) ?></div>
                                </td>
                                <td class="py-4 px-4 font-mono text-sm font-bold text-slate-900 tabular-nums">
                                    <?= (int)$c['credit_units'] ?>
                                </td>
                                <td class="py-4 px-4 text-right whitespace-nowrap">
                                    <button type="button"
                                        data-course="<?= htmlspecialchars(json_encode($safeCoursePayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="openEditCourseModal(this)"
                                        class="min-h-[34px] px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-800 rounded-lg text-xs font-semibold transition border border-slate-300">
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

<!-- Native Dialog: Course Form -->
<dialog id="course-modal" closedby="any" aria-labelledby="course-modal-title" class="rounded-2xl shadow-2xl border border-slate-200 bg-white text-slate-800 w-[min(34rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
        <h2 class="text-sm font-bold" id="course-modal-title">Register Course</h2>
        <button type="button" onclick="closeModal('course-modal')" aria-label="Close course modal" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition">&times;</button>
    </div>
    <form method="POST" action="<?= url('dashboard/admin/academic_structure.php') ?>" class="p-6 space-y-4 overflow-y-auto max-h-[calc(100dvh-6rem)]">
        <?= csrfField() ?>
        <input type="hidden" name="action_save_course" value="1">
        <input type="hidden" name="course_id" id="form-course-id" value="0">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <label for="form-course-code" class="block text-xs font-semibold text-slate-700 mb-1">Course Code <span class="text-rose-600">*</span></label>
                <input type="text" name="course_code" id="form-course-code" required placeholder="e.g. CSC401" autocorrect="off" autocapitalize="characters" spellcheck="false" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-mono uppercase text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            </div>
            <div>
                <label for="form-credit-units" class="block text-xs font-semibold text-slate-700 mb-1">Credit Units <span class="text-rose-600">*</span></label>
                <input type="number" name="credit_units" id="form-credit-units" min="1" max="6" value="3" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-mono text-slate-900 tabular-nums focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            </div>
        </div>

        <div>
            <label for="form-course-title" class="block text-xs font-semibold text-slate-700 mb-1">Course Title <span class="text-rose-600">*</span></label>
            <input type="text" name="course_title" id="form-course-title" required placeholder="e.g. Software Engineering & Architecture" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            <div>
                <label for="form-course-dept" class="block text-xs font-semibold text-slate-700 mb-1">Department <span class="text-rose-600">*</span></label>
                <select name="department_id" id="form-course-dept" required class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="form-course-level" class="block text-xs font-semibold text-slate-700 mb-1">Level <span class="text-rose-600">*</span></label>
                <select name="level_id" id="form-course-level" required class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['level_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="form-course-semester" class="block text-xs font-semibold text-slate-700 mb-1">Semester <span class="text-rose-600">*</span></label>
                <select name="semester_id" id="form-course-semester" required class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= (int)$sem['id'] ?>"><?= formatSemester($sem['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="pt-4 flex items-center justify-end gap-2.5 border-t border-slate-100">
            <button type="button" onclick="closeModal('course-modal')" class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">Cancel</button>
            <button type="submit" data-loading-text="Saving Course..." class="min-h-[38px] px-5 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition">Save Course</button>
        </div>
    </form>
</dialog>

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
