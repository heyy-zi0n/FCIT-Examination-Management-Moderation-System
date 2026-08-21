<?php
/**
 * Document Security Helper
 * Validation routines for file formats, magic bytes, and secure hashes
 */

/**
 * Validate an uploaded examination document
 * @throws InvalidArgumentException on validation failure
 */
function validateUploadedDocument(array $file, array $allowedExtensions = ['docx', 'pdf']): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new InvalidArgumentException('Invalid file upload parameters.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE specified in the form.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by PHP extension.'
        ];
        throw new InvalidArgumentException($errors[$file['error']] ?? 'Unknown upload error.');
    }

    // Check size limit
    if ($file['size'] > MAX_UPLOAD_SIZE_BYTES) {
        throw new InvalidArgumentException('File size (' . formatBytes($file['size']) . ') exceeds maximum limit of ' . formatBytes(MAX_UPLOAD_SIZE_BYTES) . '.');
    }

    // Validate Extension
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new InvalidArgumentException('Invalid file type: .' . $extension . '. Allowed formats: .' . implode(', .', $allowedExtensions));
    }

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $validMimes = [
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream'
        ],
        'pdf' => [
            'application/pdf',
            'application/x-pdf'
        ]
    ];

    if (!isset($validMimes[$extension]) || !in_array($mimeType, $validMimes[$extension], true)) {
        throw new InvalidArgumentException('File MIME type (' . $mimeType . ') does not match expected format for .' . $extension);
    }

    // Validate Magic Bytes
    $handle = fopen($file['tmp_name'], 'rb');
    if (!$handle) {
        throw new InvalidArgumentException('Unable to read uploaded file buffer.');
    }
    $header = fread($handle, 4);
    fclose($handle);

    if ($extension === 'docx' && substr($header, 0, 4) !== MAGIC_DOCX) {
        throw new InvalidArgumentException('File header signature does not match a valid Microsoft Word (.docx) document.');
    }

    if ($extension === 'pdf' && substr($header, 0, 4) !== MAGIC_PDF) {
        throw new InvalidArgumentException('File header signature does not match a valid PDF document.');
    }

    // Compute SHA-256 Hash
    $fileHash = hash_file('sha256', $file['tmp_name']);

    return [
        'original_name' => sanitizeInput($originalName),
        'extension' => $extension,
        'mime_type' => $mimeType,
        'size' => (int)$file['size'],
        'tmp_path' => $file['tmp_name'],
        'hash_sha256' => $fileHash
    ];
}
