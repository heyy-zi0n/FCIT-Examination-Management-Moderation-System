<?php
/**
 * Real cURL-Based HTTP Endpoints & Session Integration Test Suite
 */

class TestHttpClient {
    private $cookieFile;
    private $baseUrl;

    public function __construct($baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'focite_test_cookie_');
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request($uri, $method = 'GET', $data = []) {
        $url = $this->baseUrl . $uri;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_USERAGENT, 'FOCITECMS-TestClient/1.0');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);

        // Extract redirect location if present
        $location = null;
        if (preg_match('/^Location:\s*([^\r\n]+)/mi', $headers, $m)) {
            $location = trim($m[1]);
        }

        return [
            'code' => $httpCode,
            'headers' => $headers,
            'body' => $body,
            'location' => $location
        ];
    }

    public function extractCsrfToken($html) {
        if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    public function login($email, $password) {
        $getRes = $this->request('/login.php');
        $token = $this->extractCsrfToken($getRes['body']);
        if (!$token) {
            throw new RuntimeException("Could not extract CSRF token from login page.");
        }

        return $this->request('/login.php', 'POST', [
            'csrf_token' => $token,
            'email' => $email,
            'password' => $password
        ]);
    }
}

function testHttpEndpoints() {
    echo "--- Testing HTTP Endpoints via Apache Web Server ---\n";
    $baseUrl = 'http://localhost/FOCITECMS';
    $allPassed = true;

    // Test 1: Public Landing & Login Pages
    $client = new TestHttpClient($baseUrl);

    $resLanding = $client->request('/index.php');
    if ($resLanding['code'] === 200 && strpos($resLanding['body'], 'Faculty of Computing & Information Technology') !== false) {
        echo "  [PASS] Landing page (index.php) returned HTTP 200 with LASU FCIT branding.\n";
    } else {
        echo "  [FAIL] Landing page failed (Code: {$resLanding['code']}).\n";
        $allPassed = false;
    }

    $resLogin = $client->request('/login.php');
    if ($resLogin['code'] === 200 && strpos($resLogin['body'], 'Staff Portal Sign In') !== false) {
        echo "  [PASS] Login page (login.php) returned HTTP 200 with login form.\n";
    } else {
        echo "  [FAIL] Login page failed (Code: {$resLogin['code']}).\n";
        $allPassed = false;
    }

    // Test 2: Unauthenticated Security Redirects
    $unauthEndpoints = [
        '/dashboard/lecturer/index.php',
        '/dashboard/moderator/index.php',
        '/dashboard/hod/index.php',
        '/dashboard/exam_officer/index.php',
        '/dashboard/admin/index.php'
    ];

    foreach ($unauthEndpoints as $ep) {
        $c = new TestHttpClient($baseUrl);
        $res = $c->request($ep);
        if ($res['code'] === 302 && strpos((string)$res['location'], '/login.php') !== false) {
            echo "  [PASS] Unauthenticated access to {$ep} -> Redirects to login (HTTP 302).\n";
        } else {
            echo "  [FAIL] Unauthenticated access to {$ep} -> Unexpected code {$res['code']}.\n";
            $allPassed = false;
        }
    }

    // Test 3: Authenticated Role Access & Dashboard Traversal
    $roles = [
        'lecturer' => [
            'email' => 'lecturer.csc@lasu.edu.ng',
            'password' => 'Password123!',
            'pages' => [
                '/dashboard/lecturer/index.php',
                '/dashboard/lecturer/create_paper.php',
                '/dashboard/lecturer/papers.php',
                '/notifications.php',
                '/profile.php'
            ]
        ],
        'moderator' => [
            'email' => 'moderator.csc@lasu.edu.ng',
            'password' => 'Password123!',
            'pages' => [
                '/dashboard/moderator/index.php',
                '/dashboard/moderator/history.php',
                '/notifications.php',
                '/profile.php'
            ]
        ],
        'hod' => [
            'email' => 'hod.csc@lasu.edu.ng',
            'password' => 'Password123!',
            'pages' => [
                '/dashboard/hod/index.php',
                '/dashboard/hod/pending_approvals.php',
                '/dashboard/hod/schedules.php',
                '/dashboard/hod/moderator_assignments.php',
                '/dashboard/hod/delegations.php',
                '/dashboard/hod/audit_trail.php'
            ]
        ],
        'exam_officer' => [
            'email' => 'exam.officer@lasu.edu.ng',
            'password' => 'Password123!',
            'pages' => [
                '/dashboard/exam_officer/index.php',
                '/dashboard/exam_officer/pending_approvals.php',
                '/dashboard/exam_officer/print_queue.php',
                '/dashboard/exam_officer/print_history.php',
                '/dashboard/exam_officer/delegations.php'
            ]
        ],
        'admin' => [
            'email' => 'admin@lasu.edu.ng',
            'password' => 'Password123!',
            'pages' => [
                '/dashboard/admin/index.php',
                '/dashboard/admin/users.php',
                '/dashboard/admin/academic_structure.php',
                '/dashboard/admin/assignments.php',
                '/dashboard/admin/audit_logs.php',
                '/dashboard/admin/system_settings.php',
                '/dashboard/admin/reports.php'
            ]
        ]
    ];

    foreach ($roles as $roleName => $info) {
        $c = new TestHttpClient($baseUrl);
        $loginRes = $c->login($info['email'], $info['password']);

        if ($loginRes['code'] === 302) {
            echo "  [PASS] Login as [{$roleName}] ({$info['email']}) -> Successful (HTTP 302 -> {$loginRes['location']}).\n";
        } else {
            echo "  [FAIL] Login as [{$roleName}] failed with code {$loginRes['code']}.\n";
            $allPassed = false;
            continue;
        }

        foreach ($info['pages'] as $page) {
            $pRes = $c->request($page);
            if ($pRes['code'] === 200) {
                echo "    [PASS] Page {$page} loaded -> HTTP 200 OK.\n";
            } else {
                echo "    [FAIL] Page {$page} returned HTTP {$pRes['code']}.\n";
                $allPassed = false;
            }
        }
    }

    return $allPassed;
}
