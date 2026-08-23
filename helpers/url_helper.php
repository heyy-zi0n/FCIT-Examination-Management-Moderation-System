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
    $baseUrl = rtrim($appConfig['base_url'] ?? '/FOCITECMS', '/');
    
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
