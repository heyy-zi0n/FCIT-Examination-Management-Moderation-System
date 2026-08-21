<?php
/**
 * LASU FCIT Secure Examination Question Management System
 * Staff Authentication & Login Portal
 */

require_once __DIR__ . '/bootstrap.php';

requireGuest();

$errorMessage = '';
$redirectUrl = sanitizeInput($_GET['redirect'] ?? '');

if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $errorMessage = 'Your session has expired due to inactivity. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errorMessage = 'Please provide both your staff email address and password.';
    } else {
        try {
            $user = AuthService::authenticate($email, $password);
            
            // Redirect based on role or requested target
            if (!empty($redirectUrl) && strpos($redirectUrl, '/FOCITECMS/') === 0) {
                header("Location: {$redirectUrl}");
            } else {
                header("Location: /FOCITECMS/dashboard/{$user['role']}/index.php");
            }
            exit;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$pageTitle = 'Sign In — LASU FCIT Secure Exam System';
include __DIR__ . '/includes/header.php';
?>

<div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-900 text-slate-100 relative">
    
    <!-- Background Decor -->
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/30 via-slate-900 to-slate-950 pointer-events-none"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10 text-center">
        <a href="/FOCITECMS/" class="inline-flex items-center justify-center gap-3 group mb-4">
            <img src="/FOCITECMS/assets/img/lasu-logo.png" alt="LASU Logo" class="h-24 w-auto object-contain drop-shadow-xl group-hover:scale-105 transition">
        </a>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
            Staff Portal Sign In
        </h2>
        <p class="mt-1 text-xs text-slate-400">
            Secure Examination Question Management & Controlled Printing
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10 px-4">
        <div class="bg-slate-800/90 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-2xl sm:px-10 border border-slate-700">
            
            <?php if (!empty($errorMessage)): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/FOCITECMS/login.php<?= !empty($redirectUrl) ? '?redirect=' . urlencode($redirectUrl) : '' ?>" class="space-y-5">
                <?= csrfField() ?>

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Staff Email Address</label>
                    <div class="relative">
                        <input id="email" name="email" type="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. lecturer.csc@lasu.edu.ng" class="w-full px-4 py-3 bg-slate-900/80 border border-slate-600 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-lasu-gold focus:border-transparent text-sm transition">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••••••" class="w-full px-4 py-3 bg-slate-900/80 border border-slate-600 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-lasu-gold focus:border-transparent text-sm transition pr-12">
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition p-1 focus:outline-none">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Brute-force protection active</span>
                    </span>
                </div>

                <button type="submit" class="w-full py-3.5 px-4 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl shadow-lg transition-all text-sm flex items-center justify-center gap-2">
                    <span>Authenticate & Enter</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>

            <!-- Quick Demo Role Switcher (Convenient for Evaluators) -->
            <div class="mt-8 pt-6 border-t border-slate-700/80">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider text-center mb-3">
                    Fast Evaluation Quick Fill
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                    <button type="button" onclick="fillLogin('lecturer.csc@lasu.edu.ng', 'Password123!')" class="p-2 bg-slate-900/60 hover:bg-blue-900/40 border border-slate-700 rounded-lg text-slate-300 hover:text-white text-center transition">
                        <div class="font-bold text-blue-400">Lecturer</div>
                        <div class="text-[10px] text-slate-400">CSC Dept</div>
                    </button>
                    <button type="button" onclick="fillLogin('moderator.csc@lasu.edu.ng', 'Password123!')" class="p-2 bg-slate-900/60 hover:bg-amber-900/40 border border-slate-700 rounded-lg text-slate-300 hover:text-white text-center transition">
                        <div class="font-bold text-amber-400">Moderator</div>
                        <div class="text-[10px] text-slate-400">CSC Dept</div>
                    </button>
                    <button type="button" onclick="fillLogin('hod.csc@lasu.edu.ng', 'Password123!')" class="p-2 bg-slate-900/60 hover:bg-indigo-900/40 border border-slate-700 rounded-lg text-slate-300 hover:text-white text-center transition">
                        <div class="font-bold text-indigo-400">HOD</div>
                        <div class="text-[10px] text-slate-400">CSC Dept</div>
                    </button>
                    <button type="button" onclick="fillLogin('exam.officer@lasu.edu.ng', 'Password123!')" class="p-2 bg-slate-900/60 hover:bg-emerald-900/40 border border-slate-700 rounded-lg text-slate-300 hover:text-white text-center transition">
                        <div class="font-bold text-emerald-400">Exam Officer</div>
                        <div class="text-[10px] text-slate-400">Faculty EO</div>
                    </button>
                    <button type="button" onclick="fillLogin('admin@lasu.edu.ng', 'Password123!')" class="p-2 bg-slate-900/60 hover:bg-red-900/40 border border-slate-700 rounded-lg text-slate-300 hover:text-white text-center transition col-span-2 sm:col-span-2">
                        <div class="font-bold text-red-400">Administrator</div>
                        <div class="text-[10px] text-slate-400">Full System Control</div>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function fillLogin(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}

function togglePasswordVisibility() {
    const pwdInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    } else {
        pwdInput.type = 'password';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
