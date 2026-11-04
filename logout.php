<?php
/**
 * Logout Endpoint (POST + CSRF Enforced)
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('');
    exit;
}

requireCsrf();
logoutUser();
setFlash('info', 'You have been successfully logged out of the examination system.');
redirect('login.php');
exit;
