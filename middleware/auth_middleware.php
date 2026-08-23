<?php
/**
 * Authentication & Role Middleware
 * Enforces session authentication and RBAC
 */

/**
 * Require authenticated session
 */
function requireAuth(): array {
    $user = currentUser();
    if (!$user) {
        $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? url('index.php'));
        redirect("login.php?redirect={$returnUrl}");
        exit;
    }
    return $user;
}

/**
 * Require specific role(s)
 */
function requireRole(string|array $allowedRoles): array {
    $user = requireAuth();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    // Admin always has broad administrative access where applicable
    if ($user['role'] === ROLE_ADMIN) {
        return $user;
    }

    // Direct role match
    if (in_array($user['role'], $roles, true)) {
        return $user;
    }

    // Check active delegations for HOD or Exam Officer
    $pdo = getDb();
    $stmtDel = $pdo->prepare("
        SELECT role_delegated, department_id FROM delegations
        WHERE delegate_id = :user_id AND is_active = 1 AND CURRENT_DATE BETWEEN start_date AND end_date
    ");
    $stmtDel->execute(['user_id' => (int)$user['id']]);
    $delegations = $stmtDel->fetchAll();

    foreach ($delegations as $del) {
        if (in_array($del['role_delegated'], $roles, true)) {
            return $user;
        }
    }

    // Access Denied: Fail closed
    logAudit((int)$user['id'], 'unauthorized_role_access_attempt', 'security', null, [
        'required_roles' => $roles,
        'user_role' => $user['role'],
        'uri' => $_SERVER['REQUEST_URI'] ?? ''
    ]);

    http_response_code(403);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="max-w-xl mx-auto my-16 p-8 bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-red-200 text-center">
            <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Access Forbidden (403)</h2>
            <p class="text-slate-600 dark:text-slate-400 mb-6">You do not have the required role permissions to access this page.</p>
            <a href="' . url('dashboard/' . $user['role'] . '/') . '" class="px-6 py-2.5 bg-blue-900 text-white rounded-lg font-medium hover:bg-blue-800 transition">Return to My Dashboard</a>
          </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

/**
 * Require unauthenticated guest (redirect if already logged in)
 */
function requireGuest(): void {
    $user = currentUser();
    if ($user) {
        $role = $user['role'];
        redirect("dashboard/{$role}/index.php");
        exit;
    }
}
