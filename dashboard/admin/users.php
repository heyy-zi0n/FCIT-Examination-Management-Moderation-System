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
                    if (strlen($password) < 8) {
                        throw new InvalidArgumentException("Password must be at least 8 characters long.");
                    }
                    $pwdHash = hashPassword($password);
                    $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id")->execute(['p' => $pwdHash, 'id' => $userId]);
                }

                logAudit((int)$user['id'], 'update_user', 'user', $userId, ['email' => $email, 'role' => $role]);
                setFlash('success', "User account for {$firstName} {$lastName} updated successfully.");

            } else {
                // Create New User
                if (empty($password) || strlen($password) < 8) {
                    throw new InvalidArgumentException("Initial password must be at least 8 characters long.");
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

        if ($targetId > 0 && $targetId !== (int)$user['id']) {
            $pdo->prepare("UPDATE users SET status = :s WHERE id = :id")->execute(['s' => $newStatus, 'id' => $targetId]);
            logAudit((int)$user['id'], 'toggle_user_status', 'user', $targetId, ['status' => $newStatus]);
            setFlash('success', "User status updated to {$newStatus}.");
        }
        redirect('dashboard/admin/users.php');
        exit;
    }
}

// Fetch all users with department names
$usersList = $pdo->query("
    SELECT u.*, d.name AS dept_name, d.code AS dept_code
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY u.role ASC, u.first_name ASC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

$pageTitle = 'User Accounts & Roles — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">User Accounts & Role Administration</h1>
                <p class="text-xs text-slate-500">Manage credentials, departmental allocations, and RBAC permissions</p>
            </div>
            
            <button type="button" onclick="openCreateUserModal()" class="px-4 py-2.5 bg-lasu-navy text-lasu-gold hover:bg-lasu-navy-light font-bold rounded-xl text-xs shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span>Create User</span>
            </button>
        </div>

        <!-- Users Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">User Details</th>
                            <th class="py-3.5 px-4 font-semibold">Staff ID</th>
                            <th class="py-3.5 px-4 font-semibold">Role</th>
                            <th class="py-3.5 px-4 font-semibold">Department</th>
                            <th class="py-3.5 px-4 font-semibold">Account Status</th>
                            <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php foreach ($usersList as $u): ?>
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-3">
                                        <?= renderUserAvatar($u, 'w-10 h-10', 'text-xs') ?>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(($u['title'] ? $u['title'] . ' ' : '') . $u['first_name'] . ' ' . $u['last_name']) ?></div>
                                            <div class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 font-mono text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($u['staff_id']) ?>
                                </td>
                                <td class="py-4 px-4">
                                    <?= renderRoleBadge($u['role']) ?>
                                </td>
                                <td class="py-4 px-4">
                                    <?= htmlspecialchars($u['dept_name'] ?? 'Faculty Level / General') ?>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $u['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                        <?= ucfirst($u['status']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" onclick='openEditUserModal(<?= json_encode($u) ?>)' class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition border border-slate-300 dark:border-slate-700">
                                            Edit
                                        </button>
                                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                            <form method="POST" action="<?= url('dashboard/admin/users.php') ?>" class="inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action_toggle_status" value="1">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                                                <button type="submit" onclick="return confirm('Change status for this user?')" class="px-3 py-1.5 <?= $u['status'] === 'active' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?> rounded-lg text-xs font-semibold transition">
                                                    <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
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

<!-- Modal: User Form -->
<div id="user-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-lg overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 bg-lasu-navy text-white flex items-center justify-between">
            <h3 class="text-sm font-bold" id="user-modal-title">Create User Account</h3>
            <button type="button" onclick="closeModal('user-modal')" class="text-slate-300 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="<?= url('dashboard/admin/users.php') ?>" class="p-6 space-y-4 overflow-y-auto flex-1">
            <?= csrfField() ?>
            <input type="hidden" name="action_save_user" value="1">
            <input type="hidden" name="user_id" id="form-user-id" value="0">

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Title</label>
                    <input type="text" name="title" id="form-title" placeholder="Dr. / Prof." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">First Name *</label>
                    <input type="text" name="first_name" id="form-first-name" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Last Name *</label>
                    <input type="text" name="last_name" id="form-last-name" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Staff ID *</label>
                    <input type="text" name="staff_id" id="form-staff-id" required placeholder="LASU/FCIT/..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
                    <input type="email" name="email" id="form-email" required placeholder="staff@lasu.edu.ng" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">System Role *</label>
                    <select name="role" id="form-role" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-semibold">
                        <option value="lecturer">Lecturer</option>
                        <option value="moderator">Moderator</option>
                        <option value="hod">Head of Department (HOD)</option>
                        <option value="exam_officer">Faculty Examination Officer</option>
                        <option value="admin">System Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department</label>
                    <select name="department_id" id="form-dept" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                        <option value="">None / Faculty General</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1" id="form-password-label">Password *</label>
                <input type="password" name="password" id="form-password" placeholder="Min 8 characters" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-xs">
                <span class="text-[10px] text-slate-400" id="form-password-help">Leave blank when editing to keep current password.</span>
            </div>

            <div class="pt-4 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeModal('user-modal')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-lasu-navy text-lasu-gold font-bold rounded-lg text-xs shadow">Save Account</button>
            </div>
        </form>
    </div>
</div>

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
    document.getElementById('form-password').required = true;
    document.getElementById('form-password-label').textContent = 'Initial Password *';
    openModal('user-modal');
}

function openEditUserModal(u) {
    document.getElementById('user-modal-title').textContent = 'Edit User: ' + u.first_name + ' ' + u.last_name;
    document.getElementById('form-user-id').value = u.id;
    document.getElementById('form-title').value = u.title || '';
    document.getElementById('form-first-name').value = u.first_name || '';
    document.getElementById('form-last-name').value = u.last_name || '';
    document.getElementById('form-staff-id').value = u.staff_id || '';
    document.getElementById('form-email').value = u.email || '';
    document.getElementById('form-role').value = u.role || 'lecturer';
    document.getElementById('form-dept').value = u.department_id || '';
    document.getElementById('form-password').required = false;
    document.getElementById('form-password-label').textContent = 'Reset Password (Optional)';
    openModal('user-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
