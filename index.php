<?php
/**
 * LASU FCIT Secure Examination Question Management System
 * Public Portal & Landing Page
 */

require_once __DIR__ . '/bootstrap.php';

$currentUser = currentUser();
if ($currentUser) {
    header("Location: /FOCITECMS/dashboard/{$currentUser['role']}/index.php");
    exit;
}

$pageTitle = 'LASU FCIT — Secure Examination Question Management System';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';

$pdo = getDb();
$totalDepts = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalPapers = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers")->fetchColumn();
$totalPrints = (int)$pdo->query("SELECT COUNT(*) FROM print_logs WHERE print_status = 'successful'")->fetchColumn();

// Fetch departments for the grid
$departments = $pdo->query("SELECT code, name, description FROM departments ORDER BY code ASC")->fetchAll();
?>

<div class="flex-none min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-900 via-lasu-navy to-slate-950 text-white relative overflow-hidden flex flex-col justify-center py-20 px-4 sm:px-6 lg:px-8">
    
    <!-- Background Glow Effects -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-lasu-gold/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/2 -right-40 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-5xl mx-auto text-center relative z-10">
        
        <!-- Institutional Badge -->
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-xs font-semibold text-lasu-gold tracking-wide uppercase mb-6 shadow-sm">
            <span>Faculty of Computing & Information Technology</span>
            <span class="text-white/40">•</span>
            <span class="text-slate-200">Lagos State University</span>
        </div>

        <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black tracking-tight text-white leading-tight mb-6">
            Secure Examination Question Management & <span class="text-transparent bg-clip-text bg-gradient-to-r from-lasu-gold via-amber-300 to-yellow-200">Controlled Printing</span>
        </h1>

        <p class="max-w-3xl mx-auto text-lg sm:text-xl text-slate-300 font-medium mb-10 leading-relaxed">
            A state-of-the-art academic governance system providing multi-tier moderation, independent HOD + Exam Officer dual approval, cryptographic blind lockdown, and time-restricted controlled printing.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
            <a href="/FOCITECMS/login.php" class="w-full sm:w-auto px-8 py-3.5 bg-lasu-gold text-lasu-navy font-black rounded-xl hover:bg-lasu-gold-hover transition-all shadow-xl hover:shadow-lasu-gold/20 flex items-center justify-center gap-2 text-base tracking-wide">
                <span>Access Staff Portal</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
            <a href="#architecture-pillars" class="w-full sm:w-auto px-8 py-3.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl border border-white/20 transition-all text-base tracking-wide">
                System Workflow & Governance
            </a>
        </div>

        <!-- Live Statistics Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-5xl mx-auto mb-16">
            <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 text-left">
                <div class="text-3xl sm:text-4xl font-black text-white mb-2"><?= $totalDepts ?></div>
                <div class="text-sm text-lasu-gold font-bold uppercase tracking-widest">Departments</div>
            </div>
            <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 text-left">
                <div class="text-3xl sm:text-4xl font-black text-white mb-2"><?= $totalCourses ?></div>
                <div class="text-sm text-lasu-gold font-bold uppercase tracking-widest">Active Courses</div>
            </div>
            <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 text-left">
                <div class="text-3xl sm:text-4xl font-black text-white mb-2"><?= $totalPapers ?></div>
                <div class="text-sm text-lasu-gold font-bold uppercase tracking-widest">Papers Governed</div>
            </div>
            <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 text-left">
                <div class="text-3xl sm:text-4xl font-black text-white mb-2"><?= $totalPrints ?></div>
                <div class="text-sm text-lasu-gold font-bold uppercase tracking-widest">Controlled Prints</div>
            </div>
        </div>

    </div>
</div>

