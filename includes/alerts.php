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
        'success' => ['bg-emerald-50 text-emerald-800 border-emerald-200', 'M5 13l4 4L19 7'],
        'error' => ['bg-rose-50 text-rose-800 border-rose-200', 'M6 18L18 6M6 6l12 12'],
        'warning' => ['bg-amber-50 text-amber-800 border-amber-200', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        'info' => ['bg-blue-50 text-blue-800 border-blue-200', 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z']
    ];

    $html = '';
    foreach ($types as $type => $info) {
        $key = 'flash_' . $type;
        if (!empty($_SESSION[$key])) {
            $msg = htmlspecialchars($_SESSION[$key], ENT_QUOTES, 'UTF-8');
            unset($_SESSION[$key]);
            $html .= sprintf(
                '<div class="auto-dismiss-alert mb-6 p-4 rounded-xl border flex items-center gap-3 %s shadow-sm transition-all duration-300">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/></svg>
                    <span class="text-sm font-medium flex-1">%s</span>
                    <button type="button" onclick="this.parentElement.remove()" class="text-current opacity-70 hover:opacity-100"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>',
                $info[0],
                $info[1],
                $msg
            );
        }
    }
    return $html;
}
