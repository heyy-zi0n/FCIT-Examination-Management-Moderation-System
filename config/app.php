<?php
/**
 * Application Configuration
 */

require_once __DIR__ . '/constants.php';

// Set default timezone
date_default_timezone_set(APP_TIMEZONE);

return [
    'app_name' => APP_NAME,
    'full_name' => APP_FULL_NAME,
    'short_name' => APP_SHORT_NAME,
    'version' => APP_VERSION,
    'base_url' => $_ENV['APP_URL'] ?? '/FOCITECMS',
    'timezone' => APP_TIMEZONE,
    'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? 'lasu_fcit_secret_key_2026_exam_security_master_hash_v1', // Master encryption key for blind lockdown
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'session' => [
        'name' => 'LASU_FCIT_SESSID',
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true if running strictly on HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ],
    'libreoffice_path' => LIBREOFFICE_PATH
];
