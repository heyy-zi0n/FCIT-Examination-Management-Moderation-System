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
$courses = $pdo->query("SELECT * FROM courses WHERE department_id = {$deptId} ORDER BY course_code ASC")->fetchAll();
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

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Departmental Examination Schedules</h1>
                <p class="text-xs text-slate-500">Configure authoritative examination dates and venues for controlled printing enforcement</p>
            </div>
            
            <button type="button" onclick="openModal('schedule-modal')" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add / Update Schedule</span>
            </button>
        </div>

        <!-- Schedules Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">Course Code & Title</th>
                            <th class="py-3.5 px-4 font-semibold">Session / Semester</th>
                            <th class="py-3.5 px-4 font-semibold">Official Exam Date</th>
                            <th class="py-3.5 px-4 font-semibold">Time & Duration</th>
                            <th class="py-3.5 px-4 font-semibold">Venue & Capacity</th>
                            <th class="py-3.5 px-4 font-semibold">Printing Window (Africa/Lagos)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    No examination schedules have been configured yet. Click "Add / Update Schedule" to set official exam dates.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schedules as $sc): 
                                $printWindowDt = (new DateTime($sc['exam_date']))->modify('-1 day')->format('d M Y');
                            ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white font-mono text-sm"><?= htmlspecialchars($sc['course_code']) ?></div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1"><?= htmlspecialchars($sc['course_title']) ?> (<?= htmlspecialchars($sc['level_name']) ?>)</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($sc['session_name']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= formatSemester($sc['semester_name']) ?></div>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                                        <?= formatDate($sc['exam_date'], 'D, d M Y') ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div><?= htmlspecialchars($sc['exam_time']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= $sc['duration_minutes'] ?> Mins</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($sc['venue']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= $sc['expected_students'] ?> Expected Students</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-cyan-50 text-cyan-800 border border-cyan-200">
                                            <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span><?= $printWindowDt ?> ONLY</span>
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

<!-- Modal: Add/Update Schedule -->
<div id="schedule-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
            <h3 class="text-sm font-bold">Set Course Examination Schedule</h3>
            <button type="button" onclick="closeModal('schedule-modal')" class="text-slate-300 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="<?= url('dashboard/hod/schedules.php') ?>" class="p-6 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action_save_schedule" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Course *</label>
                <select name="course_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Session *</label>
                    <select name="academic_session_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['session_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Semester *</label>
                    <select name="semester_id" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= $sem['id'] ?>"><?= htmlspecialchars($sem['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Exam Date *</label>
                    <input type="date" name="exam_date" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Exam Time *</label>
                    <input type="time" name="exam_time" required value="09:00" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" value="120" min="30" max="360" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expected Students</label>
                    <input type="number" name="expected_students" value="100" min="1" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Examination Venue *</label>
                <input type="text" name="venue" required placeholder="e.g. FCIT Main Auditorium Hall A" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
            </div>

            <div class="pt-4 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeModal('schedule-modal')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-lasu-navy text-lasu-gold font-bold rounded-lg text-xs shadow">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
