<?php
/**
 * Secure Document Preview Endpoint
 * Converts DOCX to PDF securely and streams inline with authorization checks
 */

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth();

$paperId = (int)($_GET['paper_id'] ?? 0);
$versionId = (int)($_GET['version_id'] ?? 0);
$type = sanitizeInput($_GET['type'] ?? 'question'); // 'question' or 'guide'

if ($paperId <= 0) {
    http_response_code(400);
    die("Invalid examination paper ID.");
}

// 1. Authorize paper access & enforce Cryptographic Blind Lockdown
try {
    $paper = requirePaperAccess($paperId, 'preview');

    $lockedStatuses = [STATUS_APPROVED, STATUS_LOCKED, STATUS_QUEUED, STATUS_PRINTED];
    if (in_array($paper['status'], $lockedStatuses, true)) {
        logAudit((int)$user['id'], 'blocked_locked_preview_attempt', 'examination_paper', $paperId, [
            'status' => $paper['status'],
            'type' => $type
        ]);
        throw new RuntimeException("Access Denied: Examination paper is under Cryptographic Blind Lockdown (status: {$paper['status']}). Direct preview is forbidden.");
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

// Check which file to preview
$filePath = ($type === 'guide') ? $version['guide_file_path'] : $version['question_file_path'];
$fileHash = ($type === 'guide') ? $version['guide_file_hash'] : $version['question_file_hash'];
$fileName = ($type === 'guide') ? $version['guide_file_name'] : $version['question_file_name'];

if (empty($filePath) || !file_exists($filePath)) {
    http_response_code(404);
    die("The requested document file does not exist on disk.");
}

// 2. Verify SHA-256 integrity
if (!DocumentService::verifyFileIntegrity($filePath, $fileHash)) {
    logAudit((int)$user['id'], 'corrupted_file_integrity_detected', 'document', $versionId, [
        'paper_id' => $paperId,
        'file' => $fileName
    ]);
    http_response_code(500);
    die("Document integrity verification failed. Potential file corruption or tampering detected.");
}

// 3. Log preview audit
logAudit((int)$user['id'], 'preview_document', 'examination_paper', $paperId, [
    'version' => $version['version_number'],
    'type' => $type,
    'file' => $fileName
]);

// 4. If PDF, stream directly
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
if ($ext === 'pdf') {
    DocumentService::streamPdfToBrowser($filePath, $fileName, true);
    exit;
}

// 5. If DOCX, generate and stream preview PDF
$previewPdfPath = PREVIEW_STORAGE_PATH . "/preview_p{$paperId}_v{$version['version_number']}_{$type}.pdf";
$ok = DocumentService::generatePdfPreview($filePath, $previewPdfPath);

if ($ok && file_exists($previewPdfPath)) {
    DocumentService::streamPdfToBrowser($previewPdfPath, "Preview_{$fileName}.pdf", true);
} else {
    http_response_code(500);
    die("Document preview is currently unavailable for DOCX. Please ensure LibreOffice service is configured.");
}
