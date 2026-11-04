<?php
/**
 * Canonical Application Bootstrap
 * LASU FCIT Secure Examination Question Management System
 */

// Prevent multiple bootstrap executions
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

// 1. Load Configurations & Constants
require_once __DIR__ . '/config/env.php';
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    die("Configuration Error: " . $e->getMessage());
}

require_once __DIR__ . '/config/constants.php';
$appConfig = require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// 2. Set Default Timezone & Baseline Security Headers
date_default_timezone_set(APP_TIMEZONE);
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// 3. Configure Secure Sessions
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME_SECONDS);

    session_name($appConfig['session']['name']);
    session_set_cookie_params([
        'lifetime' => $appConfig['session']['lifetime'],
        'path' => $appConfig['session']['path'],
        'domain' => $appConfig['session']['domain'],
        'secure' => $appConfig['session']['secure'],
        'httponly' => $appConfig['session']['httponly'],
        'samesite' => $appConfig['session']['samesite']
    ]);

    session_start();
}

// 4. Load Core Helpers (single authoritative definitions)
require_once __DIR__ . '/helpers/security_helper.php';
require_once __DIR__ . '/helpers/audit_helper.php';
require_once __DIR__ . '/helpers/notification_helper.php';
require_once __DIR__ . '/helpers/document_helper.php';
require_once __DIR__ . '/helpers/workflow_helper.php';
require_once __DIR__ . '/helpers/url_helper.php';
require_once __DIR__ . '/helpers/view_helper.php';
require_once __DIR__ . '/includes/alerts.php';

// 5. Load Business Services
require_once __DIR__ . '/services/EncryptionService.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/DocumentService.php';
require_once __DIR__ . '/services/WorkflowService.php';
require_once __DIR__ . '/services/PrintingService.php';
require_once __DIR__ . '/services/ProfileService.php';

// 6. Load Middleware
require_once __DIR__ . '/middleware/auth_middleware.php';
require_once __DIR__ . '/middleware/csrf_middleware.php';
require_once __DIR__ . '/middleware/permission_middleware.php';

// 7. Check Inactivity & Session Expiration
checkSessionInactivity();

// 8. Ensure CSRF Token Exists
initCsrfToken();
