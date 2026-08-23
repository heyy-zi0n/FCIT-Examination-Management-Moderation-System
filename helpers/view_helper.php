<?php
/**
 * View & UI Presentation Helper
 * Formats statuses, timestamps, badges, and user-friendly elements
 */

/**
 * Render a standardized Tailwind status badge
 */
function renderStatusBadge(string $status): string {
    $colors = [
        STATUS_DRAFT => 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300',
        STATUS_SUBMITTED => 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900/40 dark:text-blue-300',
        STATUS_MODERATED => 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-900/40 dark:text-amber-300',
        STATUS_RETURNED => 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-900/40 dark:text-rose-300',
        STATUS_APPROVED => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-900/40 dark:text-emerald-300',
        STATUS_LOCKED => 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-900/40 dark:text-purple-300',
        STATUS_QUEUED => 'bg-cyan-100 text-cyan-800 border-cyan-300 dark:bg-cyan-900/40 dark:text-cyan-300',
        STATUS_PRINTED => 'bg-teal-100 text-teal-800 border-teal-300 dark:bg-teal-900/40 dark:text-teal-300'
    ];

    $classes = $colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
    $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');

    return sprintf(
        '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border %s">%s</span>',
        $classes,
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
 * Render user profile picture or fallback initials
 */
function renderUserAvatar(array $user, string $sizeClasses = 'w-10 h-10', string $textClasses = 'text-lg'): string {
    if (!empty($user['profile_picture'])) {
        $safeId = (int)$user['id'];
        $url = url('avatar.php?id=' . $safeId);
        return sprintf(
            '<img src="%s" alt="Profile Picture" class="%s rounded-full object-cover border-2 border-lasu-gold shadow-sm">',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sizeClasses, ENT_QUOTES, 'UTF-8')
        );
    }
    
    // Fallback initials
    $initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
    return sprintf(
        '<div class="%s bg-lasu-navy text-lasu-gold font-bold %s rounded-full flex items-center justify-center border-2 border-lasu-gold shadow-sm">
            %s
        </div>',
        htmlspecialchars($sizeClasses, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($textClasses, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Render role badge
 */
function renderRoleBadge(string $role): string {
    $labels = [
        ROLE_ADMIN => ['Admin', 'bg-red-500/10 text-red-600 border-red-200'],
        ROLE_HOD => ['HOD', 'bg-indigo-500/10 text-indigo-600 border-indigo-200'],
        ROLE_EXAM_OFFICER => ['Exam Officer', 'bg-emerald-500/10 text-emerald-600 border-emerald-200'],
        ROLE_MODERATOR => ['Moderator', 'bg-amber-500/10 text-amber-600 border-amber-200'],
        ROLE_LECTURER => ['Lecturer', 'bg-blue-500/10 text-blue-600 border-blue-200']
    ];

    $info = $labels[$role] ?? [ucfirst($role), 'bg-gray-100 text-gray-700 border-gray-300'];
    return sprintf(
        '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border %s">%s</span>',
        $info[1],
        htmlspecialchars($info[0], ENT_QUOTES, 'UTF-8')
    );
}
