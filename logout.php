<?php
/**
 * Logout Endpoint
 */

require_once __DIR__ . '/bootstrap.php';

logoutUser();
setFlash('info', 'You have been successfully logged out of the examination system.');
header("Location: /FOCITECMS/login.php");
exit;
