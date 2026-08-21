<?php
/**
 * Master Automated Test Runner
 * LASU FCIT Secure Examination Question Management System
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/test_database.php';
require_once __DIR__ . '/test_auth.php';
require_once __DIR__ . '/test_document.php';
require_once __DIR__ . '/test_workflow.php';
require_once __DIR__ . '/test_printing.php';
require_once __DIR__ . '/test_security_idor.php';
require_once __DIR__ . '/test_http_endpoints.php';

echo "======================================================================\n";
echo "LASU FCIT CMS — AUTOMATED SYSTEM & INTEGRITY TEST SUITE\n";
echo "======================================================================\n\n";

$results = [];

$suites = [
    'Database & Relationships' => 'testDatabase',
    'Authentication & Security Controls' => 'testAuth',
    'Document Security & AES-256 Lockdown' => 'testDocument',
    'Workflow State Machine & Dual Approval' => 'testWorkflow',
    'Controlled Printing & Lagos Timezone Restriction' => 'testPrinting',
    'IDOR Protection & Departmental Boundaries' => 'testSecurityIdor',
    'Live HTTP Endpoints & Role Dashboards' => 'testHttpEndpoints',
];

$total = count($suites);
$passed = 0;

foreach ($suites as $name => $fn) {
    echo "\n>>> RUNNING SUITE: {$name} <<<\n";
    $status = $fn();
    $results[$name] = $status;
    if ($status) {
        $passed++;
    }
}

echo "\n======================================================================\n";
echo "TEST EXECUTION SUMMARY\n";
echo "======================================================================\n";
foreach ($results as $name => $res) {
    $badge = $res ? "[PASSED]" : "[FAILED]";
    echo str_pad($name, 55, '.') . " " . $badge . "\n";
}
echo "----------------------------------------------------------------------\n";
echo "TOTAL: {$total} | PASSED: {$passed} | FAILED: " . ($total - $passed) . "\n";
echo "======================================================================\n\n";

exit($passed === $total ? 0 : 1);
