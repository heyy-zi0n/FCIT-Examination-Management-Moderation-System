<?php
/**
 * Departmental Examination Schedule Manager (HOD)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_HOD);

$pdo = getDb();
$deptId = (int)$user['department_id'];

// Handle Add / Edit Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_schedule'])) {
    requireCsrf();

    $scheduleId = (int)($_POST['schedule_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $sessionId = (int)($_POST['academic_session_id'] ?? 0);
    $semesterId = (int)($_POST['semester_id'] ?? 0);
    $examDate = sanitizeInput($_POST['exam_date'] ?? '');
    $examTime = sanitizeInput($_POST['exam_time'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 120);
    $venue = sanitizeInput($_POST['venue'] ?? '');
    $students = (int)($_POST['expected_students'] ?? 100);

    try {
        if ($courseId <= 0 || $sessionId <= 0 || $semesterId <= 0 || empty($examDate) || empty($examTime) || empty($venue)) {
            throw new InvalidArgumentException("All schedule fields marked with * are required.");
        }

        // Verify course belongs to this department
        $stmtChk = $pdo->prepare("SELECT id, course_code FROM courses WHERE id = :id AND department_id = :dept_id");
        $stmtChk->execute(['id' => $courseId, 'dept_id' => $deptId]);
        $course = $stmtChk->fetch();
        if (!$course) {
            throw new RuntimeException("Selected course does not belong to your department.");
        }

        if ($scheduleId > 0) {
            // Update
            $stmtUpd = $pdo->prepare("
                UPDATE exam_schedules
                SET exam_date = :dt, exam_time = :tm, duration_minutes = :dur, venue = :ven, expected_students = :stu, updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute([
                'dt' => $examDate,
                'tm' => $examTime,
                'dur' => $duration,
                'ven' => $venue,
                'stu' => $students,
                'id' => $scheduleId
            ]);
            logAudit((int)$user['id'], 'update_exam_schedule', 'exam_schedule', $scheduleId, ['course' => $course['course_code'], 'date' => $examDate]);
            setFlash('success', "Examination schedule for {$course['course_code']} updated successfully.");
        } else {
            // Insert
            $stmtIns = $pdo->prepare("
                INSERT INTO exam_schedules (
                    course_id, academic_session_id, semester_id, exam_date, exam_time, duration_minutes, venue, expected_students, created_by, created_at
                ) VALUES (
                    :cid, :sid, :sem_id, :dt, :tm, :dur, :ven, :stu, :uid, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    exam_date = VALUES(exam_date),
                    exam_time = VALUES(exam_time),
                    duration_minutes = VALUES(duration_minutes),
                    venue = VALUES(venue),
                    expected_students = VALUES(expected_students)
            ");
            $stmtIns->execute([
                'cid' => $courseId,
                'sid' => $sessionId,
                'sem_id' => $semesterId,
                'dt' => $examDate,
                'tm' => $examTime,
                'dur' => $duration,
                'ven' => $venue,
                'stu' => $students,
                'uid' => (int)$user['id']
            ]);
            $newId = (int)$pdo->lastInsertId();
            logAudit((int)$user['id'], 'create_exam_schedule', 'exam_schedule', $newId, ['course' => $course['course_code'], 'date' => $examDate]);
            setFlash('success', "Examination schedule for {$course['course_code']} saved successfully.");
        }

        redirect('dashboard/hod/schedules.php');
        exit;

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
}

// Fetch department courses, sessions, semesters
$stmtCourses = $pdo->prepare("SELECT * FROM courses WHERE department_id = :dept_id ORDER BY course_code ASC");
$stmtCourses->execute(['dept_id' => $deptId]);
$courses = $stmtCourses->fetchAll();

$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY is_current DESC")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters")->fetchAll();

// Fetch existing schedules
$stmtSchedules = $pdo->prepare("
    SELECT es.*, c.course_code, c.course_title, al.level_name, s.name AS semester_name, sess.session_name
    FROM exam_schedules es
    JOIN courses c ON es.course_id = c.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN semesters s ON es.semester_id = s.id
    JOIN academic_sessions sess ON es.academic_session_id = sess.id
    WHERE c.department_id = :dept_id
    ORDER BY es.exam_date ASC
");
$stmtSchedules->execute(['dept_id' => $deptId]);
$schedules = $stmtSchedules->fetchAll();

$pageTitle = 'Examination Schedules — HOD Portal — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Departmental Examination Schedules</h1>
                <p class="text-sm text-slate-600 mt-0.5">Configure authoritative examination dates and venues for controlled printing enforcement</p>
            </div>

            <button type="button" onclick="openModal('schedule-modal')" class="min-h-[40px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add / Update Schedule</span>
            </button>
        </div>

        <!-- Schedules Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div tabindex="0" role="region" aria-label="Departmental examination schedules" class="table-scroll-region overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Course Code &amp; Title</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Session / Semester</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Official Exam Date</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Time &amp; Duration</th>
                            <th scope="col" class="py-3.5 px-4 font-semibold">Venue &amp; Capacity</th>
                            <th scope="col" class="py-3.5 px-5 font-semibold">Printing Window (WAT)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="6" class="py-12 px-5 text-center">
                                    <div class="text-sm font-semibold text-slate-700">No examination schedules configured yet</div>
                                    <div class="text-xs text-slate-500 mt-1">Select &ldquo;Add / Update Schedule&rdquo; to set official examination dates and printing windows.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schedules as $sc):
                                $printWindowDt = (new DateTime($sc['exam_date']))->modify('-1 day')->format('d M Y');
                            ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-slate-900 font-mono text-sm"><?= htmlspecialchars($sc['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($sc['course_title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($sc['level_name'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                    </td>
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="font-medium text-slate-800 tabular-nums"><?= htmlspecialchars($sc['session_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= formatSemester($sc['semester_name']) ?></div>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                        <?= formatDate($sc['exam_date'], 'D, d M Y') ?>
                                    </td>
                                    <td class="py-4 px-4 tabular-nums whitespace-nowrap">
                                        <div class="font-medium text-slate-800"><?= htmlspecialchars($sc['exam_time'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500"><?= (int)$sc['duration_minutes'] ?> Mins</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-slate-800"><?= htmlspecialchars($sc['venue'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-500 tabular-nums"><?= (int)$sc['expected_students'] ?> Expected Students</div>
                                    </td>
                                    <td class="py-4 px-5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-900 border border-cyan-200 tabular-nums">
                                            <svg class="w-3.5 h-3.5 text-cyan-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span><?= htmlspecialchars($printWindowDt, ENT_QUOTES, 'UTF-8') ?> ONLY</span>
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

<!-- Native Dialog: Add/Update Schedule -->
<dialog id="schedule-modal" closedby="any" aria-labelledby="schedule-modal-title" class="rounded-2xl shadow-2xl border border-slate-200 bg-white text-slate-800 w-[min(32rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
        <h2 id="schedule-modal-title" class="text-sm font-bold">Set Course Examination Schedule</h2>
        <button type="button" onclick="closeModal('schedule-modal')" aria-label="Close modal" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition">&times;</button>
    </div>
    <form method="POST" action="<?= url('dashboard/hod/schedules.php') ?>" class="p-6 space-y-4">
        <?= csrfField() ?>
        <input type="hidden" name="action_save_schedule" value="1">

        <div>
            <label for="sched_course_id" class="block text-xs font-semibold text-slate-700 mb-1">Course <span class="text-rose-600">*</span></label>
            <select id="sched_course_id" name="course_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['course_code'], ENT_QUOTES, 'UTF-8') ?> &mdash; <?= htmlspecialchars($c['course_title'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <label for="sched_session_id" class="block text-xs font-semibold text-slate-700 mb-1">Session <span class="text-rose-600">*</span></label>
                <select id="sched_session_id" name="academic_session_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['session_name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sched_semester_id" class="block text-xs font-semibold text-slate-700 mb-1">Semester <span class="text-rose-600">*</span></label>
                <select id="sched_semester_id" name="semester_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= (int)$sem['id'] ?>"><?= htmlspecialchars($sem['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <label for="sched_exam_date" class="block text-xs font-semibold text-slate-700 mb-1">Exam Date <span class="text-rose-600">*</span></label>
                <input id="sched_exam_date" type="date" name="exam_date" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
            </div>
            <div>
                <label for="sched_exam_time" class="block text-xs font-semibold text-slate-700 mb-1">Exam Time <span class="text-rose-600">*</span></label>
                <input id="sched_exam_time" type="time" name="exam_time" required value="09:00" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <label for="sched_duration" class="block text-xs font-semibold text-slate-700 mb-1">Duration (Minutes)</label>
                <input id="sched_duration" type="number" name="duration_minutes" value="120" min="30" max="360" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
            </div>
            <div>
                <label for="sched_students" class="block text-xs font-semibold text-slate-700 mb-1">Expected Students</label>
                <input id="sched_students" type="number" name="expected_students" value="100" min="1" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
            </div>
        </div>

        <div>
            <label for="sched_venue" class="block text-xs font-semibold text-slate-700 mb-1">Examination Venue <span class="text-rose-600">*</span></label>
            <input id="sched_venue" type="text" name="venue" required placeholder="e.g. FCIT Main Auditorium Hall A" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-lasu-navy">
        </div>

        <div class="pt-4 flex items-center justify-end gap-2.5 border-t border-slate-100">
            <button type="button" onclick="closeModal('schedule-modal')" class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">Cancel</button>
            <button type="submit" data-loading-text="Saving..." class="min-h-[38px] px-5 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition">Save Schedule</button>
        </div>
    </form>
</dialog>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
