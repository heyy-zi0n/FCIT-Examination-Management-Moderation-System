<?php

/**
 * LASU FCIT Examination Question Lifecycle Management System
 * Public Portal & Landing Page
 */

require_once __DIR__ . '/bootstrap.php';

$currentUser = currentUser();
if ($currentUser) {
    redirect("dashboard/{$currentUser['role']}/index.php");
    exit;
}

$totalDepts        = 0;
$totalCourses      = 0;
$totalPapers       = 0;
$totalAwaitingMod  = 0;
$totalAwaitingAppr = 0;
$totalReadyToPrint = 0;
$totalPrinted      = 0;
$departments       = [];

try {
    $pdo = getDb();
    $totalDepts   = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $totalPapers  = (int)$pdo->query("SELECT COUNT(*) FROM examination_papers")->fetchColumn();

    $statusCountsRaw = $pdo->query("SELECT status, COUNT(*) as count FROM examination_papers GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $statusCounts = array_merge([
        STATUS_DRAFT     => 0,
        STATUS_SUBMITTED => 0,
        STATUS_RETURNED  => 0,
        STATUS_MODERATED => 0,
        STATUS_APPROVED  => 0,
        STATUS_LOCKED    => 0,
        STATUS_QUEUED    => 0,
        STATUS_PRINTED   => 0
    ], $statusCountsRaw);

    $totalAwaitingMod  = $statusCounts[STATUS_SUBMITTED] + $statusCounts[STATUS_RETURNED];
    $totalAwaitingAppr = $statusCounts[STATUS_MODERATED] + $statusCounts[STATUS_APPROVED];
    $totalReadyToPrint = $statusCounts[STATUS_LOCKED] + $statusCounts[STATUS_QUEUED];
    $totalPrinted      = $statusCounts[STATUS_PRINTED];

    $departments = $pdo->query(
        "SELECT d.code, d.name, d.description, COUNT(c.id) AS course_count
         FROM departments d
         LEFT JOIN courses c ON c.department_id = d.id AND c.is_active = 1
         GROUP BY d.id, d.code, d.name, d.description
         ORDER BY d.code ASC"
    )->fetchAll();
} catch (Throwable $e) {
    // Graceful degradation if database is temporarily unavailable
}

