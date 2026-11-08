-- ==============================================================================
-- LASU FCIT — FACULTY WEBSITE (`lasu_faculty`) + EMMS (`ems`) INTEGRATION SCHEMA
-- ==============================================================================
-- Purpose:
--   Non-destructively adds the Examination Question Lifecycle Management System
--   (EMMS) tables and compatibility SQL Views into the existing `lasu_faculty`
--   database so both systems share a single authoritative database:
--     * `staff` + `positions` + `assignments`  --> `users` (SQL VIEW + `ems_staff_meta`)
--     * `departments`                          --> shared directly
--     * `courses` + `programs`                 --> `ems_courses` (SQL VIEW) & FKs to `courses(id)`
--     * `academic_sessions`                    --> `ems_academic_sessions` (SQL VIEW) & FKs to `academic_sessions(id)`
--     * `course_allocations`                   --> `lecturer_course_assignments` (SQL VIEW)
--
-- Safety Guarantee:
--   ZERO existing `lasu_faculty` tables are dropped, truncated, or destructively altered.
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. EMMS Reference Tables (`semesters`, `academic_levels`)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `semesters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `semesters` (`id`, `name`, `code`) VALUES
    (1, 'First Semester', 'first'),
    (2, 'Second Semester', 'second')
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `code` = VALUES(`code`);

CREATE TABLE IF NOT EXISTS `academic_levels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `level_name` VARCHAR(50) NOT NULL,
    `level_code` INT NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `academic_levels` (`id`, `level_name`, `level_code`) VALUES
    (1, '100 Level', 100),
    (2, '200 Level', 200),
    (3, '300 Level', 300),
    (4, '400 Level', 400),
    (5, '500 Level', 500),
    (6, 'Diploma Year 1', 10),
    (7, 'Diploma Year 2', 20),
    (8, 'Postgraduate', 800)
ON DUPLICATE KEY UPDATE
    `level_name` = VALUES(`level_name`),
    `level_code` = VALUES(`level_code`);

