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
$submittedCopies = (int)($paper['expected_students'] ?: 100);
$submittedReprintReason = '';

// Handle Print Confirmation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_confirm_print'])) {
    requireCsrf();

    $copies = (int)($_POST['copies_to_print'] ?? $paper['expected_students']);
    $submittedCopies = $copies > 0 ? $copies : $submittedCopies;
    $submittedReprintReason = $_POST['reprint_reason'] ?? '';
    $reprintReason = sanitizeInput($submittedReprintReason ?: null);

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

$pageTitle = "Controlled Printing: {$paper['course_code']} — LASU FCIT EMMS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6 no-print">
            <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-lasu-navy mb-2 transition">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Controlled Print Queue</span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($paper['course_code']) ?>: Controlled Printing</h1>
                <?= renderStatusBadge($paper['status']) ?>
            </div>
            <p class="text-xs text-slate-600 mt-1 tabular-nums">Department: <?= htmlspecialchars($paper['dept_name']) ?> &bull; Examination Date: <?= formatDate($paper['exam_date'], 'l, d F Y') ?></p>
        </div>

        <?php if ($printExecutionResult && $decryptedPdfBase64): ?>
            <!-- PRINT SUCCESSFUL & SECURE EPHEMERAL PRINT RENDERER -->
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 mb-8 text-center no-print" role="status">
                <div class="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="text-lg font-bold text-emerald-950">Controlled Printing Authorization Generated</h2>
                <p class="text-xs text-emerald-800 mt-1.5">
                    Print Verification Serial:
                    <span class="font-mono font-bold bg-white px-3 py-1 rounded-lg border border-emerald-300 tabular-nums ml-1"><?= htmlspecialchars($printExecutionResult['verification_code']) ?></span>
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    <button type="button" onclick="triggerControlledPrint()" class="min-h-[42px] px-6 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Send to Physical Printer</span>
                    </button>
                    <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="min-h-[42px] px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 rounded-xl text-xs font-semibold transition inline-flex items-center">
                        Return to Queue
                    </a>
                </div>
            </div>

            <!-- Official Controlled Examination Document Output -->
            <div class="bg-white p-5 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
                <!-- Security Header Banner -->
                <div class="border-b-2 border-slate-900 pb-4 mb-6 text-center">
                    <div class="text-xs font-bold uppercase tracking-widest text-slate-600">Lagos State University, Ojo</div>
                    <div class="text-sm font-extrabold uppercase text-slate-900">Faculty of Computing &amp; Information Technology</div>
                    <div class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($paper['dept_name']) ?></div>
                    <div class="mt-2 text-base font-black uppercase text-slate-900"><?= htmlspecialchars($paper['course_code']) ?> &mdash; <?= htmlspecialchars($paper['course_title']) ?></div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs font-mono mt-3 pt-3 border-t border-slate-200 text-slate-700 tabular-nums">
                        <div>Time Allowed: <?= (int)$paper['duration_minutes'] ?> Mins</div>
                        <div>Exam Date: <?= formatDate($paper['exam_date'], 'd/m/Y') ?></div>
                        <div>Units: <?= (int)$paper['credit_units'] ?></div>
                    </div>
                </div>

                <!-- Ephemeral In-Memory Decrypted Document Stream -->
                <div class="h-[460px] sm:h-[640px] lg:h-[750px] w-full border border-slate-300 rounded-xl overflow-hidden mb-6">
                    <iframe id="controlled-print-frame" class="w-full h-full border-0" title="Authorized Printable Question Document"></iframe>
                </div>

                <!-- Security Verification Footer Stamp -->
                <div class="border-t-2 border-slate-900 pt-4 flex flex-wrap items-center justify-between gap-2 text-[11px] font-mono text-slate-600 tabular-nums">
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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Print Parameters Card -->
                <div class="lg:col-span-1 bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-lasu-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Controlled Print Authorization</span>
                    </h2>

                    <form method="POST" action="<?= url('dashboard/exam_officer/controlled_print.php?id=' . $paperId) ?>" class="space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="action_confirm_print" value="1">

                        <div>
                            <label for="copies_to_print" class="block text-xs font-semibold text-slate-700 mb-1.5">Copies to Print <span class="text-rose-600">*</span></label>
                            <input
                                id="copies_to_print"
                                type="number"
                                name="copies_to_print"
                                required
                                min="1"
                                max="1000"
                                value="<?= (int)$submittedCopies ?>"
                                aria-describedby="copies-hint"
                                class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-bold font-mono text-slate-900 tabular-nums focus:ring-2 focus:ring-lasu-navy focus:outline-none"
                            >
                            <span id="copies-hint" class="block text-xs text-slate-500 mt-1">Default based on scheduled expected student cohort</span>
                        </div>

                        <?php if ($isReprint): ?>
                            <div>
                                <label for="reprint_reason" class="block text-xs font-semibold text-rose-800 mb-1.5">Reprint Justification Reason <span class="text-rose-600">*</span></label>
                                <textarea
                                    id="reprint_reason"
                                    name="reprint_reason"
                                    rows="3"
                                    required
                                    placeholder="Explain why a reprint is required (e.g. printer paper jam, ink smear)..."
                                    class="w-full px-3.5 py-2.5 bg-white border border-rose-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-rose-600 focus:outline-none"
                                ><?= htmlspecialchars($submittedReprintReason) ?></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-2 tabular-nums">
                            <div class="flex justify-between gap-2"><span class="text-slate-500">Scheduled Exam Date:</span> <span class="font-bold text-slate-900"><?= formatDate($paper['exam_date'], 'd M Y') ?></span></div>
                            <div class="flex justify-between gap-2"><span class="text-slate-500">Permitted Printing Day:</span> <span class="font-bold text-cyan-800"><?= formatDate($eligibility['allowed_date'], 'd M Y') ?></span></div>
                            <div class="flex justify-between gap-2"><span class="text-slate-500">Venue / Location:</span> <span class="font-medium text-slate-800 text-right"><?= htmlspecialchars($paper['venue']) ?></span></div>
                        </div>

                        <?php if ($eligibility['eligible'] || $isReprint): ?>
                            <button
                                type="submit"
                                data-confirm-title="Authorize Controlled Printing"
                                data-confirm-message="Authorize physical examination printing for <?= htmlspecialchars($paper['course_code'], ENT_QUOTES, 'UTF-8') ?>? This decrypts the sealed payload in memory and records an immutable audit serial."
                                data-confirm-label="Authorize & Decrypt"
                                data-confirm-variant="gold"
                                data-loading-text="Decrypting & Rendering..."
                                class="min-h-[44px] w-full py-3 bg-lasu-gold hover:bg-lasu-gold-hover text-lasu-navy font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                <span>Authorize &amp; Decrypt for Printing</span>
                            </button>
                        <?php else: ?>
                            <div class="p-3.5 bg-rose-50 text-rose-900 border border-rose-200 rounded-xl text-xs leading-relaxed" role="alert">
                                <?= htmlspecialchars($eligibility['message']) ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Cryptographic Lockdown Status Card (No Pre-Authorization Document Preview) -->
                <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4">
                            Cryptographic Lockdown &amp; Integrity Verification
                        </h2>

                        <div class="p-4 rounded-xl bg-purple-50/70 border border-purple-200 mb-6">
                            <div class="flex items-center gap-2 text-purple-900 font-bold mb-2 text-xs">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span>AES-256-GCM Blind Lockdown Payload Active</span>
                            </div>
                            <div class="font-mono text-xs text-slate-700 break-all">
                                Checksum (SHA-256): <?= htmlspecialchars($paper['checksum_sha256']) ?>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 tabular-nums">
                                Sealed on: <?= formatDate($paper['locked_at']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="border-2 border-dashed border-slate-200 rounded-2xl p-8 sm:p-12 text-center bg-slate-50/70 my-auto">
                        <div class="w-14 h-14 rounded-2xl bg-lasu-navy text-lasu-gold flex items-center justify-center mx-auto mb-4 shadow-sm">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 mb-1.5">
                            Examination Content Sealed Under Blind Lockdown
                        </h3>
                        <p class="text-sm text-slate-600 max-w-md mx-auto leading-relaxed">
                            Plaintext files and previews have been purged from storage. The encrypted <code>.enc</code> payload can only be decrypted in memory and streamed to the print interface after submitting formal print authorization.
                        </p>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
