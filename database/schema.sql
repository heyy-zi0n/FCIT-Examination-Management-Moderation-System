-- ==============================================================================
-- LASU FCIT SECURE EXAMINATION QUESTION MANAGEMENT SYSTEM
-- Authoritative Database Schema (MySQL Compatible)
-- Database: `lasu_fcit_exam_db`
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `lasu_fcit_exam_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `lasu_fcit_exam_db`;

-- Drop existing tables in reverse dependency order if recreating
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `print_logs`;
DROP TABLE IF EXISTS `blind_lockdowns`;
DROP TABLE IF EXISTS `moderation_comments`;
DROP TABLE IF EXISTS `paper_approvals`;
DROP TABLE IF EXISTS `paper_versions`;
DROP TABLE IF EXISTS `examination_papers`;
DROP TABLE IF EXISTS `delegations`;
DROP TABLE IF EXISTS `exam_schedules`;
DROP TABLE IF EXISTS `moderator_assignments`;
DROP TABLE IF EXISTS `lecturer_course_assignments`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `academic_levels`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `academic_sessions`;
DROP TABLE IF EXISTS `departments`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Departments Table
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Academic Sessions Table
CREATE TABLE `academic_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_name` VARCHAR(20) NOT NULL UNIQUE,
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Semesters Table
CREATE TABLE `semesters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Academic Levels Table
CREATE TABLE `academic_levels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `level_name` VARCHAR(50) NOT NULL,
    `level_code` INT NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Users Table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(20) DEFAULT 'Dr.',
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `profile_picture` VARCHAR(255) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'lecturer', 'moderator', 'hod', 'exam_officer') NOT NULL,
    `department_id` INT NULL,
    `status` ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Courses Table
CREATE TABLE `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(20) NOT NULL UNIQUE,
    `course_title` VARCHAR(200) NOT NULL,
    `department_id` INT NOT NULL,
    `level_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `credit_units` INT NOT NULL DEFAULT 3,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_courses_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_courses_level` FOREIGN KEY (`level_id`) REFERENCES `academic_levels` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_courses_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Lecturer Course Assignments Table
CREATE TABLE `lecturer_course_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lecturer_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `academic_session_id` INT NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
    `assigned_by` INT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lca_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lca_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lca_session` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lca_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_lecturer_course_session` (`lecturer_id`, `course_id`, `academic_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Moderator Assignments Table
