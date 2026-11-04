<?php
/**
 * Application Configuration
 */

require_once __DIR__ . '/constants.php';

// Set default timezone
date_default_timezone_set(APP_TIMEZONE);

// Dynamically detect base URL if APP_URL is not configured in .env
if (!empty($_ENV['APP_URL'])) {
    $detectedBaseUrl = rtrim($_ENV['APP_URL'], '/');
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = (strpos($scriptName, '/') === 0)
        ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/')
        : '';
    $basePath = preg_replace('#/(dashboard|config|helpers|includes|middleware|services|tests)(/.*)?$#', '', $scriptDir);
    $detectedBaseUrl = $host !== '' ? "{$scheme}://{$host}{$basePath}" : $basePath;
}

// Fail-closed validation for the master encryption key
$encryptionKey = trim((string)($_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ?: ''));
if (strlen($encryptionKey) < 32) {
    throw new RuntimeException(
        'CRITICAL SECURITY ERROR: ENCRYPTION_KEY environment variable is missing or shorter than 32 bytes.'
    );
}

return [
    'app_name' => APP_NAME,
    'full_name' => APP_FULL_NAME,
    'short_name' => APP_SHORT_NAME,
    'version' => APP_VERSION,
    'base_url' => $detectedBaseUrl,
    'timezone' => APP_TIMEZONE,
    'encryption_key' => $encryptionKey,
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'session' => [
        'name' => 'LASU_FCIT_SESSID',
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ],
    'libreoffice_path' => LIBREOFFICE_PATH
];
