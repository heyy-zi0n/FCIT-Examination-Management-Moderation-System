<?php
/**
 * LASU FCIT Secure Examination Question Management System
 * Database Migration and Seeder Runner
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden: CLI execution only.');
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

echo "=======================================================\n";
echo "LASU FCIT CMS — Database Migration & Seeder\n";
echo "=======================================================\n\n";

try {
    // Step 1: Connect to MySQL server and recreate database cleanly
    echo "[1/4] Connecting to MySQL server...\n";
    $rawPdo = Database::getRawConnection();
    $rawPdo->exec("DROP DATABASE IF EXISTS `lasu_fcit_exam_db`;");
    $rawPdo->exec("CREATE DATABASE `lasu_fcit_exam_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "  -> Database `lasu_fcit_exam_db` created cleanly.\n\n";

    // Step 2: Execute authoritative schema.sql
    echo "[2/4] Executing database schema...\n";
    $pdo = Database::getConnection();
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    
    // Remove comments and split by semicolon
    $cleanSql = preg_replace('/--.*$/m', '', $schemaSql);
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
    
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    echo "  -> Schema created successfully.\n\n";

    // Step 3: Seed deterministic data
    echo "[3/4] Seeding core and institutional data...\n";
    
    // Default password hash for 'Pass5656'
    $defaultPasswordHash = password_hash('Pass5656', PASSWORD_BCRYPT, ['cost' => 12]);

    // Departments
    $departments = [
        ['code' => 'CSC', 'name' => 'Computer Science', 'description' => 'Governing over 40+ examination papers with strict cryptographic blind-lockdown and dual-approval workflows.'],
        ['code' => 'CYB', 'name' => 'Cyber Security', 'description' => 'Enforcing absolute confidentiality of examination materials through AES-256-GCM encryption and secure routing.'],
        ['code' => 'DSC', 'name' => 'Data Science', 'description' => 'Managing complex examination pipelines with robust audit trails and automated date-gate enforcement.'],
        ['code' => 'ICT', 'name' => 'Information and Communication Technology (ICT)', 'description' => 'Ensuring zero-trust question moderation and seamless, controlled physical printing authorization.'],
        ['code' => 'SEN', 'name' => 'Software Engineering', 'description' => 'Facilitating multi-tier academic review processes and securely locking down software engineering examination boards.']
    ];
    $stmtDept = $pdo->prepare("INSERT INTO `departments` (`code`, `name`, `description`) VALUES (:code, :name, :description)");
    $deptMap = [];
    foreach ($departments as $d) {
        $stmtDept->execute($d);
        $deptMap[$d['code']] = (int)$pdo->lastInsertId();
    }
    echo "  -> Seeded " . count($departments) . " departments.\n";

    // Academic Sessions
    $sessions = [
        ['session_name' => '2025/2026', 'is_current' => 1],
        ['session_name' => '2026/2027', 'is_current' => 0]
    ];
    $stmtSession = $pdo->prepare("INSERT INTO `academic_sessions` (`session_name`, `is_current`) VALUES (:session_name, :is_current)");
    $sessionMap = [];
    foreach ($sessions as $s) {
        $stmtSession->execute($s);
        $sessionMap[$s['session_name']] = (int)$pdo->lastInsertId();
    }
    $currentSessionId = $sessionMap['2025/2026'];
    echo "  -> Seeded academic sessions.\n";

    // Semesters
    $semesters = [
        ['name' => 'First Semester', 'code' => 'first'],
        ['name' => 'Second Semester', 'code' => 'second']
    ];
    $stmtSem = $pdo->prepare("INSERT INTO `semesters` (`name`, `code`) VALUES (:name, :code)");
    $semMap = [];
    foreach ($semesters as $sem) {
        $stmtSem->execute($sem);
        $semMap[$sem['code']] = (int)$pdo->lastInsertId();
    }
    $firstSemesterId = $semMap['first'];
    echo "  -> Seeded semesters.\n";

    // Academic Levels
    $levels = [
        ['level_name' => '100 Level', 'level_code' => 100],
        ['level_name' => '200 Level', 'level_code' => 200],
        ['level_name' => '300 Level', 'level_code' => 300],
        ['level_name' => '400 Level', 'level_code' => 400],
        ['level_name' => '500 Level', 'level_code' => 500]
    ];
    $stmtLvl = $pdo->prepare("INSERT INTO `academic_levels` (`level_name`, `level_code`) VALUES (:level_name, :level_code)");
    $lvlMap = [];
    foreach ($levels as $l) {
        $stmtLvl->execute($l);
        $lvlMap[$l['level_code']] = (int)$pdo->lastInsertId();
    }
    echo "  -> Seeded academic levels.\n";

    // Users
    $users = [
        // Admin
        [
            'staff_id' => 'LASU/FCIT/ADM/001',
            'title' => 'Engr.',
            'first_name' => 'Adebayo',
            'last_name' => 'Ogunlesi',
            'email' => 'admin@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_ADMIN,
            'department_id' => null
        ],
        // Exam Officer
        [
            'staff_id' => 'LASU/FCIT/EO/001',
            'title' => 'Dr.',
            'first_name' => 'Babatunde',
            'last_name' => 'Adeyemi',
            'email' => 'exam.officer@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_EXAM_OFFICER,
            'department_id' => $deptMap['CSC']
        ],
        // HOD CSC
        [
            'staff_id' => 'LASU/FCIT/CSC/HOD',
            'title' => 'Prof.',
            'first_name' => 'Olufemi',
            'last_name' => 'Balogun',
            'email' => 'hod.csc@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_HOD,
            'department_id' => $deptMap['CSC']
        ],
        // HOD CYB
        [
            'staff_id' => 'LASU/FCIT/CYB/HOD',
            'title' => 'Dr.',
            'first_name' => 'Folashade',
            'last_name' => 'Akinwande',
            'email' => 'hod.cyb@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_HOD,
            'department_id' => $deptMap['CYB']
        ],
        // Moderator CSC
        [
            'staff_id' => 'LASU/FCIT/CSC/MOD',
            'title' => 'Dr. (Mrs)',
            'first_name' => 'Chioma',
            'last_name' => 'Okoro',
            'email' => 'moderator.csc@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_MODERATOR,
            'department_id' => $deptMap['CSC']
        ],
        // Moderator CYB
        [
            'staff_id' => 'LASU/FCIT/CYB/MOD',
            'title' => 'Dr.',
            'first_name' => 'Emeka',
            'last_name' => 'Nnamdi',
            'email' => 'moderator.cyb@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_MODERATOR,
            'department_id' => $deptMap['CYB']
        ],
        // Lecturer CSC 1
        [
            'staff_id' => 'LASU/FCIT/CSC/LEC1',
            'title' => 'Dr.',
            'first_name' => 'Abiodun',
            'last_name' => 'Fasanya',
            'email' => 'lecturer.csc@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_LECTURER,
            'department_id' => $deptMap['CSC']
        ],
        // Lecturer CSC 2
        [
            'staff_id' => 'LASU/FCIT/CSC/LEC2',
            'title' => 'Mr.',
            'first_name' => 'Kehinde',
            'last_name' => 'Sowemimo',
            'email' => 'lecturer2.csc@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_LECTURER,
            'department_id' => $deptMap['CSC']
        ],
        // Lecturer CYB 1
        [
            'staff_id' => 'LASU/FCIT/CYB/LEC1',
            'title' => 'Dr.',
            'first_name' => 'Olumide',
            'last_name' => 'Bakare',
            'email' => 'lecturer.cyb@lasu.edu.ng',
            'password_hash' => $defaultPasswordHash,
            'role' => ROLE_LECTURER,
            'department_id' => $deptMap['CYB']
        ]
    ];
    $stmtUser = $pdo->prepare("INSERT INTO `users` (`staff_id`, `title`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `department_id`, `status`) 
                              VALUES (:staff_id, :title, :first_name, :last_name, :email, :password_hash, :role, :department_id, 'active')");
    $userMap = [];
    foreach ($users as $u) {
        $stmtUser->execute($u);
        $userMap[$u['email']] = (int)$pdo->lastInsertId();
    }
    echo "  -> Seeded " . count($users) . " system users.\n";

    // Courses
    $courses = [
        ['course_code' => 'CSC 301', 'course_title' => 'Structured Programming & Data Structures', 'department_id' => $deptMap['CSC'], 'level_id' => $lvlMap[300], 'semester_id' => $firstSemesterId, 'credit_units' => 3],
        ['course_code' => 'CSC 305', 'course_title' => 'Operating Systems & Architecture', 'department_id' => $deptMap['CSC'], 'level_id' => $lvlMap[300], 'semester_id' => $firstSemesterId, 'credit_units' => 3],
        ['course_code' => 'CSC 401', 'course_title' => 'Compiler Construction & Automata', 'department_id' => $deptMap['CSC'], 'level_id' => $lvlMap[400], 'semester_id' => $firstSemesterId, 'credit_units' => 3],
        ['course_code' => 'CYB 301', 'course_title' => 'Cryptography & Network Security Protocols', 'department_id' => $deptMap['CYB'], 'level_id' => $lvlMap[300], 'semester_id' => $firstSemesterId, 'credit_units' => 3],
        ['course_code' => 'CYB 405', 'course_title' => 'Digital Forensics & Incident Investigation', 'department_id' => $deptMap['CYB'], 'level_id' => $lvlMap[400], 'semester_id' => $firstSemesterId, 'credit_units' => 3],
        ['course_code' => 'SEN 301', 'course_title' => 'Software Architecture & System Design', 'department_id' => $deptMap['SEN'], 'level_id' => $lvlMap[300], 'semester_id' => $firstSemesterId, 'credit_units' => 3],
        ['course_code' => 'DSC 302', 'course_title' => 'Foundations of Machine Learning & Neural Networks', 'department_id' => $deptMap['DSC'], 'level_id' => $lvlMap[300], 'semester_id' => $firstSemesterId, 'credit_units' => 3],
        ['course_code' => 'ICT 302', 'course_title' => 'Enterprise Cloud Infrastructure & Management', 'department_id' => $deptMap['ICT'], 'level_id' => $lvlMap[300], 'semester_id' => $firstSemesterId, 'credit_units' => 3]
    ];
    $stmtCourse = $pdo->prepare("INSERT INTO `courses` (`course_code`, `course_title`, `department_id`, `level_id`, `semester_id`, `credit_units`) 
                                VALUES (:course_code, :course_title, :department_id, :level_id, :semester_id, :credit_units)");
    $courseMap = [];
    foreach ($courses as $c) {
        $stmtCourse->execute($c);
        $courseMap[$c['course_code']] = (int)$pdo->lastInsertId();
    }
    echo "  -> Seeded " . count($courses) . " academic courses.\n";

    // Lecturer Course Assignments
    $lcaData = [
        ['lecturer_id' => $userMap['lecturer.csc@lasu.edu.ng'], 'course_id' => $courseMap['CSC 301'], 'academic_session_id' => $currentSessionId, 'is_primary' => 1, 'assigned_by' => $userMap['hod.csc@lasu.edu.ng']],
        ['lecturer_id' => $userMap['lecturer.csc@lasu.edu.ng'], 'course_id' => $courseMap['CSC 401'], 'academic_session_id' => $currentSessionId, 'is_primary' => 1, 'assigned_by' => $userMap['hod.csc@lasu.edu.ng']],
        ['lecturer_id' => $userMap['lecturer2.csc@lasu.edu.ng'], 'course_id' => $courseMap['CSC 305'], 'academic_session_id' => $currentSessionId, 'is_primary' => 1, 'assigned_by' => $userMap['hod.csc@lasu.edu.ng']],
        ['lecturer_id' => $userMap['lecturer.cyb@lasu.edu.ng'], 'course_id' => $courseMap['CYB 301'], 'academic_session_id' => $currentSessionId, 'is_primary' => 1, 'assigned_by' => $userMap['hod.cyb@lasu.edu.ng']],
        ['lecturer_id' => $userMap['lecturer.cyb@lasu.edu.ng'], 'course_id' => $courseMap['CYB 405'], 'academic_session_id' => $currentSessionId, 'is_primary' => 1, 'assigned_by' => $userMap['hod.cyb@lasu.edu.ng']]
    ];
    $stmtLca = $pdo->prepare("INSERT INTO `lecturer_course_assignments` (`lecturer_id`, `course_id`, `academic_session_id`, `is_primary`, `assigned_by`) 
                             VALUES (:lecturer_id, :course_id, :academic_session_id, :is_primary, :assigned_by)");
    foreach ($lcaData as $lca) {
        $stmtLca->execute($lca);
    }
    echo "  -> Seeded lecturer-course allocations.\n";

    // Moderator Assignments (HOD sets departmental moderator assignments by level)
    $modAssignments = [
        ['moderator_id' => $userMap['moderator.csc@lasu.edu.ng'], 'department_id' => $deptMap['CSC'], 'level_id' => $lvlMap[300], 'academic_session_id' => $currentSessionId, 'assigned_by' => $userMap['hod.csc@lasu.edu.ng']],
        ['moderator_id' => $userMap['moderator.csc@lasu.edu.ng'], 'department_id' => $deptMap['CSC'], 'level_id' => $lvlMap[400], 'academic_session_id' => $currentSessionId, 'assigned_by' => $userMap['hod.csc@lasu.edu.ng']],
        ['moderator_id' => $userMap['moderator.cyb@lasu.edu.ng'], 'department_id' => $deptMap['CYB'], 'level_id' => $lvlMap[300], 'academic_session_id' => $currentSessionId, 'assigned_by' => $userMap['hod.cyb@lasu.edu.ng']],
        ['moderator_id' => $userMap['moderator.cyb@lasu.edu.ng'], 'department_id' => $deptMap['CYB'], 'level_id' => $lvlMap[400], 'academic_session_id' => $currentSessionId, 'assigned_by' => $userMap['hod.cyb@lasu.edu.ng']]
    ];
    $stmtMod = $pdo->prepare("INSERT INTO `moderator_assignments` (`moderator_id`, `department_id`, `level_id`, `academic_session_id`, `assigned_by`) 
                             VALUES (:moderator_id, :department_id, :level_id, :academic_session_id, :assigned_by)");
    foreach ($modAssignments as $ma) {
        $stmtMod->execute($ma);
    }
    echo "  -> Seeded moderator assignments.\n";

    // Examination Schedules
    // Note: Today's date in Lagos timezone:
    $today = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
    $tomorrow = (clone $today)->modify('+1 day')->format('Y-m-d');
    $inThreeDays = (clone $today)->modify('+3 days')->format('Y-m-d');
    $inFiveDays = (clone $today)->modify('+5 days')->format('Y-m-d');

    $schedules = [
        // CSC 301 exam tomorrow -> Eligible for controlled printing TODAY!
        [
            'course_id' => $courseMap['CSC 301'],
            'academic_session_id' => $currentSessionId,
            'semester_id' => $firstSemesterId,
            'exam_date' => $tomorrow,
            'exam_time' => '09:00:00',
            'duration_minutes' => 180,
            'venue' => 'FCIT Hall A, Faculty Building',
            'expected_students' => 140,
            'created_by' => $userMap['hod.csc@lasu.edu.ng']
        ],
        // CSC 401 exam in 3 days
        [
            'course_id' => $courseMap['CSC 401'],
            'academic_session_id' => $currentSessionId,
            'semester_id' => $firstSemesterId,
            'exam_date' => $inThreeDays,
            'exam_time' => '13:00:00',
            'duration_minutes' => 180,
            'venue' => 'FCIT Hall B, Faculty Building',
            'expected_students' => 95,
            'created_by' => $userMap['hod.csc@lasu.edu.ng']
        ],
        // CYB 301 exam in 5 days
        [
            'course_id' => $courseMap['CYB 301'],
            'academic_session_id' => $currentSessionId,
            'semester_id' => $firstSemesterId,
            'exam_date' => $inFiveDays,
            'exam_time' => '09:00:00',
            'duration_minutes' => 120,
            'venue' => 'Cybersecurity Lab 1',
            'expected_students' => 60,
            'created_by' => $userMap['hod.cyb@lasu.edu.ng']
        ]
    ];
    $stmtSched = $pdo->prepare("INSERT INTO `exam_schedules` (`course_id`, `academic_session_id`, `semester_id`, `exam_date`, `exam_time`, `duration_minutes`, `venue`, `expected_students`, `created_by`) 
                               VALUES (:course_id, :academic_session_id, :semester_id, :exam_date, :exam_time, :duration_minutes, :venue, :expected_students, :created_by)");
    foreach ($schedules as $sc) {
        $stmtSched->execute($sc);
    }
    echo "  -> Seeded authoritative examination schedules.\n";

    // Step 4: Ensure Storage Directories exist with .htaccess protection
    echo "[4/4] Setting up secure storage directories...\n";
    $storageDirs = [
        STORAGE_PATH,
        EXAM_STORAGE_PATH,
        PREVIEW_STORAGE_PATH,
        PROFILE_PICTURES_STORAGE_PATH,
        BACKUP_STORAGE_PATH,
        LOGS_STORAGE_PATH
    ];
    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Add .htaccess to storage directory to block direct web access
    $htaccessContent = "Order Allow,Deny\nDeny from all\n";
    file_put_contents(STORAGE_PATH . '/.htaccess', $htaccessContent);
    echo "  -> Storage directories initialized with web access restriction.\n\n";

    echo "=======================================================\n";
    echo "MIGRATION & SEEDING COMPLETED SUCCESSFULLY!\n";
    echo "=======================================================\n";
    echo "Test Accounts (Password for all accounts: Pass5656):\n";
    echo " - Admin:         admin@lasu.edu.ng\n";
    echo " - Exam Officer:  exam.officer@lasu.edu.ng\n";
    echo " - HOD (CSC):     hod.csc@lasu.edu.ng\n";
    echo " - HOD (CYB):     hod.cyb@lasu.edu.ng\n";
    echo " - Moderator CSC: moderator.csc@lasu.edu.ng\n";
    echo " - Lecturer CSC:  lecturer.csc@lasu.edu.ng\n";
    echo " - Lecturer CYB:  lecturer.cyb@lasu.edu.ng\n";
    echo "=======================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
