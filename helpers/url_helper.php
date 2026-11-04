<?php
/**
 * Application URL Helper
 * Safely generates URLs for the application, handling base URLs, paths, and query strings.
 */

/**
 * Generate an absolute URL for the application.
 *
 * @param string $path The relative path (e.g., 'dashboard/index.php' or '/dashboard/index.php?id=1#top').
 * @return string The absolute URL.
 */
function url(string $path = ''): string {
    global $appConfig;
    if (!empty($appConfig['base_url'])) {
        $baseUrl = rtrim($appConfig['base_url'], '/');
    } elseif (!empty($_ENV['APP_URL'])) {
        $baseUrl = rtrim($_ENV['APP_URL'], '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = (strpos($scriptName, '/') === 0)
            ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/')
            : '';
        $basePath = preg_replace('#/(dashboard|config|helpers|includes|middleware|services|tests)(/.*)?$#', '', $scriptDir);
        $baseUrl = $host !== '' ? "{$scheme}://{$host}{$basePath}" : $basePath;
    }
    
    if ($path === '') {
        return $baseUrl . '/';
    }
    
    // Ensure path starts with a slash
    $path = '/' . ltrim($path, '/');
    
    return $baseUrl . $path;
}

/**
 * Perform an HTTP redirect to an application URL.
 *
 * @param string $path The relative path.
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}
