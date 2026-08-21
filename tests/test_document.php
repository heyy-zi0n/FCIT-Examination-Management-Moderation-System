<?php
/**
 * Test Suite: Document Security, Integrity & AES-256 Lockdown Encryption
 */

require_once __DIR__ . '/../bootstrap.php';

function testDocument(): bool {
    echo "--- Testing Document Security & Encryption ---\n";
    $allPassed = true;

    // Create a temporary valid DOCX file (ZIP format with PK\x03\x04 header)
    $tmpDocx = sys_get_temp_dir() . '/test_exam_' . uniqid() . '.docx';
    $docxContent = MAGIC_DOCX . "\x14\x00\x06\x00\x08\x00\x00\x00SAMPLE LASU FCIT EXAMINATION QUESTION CONTENT FOR CSC 301";
    file_put_contents($tmpDocx, $docxContent);

    // Test 1: Upload Validation for valid DOCX
    $fileArray = [
        'name' => 'CSC301_Exam_Question.docx',
        'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'tmp_name' => $tmpDocx,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($docxContent)
    ];

    try {
        $validated = validateUploadedDocument($fileArray);
        if ($validated['extension'] === 'docx' && $validated['hash_sha256'] === hash('sha256', $docxContent)) {
            echo "  [PASS] Valid DOCX upload validated with matching SHA-256.\n";
        } else {
            echo "  [FAIL] Validation did not return expected structure.\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  [FAIL] Valid DOCX rejected: " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    // Test 2: Upload Validation for Invalid File (e.g. .exe pretending to be .docx)
    $tmpFake = sys_get_temp_dir() . '/fake_' . uniqid() . '.docx';
    file_put_contents($tmpFake, "MZ\x90\x00EXECUTABLE_CONTENT");
    $fakeFile = [
        'name' => 'malicious.docx',
        'type' => 'application/octet-stream',
        'tmp_name' => $tmpFake,
        'error' => UPLOAD_ERR_OK,
        'size' => 20
    ];
    try {
        validateUploadedDocument($fakeFile);
        echo "  [FAIL] Corrupted/fake magic header was not rejected.\n";
        $allPassed = false;
    } catch (InvalidArgumentException $e) {
        echo "  [PASS] Invalid magic byte header rejected correctly: " . $e->getMessage() . "\n";
    }
    @unlink($tmpFake);

    // Test 3: AES-256-GCM Encryption and Decryption
    $samplePlain = "CONFIDENTIAL EXAMINATION QUESTIONS: 1. Explain Relational Calculus vs Relational Algebra [20 Marks]";
    $enc = EncryptionService::encryptFileContent($samplePlain);
    
    if (!empty($enc['ciphertext']) && !empty($enc['iv']) && !empty($enc['tag'])) {
        echo "  [PASS] AES-256-GCM encryption produced ciphertext and authentication tag.\n";
    } else {
        echo "  [FAIL] Encryption output invalid.\n";
        $allPassed = false;
    }

    try {
        $decrypted = EncryptionService::decryptFileContent($enc['ciphertext'], $enc['iv'], $enc['tag'], $enc['checksum_sha256']);
        if ($decrypted === $samplePlain) {
            echo "  [PASS] Decryption restored exact plain content with verified checksum.\n";
        } else {
            echo "  [FAIL] Decrypted text did not match original.\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  [FAIL] Decryption failed: " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    // Test 4: Tampered Ciphertext must throw decryption authentication failure
    try {
        $tamperedCipher = $enc['ciphertext'];
        $tamperedCipher[0] = chr(ord($tamperedCipher[0]) ^ 0xFF);
        EncryptionService::decryptFileContent($tamperedCipher, $enc['iv'], $enc['tag'], $enc['checksum_sha256']);
        echo "  [FAIL] Tampered ciphertext was decrypted without error.\n";
        $allPassed = false;
    } catch (RuntimeException $e) {
        echo "  [PASS] Tampered ciphertext rejected by authentication tag.\n";
    }

    @unlink($tmpDocx);
    return $allPassed;
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    $ok = testDocument();
    exit($ok ? 0 : 1);
}
