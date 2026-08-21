<?php
/**
 * Request Emergency Print
 */
require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_EXAM_OFFICER);
$userId = $user['id'];
$pdo = getDb();

$paperId = $_GET['id'] ?? null;
if (!$paperId) {
    header("Location: /FOCITECMS/dashboard/exam_officer/print_queue.php");
    exit;
}

$stmt = $pdo->prepare("SELECT ep.*, pv.version_number FROM examination_papers ep LEFT JOIN paper_versions pv ON ep.current_version_id = pv.id WHERE ep.id = ?");
$stmt->execute([$paperId]);
$paper = $stmt->fetch();

if (!$paper || !in_array($paper['status'], [STATUS_APPROVED, STATUS_LOCKED, STATUS_QUEUED])) {
    setFlash('error', 'Paper not found or not eligible for emergency print.');
    header("Location: /FOCITECMS/dashboard/exam_officer/print_queue.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request'])) {
    requireCsrf();
    $reason = sanitizeInput($_POST['reason'] ?? '');
    try {
        PrintingService::requestEmergencyPrint((int)$paperId, (int)$paper['current_version_id'], $userId, $reason);
        setFlash('success', 'Emergency print request submitted successfully to the HOD.');
        header("Location: /FOCITECMS/dashboard/exam_officer/print_queue.php");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Request Emergency Print — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-2xl mx-auto w-full">
        <?= renderAlerts() ?>
        <?php if (isset($error)): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-800 rounded-xl font-medium text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="bg-white dark:bg-slate-900 rounded-xl shadow border border-slate-200 dark:border-slate-800 p-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Request Emergency Print Authorization</h1>
            <p class="text-sm text-slate-500 mb-6">
                You are requesting to bypass the calendar-date restriction for paper <strong class="text-slate-700 dark:text-slate-300"><?= htmlspecialchars($paper['paper_code']) ?></strong> (Version <?= $paper['version_number'] ?>). This action requires mandatory HOD approval. The paper will remain locked until the HOD signs off.
            </p>

            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action_request" value="1">
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Emergency Justification <span class="text-rose-500">*</span></label>
                    <textarea name="reason" rows="4" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-lasu-gold focus:border-lasu-gold outline-none text-sm text-slate-700 dark:text-slate-300 resize-none transition" required placeholder="Explain why this paper must be printed outside of the normal 24-hour window..."></textarea>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="px-6 py-2.5 bg-lasu-navy text-white hover:bg-lasu-navy/90 rounded-xl font-bold text-sm transition shadow-sm">Submit Request to HOD</button>
                    <a href="/FOCITECMS/dashboard/exam_officer/print_queue.php" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-bold text-sm transition">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
