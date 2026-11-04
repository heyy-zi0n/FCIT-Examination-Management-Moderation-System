<?php
/**
 * Test Suite: Authentication, Lockout & CSRF
 */

require_once __DIR__ . '/../bootstrap.php';

function testAuth(): bool {
    echo "--- Testing Authentication & Security Controls ---\n";
    $allPassed = true;

    // Test 1: Valid Login
    try {
        $user = AuthService::authenticate('lecturer.csc@lasu.edu.ng', 'Pass5656');
        if ($user && $user['email'] === 'lecturer.csc@lasu.edu.ng' && $user['role'] === ROLE_LECTURER) {
            echo "  [PASS] Valid authentication succeeds.\n";
        } else {
            echo "  [FAIL] Valid authentication returned invalid user data.\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  [FAIL] Valid authentication threw exception: " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    // Test 2: Invalid password
    try {
        AuthService::authenticate('lecturer.csc@lasu.edu.ng', 'WrongPassword!');
        echo "  [FAIL] Invalid password should have failed authentication.\n";
        $allPassed = false;
    } catch (Exception $e) {
        echo "  [PASS] Invalid password rejected correctly: " . $e->getMessage() . "\n";
    }

    // Test 3: Password Hashing & Verification
    $hash = hashPassword('TestSecret2026!');
    if (verifyPassword('TestSecret2026!', $hash) && !verifyPassword('WrongSecret!', $hash)) {
        echo "  [PASS] Password hashing and verification work as expected.\n";
    } else {
        echo "  [FAIL] Password hashing / verification logic failed.\n";
        $allPassed = false;
    }

    // Test 4: CSRF Generation and Verification
    $token = initCsrfToken();
    if (!empty($token) && verifyCsrfToken($token) && !verifyCsrfToken('tampered_token_string')) {
        echo "  [PASS] CSRF token generation and verification work as expected.\n";
    } else {
        echo "  [FAIL] CSRF verification failed.\n";
        $allPassed = false;
    }

    // Reset failed attempts for lecturer
    $pdo = getDb();
    $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE email = 'lecturer.csc@lasu.edu.ng'")->execute();

    return $allPassed;
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $ok = testAuth();
    exit($ok ? 0 : 1);
}
