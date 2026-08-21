<?php
/**
 * CSRF Protection Middleware
 * Verifies CSRF token on all state-changing HTTP requests
 */

function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!verifyCsrfToken($token)) {
            $user = currentUser();
            logAudit($user ? (int)$user['id'] : null, 'invalid_csrf_token', 'security', null, [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => getClientIp()
            ]);

            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid or expired CSRF security token. Please refresh the page and try again.']);
                exit;
            }

            die("Security Error: Invalid or expired CSRF token. Please return to the previous page, refresh, and try again.");
        }
    }
}