-- ------------------------------------------------------------------------------
-- 2. EMMS Staff Metadata Sidecar Table (`ems_staff_meta`)
--    Stores EMMS-specific governance role overrides (e.g. `exam_officer`,
--    `moderator`), lockout counters, and EMMS-specific avatar overrides without
--    mutating `lasu_faculty.staff`.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ems_staff_meta` (
    `staff_id` INT(11) NOT NULL PRIMARY KEY,
    `ems_role` ENUM('admin', 'lecturer', 'moderator', 'hod', 'exam_officer') NULL DEFAULT NULL,
    `ems_department_id` INT(11) NULL DEFAULT NULL,
    `ems_status` ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `ems_profile_picture` VARCHAR(255) NULL DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ems_meta_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ems_meta_dept` FOREIGN KEY (`ems_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. EMMS Core Workflow & Security Tables
--    All InnoDB Foreign Keys reference `staff(id)`, `departments(id)`,
--    `courses(id)`, `academic_sessions(id)`, `semesters(id)`, `academic_levels(id)`.
-- ------------------------------------------------------------------------------

-- 3.1 Moderator Assignments Table
CREATE TABLE IF NOT EXISTS `moderator_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `moderator_id` INT(11) NOT NULL,
    `department_id` INT(11) NOT NULL,
    `level_id` INT NOT NULL,
    `academic_session_id` INT(11) NOT NULL,
    `assigned_by` INT(11) NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mod_staff` FOREIGN KEY (`moderator_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_level` FOREIGN KEY (`level_id`) REFERENCES `academic_levels` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_session` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_mod_dept_level_session` (`moderator_id`, `department_id`, `level_id`, `academic_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.2 Examination Schedules Table (Authoritative Dates for Controlled Printing)
CREATE TABLE IF NOT EXISTS `exam_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT(11) NOT NULL,
    `academic_session_id` INT(11) NOT NULL,
    `semester_id` INT NOT NULL,
    `exam_date` DATE NOT NULL,
    `exam_time` TIME NOT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 120,
    `venue` VARCHAR(150) NOT NULL,
    `expected_students` INT NOT NULL DEFAULT 100,
    `created_by` INT(11) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sched_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sched_session` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sched_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sched_creator` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_course_session_semester_schedule` (`course_id`, `academic_session_id`, `semester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.3 Delegations Table (HOD and EO Formal Delegation)
CREATE TABLE IF NOT EXISTS `delegations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `delegator_id` INT(11) NOT NULL,
    `delegate_id` INT(11) NOT NULL,
    `role_delegated` ENUM('hod', 'exam_officer') NOT NULL,
    `department_id` INT(11) NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `reason` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_del_delegator` FOREIGN KEY (`delegator_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_delegate` FOREIGN KEY (`delegate_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4 Examination Papers Table
CREATE TABLE IF NOT EXISTS `examination_papers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_code` VARCHAR(50) NOT NULL UNIQUE,
    `course_id` INT(11) NOT NULL,
    `academic_session_id` INT(11) NOT NULL,
    `semester_id` INT NOT NULL,
    `exam_schedule_id` INT NULL,
    `lecturer_id` INT(11) NOT NULL,
    `current_version_id` INT NULL,
    `status` ENUM('Draft', 'Submitted', 'Returned', 'Moderated', 'Approved', 'Locked', 'Queued', 'Printed') NOT NULL DEFAULT 'Draft',
    `moderator_id` INT(11) NULL,
    `moderator_decision` ENUM('pending', 'approved', 'returned') NOT NULL DEFAULT 'pending',
    `moderator_decision_at` DATETIME NULL,
    `hod_id` INT(11) NULL,
    `hod_status` ENUM('pending', 'approved', 'returned') NOT NULL DEFAULT 'pending',
    `hod_decision_at` DATETIME NULL,
    `eo_id` INT(11) NULL,
    `eo_status` ENUM('pending', 'approved', 'returned') NOT NULL DEFAULT 'pending',
    `eo_decision_at` DATETIME NULL,
    `locked_at` DATETIME NULL,
    `printed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_papers_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_papers_session` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_papers_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_papers_schedule` FOREIGN KEY (`exam_schedule_id`) REFERENCES `exam_schedules` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_papers_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_papers_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_papers_hod` FOREIGN KEY (`hod_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_papers_eo` FOREIGN KEY (`eo_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    INDEX `idx_papers_status` (`status`),
    INDEX `idx_papers_course_session` (`course_id`, `academic_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.5 Paper Versions Table (Immutable Versioning)
CREATE TABLE IF NOT EXISTS `paper_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_number` INT NOT NULL DEFAULT 1,
    `question_file_path` VARCHAR(255) NOT NULL,
    `question_file_name` VARCHAR(255) NOT NULL,
    `question_file_hash` VARCHAR(64) NOT NULL,
    `question_file_size` INT NOT NULL,
    `guide_file_path` VARCHAR(255) NULL,
    `guide_file_name` VARCHAR(255) NULL,
    `guide_file_hash` VARCHAR(64) NULL,
    `guide_file_size` INT NULL,
    `submission_notes` TEXT NULL,
    `changes_summary` TEXT NULL,
    `created_by` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_versions_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_versions_creator` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_paper_version` (`paper_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.6 Paper Approvals Table (Independent Decision Trail for Moderator, HOD, and Exam Officer)
CREATE TABLE IF NOT EXISTS `paper_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `stage` ENUM('moderator', 'hod', 'exam_officer') NOT NULL,
    `approver_id` INT(11) NOT NULL,
    `decision` ENUM('approved', 'returned') NOT NULL,
    `comments` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_appr_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appr_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appr_user` FOREIGN KEY (`approver_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    INDEX `idx_appr_stage_dec` (`stage`, `decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.7 Moderation Comments Table (Line-by-Line Annotation and Rubric Feedback)
CREATE TABLE IF NOT EXISTS `moderation_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `author_id` INT(11) NOT NULL,
    `line_reference` VARCHAR(100) NULL,
    `comment_type` ENUM('correction', 'clarity', 'marks_allocation', 'rubric', 'general') NOT NULL DEFAULT 'general',
    `comment_text` TEXT NOT NULL,
    `status` ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    `resolved_by` INT(11) NULL,
    `resolved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_modcomm_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_modcomm_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_modcomm_author` FOREIGN KEY (`author_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_modcomm_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.8 Blind Lockdowns Table (AES-256-GCM Encrypted Final Storage)
CREATE TABLE IF NOT EXISTS `blind_lockdowns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL UNIQUE,
    `version_id` INT NOT NULL,
    `locked_by` INT(11) NOT NULL,
    `encrypted_file_path` VARCHAR(255) NOT NULL,
    `checksum_sha256` VARCHAR(64) NOT NULL,
    `encryption_algorithm` VARCHAR(50) NOT NULL DEFAULT 'aes-256-gcm',
    `encryption_iv` VARCHAR(255) NOT NULL,
    `encryption_tag` VARCHAR(255) NOT NULL,
    `locked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_unlocked` TINYINT(1) NOT NULL DEFAULT 0,
    `unlock_reason` TEXT NULL,
    `unlocked_by` INT(11) NULL,
    `unlocked_at` DATETIME NULL,
    CONSTRAINT `fk_lock_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lock_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lock_user` FOREIGN KEY (`locked_by`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_unlock_user` FOREIGN KEY (`unlocked_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.9 Controlled Print Logs Table
CREATE TABLE IF NOT EXISTS `print_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `exam_officer_id` INT(11) NOT NULL,
    `copies_printed` INT NOT NULL DEFAULT 1,
    `print_status` ENUM('successful', 'failed', 'reprint') NOT NULL DEFAULT 'successful',
    `verification_code` VARCHAR(64) NOT NULL,
    `client_ip` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `is_reprint` TINYINT(1) NOT NULL DEFAULT 0,
    `reprint_reason` TEXT NULL,
    `reprint_approved_by` INT(11) NULL,
    `printed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_print_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_print_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_print_eo` FOREIGN KEY (`exam_officer_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reprint_approver` FOREIGN KEY (`reprint_approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    INDEX `idx_print_paper_eo` (`paper_id`, `exam_officer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.10 Emergency Print Requests Table
CREATE TABLE IF NOT EXISTS `emergency_print_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `exam_officer_id` INT(11) NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `hod_id` INT(11) NULL,
    `hod_decision_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_epr_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_epr_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_epr_eo` FOREIGN KEY (`exam_officer_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_epr_hod` FOREIGN KEY (`hod_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    INDEX `idx_epr_paper_status` (`paper_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.11 In-App Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    INDEX `idx_notif_user_unread` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.12 Audit Logs Table (Tamper-Evident Trail)
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    INDEX `idx_audit_user_action` (`user_id`, `action`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 4. Compatibility SQL Views (`users`, `lecturer_course_assignments`,
--    `ems_courses`, `ems_academic_sessions`)
-- ------------------------------------------------------------------------------

-- 4.1 `users` View: Projects `lasu_faculty.staff` + `assignments` + `ems_staff_meta`
--     into the exact column shape expected by EMMS (`users`).
CREATE OR REPLACE VIEW `users` AS
SELECT
    s.id AS id,
    s.staff_id AS staff_id,
    s.pf_number AS pf_number,
    CASE
        WHEN s.name LIKE 'Dr. (Mrs.) %' THEN 'Dr. (Mrs.)'
        WHEN s.name LIKE 'Dr. (Mr.) %'  THEN 'Dr. (Mr.)'
        WHEN s.name LIKE 'Dr. (Ms.) %'  THEN 'Dr. (Ms.)'
        WHEN s.name LIKE 'Prof. (Mrs.) %' THEN 'Prof. (Mrs.)'
        WHEN s.name LIKE 'Assoc. Prof. %' THEN 'Assoc. Prof.'
        WHEN SUBSTRING_INDEX(s.name, ' ', 1) IN ('Prof.', 'Dr.', 'Mr.', 'Mrs.', 'Miss', 'Ms.', 'Engr.', 'Arc.', 'Barr.', 'Chief', 'Alh.', 'Pastor', 'Rev.')
            THEN SUBSTRING_INDEX(s.name, ' ', 1)
        ELSE ''
    END AS title,
    SUBSTRING_INDEX(
        TRIM(
            CASE
                WHEN s.name LIKE 'Dr. (Mrs.) %' THEN SUBSTRING(s.name, 12)
                WHEN s.name LIKE 'Dr. (Mr.) %'  THEN SUBSTRING(s.name, 11)
                WHEN s.name LIKE 'Dr. (Ms.) %'  THEN SUBSTRING(s.name, 11)
                WHEN s.name LIKE 'Prof. (Mrs.) %' THEN SUBSTRING(s.name, 14)
                WHEN s.name LIKE 'Assoc. Prof. %' THEN SUBSTRING(s.name, 14)
                WHEN SUBSTRING_INDEX(s.name, ' ', 1) IN ('Prof.', 'Dr.', 'Mr.', 'Mrs.', 'Miss', 'Ms.', 'Engr.', 'Arc.', 'Barr.', 'Chief', 'Alh.', 'Pastor', 'Rev.')
                    THEN SUBSTRING(s.name, CHAR_LENGTH(SUBSTRING_INDEX(s.name, ' ', 1)) + 2)
                ELSE s.name
            END
        ),
        ' ',
        1
    ) AS first_name,
    CASE
        WHEN LOCATE(
            ' ',
            TRIM(
                CASE
                    WHEN s.name LIKE 'Dr. (Mrs.) %' THEN SUBSTRING(s.name, 12)
                    WHEN s.name LIKE 'Dr. (Mr.) %'  THEN SUBSTRING(s.name, 11)
                    WHEN s.name LIKE 'Dr. (Ms.) %'  THEN SUBSTRING(s.name, 11)
                    WHEN s.name LIKE 'Prof. (Mrs.) %' THEN SUBSTRING(s.name, 14)
                    WHEN s.name LIKE 'Assoc. Prof. %' THEN SUBSTRING(s.name, 14)
                    WHEN SUBSTRING_INDEX(s.name, ' ', 1) IN ('Prof.', 'Dr.', 'Mr.', 'Mrs.', 'Miss', 'Ms.', 'Engr.', 'Arc.', 'Barr.', 'Chief', 'Alh.', 'Pastor', 'Rev.')
                        THEN SUBSTRING(s.name, CHAR_LENGTH(SUBSTRING_INDEX(s.name, ' ', 1)) + 2)
                    ELSE s.name
                END
            )
        ) > 0
        THEN TRIM(
            SUBSTRING(
                TRIM(
                    CASE
                        WHEN s.name LIKE 'Dr. (Mrs.) %' THEN SUBSTRING(s.name, 12)
                        WHEN s.name LIKE 'Dr. (Mr.) %'  THEN SUBSTRING(s.name, 11)
                        WHEN s.name LIKE 'Dr. (Ms.) %'  THEN SUBSTRING(s.name, 11)
                        WHEN s.name LIKE 'Prof. (Mrs.) %' THEN SUBSTRING(s.name, 14)
                        WHEN s.name LIKE 'Assoc. Prof. %' THEN SUBSTRING(s.name, 14)
                        WHEN SUBSTRING_INDEX(s.name, ' ', 1) IN ('Prof.', 'Dr.', 'Mr.', 'Mrs.', 'Miss', 'Ms.', 'Engr.', 'Arc.', 'Barr.', 'Chief', 'Alh.', 'Pastor', 'Rev.')
                            THEN SUBSTRING(s.name, CHAR_LENGTH(SUBSTRING_INDEX(s.name, ' ', 1)) + 2)
                        ELSE s.name
                    END
                ),
                LOCATE(
                    ' ',
                    TRIM(
                        CASE
                            WHEN s.name LIKE 'Dr. (Mrs.) %' THEN SUBSTRING(s.name, 12)
                            WHEN s.name LIKE 'Dr. (Mr.) %'  THEN SUBSTRING(s.name, 11)
                            WHEN s.name LIKE 'Dr. (Ms.) %'  THEN SUBSTRING(s.name, 11)
                            WHEN s.name LIKE 'Prof. (Mrs.) %' THEN SUBSTRING(s.name, 14)
                            WHEN s.name LIKE 'Assoc. Prof. %' THEN SUBSTRING(s.name, 14)
                            WHEN SUBSTRING_INDEX(s.name, ' ', 1) IN ('Prof.', 'Dr.', 'Mr.', 'Mrs.', 'Miss', 'Ms.', 'Engr.', 'Arc.', 'Barr.', 'Chief', 'Alh.', 'Pastor', 'Rev.')
                                THEN SUBSTRING(s.name, CHAR_LENGTH(SUBSTRING_INDEX(s.name, ' ', 1)) + 2)
                            ELSE s.name
                        END
                    )
                ) + 1
            )
        )
        ELSE ''
    END AS last_name,
    s.email AS email,
    COALESCE(NULLIF(m.ems_profile_picture, ''), NULLIF(s.photo, '')) AS profile_picture,
    s.password AS password_hash,
    COALESCE(
        m.ems_role,
        CASE
            WHEN s.role IN ('admin', 'dean', 'web_administrator') THEN 'admin'
            WHEN EXISTS (
                SELECT 1 FROM assignments a JOIN positions p ON a.position_id = p.id
                WHERE a.staff_id = s.id AND a.is_current = 1
                  AND p.title IN ('Dean', 'Sub-Dean', 'Deputy Dean', 'Web Administrator')
            ) THEN 'admin'
            WHEN s.role = 'hod' THEN 'hod'
            WHEN EXISTS (
                SELECT 1 FROM assignments a JOIN positions p ON a.position_id = p.id
                WHERE a.staff_id = s.id AND a.is_current = 1 AND p.title = 'Head of Department'
            ) THEN 'hod'
            WHEN EXISTS (
                SELECT 1 FROM departments dh WHERE dh.hod_id = s.id AND dh.status = 'active'
            ) THEN 'hod'
            WHEN EXISTS (
                SELECT 1 FROM assignments a JOIN positions p ON a.position_id = p.id
                WHERE a.staff_id = s.id AND a.is_current = 1
                  AND (p.title LIKE '%Examination Officer%' OR p.title LIKE '%Exam Officer%')
            ) THEN 'exam_officer'
            ELSE 'lecturer'
        END
    ) AS role,
    COALESCE(
        m.ems_department_id,
        (SELECT a.department_id FROM assignments a JOIN positions p ON a.position_id = p.id
         WHERE a.staff_id = s.id AND a.is_current = 1 AND p.title = 'Head of Department' AND a.department_id IS NOT NULL
         ORDER BY a.id DESC LIMIT 1),
        (SELECT dh.id FROM departments dh WHERE dh.hod_id = s.id AND dh.status = 'active' ORDER BY dh.id ASC LIMIT 1),
        s.department_id
    ) AS department_id,
    CASE
        WHEN s.status != 'active' THEN 'inactive'
        WHEN m.ems_status = 'inactive' THEN 'inactive'
        WHEN m.ems_status = 'locked' AND m.locked_until IS NOT NULL AND m.locked_until > NOW() THEN 'locked'
        ELSE 'active'
    END AS status,
    COALESCE(m.failed_attempts, 0) AS failed_attempts,
    m.locked_until AS locked_until,
    m.last_login_at AS last_login_at,
    COALESCE(m.updated_at, CURRENT_TIMESTAMP) AS created_at,
    COALESCE(m.updated_at, CURRENT_TIMESTAMP) AS updated_at
FROM `staff` s
LEFT JOIN `ems_staff_meta` m ON m.staff_id = s.id;

-- 4.2 `lecturer_course_assignments` View: Maps `lasu_faculty.course_allocations`
CREATE OR REPLACE VIEW `lecturer_course_assignments` AS
SELECT
    MIN(ca.id) AS id,
    ca.staff_id AS lecturer_id,
    ca.course_id AS course_id,
    sess.id AS academic_session_id,
    MAX(ca.is_current) AS is_primary,
    MAX(ca.allocated_by) AS assigned_by,
    MAX(ca.created_at) AS assigned_at
FROM `course_allocations` ca
JOIN `academic_sessions` sess ON ca.academic_session = sess.name
WHERE ca.is_current = 1
GROUP BY ca.staff_id, ca.course_id, sess.id;

-- 4.3 `ems_courses` View: Maps `lasu_faculty.courses` + `programs` into EMMS columns
CREATE OR REPLACE VIEW `ems_courses` AS
SELECT
    c.id AS id,
    c.program_id AS program_id,
    p.name AS program_name,
    c.code AS course_code,
    c.title AS course_title,
    p.department_id AS department_id,
    CASE
        WHEN TRIM(c.level) IN ('100', '100 Level') THEN 1
        WHEN TRIM(c.level) IN ('200', '200 Level') THEN 2
        WHEN TRIM(c.level) IN ('300', '300 Level') THEN 3
        WHEN TRIM(c.level) IN ('400', '400 Level') THEN 4
        WHEN TRIM(c.level) IN ('500', '500 Level') THEN 5
        WHEN TRIM(c.level) IN ('Year 1', 'Diploma Yr 1', 'Diploma Year 1') THEN 6
        WHEN TRIM(c.level) IN ('Year 2', 'Diploma Yr 2', 'Diploma Year 2') THEN 7
        ELSE 8
    END AS level_id,
    CASE WHEN c.semester IN (1, 2) THEN c.semester ELSE 1 END AS semester_id,
    c.credit_unit AS credit_units,
    CASE WHEN c.status = 'active' THEN 1 ELSE 0 END AS is_active,
    CURRENT_TIMESTAMP AS created_at
FROM `courses` c
JOIN `programs` p ON c.program_id = p.id;

-- 4.4 `ems_academic_sessions` View: Maps `lasu_faculty.academic_sessions` into EMMS columns
CREATE OR REPLACE VIEW `ems_academic_sessions` AS
SELECT
    id,
    name AS session_name,
    CASE WHEN status = 'current' THEN 1 ELSE 0 END AS is_current,
    start_date,
    end_date,
    status,
    created_at
FROM `academic_sessions`;

-- ------------------------------------------------------------------------------
-- 5. Non-Breaking Virtual Alias Columns on `academic_sessions` & `courses`
--    Allows all existing EMMS `SELECT` queries against `courses` and
--    `academic_sessions` to work directly without modifying `faculty` queries.
-- ------------------------------------------------------------------------------
ALTER TABLE `academic_sessions`
    ADD COLUMN IF NOT EXISTS `session_name` VARCHAR(20) GENERATED ALWAYS AS (`name`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `is_current` TINYINT(1) GENERATED ALWAYS AS (IF(`status` = 'current', 1, 0)) VIRTUAL;

ALTER TABLE `courses`
    ADD COLUMN IF NOT EXISTS `course_code` VARCHAR(20) GENERATED ALWAYS AS (`code`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `course_title` VARCHAR(255) GENERATED ALWAYS AS (`title`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `department_id` INT(11) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `level_id` INT(11) GENERATED ALWAYS AS (
        CASE
            WHEN TRIM(`level`) IN ('100', '100 Level') THEN 1
            WHEN TRIM(`level`) IN ('200', '200 Level') THEN 2
            WHEN TRIM(`level`) IN ('300', '300 Level') THEN 3
            WHEN TRIM(`level`) IN ('400', '400 Level') THEN 4
            WHEN TRIM(`level`) IN ('500', '500 Level') THEN 5
            WHEN TRIM(`level`) IN ('Year 1', 'Diploma Yr 1', 'Diploma Year 1') THEN 6
            WHEN TRIM(`level`) IN ('Year 2', 'Diploma Yr 2', 'Diploma Year 2') THEN 7
            ELSE 8
        END
    ) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `semester_id` INT(11) GENERATED ALWAYS AS (IF(`semester` IN (1, 2), `semester`, 1)) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `credit_units` INT(11) GENERATED ALWAYS AS (`credit_unit`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) GENERATED ALWAYS AS (IF(`status` = 'active', 1, 0)) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

UPDATE `courses` c
JOIN `programs` p ON c.program_id = p.id
SET c.department_id = p.department_id
WHERE c.department_id IS NULL;

