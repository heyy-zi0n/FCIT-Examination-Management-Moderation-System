<?php
/**
 * Authentication Service
 * Handles user authentication, rate limiting, and brute-force protection
 */

class AuthService {

    /**
     * Authenticate user credentials and manage account lockout
     * @throws RuntimeException on invalid login or locked account
     */
    public static function authenticate(string $email, string $password): array {
        $cleanEmail = trim(strtolower($email));
        $pdo = getDb();

        $stmt = $pdo->prepare("
            SELECT u.*, d.code AS department_code, d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $cleanEmail]);
        $user = $stmt->fetch();

        if (!$user) {
            // Log generic failed login
            logAudit(null, 'failed_login', 'auth', null, [
                'email' => $cleanEmail,
                'reason' => 'user_not_found'
            ]);
            throw new RuntimeException('Invalid email address or password.');
        }

        // Check if account is inactive
        if ($user['status'] === USER_STATUS_INACTIVE) {
            throw new RuntimeException('This account has been deactivated. Please contact the administrator.');
        }

        // Check if account is currently locked out
        if ($user['locked_until'] !== null) {
            $lockExpiry = strtotime($user['locked_until']);
            if (time() < $lockExpiry) {
                $remainingMinutes = ceil(($lockExpiry - time()) / 60);
                throw new RuntimeException("Account is temporarily locked due to excessive failed attempts. Please retry in {$remainingMinutes} minute(s).");
            }
        }

        // Verify Password
        if (!verifyPassword($password, $user['password_hash'])) {
            $newAttempts = (int)$user['failed_attempts'] + 1;
            $lockedUntil = null;

            if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME_SECONDS);
                $stmtLock = $pdo->prepare("UPDATE users SET failed_attempts = :att, locked_until = :lock_dt, status = 'locked' WHERE id = :id");
                $stmtLock->execute(['att' => $newAttempts, 'lock_dt' => $lockedUntil, 'id' => (int)$user['id']]);

                logAudit((int)$user['id'], 'account_lockout', 'user', (int)$user['id'], [
                    'email' => $cleanEmail,
                    'failed_attempts' => $newAttempts,
                    'locked_until' => $lockedUntil
                ]);

                throw new RuntimeException("Too many failed attempts. Your account has been locked for 15 minutes.");
            } else {
                $stmtAtt = $pdo->prepare("UPDATE users SET failed_attempts = :att WHERE id = :id");
                $stmtAtt->execute(['att' => $newAttempts, 'id' => (int)$user['id']]);

                logAudit((int)$user['id'], 'failed_login_bad_password', 'auth', (int)$user['id'], [
                    'email' => $cleanEmail,
                    'failed_attempts' => $newAttempts
                ]);

                $remaining = MAX_LOGIN_ATTEMPTS - $newAttempts;
                throw new RuntimeException("Invalid email address or password. {$remaining} attempt(s) remaining before lockout.");
            }
        }

        // Password is correct: login and reset lockout counters
        loginUser($user);

        logAudit((int)$user['id'], 'successful_login', 'auth', (int)$user['id'], [
            'email' => $cleanEmail,
            'role' => $user['role']
        ]);

        return $user;
    }
}
