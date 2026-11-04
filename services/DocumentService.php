<?php
/**
 * Document Storage & Preview Service
 * Handles physical file isolation, integrity verification, and LibreOffice conversion
 */

class DocumentService {

    /**
     * Store version files into isolated physical directory
     */
    public static function storeVersionFiles(int $paperId, int $versionNumber, array $questionUpload, ?array $guideUpload = null): array {
        $versionDir = EXAM_STORAGE_PATH . "/paper-{$paperId}/v{$versionNumber}";

        if (!is_dir($versionDir)) {
            if (!mkdir($versionDir, 0755, true)) {
                throw new RuntimeException("Failed to create secure storage directory for paper {$paperId} version {$versionNumber}.");
            }
        }

        // Generate non-guessable secure file names
        $randomPrefix = bin2hex(random_bytes(8));
        $qFileName = "q_{$randomPrefix}." . $questionUpload['extension'];
        $qTarget = "{$versionDir}/{$qFileName}";

        if (!move_uploaded_file($questionUpload['tmp_path'], $qTarget)) {
            // Fallback for non-HTTP uploads during unit tests
            if (!copy($questionUpload['tmp_path'], $qTarget)) {
                throw new RuntimeException("Failed to move question paper file to secure destination.");
            }
        }

        // Verify stored file hash
        $storedQHash = hash_file('sha256', $qTarget);
        if (!hash_equals($questionUpload['hash_sha256'], $storedQHash)) {
            unlink($qTarget);
            throw new RuntimeException("Integrity check failed immediately after storing question file.");
        }

        $result = [
            'question_path' => $qTarget,
            'question_name' => $questionUpload['original_name'],
            'question_hash' => $storedQHash,
            'question_size' => filesize($qTarget),
            'guide_path' => null,
            'guide_name' => null,
            'guide_hash' => null,
            'guide_size' => null
        ];

        // Store marking guide if provided
        if ($guideUpload !== null && !empty($guideUpload['tmp_path'])) {
            $gFileName = "g_{$randomPrefix}." . $guideUpload['extension'];
            $gTarget = "{$versionDir}/{$gFileName}";

            if (!move_uploaded_file($guideUpload['tmp_path'], $gTarget)) {
                if (!copy($guideUpload['tmp_path'], $gTarget)) {
                    throw new RuntimeException("Failed to move marking guide file to secure destination.");
                }
            }

            $storedGHash = hash_file('sha256', $gTarget);
            if (!hash_equals($guideUpload['hash_sha256'], $storedGHash)) {
                unlink($gTarget);
                throw new RuntimeException("Integrity check failed immediately after storing guide file.");
            }

            $result['guide_path'] = $gTarget;
            $result['guide_name'] = $guideUpload['original_name'];
            $result['guide_hash'] = $storedGHash;
            $result['guide_size'] = filesize($gTarget);
        }

        return $result;
    }

    /**
     * Verify physical file existence and SHA-256 hash match
     */
    public static function verifyFileIntegrity(string $filePath, string $expectedHash): bool {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            error_log("Document integrity check failed: File does not exist or unreadable ({$filePath})");
            return false;
        }

        $actualHash = hash_file('sha256', $filePath);
        return hash_equals($expectedHash, $actualHash);
    }

    /**
     * Generate PDF Preview using LibreOffice Headless
     * Uses parameterized array proc_open() to prevent OS command injection.
     */
    public static function generatePdfPreview(string $sourceDocxPath, string $previewPdfPath): bool {
        if (file_exists($previewPdfPath)) {
            return true; // Already generated
        }

        if (!file_exists($sourceDocxPath) || !is_readable($sourceDocxPath)) {
            error_log("LibreOffice PDF conversion aborted: Source file does not exist ({$sourceDocxPath})");
            return false;
        }

        $previewDir = dirname($previewPdfPath);
        if (!is_dir($previewDir)) {
            mkdir($previewDir, 0755, true);
        }

        $libreOfficePath = LIBREOFFICE_PATH;
        if (!file_exists($libreOfficePath)) {
            // Check alternate default locations
            $alts = [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                '/usr/bin/soffice',
                '/usr/bin/libreoffice'
            ];
            foreach ($alts as $alt) {
                if (file_exists($alt)) {
                    $libreOfficePath = $alt;
                    break;
                }
            }
        }

        $sourceBaseName = pathinfo($sourceDocxPath, PATHINFO_FILENAME);
        $tempConvertedPdf = $previewDir . DIRECTORY_SEPARATOR . $sourceBaseName . '.pdf';

        // Normalize paths
        $realSourceDocxPath = realpath($sourceDocxPath) ?: $sourceDocxPath;
        $realPreviewDir = realpath($previewDir) ?: $previewDir;

        // Execute LibreOffice directly via proc_open with argument array (bypasses cmd.exe / PowerShell shell parsing)
        $cmdArgs = [
            $libreOfficePath,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $realPreviewDir,
            $realSourceDocxPath
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = @proc_open($cmdArgs, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        $output = '';
        $returnVar = -1;

        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $output = trim($stdout . "\n" . $stderr);
            $returnVar = proc_close($process);
        }

        if ($returnVar === 0 && file_exists($tempConvertedPdf)) {
            if ($tempConvertedPdf !== $previewPdfPath) {
                rename($tempConvertedPdf, $previewPdfPath);
            }
            return true;
        }

        error_log("LibreOffice PDF conversion failed for {$sourceDocxPath}. Return code: {$returnVar}. Output: {$output}");
        return false;
    }

    /**
     * Get or build preview PDF for a specific version
     */
    public static function getPaperPreviewPdf(int $paperId, int $versionId): ?string {
        $pdo = getDb();
        $stmt = $pdo->prepare("
            SELECT pv.*, ep.paper_code
            FROM paper_versions pv
            JOIN examination_papers ep ON pv.paper_id = ep.id
            WHERE pv.id = :version_id AND pv.paper_id = :paper_id
        ");
        $stmt->execute(['version_id' => $versionId, 'paper_id' => $paperId]);
        $version = $stmt->fetch();

        if (!$version) {
            return null;
        }

        // Check if question file is already a PDF
        $ext = strtolower(pathinfo($version['question_file_path'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            if (self::verifyFileIntegrity($version['question_file_path'], $version['question_file_hash'])) {
                return $version['question_file_path'];
            }
            return null;
        }

        // For DOCX, generate preview PDF
        $previewPdfPath = PREVIEW_STORAGE_PATH . "/paper_{$paperId}_v{$version['version_number']}.pdf";
        if (file_exists($previewPdfPath)) {
            return $previewPdfPath;
        }

        if (self::verifyFileIntegrity($version['question_file_path'], $version['question_file_hash'])) {
            $ok = self::generatePdfPreview($version['question_file_path'], $previewPdfPath);
            return $ok ? $previewPdfPath : null;
        }

        return null;
    }

    /**
     * Securely stream PDF to browser with cache headers
     */
    public static function streamPdfToBrowser(string $pdfPath, string $downloadFilename, bool $inline = true): void {
        if (!file_exists($pdfPath) || !is_readable($pdfPath)) {
            http_response_code(404);
            die("Requested document preview is unavailable.");
        }

        // Clean output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = basename($downloadFilename);

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('X-Content-Type-Options: nosniff');

        readfile($pdfPath);
        exit;
    }
}
