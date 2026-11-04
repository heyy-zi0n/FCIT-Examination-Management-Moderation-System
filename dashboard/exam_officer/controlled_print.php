<?php
/**
 * Controlled Examination Printing Execution (Exam Officer)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);

$paperId = (int)($_GET['id'] ?? 0);
$pdo = getDb();
$userId = (int)$user['id'];

// Load Paper with Schedule and Lockdown
$stmtPaper = $pdo->prepare("
    SELECT ep.*, c.course_code, c.course_title, c.credit_units, d.name AS dept_name, al.level_name,
           es.exam_date, es.exam_time, es.venue, es.expected_students, es.duration_minutes,
           bl.id AS lockdown_id, bl.checksum_sha256, bl.locked_at, bl.is_unlocked,
           pv.version_number
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_levels al ON c.level_id = al.id
    JOIN exam_schedules es ON ep.exam_schedule_id = es.id
    JOIN blind_lockdowns bl ON ep.id = bl.paper_id
    LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id
    WHERE ep.id = :id AND c.department_id = :dept_id
");
$stmtPaper->execute(['id' => $paperId, 'dept_id' => $user['department_id']]);
$paper = $stmtPaper->fetch();

if (!$paper) {
    setFlash('error', 'Examination paper not found or not eligible for printing.');
    redirect('dashboard/exam_officer/print_queue.php');
    exit;
}

// Check printing date eligibility
$eligibility = PrintingService::checkPrintEligibility($paper['exam_date']);
$isEmergencyApproved = PrintingService::hasApprovedEmergencyPrint($paperId, (int)$paper['current_version_id']);
if ($isEmergencyApproved) {
    $eligibility['eligible'] = true;
    $eligibility['status'] = 'emergency_approved';
}
$isReprint = ($paper['status'] === STATUS_PRINTED);

$printExecutionResult = null;
$decryptedPdfBase64 = null;

// Handle Print Confirmation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_confirm_print'])) {
    requireCsrf();

    $copies = (int)($_POST['copies_to_print'] ?? $paper['expected_students']);
    $reprintReason = sanitizeInput($_POST['reprint_reason'] ?? null);

    try {
        if ($copies <= 0) {
            throw new InvalidArgumentException("Number of copies must be greater than zero.");
        }

        if ($isReprint && empty(trim((string)$reprintReason))) {
            throw new InvalidArgumentException("A mandatory justification reason is required for reprint authorization.");
        }

        // Execute Controlled Print via Service (decrypts .enc payload into memory)
        $printExecutionResult = PrintingService::executeControlledPrint($paperId, $userId, $copies, $reprintReason);

        // Convert in-memory decrypted DOCX content to an ephemeral PDF and immediately unlink temp files
        $ephemeralToken = bin2hex(random_bytes(12));
        $tmpDocxPath = PREVIEW_STORAGE_PATH . "/ephemeral_print_{$ephemeralToken}.docx";
        $tmpPdfPath  = PREVIEW_STORAGE_PATH . "/ephemeral_print_{$ephemeralToken}.pdf";

        try {
            if (!is_dir(PREVIEW_STORAGE_PATH)) {
                mkdir(PREVIEW_STORAGE_PATH, 0755, true);
            }
            file_put_contents($tmpDocxPath, $printExecutionResult['decrypted_content'], LOCK_EX);

            if (DocumentService::generatePdfPreview($tmpDocxPath, $tmpPdfPath) && is_file($tmpPdfPath)) {
                $pdfBytes = file_get_contents($tmpPdfPath);
                if ($pdfBytes !== false) {
                    $decryptedPdfBase64 = base64_encode($pdfBytes);
                }
            }
        } finally {
            if (is_file($tmpDocxPath)) {
                @unlink($tmpDocxPath);
            }
            if (is_file($tmpPdfPath)) {
                @unlink($tmpPdfPath);
            }
            // Wipe raw plaintext from result array after conversion
            unset($printExecutionResult['decrypted_content']);
        }

        if ($decryptedPdfBase64 === null) {
            throw new RuntimeException("Controlled print logged, but PDF rendering failed. Ensure LibreOffice is installed and configured.");
        }

        logAudit($userId, 'controlled_print_render', 'print_log', $paperId, [
            'paper_code' => $paper['paper_code'],
            'copies' => $copies,
            'verification_code' => $printExecutionResult['verification_code']
        ]);

    } catch (Exception $e) {
        $printExecutionResult = null;
        setFlash('error', $e->getMessage());
    }
}

$pageTitle = "Controlled Printing: {$paper['course_code']} — LASU FCIT CMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6 no-print">
            <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center gap-1 mb-2">
                &larr; Back to Controlled Print Queue
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($paper['course_code']) ?>: Controlled Printing</h1>
                <?= renderStatusBadge($paper['status']) ?>
            </div>
            <p class="text-xs text-slate-500 mt-1">Department: <?= htmlspecialchars($paper['dept_name']) ?> • Examination Date: <?= formatDate($paper['exam_date'], 'l, d F Y') ?></p>
        </div>

        <?php if ($printExecutionResult && $decryptedPdfBase64): ?>
            <!-- PRINT SUCCESSFUL & SECURE EPHEMERAL PRINT RENDERER -->
            <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50 rounded-2xl p-6 mb-8 text-center no-print">
                <div class="w-14 h-14 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="text-lg font-bold text-emerald-900 dark:text-emerald-300">Controlled Printing Authorization Generated</h2>
                <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-1">
                    Print Verification Serial: <span class="font-mono font-bold bg-white dark:bg-slate-900 px-3 py-1 rounded-lg border border-emerald-300"><?= htmlspecialchars($printExecutionResult['verification_code']) ?></span>
                </p>
                <div class="mt-4 flex items-center justify-center gap-3">
                    <button type="button" onclick="triggerControlledPrint()" class="px-6 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Send to Physical Printer</span>
                    </button>
                    <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="px-4 py-2.5 bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-xl text-xs font-semibold transition">
                        Return to Queue
                    </a>
                </div>
            </div>

            <!-- Official Controlled Examination Document Output -->
            <div class="bg-white dark:bg-slate-900 p-8 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800">
                <!-- Security Header Banner -->
                <div class="border-b-2 border-slate-900 pb-4 mb-6 text-center">
                    <div class="text-xs font-bold uppercase tracking-widest text-slate-600">Lagos State University, Ojo</div>
                    <div class="text-sm font-extrabold uppercase text-slate-900 dark:text-white">Faculty of Computing & Information Technology</div>
                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($paper['dept_name']) ?></div>
                    <div class="mt-2 text-base font-black uppercase text-slate-900 dark:text-white"><?= htmlspecialchars($paper['course_code']) ?> — <?= htmlspecialchars($paper['course_title']) ?></div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs font-mono mt-3 pt-3 border-t border-slate-200 text-slate-700 dark:text-slate-300">
                        <div>Time Allowed: <?= (int)$paper['duration_minutes'] ?> Mins</div>
                        <div>Exam Date: <?= formatDate($paper['exam_date'], 'd/m/Y') ?></div>
                        <div>Units: <?= (int)$paper['credit_units'] ?></div>
                    </div>
                </div>

                <!-- Ephemeral In-Memory Decrypted Document Stream -->
                <div class="h-[750px] w-full border border-slate-300 dark:border-slate-700 rounded-xl overflow-hidden mb-6">
                    <iframe id="controlled-print-frame" class="w-full h-full border-0" title="Authorized Printable Question Document"></iframe>
                </div>

                <!-- Security Verification Footer Stamp -->
                <div class="border-t-2 border-slate-900 pt-4 flex items-center justify-between text-[10px] font-mono text-slate-600 dark:text-slate-400">
                    <div>
                        <span>SERIAL: <?= htmlspecialchars($printExecutionResult['verification_code']) ?></span> |
                        <span>OFFICER: <?= formatUserName($user) ?></span>
                    </div>
                    <div>
                        <span>PRINTED: <?= date('d-M-Y H:i:s T') ?></span> |
                        <span>COPIES: <?= (int)$printExecutionResult['copies'] ?></span>
                    </div>
                </div>
            </div>

            <script>
            (function () {
                const base64Pdf = <?= json_encode($decryptedPdfBase64) ?>;
                const binary = atob(base64Pdf);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                const blob = new Blob([bytes], { type: 'application/pdf' });
                const blobUrl = URL.createObjectURL(blob);
                const frame = document.getElementById('controlled-print-frame');
                if (frame) {
                    frame.src = blobUrl;
                }

                window.triggerControlledPrint = function () {
                    if (frame && frame.contentWindow) {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    } else {
                        window.print();
                    }
                };
            })();
            </script>

        <?php else: ?>

            <!-- Print Authorization Form & Gate Check -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <!-- Print Parameters Card -->
                <div class="lg:col-span-1 bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Controlled Print Authorization</span>
                    </h2>

                    <form method="POST" action="<?= url('dashboard/exam_officer/controlled_print.php?id=' . $paperId) ?>" class="space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="action_confirm_print" value="1">

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Copies to Print *</label>
                            <input type="number" name="copies_to_print" required min="1" max="1000" value="<?= (int)($paper['expected_students'] ?: 100) ?>" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold font-mono">
                            <span class="text-[10px] text-slate-400">Default based on scheduled expected student cohort</span>
                        </div>

                        <?php if ($isReprint): ?>
                            <div>
                                <label class="block text-xs font-semibold text-rose-700 dark:text-rose-400 mb-1">Reprint Justification Reason *</label>
                                <textarea name="reprint_reason" rows="3" required placeholder="Explain why a reprint is required (e.g. printer paper jam, ink smear)..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-rose-300 dark:border-rose-800 rounded-xl text-xs focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 text-xs space-y-1.5">
                            <div class="flex justify-between"><span class="text-slate-400">Scheduled Exam Date:</span> <span class="font-bold text-slate-800 dark:text-slate-200"><?= formatDate($paper['exam_date'], 'd M Y') ?></span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Permitted Printing Day:</span> <span class="font-bold text-cyan-600"><?= formatDate($eligibility['allowed_date'], 'd M Y') ?></span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Venue / Location:</span> <span class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($paper['venue']) ?></span></div>
                        </div>

                        <?php if ($eligibility['eligible'] || $isReprint): ?>
                            <button type="submit" onclick="return confirm('Authorize physical examination printing? This decrypts the sealed payload and creates an immutable audit record.')" class="w-full py-3 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                <span>Authorize &amp; Decrypt for Printing</span>
                            </button>
                        <?php else: ?>
                            <div class="p-3 bg-rose-50 text-rose-800 border border-rose-200 rounded-xl text-xs">
                                <?= htmlspecialchars($eligibility['message']) ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Cryptographic Lockdown Status Card (No Pre-Authorization Document Preview) -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">
                            Cryptographic Lockdown &amp; Integrity Verification
                        </h2>

                        <div class="p-4 rounded-xl bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900/50 mb-6">
                            <div class="flex items-center gap-2 text-purple-900 dark:text-purple-300 font-bold mb-2 text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span>AES-256-GCM Blind Lockdown Payload Active</span>
                            </div>
                            <div class="font-mono text-[11px] text-slate-600 dark:text-slate-400 break-all">
                                Checksum (SHA-256): <?= htmlspecialchars($paper['checksum_sha256']) ?>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-1">
                                Sealed on: <?= formatDate($paper['locked_at']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center bg-slate-50/50 dark:bg-slate-950/40 my-auto">
                        <div class="w-16 h-16 rounded-2xl bg-slate-900 text-lasu-gold flex items-center justify-center mx-auto mb-4 shadow-md">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">
                            Examination Content Sealed Under Blind Lockdown
                        </h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                            Plaintext files and previews have been purged from storage. The encrypted `.enc` payload can only be decrypted in memory and streamed to the print interface after submitting the authorization form on the left.
                        </p>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
