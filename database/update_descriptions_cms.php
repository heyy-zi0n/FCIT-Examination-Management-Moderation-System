<?php
/**
 * Migration Script: Update Departments Descriptions to CMS Context
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden: CLI execution only.');
}

require_once __DIR__ . '/../bootstrap.php';
$pdo = getDb();

// Map of code -> description focused on the Secure CMS
$updates = [
    'CSC' => 'Governing over 40+ examination papers with strict cryptographic blind-lockdown and dual-approval workflows.',
    'CYB' => 'Enforcing absolute confidentiality of examination materials through AES-256-GCM encryption and secure routing.',
    'DSC' => 'Managing complex examination pipelines with robust audit trails and automated date-gate enforcement.',
    'ICT' => 'Ensuring zero-trust question moderation and seamless, controlled physical printing authorization.',
    'SEN' => 'Facilitating multi-tier academic review processes and securely locking down software engineering examination boards.'
];

$stmt = $pdo->prepare("UPDATE departments SET description = ? WHERE code = ?");
foreach ($updates as $code => $desc) {
    $stmt->execute([$desc, $code]);
}

echo "Department descriptions updated successfully to reflect CMS workflows!\n";
