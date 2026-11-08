<?php
/**
 * User Profile & Security Settings
 */

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth();
$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    requireCsrf();

    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        setFlash('error', 'All password fields are required.');
    } elseif ($newPass !== $confirmPass) {
        setFlash('error', 'New passwords do not match.');
    } elseif (strlen($newPass) < 8) {
        setFlash('error', 'New password must be at least 8 characters long.');
    } else {
        $stmtCheck = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmtCheck->execute(['id' => (int)$user['id']]);
        $hash = $stmtCheck->fetchColumn();

        if (!verifyPassword($currentPass, $hash)) {
            setFlash('error', 'Current password is incorrect.');
        } else {
            $newHash = hashPassword($newPass);
            $stmtUpd = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['hash' => $newHash, 'id' => (int)$user['id']]);

            logAudit((int)$user['id'], 'password_change', 'user', (int)$user['id']);
            setFlash('success', 'Your password has been successfully updated.');
        }
    }
    redirect('profile.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_profile_picture') {
    requireCsrf();
    try {
        if (!isset($_FILES['profile_picture'])) {
            throw new Exception("No file was uploaded.");
        }
        ProfileService::uploadProfilePicture((int)$user['id'], $_FILES['profile_picture']);
        setFlash('success', 'Profile picture updated successfully.');
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
    redirect('profile.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_profile_picture') {
    requireCsrf();
    ProfileService::removeProfilePicture((int)$user['id']);
    setFlash('success', 'Profile picture removed successfully.');
    redirect('profile.php');
    exit;
}

$pageTitle = 'Security & Profile — LASU FCIT EMMS';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';

// Fetch recent user audit activities
$stmtAudit = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = :id ORDER BY created_at DESC LIMIT 10");
$stmtAudit->execute(['id' => (int)$user['id']]);
$userLogs = $stmtAudit->fetchAll();
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Security &amp; Staff Profile</h1>
            <p class="text-sm text-slate-600 mt-0.5">Manage account credentials, staff avatar, and view your security activity trail.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Left Column: User Details Card -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 self-start">
                <div class="text-center pb-6 border-b border-slate-100">
                    <div class="mb-4 relative inline-block group">
                        <?= renderUserAvatar($user, 'w-24 h-24', 'text-3xl') ?>
                        <button
                            type="button"
                            onclick="openModal('profile-pic-modal')"
                            aria-label="Update staff profile picture"
                            class="absolute inset-0 bg-slate-900/65 rounded-full flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-opacity cursor-pointer"
                        >
                            <svg class="w-6 h-6 text-white mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="text-[11px] text-white font-semibold">Change</span>
                        </button>
                    </div>
                    <h2 class="text-base font-bold text-slate-900"><?= formatUserName($user) ?></h2>
                    <div class="text-xs text-slate-600 font-mono mt-0.5 tabular-nums"><?= htmlspecialchars($user['staff_id']) ?></div>
                    <div class="mt-2.5"><?= renderRoleBadge($user['role']) ?></div>
                    
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <button type="button" onclick="openModal('profile-pic-modal')" class="min-h-[34px] px-3.5 py-1.5 text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-lg transition">
                            Change Photo
                        </button>
                        <?php if (!empty($user['profile_picture'])): ?>
                            <form method="POST" action="<?= url('profile.php') ?>" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="remove_profile_picture">
                                <button
                                    type="submit"
                                    data-confirm-title="Remove Profile Picture"
                                    data-confirm-message="Are you sure you want to remove your staff profile picture?"
                                    data-confirm-label="Remove Photo"
                                    data-confirm-variant="danger"
                                    data-loading-text="Removing..."
                                    class="min-h-[34px] px-3.5 py-1.5 text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 rounded-lg transition"
                                >
                                    Remove
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <dl class="mt-4 space-y-3.5 text-sm">
                    <div>
                        <dt class="text-slate-500 uppercase tracking-wider text-[11px] font-semibold">Email Address</dt>
                        <dd class="font-medium text-slate-900 mt-0.5 break-all"><?= htmlspecialchars($user['email']) ?></dd>
                    </div>
                    <?php if (!empty($user['department_name'])): ?>
                        <div>
                            <dt class="text-slate-500 uppercase tracking-wider text-[11px] font-semibold">Department</dt>
                            <dd class="font-medium text-slate-900 mt-0.5"><?= htmlspecialchars($user['department_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt class="text-slate-500 uppercase tracking-wider text-[11px] font-semibold">Account Status</dt>
                        <dd class="inline-flex items-center gap-1.5 font-semibold text-emerald-800 text-xs mt-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-600" aria-hidden="true"></span>
                            <span>Active &amp; Verified</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Middle/Right Column: Change Password & Security Trail -->
            <div class="md:col-span-2 space-y-6">
                <!-- Change Password Form -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-lasu-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Change Password</span>
                    </h2>

                    <form method="POST" action="<?= url('profile.php') ?>" class="space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label for="current_password" class="block text-xs font-semibold text-slate-700 mb-1.5">Current Password <span class="text-rose-600">*</span></label>
                            <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="new_password" class="block text-xs font-semibold text-slate-700 mb-1.5">New Password <span class="text-rose-600">*</span></label>
                                <input id="new_password" type="password" name="new_password" required minlength="8" autocomplete="new-password" aria-describedby="new-password-hint" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                                <span id="new-password-hint" class="block text-xs text-slate-500 mt-1">Minimum 8 characters.</span>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-xs font-semibold text-slate-700 mb-1.5">Confirm New Password <span class="text-rose-600">*</span></label>
                                <input id="confirm_password" type="password" name="confirm_password" required minlength="8" autocomplete="new-password" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" data-loading-text="Updating Password..." class="min-h-[42px] px-5 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs transition shadow-sm">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Recent Activity Trail -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <h2 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-lasu-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Recent Security &amp; Activity History</span>
                    </h2>

                    <div class="table-scroll-region" tabindex="0" role="region" aria-label="Recent security activity history">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                    <th scope="col" class="pb-2.5 pr-3 font-bold">Action</th>
                                    <th scope="col" class="pb-2.5 px-3 font-bold">Entity</th>
                                    <th scope="col" class="pb-2.5 px-3 font-bold">IP Address</th>
                                    <th scope="col" class="pb-2.5 pl-3 font-bold">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <?php if (empty($userLogs)): ?>
                                    <tr><td colspan="4" class="py-6 text-center text-sm text-slate-500">No activity logs recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($userLogs as $l): ?>
                                        <tr class="hover:bg-slate-50/70 transition">
                                            <td class="py-3 pr-3 font-mono text-xs font-semibold text-lasu-navy"><?= htmlspecialchars($l['action']) ?></td>
                                            <td class="py-3 px-3 text-slate-600"><?= htmlspecialchars($l['entity_type']) ?> #<?= $l['entity_id'] ?: '—' ?></td>
                                            <td class="py-3 px-3 font-mono text-xs text-slate-600 tabular-nums"><?= htmlspecialchars($l['ip_address']) ?></td>
                                            <td class="py-3 pl-3 text-slate-500 tabular-nums whitespace-nowrap"><?= formatDate($l['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Native Dialog: Profile Picture Upload -->
<dialog id="profile-pic-modal" closedby="any" aria-labelledby="profile-pic-modal-title" class="rounded-2xl shadow-2xl border border-slate-200 bg-white text-slate-800 w-[min(26rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
        <h2 id="profile-pic-modal-title" class="text-sm font-bold">Update Profile Picture</h2>
        <button type="button" onclick="closeModal('profile-pic-modal')" aria-label="Close profile photo modal" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition">&times;</button>
    </div>

    <form method="POST" action="<?= url('profile.php') ?>" enctype="multipart/form-data" class="p-6">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_profile_picture">

        <p class="text-xs text-slate-600 mb-4 leading-relaxed">Select a square or wide image. It will automatically be cropped and optimized (JPG, PNG, or WebP, max 5 MB).</p>
        
        <div class="mb-5 flex flex-col items-center">
            <!-- Preview Canvas -->
            <div id="image-preview-container" class="hidden mb-4">
                <img id="image-preview" src="about:blank" alt="Selected profile preview" class="w-28 h-28 rounded-full object-cover border-4 border-lasu-gold shadow-md">
            </div>

            <div class="w-full">
                <input
                    type="file"
                    name="profile_picture"
                    id="profile-upload-input"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only peer"
                    required
                    aria-describedby="profile-upload-feedback"
                    onchange="previewImage(this)"
                >
                <label for="profile-upload-input" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 peer-focus-visible:ring-2 peer-focus-visible:ring-lasu-navy peer-focus-visible:border-lasu-navy transition">
                    <div class="flex flex-col items-center justify-center py-4 px-3 text-center">
                        <svg class="w-7 h-7 mb-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="mb-0.5 text-xs text-slate-700"><span class="font-bold">Click or press Space/Enter to choose image</span></p>
                        <p class="text-[11px] text-slate-500">JPG, PNG, or WebP (max 5 MB)</p>
                    </div>
                </label>
                <div id="profile-upload-feedback" class="hidden" aria-live="polite"></div>
            </div>
        </div>

        <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
            <button type="button" onclick="closeModal('profile-pic-modal')" class="min-h-[38px] px-4 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            <button type="submit" data-loading-text="Uploading..." class="min-h-[38px] px-5 py-2 text-xs font-bold text-lasu-navy bg-lasu-gold hover:bg-lasu-gold-hover rounded-xl transition shadow-sm">Save Picture</button>
        </div>
    </form>
</dialog>

<script>
function previewImage(input) {
    const previewContainer = document.getElementById('image-preview-container');
    const previewImg = document.getElementById('image-preview');
    const feedback = document.getElementById('profile-upload-feedback');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const sizeMb = file.size / (1024 * 1024);

        if (sizeMb > 5) {
            input.value = '';
            previewContainer.classList.add('hidden');
            feedback.textContent = `Selected file (${sizeMb.toFixed(2)} MB) exceeds the 5 MB maximum limit.`;
            feedback.className = 'mt-2.5 px-3 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold block';
            return;
        }

        feedback.textContent = `Selected: ${file.name} (${sizeMb >= 1 ? sizeMb.toFixed(2) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB'})`;
        feedback.className = 'mt-2.5 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold block truncate';

        const reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewContainer.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        previewContainer.classList.add('hidden');
        feedback.textContent = '';
        feedback.className = 'hidden';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
