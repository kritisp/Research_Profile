<?php
/**
 * Super Administrator Central Management Console
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('super_admin');

$db = Database::getConnection();

// Handle Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dept') {
    require_csrf();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!empty($code) && !empty($name)) {
        try {
            $stmt = $db->prepare("INSERT INTO departments (code, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $desc]);
            record_audit('department_created', 'departments', (int)$db->lastInsertId(), "Created department {$code}");
            set_flash('success', "Department {$name} ({$code}) created successfully.");
            redirect('admin/index.php');
        } catch (Exception $e) {
            error_log("Error adding department: " . $e->getMessage());
            set_flash('danger', 'Failed to add department. The department code may already exist.');
        }
    }
}

// Handle User Role Change or Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    require_csrf();
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $newRole      = $_POST['new_role'] ?? '';
    $newStatus    = $_POST['new_status'] ?? '';

    if ($targetUserId === user_id() && ($newRole !== 'super_admin' || $newStatus !== 'active')) {
        set_flash('warning', 'Security Safeguard: You cannot demote or deactivate your own active Super Administrator account.');
        redirect('admin/index.php');
    }

    if ($targetUserId > 0 && in_array($newRole, ['super_admin', 'admin', 'faculty'], true) && in_array($newStatus, ['active', 'inactive'], true)) {
        $uStmt = $db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $uStmt->execute([$newRole, $newStatus, $targetUserId]);
        record_audit('user_role_updated', 'users', $targetUserId, "Updated user {$targetUserId} role to {$newRole}, status to {$newStatus}");
        set_flash('success', 'User updated successfully.');
        redirect('admin/index.php');
    }
}

// Metrics
$metrics = [
    'departments' => (int)$db->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
    'users'       => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'faculties'   => (int)$db->query("SELECT COUNT(*) FROM faculty_profiles")->fetchColumn(),
    'pubs'        => (int)$db->query("SELECT COUNT(*) FROM publications")->fetchColumn(),
    'citations'   => (int)$db->query("SELECT COALESCE(SUM(total_citations), 0) FROM faculty_profiles")->fetchColumn(),
];

// Fetch Departments
$departments = $db->query("
    SELECT d.*, COUNT(fp.id) as faculty_count 
    FROM departments d
    LEFT JOIN faculty_profiles fp ON fp.department_id = d.id
    GROUP BY d.id
    ORDER BY d.name ASC
")->fetchAll();

// Fetch Recent Users
$users = $db->query("
    SELECT u.*, d.name as dept_name, fp.id as profile_id
    FROM users u
    LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    ORDER BY u.id DESC
    LIMIT 15
")->fetchAll();

// Fetch Audit Logs
$logs = $db->query("
    SELECT al.*, u.full_name as actor_name, u.email as actor_email
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.id DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'Super Admin Console';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-slate-900 text-white py-10 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-mono font-bold text-amber-300 uppercase tracking-widest">Institutional Oversight</span>
                <h1 class="text-2xl sm:text-3xl font-bold font-serif-title mt-1">Super Administration Console</h1>
                <p class="text-xs text-slate-300 mt-1">
                    System configuration, departmental management, user roles, and audit trail for ITER Research Portal.
                </p>
            </div>
            <div>
                <a href="<?= url('assistant/index.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 border border-slate-700 transition">
                    <i class="fa-solid fa-users-gear text-xs"></i>
                    <span>Manage Any Faculty as Assistant</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">

    <!-- KPI Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono uppercase text-slate-400 font-bold block">Departments</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= $metrics['departments'] ?></div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono uppercase text-slate-400 font-bold block">Registered Users</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= $metrics['users'] ?></div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono uppercase text-slate-400 font-bold block">Faculty Profiles</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= $metrics['faculties'] ?></div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono uppercase text-slate-400 font-bold block">Total Publications</span>
            <div class="text-2xl font-bold font-mono text-emerald-700 mt-1"><?= $metrics['pubs'] ?></div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono uppercase text-slate-400 font-bold block">Indexed Citations</span>
            <div class="text-2xl font-bold font-mono text-blue-700 mt-1"><?= number_format($metrics['citations']) ?></div>
        </div>
    </div>

    <!-- Section 1: User & Role Management -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200">
            <h2 class="font-bold text-slate-900 text-base font-serif-title">User Accounts & Role Permissions</h2>
            <p class="text-xs text-slate-500 mt-0.5">Control roles (Super Admin, Assistant / Delegate, Faculty) and account activation</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-slate-200">
                <thead class="bg-slate-50 text-slate-500 font-mono text-[10px] uppercase">
                    <tr>
                        <th class="py-3 px-4 font-semibold">User</th>
                        <th class="py-3 px-4 font-semibold">Email</th>
                        <th class="py-3 px-4 font-semibold">Department</th>
                        <th class="py-3 px-4 font-semibold">Current Role</th>
                        <th class="py-3 px-4 font-semibold">Status</th>
                        <th class="py-3 px-4 font-semibold text-right">Update Permissions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php foreach ($users as $usr): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4 font-semibold text-slate-900">
                                <?= e($usr['full_name']) ?>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-500">
                                <?= e($usr['email']) ?>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <?= e($usr['dept_name'] ?? '—') ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded font-mono text-[10px] uppercase font-bold <?= $usr['role'] === 'super_admin' ? 'bg-rose-100 text-rose-800' : ($usr['role'] === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') ?>">
                                    <?= e(str_replace('_', ' ', $usr['role'])) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center gap-1 text-[11px] <?= $usr['status'] === 'active' ? 'text-emerald-700 font-semibold' : 'text-slate-400' ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $usr['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                    <span class="capitalize"><?= e($usr['status']) ?></span>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <form action="<?= url('admin/index.php') ?>" method="POST" class="inline-flex items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="target_user_id" value="<?= $usr['id'] ?>">

                                    <select name="new_role" class="px-2 py-1 bg-slate-50 border border-slate-200 rounded text-[11px]">
                                        <option value="faculty" <?= $usr['role'] === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                        <option value="admin" <?= $usr['role'] === 'admin' ? 'selected' : '' ?>>Assistant (Admin)</option>
                                        <option value="super_admin" <?= $usr['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                    </select>

                                    <select name="new_status" class="px-2 py-1 bg-slate-50 border border-slate-200 rounded text-[11px]">
                                        <option value="active" <?= $usr['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $usr['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>

                                    <button type="submit" class="px-2.5 py-1 rounded bg-slate-800 text-white font-semibold text-[11px] hover:bg-slate-900 transition">
                                        Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Department Management & Creation -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-bold text-slate-900 text-base font-serif-title mb-4">ITER Academic Departments (<?= count($departments) ?>)</h2>
            <div class="divide-y divide-slate-100 text-xs">
                <?php foreach ($departments as $d): ?>
                    <div class="py-3 flex items-center justify-between gap-4">
                        <div>
                            <span class="inline-block px-2 py-0.5 rounded bg-iter-50 text-iter-900 font-mono font-bold text-[11px] mr-2">
                                <?= e($d['code']) ?>
                            </span>
                            <span class="font-bold text-slate-800"><?= e($d['name']) ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-slate-500 font-mono text-[11px]"><?= (int)$d['faculty_count'] ?> faculty</span>
                            <a href="<?= url('directory.php?dept=' . urlencode($d['code'])) ?>" target="_blank" class="text-iter-700 hover:underline">
                                Browse
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-bold text-slate-900 text-base font-serif-title mb-3">Add Department</h2>
            <form action="<?= url('admin/index.php') ?>" method="POST" class="space-y-4 text-xs">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_dept">

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Department Code</label>
                    <input type="text" name="code" required placeholder="e.g. AI-ML / DS / BIOTECH"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono uppercase">
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Department Name</label>
                    <input type="text" name="name" required placeholder="e.g. Artificial Intelligence & Data Science"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="3" placeholder="Department overview..."
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 bg-iter-800 hover:bg-iter-900 text-white rounded-xl font-semibold shadow-sm transition">
                    Create Department
                </button>
            </form>
        </div>
    </div>

    <!-- Section 3: Audit Trail -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-bold text-slate-900 text-base font-serif-title mb-4 flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-iter-700"></i>
            <span>System Audit Trail</span>
        </h2>

        <div class="divide-y divide-slate-100 text-xs">
            <?php foreach ($logs as $log): ?>
                <div class="py-2.5 flex items-start justify-between gap-4 font-mono">
                    <div>
                        <span class="text-slate-900 font-bold"><?= e($log['action']) ?></span>
                        <?php if (!empty($log['actor_name'])): ?>
                            <span class="text-slate-500 font-sans">by <?= e($log['actor_name']) ?> (<?= e($log['actor_email']) ?>)</span>
                        <?php endif; ?>
                        <?php if (!empty($log['details'])): ?>
                            <p class="text-[11px] text-slate-600 font-sans mt-0.5"><?= e($log['details']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right flex-shrink-0 text-[11px] text-slate-400">
                        <span><?= e($log['created_at']) ?></span>
                        <span class="block text-[10px] text-slate-400"><?= e($log['ip_address'] ?? '') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