CREATE TABLE `moderator_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `moderator_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `level_id` INT NOT NULL,
    `academic_session_id` INT NOT NULL,
    `assigned_by` INT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mod_user` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_level` FOREIGN KEY (`level_id`) REFERENCES `academic_levels` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_session` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_mod_dept_level_session` (`moderator_id`, `department_id`, `level_id`, `academic_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Examination Schedules Table (Authoritative Dates for Controlled Printing)
CREATE TABLE `exam_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `academic_session_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `exam_date` DATE NOT NULL,
    `exam_time` TIME NOT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 120,
    `venue` VARCHAR(150) NOT NULL,
    `expected_students` INT NOT NULL DEFAULT 100,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sched_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sched_session` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sched_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sched_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_course_session_semester_schedule` (`course_id`, `academic_session_id`, `semester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Delegations Table (HOD and EO Formal Delegation)
CREATE TABLE `delegations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `delegator_id` INT NOT NULL,
    `delegate_id` INT NOT NULL,
    `role_delegated` ENUM('hod', 'exam_officer') NOT NULL,
    `department_id` INT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `reason` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_del_delegator` FOREIGN KEY (`delegator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_delegate` FOREIGN KEY (`delegate_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Examination Papers Table
CREATE TABLE `examination_papers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_code` VARCHAR(50) NOT NULL UNIQUE,
    `course_id` INT NOT NULL,
    `academic_session_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `exam_schedule_id` INT NULL,
    `lecturer_id` INT NOT NULL,
    `current_version_id` INT NULL,
    `status` ENUM('Draft', 'Submitted', 'Returned', 'Moderated', 'Approved', 'Locked', 'Queued', 'Printed') NOT NULL DEFAULT 'Draft',
    `moderator_id` INT NULL,
    `moderator_decision` ENUM('pending', 'approved', 'returned') NOT NULL DEFAULT 'pending',
    `moderator_decision_at` DATETIME NULL,
    `hod_id` INT NULL,
    `hod_status` ENUM('pending', 'approved', 'returned') NOT NULL DEFAULT 'pending',
    `hod_decision_at` DATETIME NULL,
    `eo_id` INT NULL,
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
    CONSTRAINT `fk_papers_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_papers_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_papers_hod` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_papers_eo` FOREIGN KEY (`eo_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_papers_status` (`status`),
    INDEX `idx_papers_course_session` (`course_id`, `academic_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Paper Versions Table (Immutable Versioning)
CREATE TABLE `paper_versions` (
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
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_versions_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_versions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_paper_version` (`paper_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update foreign key in examination_papers to point to current_version_id
ALTER TABLE `examination_papers`
    ADD CONSTRAINT `fk_papers_cur_version`
    FOREIGN KEY (`current_version_id`) REFERENCES `paper_versions` (`id`) ON DELETE SET NULL;

-- 13. Paper Approvals Table (Independent Decision Trail for Moderator, HOD, and Exam Officer)
CREATE TABLE `paper_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `stage` ENUM('moderator', 'hod', 'exam_officer') NOT NULL,
    `approver_id` INT NOT NULL,
    `decision` ENUM('approved', 'returned') NOT NULL,
    `comments` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_appr_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appr_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appr_user` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_appr_stage_dec` (`stage`, `decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Moderation Comments Table (Line-by-Line Annotation and Rubric Feedback)
CREATE TABLE `moderation_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `author_id` INT NOT NULL,
    `line_reference` VARCHAR(100) NULL,
    `comment_type` ENUM('correction', 'clarity', 'marks_allocation', 'rubric', 'general') NOT NULL DEFAULT 'general',
    `comment_text` TEXT NOT NULL,
    `status` ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    `resolved_by` INT NULL,
    `resolved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_modcomm_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_modcomm_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_modcomm_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_modcomm_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Blind Lockdowns Table (AES-256 Encrypted Final Storage)
CREATE TABLE `blind_lockdowns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL UNIQUE,
    `version_id` INT NOT NULL,
    `locked_by` INT NOT NULL,
    `encrypted_file_path` VARCHAR(255) NOT NULL,
    `checksum_sha256` VARCHAR(64) NOT NULL,
    `encryption_algorithm` VARCHAR(50) NOT NULL DEFAULT 'aes-256-gcm',
    `encryption_iv` VARCHAR(255) NOT NULL,
    `encryption_tag` VARCHAR(255) NOT NULL,
    `locked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_unlocked` TINYINT(1) NOT NULL DEFAULT 0,
    `unlock_reason` TEXT NULL,
    `unlocked_by` INT NULL,
    `unlocked_at` DATETIME NULL,
    CONSTRAINT `fk_lock_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lock_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lock_user` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_unlock_user` FOREIGN KEY (`unlocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Controlled Print Logs Table
CREATE TABLE `print_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `exam_officer_id` INT NOT NULL,
    `copies_printed` INT NOT NULL DEFAULT 1,
    `print_status` ENUM('successful', 'failed', 'reprint') NOT NULL DEFAULT 'successful',
    `verification_code` VARCHAR(64) NOT NULL,
    `client_ip` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `is_reprint` TINYINT(1) NOT NULL DEFAULT 0,
    `reprint_reason` TEXT NULL,
    `reprint_approved_by` INT NULL,
    `printed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_print_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_print_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_print_eo` FOREIGN KEY (`exam_officer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reprint_approver` FOREIGN KEY (`reprint_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_print_paper_eo` (`paper_id`, `exam_officer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Emergency Print Requests Table
CREATE TABLE `emergency_print_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `paper_id` INT NOT NULL,
    `version_id` INT NOT NULL,
    `exam_officer_id` INT NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `hod_id` INT NULL,
    `hod_decision_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_epr_paper` FOREIGN KEY (`paper_id`) REFERENCES `examination_papers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_epr_version` FOREIGN KEY (`version_id`) REFERENCES `paper_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_epr_eo` FOREIGN KEY (`exam_officer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_epr_hod` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_epr_paper_status` (`paper_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. In-App Notifications Table
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_notif_user_unread` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Audit Logs Table (Tamper-Evident Trail)
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_audit_user_action` (`user_id`, `action`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
