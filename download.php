<?php
/**
 * Secure Document Download Endpoint
 */

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth();

$paperId = (int)($_GET['paper_id'] ?? 0);
$versionId = (int)($_GET['version_id'] ?? 0);
$type = sanitizeInput($_GET['type'] ?? 'question');

if ($paperId <= 0) {
    http_response_code(400);
    die("Invalid examination paper ID.");
}

try {
    $paper = requirePaperAccess($paperId, 'download');

    // Enforce Cryptographic Blind Lockdown: block direct downloads once approved/locked
    $lockedStatuses = [STATUS_APPROVED, STATUS_LOCKED, STATUS_QUEUED, STATUS_PRINTED];
    if (in_array($paper['status'], $lockedStatuses, true)) {
        logAudit((int)$user['id'], 'blocked_locked_download_attempt', 'examination_paper', $paperId, [
            'status' => $paper['status'],
            'type' => $type
        ]);
        throw new RuntimeException("Access Denied: Examination paper is under Cryptographic Blind Lockdown (status: {$paper['status']}). Direct download is forbidden.");
    }
} catch (RuntimeException $e) {
    http_response_code(403);
    die($e->getMessage());
}

$pdo = getDb();
if ($versionId <= 0) {
    $versionId = (int)$paper['current_version_id'];
}

$stmtVer = $pdo->prepare("SELECT * FROM paper_versions WHERE id = :id AND paper_id = :paper_id");
$stmtVer->execute(['id' => $versionId, 'paper_id' => $paperId]);
$version = $stmtVer->fetch();

if (!$version) {
    http_response_code(404);
    die("Specified examination version not found.");
}

$filePath = ($type === 'guide') ? $version['guide_file_path'] : $version['question_file_path'];
$fileHash = ($type === 'guide') ? $version['guide_file_hash'] : $version['question_file_hash'];
$fileName = ($type === 'guide') ? $version['guide_file_name'] : $version['question_file_name'];

if (empty($filePath) || !file_exists($filePath)) {
    http_response_code(404);
    die("File not found on disk.");
}

if (!DocumentService::verifyFileIntegrity($filePath, $fileHash)) {
    logAudit((int)$user['id'], 'corrupted_download_attempt', 'document', $versionId, [
        'paper_id' => $paperId,
        'file' => $fileName
    ]);
    http_response_code(500);
    die("Document integrity verification failed. Potential file corruption.");
}

logAudit((int)$user['id'], 'download_document', 'examination_paper', $paperId, [
    'version' => $version['version_number'],
    'type' => $type,
    'file' => $fileName
]);

// Stream file download
while (ob_get_level()) {
    ob_end_clean();
}

$mime = mime_content_type($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