$isLandingPage = true;
$pageTitle = 'LASU FCIT - Examination Question Lifecycle Management System';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<main id="main-content" class="flex-none w-full overflow-x-hidden">
<!-- HERO SECTION -->
<div id="hero" class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-900 via-lasu-navy to-slate-950 text-white relative overflow-hidden flex flex-col justify-center py-12 md:py-20 px-4 sm:px-6 lg:px-8">
    <div class="hero-blob hero-blob-1 parallax-shape" data-parallax-speed="0.08" aria-hidden="true"></div>
    <div class="hero-blob hero-blob-2 parallax-shape" data-parallax-speed="-0.05" aria-hidden="true"></div>
    <div class="hero-blob hero-blob-3 parallax-shape" data-parallax-speed="0.12" aria-hidden="true"></div>
    <div class="hero-grid" aria-hidden="true"></div>
    <div class="absolute top-16 right-12 w-32 h-32 rounded-full border border-lasu-gold/10 parallax-shape" data-parallax-speed="0.15" aria-hidden="true"></div>
    <div class="absolute bottom-24 left-8 w-20 h-20 rounded-full border border-blue-400/10 parallax-shape" data-parallax-speed="-0.10" aria-hidden="true"></div>
    <div class="max-w-5xl mx-auto text-center relative z-10">
        <div class="flex flex-col sm:flex-row sm:inline-flex items-center gap-1 sm:gap-2 px-4 py-2 sm:py-1.5 rounded-2xl sm:rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-[11px] sm:text-xs font-semibold text-lasu-gold tracking-wide uppercase mb-6 shadow-sm animate-fade-down text-center">
            <span>Faculty of Computing &amp; Information Technology</span>
            <span class="hidden sm:inline text-white/40" aria-hidden="true">&bull;</span>
            <span class="text-slate-200">Lagos State University</span>
        </div>
        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-tight mb-4 sm:mb-6 animate-fade-up delay-100">
            Examination Question Lifecycle Management &amp;
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-lasu-gold via-amber-300 to-yellow-200">Moderation Workflow</span>
        </h1>
        <p class="max-w-3xl mx-auto text-base sm:text-xl text-slate-300 font-medium mb-8 sm:mb-10 leading-relaxed animate-fade-up delay-200">
            A centralized academic workflow platform for managing and tracking examination questions from lecturer submission through moderation, revision, approval, blind lockdown and controlled printing with complete accountability at every stage.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16 animate-fade-up delay-300">
            <a href="<?= url('login.php') ?>" id="cta-access-portal"
                class="btn-hero-primary w-full sm:w-auto px-8 py-3.5 bg-lasu-gold text-lasu-navy font-black rounded-xl shadow-xl flex items-center justify-center gap-2 text-base tracking-wide">
                <span>Access Staff Portal</span>
                <svg class="btn-arrow w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </a>
            <a href="#how-it-works" id="cta-explore-workflow"
                class="btn-hero-secondary w-full sm:w-auto px-8 py-3.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl border border-white/20 text-base tracking-wide flex items-center justify-center gap-2">
                <span>Explore the Workflow</span>
                <svg class="btn-arrow-down w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </a>
        </div>
        <?php
        $stats = [
            ['label' => 'Departments',    'value' => $totalDepts,        'color' => 'text-lasu-gold'],
            ['label' => 'Courses',        'value' => $totalCourses,      'color' => 'text-lasu-gold'],
            ['label' => 'Papers',         'value' => $totalPapers,       'color' => 'text-lasu-gold'],
            ['label' => 'Awaiting Mod.',  'value' => $totalAwaitingMod,  'color' => 'text-amber-300'],
            ['label' => 'Awaiting Appr.', 'value' => $totalAwaitingAppr, 'color' => 'text-sky-300'],
            ['label' => 'Ready to Print', 'value' => $totalReadyToPrint, 'color' => 'text-purple-300'],
            ['label' => 'Printed',        'value' => $totalPrinted,      'color' => 'text-emerald-300'],
        ];
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5 sm:gap-3 max-w-6xl mx-auto animate-fade-up delay-400">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-card bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-4 text-center">
                    <div class="stat-number tabular-nums text-2xl font-black text-white mb-1" data-count-to="<?= $stat['value'] ?>"><?= $stat['value'] ?></div>
                    <div class="text-[11px] <?= $stat['color'] ?> font-bold uppercase tracking-wider"><?= $stat['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<section id="how-it-works" class="py-16 md:py-24 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12 md:mb-20">
            <p class="text-xs font-extrabold text-lasu-navy uppercase tracking-widest mb-3 reveal reveal-up">Examination Lifecycle</p>
            <h2 class="text-2xl md:text-3xl font-black text-slate-900 section-heading-line reveal reveal-up delay-100">How the Workflow Works</h2>
            <p class="mt-5 text-slate-600 font-medium reveal reveal-up delay-200">A structured, accountable path from the initial draft to the final printed examination paper.</p>
        </div>
        <div class="relative max-w-4xl mx-auto">
            <div class="block workflow-line-wrap" aria-hidden="true">
                <div class="workflow-line-fill"></div>
            </div>
            <?php
            $steps = [
                ['title' => 'Lecturer Submission', 'desc' => 'Course lecturers upload the initial draft of the examination question paper for their assigned courses.', 'pulse' => false],
                ['title' => 'Moderator Review', 'desc' => 'Appointed moderators review the submitted questions for academic quality, standards, and clarity.', 'pulse' => false],
                ['title' => 'Corrections &amp; Revision', 'desc' => 'If necessary, the paper is returned to the lecturer for revisions. A new version is securely tracked.', 'pulse' => false],
                ['title' => 'Moderator Approval', 'desc' => 'The moderator formally approves the revised paper, moving it forward to the departmental sign-off stage.', 'pulse' => true],
                ['title' => 'HOD + Exam Officer Approval', 'desc' => 'The Head of Department and Examination Officer independently verify and sign off on the paper.', 'pulse' => true],
                ['title' => 'Blind Lockdown', 'desc' => 'Upon dual approval, the paper is automatically sealed with AES-256 encryption to prevent any further modifications.', 'pulse' => true],
                ['title' => 'Controlled Printing', 'desc' => 'The Exam Officer accesses the locked paper for secure printing strictly within the permitted timeframe.', 'pulse' => false],
                ['title' => 'Complete Audit History', 'desc' => 'Every action throughout the lifecycle is persistently logged for accountability and transparency.', 'pulse' => false],
            ];
            foreach ($steps as $idx => $step):
                $isLeft = $idx % 2 === 0;
                $delay = round($idx * 0.08, 2) . 's';
            ?>
                <div class="relative flex flex-col md:flex-row items-start md:items-center mb-10 last:mb-0">
                    <div class="md:w-1/2 <?= $isLeft ? 'md:pr-14 md:text-right' : 'md:pl-14 md:order-last' ?> mb-0 w-full pl-12 md:pl-0">
                        <div class="workflow-step-card bg-white border border-slate-200 rounded-2xl p-5 shadow-sm reveal <?= $isLeft ? 'reveal-left' : 'reveal-right' ?>" style="transition-delay: <?= $delay ?>">
                            <div class="text-xs font-extrabold text-lasu-gold-ink tracking-widest uppercase mb-1.5">Step <?= str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT) ?></div>
                            <h3 class="text-base font-bold text-slate-900 mb-1.5"><?= $step['title'] ?></h3>
                            <p class="text-sm text-slate-600 leading-relaxed"><?= $step['desc'] ?></p>
                        </div>
                    </div>
                    <div class="workflow-node flex w-9 h-9 rounded-full bg-lasu-navy text-lasu-gold items-center justify-center font-black text-xs border-4 border-slate-50 shadow-md <?= $step['pulse'] ? 'workflow-node-pulse' : '' ?>">
                        <?= $idx + 1 ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- TRACK EVERY STAGE + ACCOUNTABILITY -->
