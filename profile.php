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
    header('Location: /FOCITECMS/profile.php');
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
    header('Location: /FOCITECMS/profile.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_profile_picture') {
    requireCsrf();
    ProfileService::removeProfilePicture((int)$user['id']);
    setFlash('success', 'Profile picture removed successfully.');
    header('Location: /FOCITECMS/profile.php');
    exit;
}

$pageTitle = 'Security & Profile — LASU FCIT CMS';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';

// Fetch recent user audit activities
$stmtAudit = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = :id ORDER BY created_at DESC LIMIT 10");
$stmtAudit->execute(['id' => (int)$user['id']]);
$userLogs = $stmtAudit->fetchAll();
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-5xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Security & Staff Profile</h1>
            <p class="text-xs text-slate-500">Manage account credentials and view your security activity trail</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Left Column: User Details Card -->
            <div class="bg-white dark:bg-slate-900 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                <div class="text-center pb-6 border-b border-slate-100 dark:border-slate-800">
                    <div class="mb-4 relative inline-block group">
                        <?= renderUserAvatar($user, 'w-24 h-24', 'text-3xl') ?>
                        <button type="button" onclick="document.getElementById('profile-pic-modal').classList.remove('hidden')" class="absolute inset-0 bg-slate-900/60 rounded-full flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                            <svg class="w-6 h-6 text-white mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="text-[10px] text-white font-semibold">Change</span>
                        </button>
                    </div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white"><?= formatUserName($user) ?></h2>
                    <div class="text-xs text-slate-500 font-mono mt-0.5"><?= htmlspecialchars($user['staff_id']) ?></div>
                    <div class="mt-2"><?= renderRoleBadge($user['role']) ?></div>
                    
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <button type="button" onclick="document.getElementById('profile-pic-modal').classList.remove('hidden')" class="px-3 py-1.5 text-[10px] font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg transition">Change Photo</button>
                        <?php if (!empty($user['profile_picture'])): ?>
                            <form method="POST" action="/FOCITECMS/profile.php" class="inline" onsubmit="return confirm('Are you sure you want to remove your profile picture?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="remove_profile_picture">
                                <button type="submit" class="px-3 py-1.5 text-[10px] font-bold bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/30 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 rounded-lg transition">Remove</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 block uppercase tracking-wider text-[10px]">Email Address</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <?php if (!empty($user['department_name'])): ?>
                        <div>
                            <span class="text-slate-400 block uppercase tracking-wider text-[10px]">Department</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($user['department_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-slate-400 block uppercase tracking-wider text-[10px]">Account Status</span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active & Verified
                        </span>
                    </div>
                </div>
            </div>

            <!-- Middle/Right Column: Change Password & Security Trail -->
            <div class="md:col-span-2 space-y-6">
                
                <!-- Change Password Form -->
                <div class="bg-white dark:bg-slate-900 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-lasu-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Change Password</span>
                    </h3>

                    <form method="POST" action="/FOCITECMS/profile.php" class="space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Current Password</label>
                            <input type="password" name="current_password" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs focus:ring-2 focus:ring-lasu-navy dark:focus:ring-lasu-gold focus:outline-none">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password</label>
                                <input type="password" name="new_password" required minlength="8" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs focus:ring-2 focus:ring-lasu-navy dark:focus:ring-lasu-gold focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="8" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs focus:ring-2 focus:ring-lasu-navy dark:focus:ring-lasu-gold focus:outline-none">
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="px-5 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-lg text-xs transition shadow">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Recent Activity Trail -->
                <div class="bg-white dark:bg-slate-900 rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Recent Security & Activity History</span>
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                    <th class="pb-2 font-semibold">Action</th>
                                    <th class="pb-2 font-semibold">Entity</th>
                                    <th class="pb-2 font-semibold">IP Address</th>
                                    <th class="pb-2 font-semibold">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                <?php if (empty($userLogs)): ?>
                                    <tr><td colspan="4" class="py-4 text-center text-slate-400">No activity logs recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($userLogs as $l): ?>
                                        <tr>
                                            <td class="py-2.5 font-mono text-[11px] text-blue-600 dark:text-blue-400"><?= htmlspecialchars($l['action']) ?></td>
                                            <td class="py-2.5 text-slate-500"><?= htmlspecialchars($l['entity_type']) ?> #<?= $l['entity_id'] ?: '—' ?></td>
                                            <td class="py-2.5 font-mono text-[11px] text-slate-500"><?= htmlspecialchars($l['ip_address']) ?></td>
                                            <td class="py-2.5 text-slate-400"><?= formatDate($l['created_at']) ?></td>
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

<!-- Profile Picture Upload Modal -->
<div id="profile-pic-modal" class="hidden fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-sm overflow-y-auto h-full w-full flex items-center justify-center px-4">
    <div class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-sm w-full border border-slate-200 dark:border-slate-800 p-6">
        <button type="button" onclick="document.getElementById('profile-pic-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Update Profile Picture</h3>
        <p class="text-xs text-slate-500 mb-5">Select a square or wide image. It will automatically be cropped and optimized. (Max 5MB)</p>
        
        <form method="POST" action="/FOCITECMS/profile.php" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="upload_profile_picture">
            
            <div class="mb-5 flex flex-col items-center">
                <!-- Preview Canvas -->
                <div id="image-preview-container" class="hidden mb-4">
                    <img id="image-preview" src="#" alt="Preview" class="w-32 h-32 rounded-full object-cover border-4 border-lasu-gold shadow-lg">
                </div>

                <div class="w-full">
                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-xl cursor-pointer bg-slate-50 dark:hover:bg-slate-800 dark:bg-slate-900/50 hover:bg-slate-100 dark:border-slate-700 transition">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-8 h-8 mb-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <p class="mb-1 text-sm text-slate-500"><span class="font-bold">Click to upload</span></p>
                            <p class="text-[10px] text-slate-400">JPG, PNG, or WebP</p>
                        </div>
                        <input type="file" name="profile_picture" id="profile-upload-input" accept="image/jpeg, image/png, image/webp" class="hidden" required onchange="previewImage(this)">
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('profile-pic-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-lasu-navy bg-lasu-gold hover:bg-lasu-gold-hover rounded-lg transition shadow-md">Save Picture</button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    const previewContainer = document.getElementById('image-preview-container');
    const previewImage = document.getElementById('image-preview');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Basic frontend validation
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            previewContainer.classList.add('hidden');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewContainer.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    } else {
        previewContainer.classList.add('hidden');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
