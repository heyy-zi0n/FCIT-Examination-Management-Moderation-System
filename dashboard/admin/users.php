<?php
/**
 * User Accounts & RBAC Management (Administrator)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

// Handle User Actions (Create, Edit, Toggle Status, Reset Password)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // 1. Create / Edit User
    if (isset($_POST['action_save_user'])) {
        $userId = (int)($_POST['user_id'] ?? 0);
        $staffId = sanitizeInput($_POST['staff_id'] ?? '');
        $title = sanitizeInput($_POST['title'] ?? '');
        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? ROLE_LECTURER);
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $password = $_POST['password'] ?? '';

        try {
            if (empty($staffId) || empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
                throw new InvalidArgumentException("All fields marked with * are required.");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Please provide a valid email address.");
            }

            $validRoles = [ROLE_ADMIN, ROLE_HOD, ROLE_EXAM_OFFICER, ROLE_MODERATOR, ROLE_LECTURER];
            if (!in_array($role, $validRoles, true)) {
                throw new InvalidArgumentException("Invalid system role selected.");
            }

            if ($role !== ROLE_ADMIN && $deptId === null) {
                throw new InvalidArgumentException("Department assignment is required for non-administrator roles.");
            }

            if ($userId > 0) {
                // Update User
                $stmtUpd = $pdo->prepare("
                    UPDATE users
                    SET staff_id = :sid, title = :title, first_name = :fn, last_name = :ln,
                        email = :em, role = :role, department_id = :dept_id,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpd->execute([
                    'sid' => $staffId,
                    'title' => $title,
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'em' => $email,
                    'role' => $role,
                    'dept_id' => $deptId,
                    'id' => $userId
                ]);

                if (!empty($password)) {
                    if (strlen($password) < PASSWORD_MIN_LENGTH) {
                        throw new InvalidArgumentException("Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.");
                    }
                    $pwdHash = hashPassword($password);
                    $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id")->execute(['p' => $pwdHash, 'id' => $userId]);
                }

                logAudit((int)$user['id'], 'update_user', 'user', $userId, ['email' => $email, 'role' => $role]);
                setFlash('success', "User account for {$firstName} {$lastName} updated successfully.");

            } else {
                // Create New User
                if (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH) {
                    throw new InvalidArgumentException("Initial password must be at least " . PASSWORD_MIN_LENGTH . " characters long.");
                }
                $pwdHash = hashPassword($password);

                $stmtIns = $pdo->prepare("
                    INSERT INTO users (staff_id, title, first_name, last_name, email, password_hash, role, department_id, status, created_at)
                    VALUES (:sid, :title, :fn, :ln, :em, :pwd, :role, :dept_id, 'active', NOW())
                ");
                $stmtIns->execute([
                    'sid' => $staffId,
                    'title' => $title,
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'em' => $email,
                    'pwd' => $pwdHash,
                    'role' => $role,
                    'dept_id' => $deptId
                ]);

                $newId = (int)$pdo->lastInsertId();
                logAudit((int)$user['id'], 'create_user', 'user', $newId, ['email' => $email, 'role' => $role]);
                setFlash('success', "New user account {$email} created successfully.");
            }

            redirect('dashboard/admin/users.php');
            exit;

        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }

    // 2. Toggle Status
    if (isset($_POST['action_toggle_status'])) {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $newStatus = sanitizeInput($_POST['new_status'] ?? 'active');
        if (!in_array($newStatus, ['active', 'inactive', 'suspended'], true)) {
            $newStatus = 'inactive';
        }

        if ($targetId > 0 && $targetId !== (int)$user['id']) {
            $pdo->prepare("UPDATE users SET status = :s WHERE id = :id")->execute(['s' => $newStatus, 'id' => $targetId]);
            logAudit((int)$user['id'], 'toggle_user_status', 'user', $targetId, ['status' => $newStatus]);
            setFlash('success', "User status updated to {$newStatus}.");
        }
        redirect('dashboard/admin/users.php');
        exit;
    }
}

// Fetch all users with department names (explicitly exclude password_hash, failed_attempts, locked_until)
$usersList = $pdo->query("
    SELECT u.id, u.staff_id, u.title, u.first_name, u.last_name, u.email,
           u.profile_picture, u.role, u.department_id, u.status, u.last_login_at, u.created_at,
           d.name AS dept_name, d.code AS dept_code
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY u.role ASC, u.first_name ASC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

$pageTitle = 'User Accounts & Roles — LASU FCIT EMMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">User Accounts &amp; Role Administration</h1>
                <p class="text-sm text-slate-600 mt-0.5">Manage staff credentials, departmental allocations, and RBAC permissions.</p>
            </div>
            
            <button type="button" onclick="openCreateUserModal()" class="min-h-[42px] px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow-sm transition inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span>Create User</span>
            </button>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="table-scroll-region" tabindex="0" role="region" aria-label="Staff accounts table">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" class="py-3.5 px-4 font-bold">User Details</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Staff ID</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Role</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Department</th>
                            <th scope="col" class="py-3.5 px-4 font-bold">Account Status</th>
                            <th scope="col" class="py-3.5 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($usersList as $u):
                            $safeUserPayload = [
                                'id' => (int)$u['id'],
                                'staff_id' => $u['staff_id'],
                                'title' => $u['title'],
                                'first_name' => $u['first_name'],
                                'last_name' => $u['last_name'],
                                'email' => $u['email'],
                                'role' => $u['role'],
                                'department_id' => $u['department_id']
                            ];
                            $displayName = trim(($u['title'] ? $u['title'] . ' ' : '') . $u['first_name'] . ' ' . $u['last_name']);
                            $isActive = ($u['status'] === 'active');
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-3">
                                        <?= renderUserAvatar($u, 'w-10 h-10 shrink-0', 'text-xs') ?>
                                        <div class="min-w-0">
                                            <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($displayName) ?></div>
                                            <div class="text-xs text-slate-500 font-mono truncate"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 font-mono text-xs text-slate-700 tabular-nums whitespace-nowrap">
                                    <?= htmlspecialchars($u['staff_id']) ?>
                                </td>
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <?= renderRoleBadge($u['role']) ?>
                                </td>
                                <td class="py-4 px-4 text-sm text-slate-700">
                                    <?= htmlspecialchars($u['dept_name'] ?? 'Faculty Level / General') ?>
                                </td>
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border <?= $isActive ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-emerald-600' : 'bg-rose-600' ?>" aria-hidden="true"></span>
                                        <span><?= htmlspecialchars(ucfirst($u['status'])) ?></span>
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        <button type="button"
                                            data-user="<?= htmlspecialchars(json_encode($safeUserPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="openEditUserModal(this)"
                                            class="min-h-[34px] px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-800 rounded-lg text-xs font-semibold transition border border-slate-300">
                                            Edit
                                        </button>
                                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                            <form method="POST" action="<?= url('dashboard/admin/users.php') ?>" class="inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action_toggle_status" value="1">
                                                <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                                <button
                                                    type="submit"
                                                    data-confirm-title="<?= $isActive ? 'Deactivate Staff Account' : 'Activate Staff Account' ?>"
                                                    data-confirm-message="<?= $isActive ? 'Deactivate portal access for ' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '?' : 'Restore active portal access for ' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '?' ?>"
                                                    data-confirm-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                                                    data-confirm-variant="<?= $isActive ? 'danger' : 'success' ?>"
                                                    data-loading-text="Updating..."
                                                    class="min-h-[34px] px-3 py-1.5 <?= $isActive ? 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100' ?> rounded-lg text-xs font-semibold transition"
                                                >
                                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Native Dialog: User Form -->
<dialog id="user-modal" closedby="any" aria-labelledby="user-modal-title" class="rounded-2xl shadow-2xl border border-slate-200 bg-white text-slate-800 w-[min(36rem,calc(100vw-2rem))] p-0 overflow-hidden">
    <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
        <h2 class="text-sm font-bold" id="user-modal-title">Create User Account</h2>
        <button type="button" onclick="closeModal('user-modal')" aria-label="Close user modal" class="w-8 h-8 rounded-lg inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition">&times;</button>
    </div>
    <form method="POST" action="<?= url('dashboard/admin/users.php') ?>" class="p-6 space-y-4 overflow-y-auto max-h-[calc(100dvh-6rem)]">
        <?= csrfField() ?>
        <input type="hidden" name="action_save_user" value="1">
        <input type="hidden" name="user_id" id="form-user-id" value="0">

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            <div>
                <label for="form-title" class="block text-xs font-semibold text-slate-700 mb-1">Title</label>
                <input type="text" name="title" id="form-title" placeholder="Dr. / Prof." autocomplete="honorific-prefix" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            </div>
            <div>
                <label for="form-first-name" class="block text-xs font-semibold text-slate-700 mb-1">First Name <span class="text-rose-600">*</span></label>
                <input type="text" name="first_name" id="form-first-name" required autocomplete="given-name" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            </div>
            <div>
                <label for="form-last-name" class="block text-xs font-semibold text-slate-700 mb-1">Last Name <span class="text-rose-600">*</span></label>
                <input type="text" name="last_name" id="form-last-name" required autocomplete="family-name" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <label for="form-staff-id" class="block text-xs font-semibold text-slate-700 mb-1">Staff ID <span class="text-rose-600">*</span></label>
                <input type="text" name="staff_id" id="form-staff-id" required placeholder="LASU/FCIT/..." autocorrect="off" autocapitalize="characters" spellcheck="false" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-mono text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            </div>
            <div>
                <label for="form-email" class="block text-xs font-semibold text-slate-700 mb-1">Email Address <span class="text-rose-600">*</span></label>
                <input type="email" name="email" id="form-email" required placeholder="staff@lasu.edu.ng" autocomplete="email" autocorrect="off" autocapitalize="off" spellcheck="false" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <label for="form-role" class="block text-xs font-semibold text-slate-700 mb-1">System Role <span class="text-rose-600">*</span></label>
                <select name="role" id="form-role" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                    <option value="lecturer">Lecturer</option>
                    <option value="moderator">Moderator</option>
                    <option value="hod">Head of Department (HOD)</option>
                    <option value="exam_officer">Faculty Examination Officer</option>
                    <option value="admin">System Administrator</option>
                </select>
            </div>
            <div>
                <label for="form-dept" class="block text-xs font-semibold text-slate-700 mb-1">Department</label>
                <select name="department_id" id="form-dept" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
                    <option value="">None / Faculty General</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="form-password" class="block text-xs font-semibold text-slate-700 mb-1" id="form-password-label">Password <span class="text-rose-600">*</span></label>
            <input type="password" name="password" id="form-password" minlength="<?= (int)PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" aria-describedby="form-password-help" placeholder="Minimum <?= (int)PASSWORD_MIN_LENGTH ?> characters" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-lasu-navy focus:outline-none">
            <span class="block text-xs text-slate-500 mt-1" id="form-password-help">Leave blank when editing to preserve the existing password.</span>
        </div>

        <div class="pt-4 flex items-center justify-end gap-2.5 border-t border-slate-100">
            <button type="button" onclick="closeModal('user-modal')" class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">Cancel</button>
            <button type="submit" data-loading-text="Saving Account..." class="min-h-[38px] px-5 py-2 bg-lasu-navy hover:bg-lasu-navy-light text-lasu-gold font-bold rounded-xl text-xs shadow-sm transition">Save Account</button>
        </div>
    </form>
</dialog>

<script>
function openCreateUserModal() {
    document.getElementById('user-modal-title').textContent = 'Create User Account';
    document.getElementById('form-user-id').value = '0';
    document.getElementById('form-title').value = '';
    document.getElementById('form-first-name').value = '';
    document.getElementById('form-last-name').value = '';
    document.getElementById('form-staff-id').value = '';
    document.getElementById('form-email').value = '';
    document.getElementById('form-role').value = 'lecturer';
    document.getElementById('form-dept').value = '';
    document.getElementById('form-password').value = '';
    document.getElementById('form-password').required = true;
    document.getElementById('form-password-label').innerHTML = 'Initial Password <span class="text-rose-600">*</span>';
    openModal('user-modal');
}

function openEditUserModal(btn) {
    const u = JSON.parse(btn.getAttribute('data-user'));
    document.getElementById('user-modal-title').textContent = 'Edit User: ' + (u.first_name || '') + ' ' + (u.last_name || '');
    document.getElementById('form-user-id').value = u.id;
    document.getElementById('form-title').value = u.title || '';
    document.getElementById('form-first-name').value = u.first_name || '';
    document.getElementById('form-last-name').value = u.last_name || '';
    document.getElementById('form-staff-id').value = u.staff_id || '';
    document.getElementById('form-email').value = u.email || '';
    document.getElementById('form-role').value = u.role || 'lecturer';
    document.getElementById('form-dept').value = u.department_id || '';
    document.getElementById('form-password').value = '';
    document.getElementById('form-password').required = false;
    document.getElementById('form-password-label').textContent = 'Reset Password (Optional)';
    openModal('user-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