<section class="py-16 md:py-24 bg-white border-t border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div class="reveal reveal-left">
                <h2 class="text-2xl md:text-3xl font-black text-slate-900 mb-4">Track Every Stage</h2>
                <p class="text-slate-600 mb-8 leading-relaxed font-medium">The platform provides complete visibility into the examination preparation process—from initial upload through moderation and final printing.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 reveal-stagger-group">
                    <?php foreach (['Has the lecturer submitted the paper?', 'Who is currently responsible for it?', 'Is it awaiting moderation?', 'Has it been returned for correction?', 'Which version is currently active?', 'Has the moderator approved it?', 'Is HOD approval pending?', 'Is Exam Officer approval pending?', 'Is the examination fully approved?', 'Has Blind Lockdown been activated?', 'Is the paper eligible for printing?', 'Has it already been printed?'] as $q): ?>
                        <div class="track-item flex items-start gap-3 reveal reveal-up">
                            <svg class="check-icon w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium text-slate-700"><?= $q ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="reveal reveal-right">
                <div class="bg-slate-50 rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm">
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 mb-3">Workflow Accountability</h3>
                    <p class="text-slate-600 mb-7 font-medium italic">&ldquo;Know what happened, who performed the action, when it happened, and what needs to happen next.&rdquo;</p>
                    <div class="space-y-3 reveal-stagger-group">
                        <?php foreach (['Submission tracking', 'Moderator review history', 'Correction and revision tracking', 'Immutable paper version history', 'HOD &amp; Exam Officer approval tracking', 'Current responsible officer/action', 'Complete examination timeline', 'System notifications', 'Audit logs', 'Printing history'] as $f): ?>
                            <div class="track-feature-row reveal reveal-up flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200/80 shadow-sm">
                                <div class="w-8 h-8 rounded-lg bg-lasu-navy/10 text-lasu-navy flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-semibold text-slate-800"><?= $f ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ZERO-TRUST SECURITY VAULT -->