<!-- Departments Grid Section -->
<section class="py-16 bg-white dark:bg-slate-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-10">
            <h2 class="text-3xl font-black text-slate-900 dark:text-white">Faculty Departments</h2>
            <p class="mt-2 text-base font-medium text-slate-600 dark:text-slate-400">Independent examination pipelines across all FCIT departments</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
            <?php foreach ($departments as $dept): ?>
                <div class="bg-white dark:bg-slate-900 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 hover:-translate-y-1 hover:shadow-xl hover:border-lasu-gold/50 transition-all duration-300 group cursor-default">
                    <div class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 text-xs font-black tracking-widest mb-4 group-hover:bg-lasu-gold/20 group-hover:text-lasu-gold transition-colors">
                        <?= htmlspecialchars($dept['code']) ?>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white mb-3 leading-tight"><?= htmlspecialchars($dept['name']) ?></h3>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 leading-relaxed">
                        <?= htmlspecialchars($dept['description'] ?? 'Dedicated HOD & Exam Officer workflows') ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Workflow Pillars Section -->
<section id="architecture-pillars" class="py-16 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-4xl mx-auto mb-16">
            <h2 class="text-sm font-extrabold text-blue-900 dark:text-lasu-gold uppercase tracking-widest mb-3">Authoritative Security Architecture</h2>
            <h3 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white leading-tight">Strict Separation of Duties & Lifecycle Controls</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Pillar 1 -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 group hover:border-blue-500/50 hover:shadow-lg transition-all cursor-default">
                <div class="w-14 h-14 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-900 dark:text-blue-400 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" class="transition-transform duration-300 origin-center group-hover:scale-105"></path>
                        <path d="M9 12l2 2 4-4" class="transition-all duration-500 origin-center scale-0 group-hover:scale-100 opacity-0 group-hover:opacity-100"></path>
                    </svg>
                </div>
                <h4 class="text-xl font-extrabold text-slate-900 dark:text-white mb-3">Dual HOD + EO Approval</h4>
                <p class="text-base font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                    Neither HOD nor Exam Officer can authorize a paper independently. Final approval and blind lockdown strictly require concurrent sign-off from both officers.
                </p>
            </div>

            <!-- Pillar 2 -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 group hover:border-purple-500/50 hover:shadow-lg transition-all cursor-default">
                <div class="w-14 h-14 rounded-xl bg-purple-100 dark:bg-purple-900/30 text-purple-900 dark:text-purple-400 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7">
                        <!-- Shackle (Starts translated UP for unlocked gap, moves down to lock) -->
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" class="transition-transform duration-500 origin-center -translate-y-2 group-hover:translate-y-0"></path>
                        <!-- Body -->
                        <rect x="5" y="11" width="14" height="10" rx="2" ry="2" class="transition-colors duration-300 group-hover:fill-purple-200 dark:group-hover:fill-purple-800"></rect>
                        <!-- Keyhole -->
                        <path d="M12 15v2"></path>
                    </svg>
                </div>
                <h4 class="text-xl font-extrabold text-slate-900 dark:text-white mb-3">Cryptographic Blind Lockdown</h4>
                <p class="text-base font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                    Immediately upon dual approval, the approved version is sealed with AES-256-GCM encryption and SHA-256 integrity tags, rendering files immutable.
                </p>
            </div>

            <!-- Pillar 3 -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 group hover:border-emerald-500/50 hover:shadow-lg transition-all cursor-default">
                <div class="w-14 h-14 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-900 dark:text-emerald-400 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 overflow-hidden">
                        <!-- Top paper -->
                        <path d="M7 9V5h10v4"></path>
                        <!-- Bottom paper (slides out on hover) -->
                        <g class="transition-transform duration-700 ease-out -translate-y-6 group-hover:translate-y-0">
                            <path d="M7 17v5h10v-5"></path>
                            <line x1="9" y1="19" x2="15" y2="19"></line>
                            <line x1="9" y1="21" x2="13" y2="21"></line>
                        </g>
                        <!-- Printer body (drawn last, filled to hide sliding paper behind it) -->
                        <path d="M5 9h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2z" class="fill-[#d1fae5] dark:fill-[#064e3b]"></path>
                        <!-- Printer slot line -->
                        <line x1="7" y1="13" x2="17" y2="13"></line>
                    </svg>
                </div>
                <h4 class="text-xl font-extrabold text-slate-900 dark:text-white mb-3">Calendar-Day Printing Window</h4>
                <p class="text-base font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                    Printing is permitted server-side strictly on the calendar day before the official exam date in the Africa/Lagos timezone, preventing unauthorized early printing.
                </p>
            </div>

        </div>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
