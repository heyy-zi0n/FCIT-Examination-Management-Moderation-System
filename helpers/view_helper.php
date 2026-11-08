<?php
/**
 * View & UI Presentation Helper
 * Formats statuses, timestamps, badges, and user-friendly elements
 */

/**
 * Render a standardized Tailwind status badge with accessible contrast & leading icon
 */
function renderStatusBadge(string $status): string {
    $config = [
        STATUS_DRAFT => [
            'label' => 'Draft Paper',
            'classes' => 'bg-slate-100 text-slate-700 border-slate-300',
            'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        ],
        STATUS_SUBMITTED => [
            'label' => 'Under Moderation',
            'classes' => 'bg-blue-50 text-blue-800 border-blue-200',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        STATUS_MODERATED => [
            'label' => 'Moderator Approved',
            'classes' => 'bg-amber-50 text-amber-900 border-amber-300',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        STATUS_RETURNED => [
            'label' => 'Revision Required',
            'classes' => 'bg-rose-50 text-rose-800 border-rose-200',
            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        ],
        STATUS_APPROVED => [
            'label' => 'Dual Approved',
            'classes' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        ],
        STATUS_LOCKED => [
            'label' => 'Blind Locked',
            'classes' => 'bg-purple-50 text-purple-800 border-purple-200',
            'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        ],
        STATUS_QUEUED => [
            'label' => 'Ready for Printing',
            'classes' => 'bg-cyan-50 text-cyan-900 border-cyan-200',
            'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
        ],
        STATUS_PRINTED => [
            'label' => 'Printed',
            'classes' => 'bg-teal-50 text-teal-800 border-teal-200',
            'icon' => 'M5 13l4 4L19 7',
        ],
    ];

    $entry = $config[$status] ?? [
        'label' => $status,
        'classes' => 'bg-slate-100 text-slate-800 border-slate-300',
        'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    $safeStatus = htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8');

    return sprintf(
        '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border whitespace-nowrap %s"><svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/></svg><span>%s</span></span>',
        $entry['classes'],
        $entry['icon'],
        $safeStatus
    );
}

/**
 * Format bytes into human readable string
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format date in Africa/Lagos timezone
 */
function formatDate(?string $date, string $format = 'd M Y, h:i A'): string {
    if (empty($date)) {
        return '—';
    }
    try {
        $dt = new DateTime($date, new DateTimeZone(APP_TIMEZONE));
        return $dt->format($format);
    } catch (Exception $e) {
        return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Get display title and full name of user
 */
function formatUserName(array $user): string {
    $title = !empty($user['title']) ? trim($user['title']) . ' ' : '';
    return htmlspecialchars($title . $user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize and format semester name to strictly "First Semester" or "Second Semester"
 */
function formatSemester(?string $semesterName): string {
    if (empty($semesterName)) {
        return '—';
    }
    $lower = strtolower($semesterName);
    if (strpos($lower, 'first') !== false || strpos($lower, 'harmattan') !== false) {
        return 'First Semester';
    }
    if (strpos($lower, 'second') !== false || strpos($lower, 'rain') !== false) {
        return 'Second Semester';
    }
    return htmlspecialchars($semesterName, ENT_QUOTES, 'UTF-8');
}

/**
 * Render user profile picture or fallback initials
 */
function renderUserAvatar(array $user, string $sizeClasses = 'w-10 h-10', string $textClasses = 'text-lg'): string {
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $altText = $fullName !== '' ? $fullName : 'Staff Avatar';

    if (!empty($user['profile_picture'])) {
        $safeId = (int)$user['id'];
        $url = url('avatar.php?id=' . $safeId);
        return sprintf(
            '<img src="%s" alt="%s" class="%s rounded-full object-cover border-2 border-lasu-gold shadow-sm">',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($altText, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sizeClasses, ENT_QUOTES, 'UTF-8')
        );
    }
    
    // Fallback initials
    $initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
    return sprintf(
        '<div class="%s bg-lasu-navy text-lasu-gold font-bold %s rounded-full flex items-center justify-center border-2 border-lasu-gold shadow-sm" aria-hidden="true">%s</div>',
        htmlspecialchars($sizeClasses, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($textClasses, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Render role badge with accessible contrast
 */
function renderRoleBadge(string $role): string {
    $labels = [
        ROLE_ADMIN        => ['Admin', 'bg-rose-50 text-rose-800 border-rose-200'],
        ROLE_HOD          => ['HOD', 'bg-indigo-50 text-indigo-800 border-indigo-200'],
        ROLE_EXAM_OFFICER => ['Exam Officer', 'bg-emerald-50 text-emerald-800 border-emerald-200'],
        ROLE_MODERATOR    => ['Moderator', 'bg-amber-50 text-amber-900 border-amber-200'],
        ROLE_LECTURER     => ['Lecturer', 'bg-blue-50 text-blue-800 border-blue-200'],
    ];

    $info = $labels[$role] ?? [ucfirst($role), 'bg-slate-100 text-slate-700 border-slate-300'];
    return sprintf(
        '<span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold border whitespace-nowrap %s">%s</span>',
        $info[1],
        htmlspecialchars($info[0], ENT_QUOTES, 'UTF-8')
    );
}
