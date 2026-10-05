<?php
/**
 * Super Administrator Central Management Console
 * Design: Oxford-Ivy Modernity × Swiss Academic Editorial
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

<!-- Institutional Oversight Masthead -->
<div class="bg-oxford-950 text-white py-10 border-b border-oxford-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-[4px] bg-academic-gold/15 text-academic-gold border border-academic-gold/30 text-[11px] font-mono uppercase tracking-wider font-bold mb-2">
                    <i class="fa-solid fa-shield-halved text-[10px]"></i>
                    Institutional Oversight
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold font-serif-title tracking-tight text-white">Super Administration Console</h1>
                <p class="text-xs text-slate-300 mt-1 max-w-2xl font-sans">
                    System configuration, departmental management, user accounts, and immutable audit logging for the institutional repository.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= url('assistant/index.php') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-[6px] bg-oxford-900 hover:bg-oxford-800 text-slate-200 border border-oxford-800 hover:border-oxford-700 text-xs font-semibold shadow-sm transition">
                    <i class="fa-solid fa-users-gear text-academic-gold text-xs"></i>
                    <span>Manage Faculty as Assistant</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">

    <!-- KPI Stats Ribbon -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="academic-card p-5 bg-white">
            <span class="text-[11px] font-mono uppercase tracking-wider text-slate-500 font-bold block">Departments</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= $metrics['departments'] ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block">Active faculties</span>
        </div>
        <div class="academic-card p-5 bg-white">
            <span class="text-[11px] font-mono uppercase tracking-wider text-slate-500 font-bold block">Registered Users</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= $metrics['users'] ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block">System accounts</span>
        </div>
        <div class="academic-card p-5 bg-white">
            <span class="text-[11px] font-mono uppercase tracking-wider text-slate-500 font-bold block">Faculty Profiles</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= $metrics['faculties'] ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block">Public scholar pages</span>
        </div>
        <div class="academic-card p-5 bg-white">
            <span class="text-[11px] font-mono uppercase tracking-wider text-slate-500 font-bold block">Publications</span>
            <div class="text-2xl font-bold font-mono text-oxford-800 mt-1"><?= $metrics['pubs'] ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block">Indexed records</span>
        </div>
        <div class="academic-card p-5 bg-white col-span-2 sm:col-span-1">
            <span class="text-[11px] font-mono uppercase tracking-wider text-slate-500 font-bold block">Indexed Citations</span>
            <div class="text-2xl font-bold font-mono text-academic-gold mt-1"><?= number_format($metrics['citations']) ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block">Self-reported total</span>
        </div>
    </div>

    <!-- Section 1: User Accounts & Role Permissions -->
    <div class="academic-card overflow-hidden bg-white">
        <div class="p-6 border-b border-slate-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="font-bold text-slate-900 text-lg font-serif-title">User Accounts & Role Permissions</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Control administrative roles, delegate permissions, and account activation states</p>
                </div>
                <div class="text-xs font-mono text-slate-400">
                    Showing latest <?= count($users) ?> accounts
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="academic-table w-full text-left text-xs">
                <thead>
                    <tr>
                        <th class="py-3 px-4 font-semibold">User</th>
                        <th class="py-3 px-4 font-semibold">Email</th>
                        <th class="py-3 px-4 font-semibold">Department</th>
                        <th class="py-3 px-4 font-semibold">Role</th>
                        <th class="py-3 px-4 font-semibold">Status</th>
                        <th class="py-3 px-4 font-semibold text-right">Update Permissions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php foreach ($users as $usr): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-4 font-semibold text-slate-900 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-oxford-100 text-oxford-800 flex items-center justify-center font-bold font-serif-title text-xs flex-shrink-0">
                                        <?= strtoupper(substr($usr['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-slate-900"><?= e($usr['full_name']) ?></div>
                                        <?php if (!empty($usr['profile_id'])): ?>
                                            <a href="<?= url('profile.php?id=' . $usr['profile_id']) ?>" target="_blank" class="text-[11px] text-oxford-700 hover:text-oxford-900 inline-flex items-center gap-1">
                                                <span>View Profile</span>
                                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-500 whitespace-nowrap">
                                <?= e($usr['email']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                                <?= e($usr['dept_name'] ?? '—') ?>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <?php if ($usr['role'] === 'super_admin'): ?>
                                    <span class="inline-block px-2.5 py-0.5 rounded-[4px] font-mono text-[10px] uppercase font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                        Super Admin
                                    </span>
                                <?php elseif ($usr['role'] === 'admin'): ?>
                                    <span class="inline-block px-2.5 py-0.5 rounded-[4px] font-mono text-[10px] uppercase font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        Assistant (Admin)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2.5 py-0.5 rounded-[4px] font-mono text-[10px] uppercase font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        Faculty
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 text-xs <?= $usr['status'] === 'active' ? 'text-emerald-700 font-medium' : 'text-slate-400 font-medium' ?>">
                                    <span class="w-2 h-2 rounded-full <?= $usr['status'] === 'active' ? 'bg-emerald-600' : 'bg-slate-400' ?>"></span>
                                    <span class="capitalize"><?= e($usr['status']) ?></span>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <form action="<?= url('admin/index.php') ?>" method="POST" class="inline-flex items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="target_user_id" value="<?= $usr['id'] ?>">

                                    <select name="new_role" class="academic-input py-1 px-2 text-xs" aria-label="Select Role">
                                        <option value="faculty" <?= $usr['role'] === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                        <option value="admin" <?= $usr['role'] === 'admin' ? 'selected' : '' ?>>Assistant (Admin)</option>
                                        <option value="super_admin" <?= $usr['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                    </select>

                                    <select name="new_status" class="academic-input py-1 px-2 text-xs" aria-label="Select Status">
                                        <option value="active" <?= $usr['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $usr['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>

                                    <button type="submit" class="btn-academic-secondary py-1 px-3 text-xs font-semibold">
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
        <!-- Department List -->
        <div class="lg:col-span-2 academic-card p-6 bg-white">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
                <div>
                    <h2 class="font-bold text-slate-900 text-lg font-serif-title">Academic Departments</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Currently registered institutional departments (<?= count($departments) ?>)</p>
                </div>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                <?php foreach ($departments as $d): ?>
                    <div class="py-3.5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="academic-tag font-mono font-bold text-xs bg-slate-100 text-oxford-900 border border-slate-200">
                                <?= e($d['code']) ?>
                            </span>
                            <span class="font-semibold text-slate-900"><?= e($d['name']) ?></span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-slate-500 font-mono text-xs"><?= (int)$d['faculty_count'] ?> faculty</span>
                            <a href="<?= url('directory.php?dept=' . urlencode($d['code'])) ?>" target="_blank" class="text-oxford-700 hover:text-oxford-900 font-medium inline-flex items-center gap-1 underline underline-offset-2">
                                <span>Directory</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Add Department Form -->
        <div class="academic-card p-6 bg-white">
            <h2 class="font-bold text-slate-900 text-lg font-serif-title mb-1">Add Department</h2>
            <p class="text-xs text-slate-500 mb-5">Create a new academic unit or division</p>

            <form action="<?= url('admin/index.php') ?>" method="POST" class="space-y-4 text-xs">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_dept">

                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Department Code</label>
                    <input type="text" name="code" required placeholder="e.g. AI-ML / DS / BIOTECH"
                        class="academic-input w-full font-mono uppercase text-xs">
                    <span class="text-[11px] text-slate-400 mt-1 block">Unique identifier for URL filters and tags</span>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Department Name</label>
                    <input type="text" name="name" required placeholder="e.g. Artificial Intelligence & Data Science"
                        class="academic-input w-full text-xs">
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Description</label>
                    <textarea name="description" rows="3" placeholder="Department research scope and overview..."
                        class="academic-input w-full text-xs"></textarea>
                </div>

                <button type="submit" class="btn-academic-primary w-full py-2.5 text-xs font-semibold justify-center">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Create Department</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Section 3: Audit Trail -->
    <div class="academic-card p-6 bg-white">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
            <div>
                <h2 class="font-bold text-slate-900 text-lg font-serif-title flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-academic-gold text-base"></i>
                    <span>System Audit Trail</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Immutable record of security-critical administrative actions</p>
            </div>
            <span class="text-[11px] font-mono text-slate-400">Latest <?= count($logs) ?> entries</span>
        </div>

        <div class="divide-y divide-slate-100 text-xs">
            <?php foreach ($logs as $log): ?>
                <div class="py-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="academic-tag font-mono text-[10px] font-bold uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                <?= e($log['action']) ?>
                            </span>
                            <?php if (!empty($log['actor_name'])): ?>
                                <span class="text-slate-600 font-medium">by <?= e($log['actor_name']) ?> <span class="font-mono text-slate-400">(<?= e($log['actor_email']) ?>)</span></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($log['details'])): ?>
                            <p class="text-xs text-slate-600 bg-slate-50 p-2 rounded-[6px] border border-slate-100 font-sans"><?= e($log['details']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-left sm:text-right flex-shrink-0 font-mono text-[11px] text-slate-400">
                        <div><?= e($log['created_at']) ?></div>
                        <?php if (!empty($log['ip_address'])): ?>
                            <div class="text-[10px] text-slate-400 mt-0.5">IP: <?= e($log['ip_address']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
