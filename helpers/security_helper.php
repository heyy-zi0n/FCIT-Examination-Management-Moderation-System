<?php
/**
 * Security & Session Management Helper
 * Authoritative implementation for authentication, CSRF, and session validation
 */

/**
 * Get current logged in user array or null
 */
function currentUser(): ?array {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return null;
    }

    // Return cached user if present in session
    if (isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }

    // Otherwise fetch fresh from database
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("
            SELECT u.id, u.staff_id, u.title, u.first_name, u.last_name, u.email, u.role, u.department_id, u.status, u.profile_picture,
                   d.code AS department_code, d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = :id AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute(['id' => (int)$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_data'] = $user;
            return $user;
        }
    } catch (Exception $e) {
        error_log('Error loading current user: ' . $e->getMessage());
    }

    return null;
}

/**
 * Check if user is currently authenticated
 */
function isLoggedIn(): bool {
    return currentUser() !== null;
}

/**
 * Establish authenticated user session
 */
function loginUser(array $user): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Prevent session fixation
    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_data'] = $user;
    $_SESSION['last_activity'] = time();

    // Regenerate CSRF token on login
    $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));

    // Update last_login_at in database
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = :id");
        $stmt->execute(['id' => (int)$user['id']]);
    } catch (Exception $e) {
        error_log('Failed to update user login time: ' . $e->getMessage());
    }
}

/**
 * Invalidate user session and logout
 */
function logoutUser(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        logAudit($userId, 'logout', 'user', $userId, ['description' => 'User logged out']);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Check session inactivity and expire if idle too long
 */
function checkSessionInactivity(): void {
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME_SECONDS)) {
            $expiredUserId = $_SESSION['user_id'];
            logoutUser();
            redirect('login.php?error=session_expired');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Initialize CSRF Token
 */
function initCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * Get active CSRF Token
 */
function getCsrfToken(): string {
    return initCsrfToken();
}

/**
 * Output HTML hidden CSRF input field
 */
function csrfField(): string {
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify submitted CSRF token against session
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION[CSRF_TOKEN_KEY])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_KEY], $token);
}

/**
 * Sanitize string input
 */
function sanitizeInput(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    if (is_string($data)) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Get client IP address safely
 */
function getClientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/**
 * Get client User Agent string
 */
function getUserAgent(): string {
    return substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);
}

/**
 * Secure password hashing using bcrypt
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Secure password verification
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}
