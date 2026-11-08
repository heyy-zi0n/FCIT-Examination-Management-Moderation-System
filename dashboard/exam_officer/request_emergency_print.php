<?php
/**
 * Request Emergency Print
 */
require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);
$userId = (int)$user['id'];
$pdo = getDb();

$paperId = $_GET['id'] ?? null;
if (!$paperId) {
    redirect('dashboard/exam_officer/print_queue.php');
    exit;
}

$stmt = $pdo->prepare("SELECT ep.*, pv.version_number FROM examination_papers ep LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id JOIN courses c ON ep.course_id = c.id WHERE ep.id = ? AND c.department_id = ?");
$stmt->execute([$paperId, $user['department_id']]);
$paper = $stmt->fetch();

if (!$paper || !in_array($paper['status'], [STATUS_APPROVED, STATUS_LOCKED, STATUS_QUEUED])) {
    setFlash('error', 'Paper not found or not eligible for emergency print.');
    redirect('dashboard/exam_officer/print_queue.php');
    exit;
}

$submittedReason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request'])) {
    requireCsrf();
    $submittedReason = $_POST['reason'] ?? '';
    $reason = sanitizeInput($submittedReason);
    try {
        PrintingService::requestEmergencyPrint((int)$paperId, (int)$paper['current_version_id'], $userId, $reason);
        setFlash('success', 'Emergency print request submitted successfully to the HOD.');
        redirect('dashboard/exam_officer/print_queue.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Request Emergency Print — LASU FCIT EMMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto w-full">
        <?= renderAlerts() ?>
        <?php if (isset($error)): ?>
            <div role="alert" class="mb-5 p-4 bg-rose-50 border border-rose-200 text-rose-900 rounded-xl font-medium text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="mb-5">
            <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-lasu-navy transition">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Controlled Print Queue</span>
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight mb-2">Request Emergency Print Authorization</h1>
            <p class="text-sm text-slate-600 leading-relaxed mb-6">
                You are requesting to bypass the calendar-date restriction for paper <strong class="font-mono text-slate-900"><?= htmlspecialchars($paper['paper_code']) ?></strong> (Version <?= (int)($paper['version_number'] ?: 1) ?>). This action requires mandatory HOD approval. The paper remains cryptographically locked until the HOD signs off.
            </p>

            <form method="POST" action="<?= url('dashboard/exam_officer/request_emergency_print.php?id=' . (int)$paperId) ?>" class="space-y-5">
                <?= csrfField() ?>
                <input type="hidden" name="action_request" value="1">
                
                <div>
                    <label for="emergency_reason" class="block text-xs font-semibold text-slate-700 mb-1.5">Emergency Justification <span class="text-rose-600">*</span></label>
                    <textarea
                        id="emergency_reason"
                        name="reason"
                        rows="4"
                        required
                        placeholder="Explain why this paper must be printed outside of the normal 24-hour window..."
                        class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-lasu-navy focus:outline-none text-sm text-slate-900 transition"
                    ><?= htmlspecialchars($submittedReason) ?></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100">
                    <button
                        type="submit"
                        data-confirm-title="Submit Emergency Print Request"
                        data-confirm-message="Submit this emergency print bypass request to the Head of Department for review?"
                        data-confirm-label="Submit Request"
                        data-confirm-variant="primary"
                        data-loading-text="Submitting Request..."
                        class="min-h-[42px] px-6 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light rounded-xl font-bold text-xs transition shadow-sm"
                    >
                        Submit Request to HOD
                    </button>
                    <a href="<?= url('dashboard/exam_officer/print_queue.php') ?>" class="min-h-[42px] px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-xl font-semibold text-xs transition inline-flex items-center">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