<section class="py-16 md:py-24 bg-slate-950 text-white relative">
    <!-- Background Glows -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[300px] sm:w-[600px] h-[300px] sm:h-[600px] bg-blue-900/20 rounded-full blur-[80px] sm:blur-[100px]"></div>
        <div class="absolute top-0 right-0 w-[220px] sm:w-[400px] h-[220px] sm:h-[400px] bg-lasu-gold/10 rounded-full blur-[60px] sm:blur-[80px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">

            <!-- Text Content -->
            <div class="reveal reveal-left w-full">
                <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black text-white mb-4 sm:mb-6 leading-tight">
                    The Cryptographic <span class="text-lasu-gold">Blind Vault</span>
                </h2>
                <p class="text-base sm:text-lg text-slate-300 font-medium mb-8 leading-relaxed">
                    Once a paper receives dual-authorization, it enters the Blind Vault. Secured by AES-256-GCM encryption, the contents become inaccessible to all users—including system administrators—until the designated print window.
                </p>

                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-900/40 border border-blue-800/50 flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-white mb-1">AES-256-GCM Encryption</h3>
                            <p class="text-sm text-slate-300">Papers are encrypted at rest with authenticated encryption, guaranteeing data confidentiality and integrity.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-900/40 border border-amber-800/50 flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-white mb-1">Time-Gated Access Keys</h3>
                            <p class="text-sm text-slate-300">Decryption is programmatically restricted to the calendar day immediately preceding the scheduled examination.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-900/40 border border-emerald-800/50 flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-white mb-1">Separation of Administrative Duty</h3>
                            <p class="text-sm text-slate-300">System administrators manage accounts and academic structure without access to sealed examination question files.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visual Graphic -->
            <div class="reveal reveal-right relative w-full h-full min-h-[260px] sm:min-h-[300px] flex items-center justify-center" aria-hidden="true">
                <div class="relative w-full max-w-[280px] sm:max-w-md mx-auto aspect-square flex items-center justify-center">
                    <div class="absolute inset-2 sm:inset-4 rounded-full border border-blue-500/20 animate-[spin_15s_linear_infinite]"></div>
                    <div class="absolute inset-6 sm:inset-10 rounded-full border border-dashed border-lasu-gold/30 animate-[spin_20s_linear_infinite_reverse]"></div>
                    <div class="absolute inset-12 sm:inset-20 rounded-full border border-blue-400/10 animate-[spin_25s_linear_infinite]"></div>

                    <div class="relative w-36 h-36 sm:w-48 sm:h-48 bg-slate-900 border border-slate-700/50 rounded-3xl shadow-[0_0_50px_rgba(59,130,246,0.3)] flex items-center justify-center overflow-hidden group hover:border-blue-500/50 transition-colors duration-500 z-10">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <svg class="w-16 h-16 sm:w-20 sm:h-20 text-lasu-gold drop-shadow-[0_0_15px_rgba(251,191,36,0.4)] relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>

                    <div class="absolute top-1/4 right-2 sm:right-0 bg-slate-800/90 backdrop-blur-md border border-slate-700 rounded-lg py-1.5 px-3 sm:py-2 sm:px-4 shadow-xl z-20 transform -translate-y-2">
                        <span class="text-[11px] sm:text-xs font-black text-emerald-400 tracking-wider">LOCKED</span>
                    </div>
                    <div class="absolute bottom-1/4 left-2 sm:left-0 bg-slate-800/90 backdrop-blur-md border border-slate-700 rounded-lg py-1.5 px-3 sm:py-2 sm:px-4 shadow-xl z-20 transform translate-y-2">
                        <span class="text-[11px] sm:text-xs font-black text-blue-400 tracking-wider">AES-256</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
