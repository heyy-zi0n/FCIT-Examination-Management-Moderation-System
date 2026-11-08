<?php
/**
 * Flash Message / Alert Helper & Renderer
 */

function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_' . $type] = $message;
}

function renderAlerts(): string {
    $types = [
        'success' => [
            'classes' => 'bg-emerald-50 text-emerald-900 border-emerald-200',
            'icon_color' => 'text-emerald-600',
            'icon' => 'M5 13l4 4L19 7',
            'role' => 'status',
            'auto_dismiss' => true,
        ],
        'error' => [
            'classes' => 'bg-rose-50 text-rose-900 border-rose-200',
            'icon_color' => 'text-rose-600',
            'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'role' => 'alert',
            'auto_dismiss' => false, // Keep errors visible until user reads/dismisses
        ],
        'warning' => [
            'classes' => 'bg-amber-50 text-amber-900 border-amber-200',
            'icon_color' => 'text-amber-600',
            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'role' => 'alert',
            'auto_dismiss' => false,
        ],
        'info' => [
            'classes' => 'bg-blue-50 text-blue-900 border-blue-200',
            'icon_color' => 'text-blue-600',
            'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'role' => 'status',
            'auto_dismiss' => true,
        ],
    ];

    $html = '';
    foreach ($types as $type => $info) {
        $key = 'flash_' . $type;
        if (!empty($_SESSION[$key])) {
            $msg = htmlspecialchars($_SESSION[$key], ENT_QUOTES, 'UTF-8');
            unset($_SESSION[$key]);
            $dismissClass = $info['auto_dismiss'] ? 'auto-dismiss-alert ' : '';
            $html .= sprintf(
                '<div role="%s" class="%smb-6 p-4 rounded-xl border flex items-start sm:items-center gap-3 %s shadow-sm transition-all duration-300">
                    <svg class="w-5 h-5 shrink-0 mt-0.5 sm:mt-0 %s" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/></svg>
                    <span class="text-sm font-medium flex-1 leading-relaxed">%s</span>
                    <button type="button" onclick="this.parentElement.remove()" aria-label="Dismiss message" class="p-1.5 rounded-lg text-current opacity-70 hover:opacity-100 hover:bg-black/5 transition shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>',
                $info['role'],
                $dismissClass,
                $info['classes'],
                $info['icon_color'],
                $info['icon'],
                $msg
            );
        }
    }
    return $html;
}
