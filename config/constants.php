<?php
/**
 * LASU FCIT Secure Examination Question Management System
 * System Constants and Enums
 */

// Application Info
define('APP_NAME', 'LASU FCIT Examination Lifecycle System');
define('APP_FULL_NAME', 'Design and Implementation of a Secure Examination Question Lifecycle Management System with Moderation, Approval, Audit and Controlled Printing for LASU FCIT');
define('APP_SHORT_NAME', 'LASU FCIT CMS');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Africa/Lagos');

// System Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_LECTURER', 'lecturer');
define('ROLE_MODERATOR', 'moderator');
define('ROLE_HOD', 'hod');
define('ROLE_EXAM_OFFICER', 'exam_officer');

// Workflow States (Authoritative State Machine)
define('STATUS_DRAFT', 'Draft');
define('STATUS_SUBMITTED', 'Submitted');
define('STATUS_MODERATED', 'Moderated');
define('STATUS_APPROVED', 'Approved');
define('STATUS_LOCKED', 'Locked');
define('STATUS_QUEUED', 'Queued');
define('STATUS_PRINTED', 'Printed');
define('STATUS_RETURNED', 'Returned');

// Approval Decisions
define('DECISION_APPROVED', 'approved');
define('DECISION_RETURNED', 'returned');
define('DECISION_PENDING', 'pending');

// Approval Stages
define('STAGE_MODERATOR', 'moderator');
define('STAGE_HOD', 'hod');
define('STAGE_EXAM_OFFICER', 'exam_officer');

// User Account Status
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_INACTIVE', 'inactive');
define('USER_STATUS_LOCKED', 'locked');

// Security Settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME_SECONDS', 900); // 15 minutes
define('SESSION_LIFETIME_SECONDS', 1800); // 30 minutes
define('CSRF_TOKEN_KEY', 'csrf_token');

// Document & Media Storage Constraints
define('MAX_UPLOAD_SIZE_BYTES', 20 * 1024 * 1024); // 20MB
define('ALLOWED_DOC_EXTENSIONS', ['docx', 'pdf']);
define('ALLOWED_DOC_MIMES', [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/pdf',
    'application/msword'
]);

define('MAX_PROFILE_PIC_SIZE_BYTES', 5 * 1024 * 1024); // 5MB
define('ALLOWED_PROFILE_PIC_MIMES', [
    'image/jpeg',
    'image/png',
    'image/webp'
]);

// Document magic byte headers
define('MAGIC_DOCX', "PK\x03\x04");
define('MAGIC_PDF', "%PDF");

// Paths
define('STORAGE_PATH', __DIR__ . '/../storage');
define('EXAM_STORAGE_PATH', STORAGE_PATH . '/examinations');
define('PREVIEW_STORAGE_PATH', STORAGE_PATH . '/previews');
define('PROFILE_PICTURES_STORAGE_PATH', STORAGE_PATH . '/profile_pictures');
define('BACKUP_STORAGE_PATH', STORAGE_PATH . '/backups');
define('LOGS_STORAGE_PATH', STORAGE_PATH . '/logs');

// Default LibreOffice Path (Windows / Configurable)
define('LIBREOFFICE_PATH', 'C:\\Program Files\\LibreOffice\\program\\soffice.exe');

// Encryption Settings
define('ENCRYPTION_CIPHER', 'aes-256-gcm');
