<?php
/**
 * LASU FCIT Secure Examination Question Management System
 * Secure Avatar Delivery Endpoint
 */

require_once __DIR__ . '/bootstrap.php';

// Ensure user is authenticated to view avatars
requireAuth();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    exit('Invalid User ID');
}

$pdo = getDb();
$stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$profilePicture = $stmt->fetchColumn();

if (empty($profilePicture)) {
    http_response_code(404);
    exit('Not Found');
}

// Ensure the filename is clean and prevent path traversal
$filename = basename($profilePicture);
$filepath = PROFILE_PICTURES_STORAGE_PATH . '/' . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('Not Found');
}

// Determine MIME type
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
$mime = 'image/jpeg';
if ($ext === 'png') {
    $mime = 'image/png';
} elseif ($ext === 'webp') {
    $mime = 'image/webp';
}

// Send file headers and content
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=86400'); // Cache for 1 day
readfile($filepath);
exit;