<!-- FACULTY DEPARTMENTS -->
<section class="py-16 md:py-24 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <p class="text-xs font-extrabold text-lasu-navy uppercase tracking-widest mb-3 reveal reveal-up">Academic Units</p>
            <h2 class="text-2xl md:text-3xl font-black text-slate-900 section-heading-line reveal reveal-up">Faculty Departments</h2>
            <p class="mt-4 text-base font-medium text-slate-600 reveal reveal-up delay-100">Independent examination moderation and approval pipelines across all FCIT departments.</p>
        </div>

        <?php
        $deptMeta = [
            'CSC' => 'Core computing theory, algorithms, systems architecture, and undergraduate examination moderation.',
            'CYB' => 'Information assurance, cryptography, and network security assessment governance.',
            'DSC' => 'Statistical computing, machine learning, and data analytics examination workflows.',
            'ICT' => 'Enterprise information systems, networking infrastructure, and applied technology assessments.',
            'SEN' => 'Software design, quality engineering, and formal verification examination pipelines.',
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 reveal-stagger-group">
            <?php foreach ($departments as $i => $dept):
                $code = htmlspecialchars($dept['code'], ENT_QUOTES, 'UTF-8');
                $courseCount = (int)($dept['course_count'] ?? 0);
                $rawDesc = !empty($dept['description']) ? $dept['description'] : ($deptMeta[$dept['code']] ?? 'Departmental examination moderation, dual sign-off, and controlled printing pipeline.');
            ?>
                <div class="dept-card-v2 reveal reveal-up bg-white rounded-xl border border-slate-200 p-6 flex flex-col shadow-sm" style="transition-delay:<?= $i * 0.08 ?>s">
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <span class="dept-code-badge-v2 inline-flex items-center px-2.5 py-1 rounded-md bg-lasu-navy/10 text-lasu-navy text-xs font-extrabold tracking-wider uppercase"><?= $code ?></span>
                        <span class="tabular-nums text-xs font-semibold text-slate-500"><?= $courseCount ?> <?= $courseCount === 1 ? 'Course' : 'Courses' ?></span>
                    </div>
                    <h3 class="text-sm font-extrabold text-slate-900 leading-snug mb-2.5"><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-xs font-medium text-slate-600 leading-relaxed dept-desc mt-auto"><?= htmlspecialchars($rawDesc, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- GOVERNANCE, ACCOUNTABILITY & SECURITY -->
<section class="py-16 md:py-24 bg-slate-50 border-t border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-4xl mx-auto mb-12 sm:mb-16">
            <p class="text-xs font-extrabold text-lasu-navy uppercase tracking-widest mb-3 reveal reveal-up">Governance, Accountability &amp; Security</p>
            <h2 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight section-heading-line reveal reveal-up delay-100">Strict Separation of Duties &amp; Lifecycle Controls</h2>
        </div>
        <?php
        $governance = [
            ['Role &amp; Department-Based Access', 'Users only access examination records permitted by their role, department, and active course assignments.', 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', false],
            ['Moderator Review &amp; Version Control', 'Every correction and resubmission is traceable without overwriting historical examination versions.', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', false],
            ['Independent HOD + EO Approval', 'Both departmental and faculty examination officer approvals are required before Blind Lockdown.', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', false],
            ['Blind Lockdown &amp; Document Integrity', 'The final approved version is AES-256 encrypted and protected against unauthorized modification.', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', true],
            ['Controlled Printing', 'Printing is restricted to the calendar day before the examination, with audited Emergency Printing workflows for exceptions.', 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z', false],
            ['Complete Audit Trail', 'Every submission, moderation, approval, lockdown, and printing activity is recorded for full accountability.', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', false],
        ];
        ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 reveal-stagger-group">
            <?php foreach ($governance as $i => $gov): ?>
                <div class="feature-card reveal reveal-up bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col items-start text-left" style="transition-delay:<?= $i * 0.09 ?>s">
                    <div class="feature-icon w-11 h-11 rounded-xl bg-lasu-navy/10 text-lasu-navy flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 <?= $gov[3] ? 'icon-lock' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $gov[2] ?>" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-2"><?= $gov[0] ?></h3>
                    <p class="text-sm font-medium text-slate-600 leading-relaxed"><?= $gov[1] ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- AUTHORIZED STAFF PORTAL CTA -->
<section class="py-14 sm:py-16 bg-lasu-navy text-white border-t border-lasu-navy-light">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-lasu-gold mb-1.5">Authorized Academic &amp; Administrative Staff</p>
            <h2 class="text-xl sm:text-2xl font-black text-white">Ready to submit, moderate, or authorize examination papers?</h2>
            <p class="text-sm text-slate-300 mt-1.5 max-w-2xl">Sign in with your assigned LASU FCIT staff credentials to access your role-scoped examination workspace.</p>
        </div>
        <a href="<?= url('login.php') ?>" class="btn-hero-primary shrink-0 w-full sm:w-auto px-7 py-3.5 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-black rounded-xl shadow-lg inline-flex items-center justify-center gap-2 text-sm tracking-wide">
            <span>Sign In to Staff Portal</span>
            <svg class="btn-arrow w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>
</section>
</main>

<script src="<?= url('assets/js/landing.js') ?>?v=<?= @filemtime(__DIR__ . '/assets/js/landing.js') ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>