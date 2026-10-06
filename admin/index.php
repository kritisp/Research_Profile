<?php
/**
 * Super Administrator Central Management Console
 * Design: Oxford-Ivy Modernity × Swiss Academic Editorial
 * Authority: Master CRIS Architecture
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('super_admin');

$db = Database::getConnection();
$currentTab = $_GET['tab'] ?? 'overview';
if (!in_array($currentTab, ['overview', 'users', 'departments', 'delegations', 'audit'], true)) {
    $currentTab = 'overview';
}

// =========================================================================
// POST HANDLERS (With strict CSRF & Audit Logging)
// =========================================================================

// 1. Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dept') {
    require_csrf();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!empty($code) && !empty($name)) {
        try {
            $stmt = $db->prepare("INSERT INTO departments (code, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $desc]);
            $newId = (int)$db->lastInsertId();
            record_audit('department_created', 'departments', $newId, "Created department {$name} ({$code})");
            set_flash('success', "Department {$name} ({$code}) created successfully.");
        } catch (Exception $e) {
            error_log("Error adding department: " . $e->getMessage());
            set_flash('danger', 'Failed to add department. The department code may already exist.');
        }
    } else {
        set_flash('warning', 'Department code and name are required.');
    }
    redirect('admin/index.php?tab=departments');
}

// 2. Edit Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_dept') {
    require_csrf();
    $deptId = (int)($_POST['dept_id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');

    if ($deptId > 0 && !empty($name)) {
        try {
            $stmt = $db->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $desc, $deptId]);
            record_audit('department_updated', 'departments', $deptId, "Updated department ID {$deptId} details");
            set_flash('success', "Department updated successfully.");
        } catch (Exception $e) {
            error_log("Error updating department: " . $e->getMessage());
            set_flash('danger', 'Failed to update department details.');
        }
    }
    redirect('admin/index.php?tab=departments');
}

// 3. Delete Department (Safe: blocked if faculty attached)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dept') {
    require_csrf();
    $deptId = (int)($_POST['dept_id'] ?? 0);

    if ($deptId > 0) {
        $cStmt = $db->prepare("SELECT COUNT(*) FROM faculty_profiles WHERE department_id = ?");
        $cStmt->execute([$deptId]);
        $facultyCount = (int)$cStmt->fetchColumn();

        if ($facultyCount > 0) {
            set_flash('danger', "Cannot delete department: {$facultyCount} active faculty profile(s) are affiliated with this unit. Reassign them first.");
        } else {
            try {
                $dStmt = $db->prepare("DELETE FROM departments WHERE id = ?");
                $dStmt->execute([$deptId]);
                record_audit('department_deleted', 'departments', $deptId, "Deleted empty department ID {$deptId}");
                set_flash('success', "Department deleted successfully.");
            } catch (Exception $e) {
                error_log("Error deleting department: " . $e->getMessage());
                set_flash('danger', 'Failed to remove department.');
            }
        }
    }
    redirect('admin/index.php?tab=departments');
}

// 4. Update User Role & Account Status (With self-demotion / self-deactivation guard)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    require_csrf();
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $newRole      = $_POST['new_role'] ?? '';
    $newStatus    = $_POST['new_status'] ?? '';
    $retTab       = $_POST['return_tab'] ?? 'users';

    if ($targetUserId === user_id() && ($newRole !== 'super_admin' || $newStatus !== 'active')) {
        set_flash('warning', 'Security Safeguard: You cannot demote or deactivate your own active Super Administrator account.');
        redirect('admin/index.php?tab=' . urlencode($retTab));
    }

    if ($targetUserId > 0 && in_array($newRole, ['super_admin', 'admin', 'faculty'], true) && in_array($newStatus, ['active', 'inactive'], true)) {
        try {
            $uStmt = $db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
            $uStmt->execute([$newRole, $newStatus, $targetUserId]);
            record_audit('user_role_updated', 'users', $targetUserId, "Updated user {$targetUserId} role to {$newRole}, status to {$newStatus}");
            set_flash('success', "User permissions updated successfully.");
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            set_flash('danger', 'Failed to update user record.');
        }
    }
    redirect('admin/index.php?tab=' . urlencode($retTab));
}

// 5. Revoke Delegation Relationship
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_delegation') {
    require_csrf();
    $delId = (int)($_POST['delegation_id'] ?? 0);

    if ($delId > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM faculty_delegates WHERE id = ?");
            $stmt->execute([$delId]);
            record_audit('delegate_revoked', 'faculty_delegates', $delId, "Revoked delegation relationship ID {$delId} via Super Admin Console");
            set_flash('success', "Delegation relationship revoked successfully.");
        } catch (Exception $e) {
            error_log("Error revoking delegation: " . $e->getMessage());
            set_flash('danger', 'Failed to revoke delegation.');
        }
    }
    redirect('admin/index.php?tab=delegations');
}

// 6. Create New User / Assistant directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    require_csrf();
    $name     = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'admin';
    $status   = $_POST['status'] ?? 'active';

    if (empty($name) || empty($email) || empty($password)) {
        set_flash('danger', 'Name, email, and password are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please provide a valid email address.');
    } elseif (!in_array($role, ['faculty', 'admin', 'super_admin'], true)) {
        set_flash('danger', 'Invalid role selected.');
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        set_flash('danger', 'Invalid status selected.');
    } else {
        try {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                set_flash('danger', 'A user account with this email address already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hash, $role, $status]);
                $newUserId = (int)$db->lastInsertId();
                record_audit('user_created', 'users', $newUserId, "Created user {$name} ({$email}) with role {$role}");
                set_flash('success', "Account for {$name} ({$role}) created successfully.");
            }
        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            set_flash('danger', 'Failed to create user account.');
        }
    }
    redirect('admin/index.php?tab=users');
}

// =========================================================================
// DATA QUERIES & METRICS
// =========================================================================

// Operational Metrics
$metrics = [
    'departments' => (int)$db->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
    'users'       => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users'=> (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'inactive_users'=> (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn(),
    'faculties'   => (int)$db->query("SELECT COUNT(*) FROM faculty_profiles")->fetchColumn(),
    'pubs'        => (int)$db->query("SELECT COUNT(*) FROM publications")->fetchColumn(),
    'citations'   => (int)$db->query("SELECT COALESCE(SUM(total_citations), 0) FROM faculty_profiles")->fetchColumn(),
    'grants_lakhs'=> (float)$db->query("SELECT COALESCE(SUM(amount_lakhs), 0) FROM projects")->fetchColumn(),
    'delegations' => (int)$db->query("SELECT COUNT(*) FROM faculty_delegates")->fetchColumn(),
];

// Profile Completeness Scoring Function
function compute_completeness(array $u): int {
    if (empty($u['profile_id'])) {
        return -1; // Not a faculty profile
    }
    $score = 0;
    if (!empty($u['bio'])) $score += 20;
    if (!empty($u['research_interests'])) $score += 15;
    if (!empty($u['cabin']) || !empty($u['phone'])) $score += 10;
    if (!empty($u['orcid_id']) || !empty($u['google_scholar_url']) || !empty($u['scopus_id'])) $score += 15;
    if (!empty($u['pub_count']) && (int)$u['pub_count'] > 0) $score += 20;
    if (!empty($u['edu_count']) && (int)$u['edu_count'] > 0) $score += 20;
    return min(100, $score);
}

// TAB DATA PREPARATION

// 1. Departments List
$departments = $db->query("
    SELECT d.*, COUNT(fp.id) as faculty_count 
    FROM departments d
    LEFT JOIN faculty_profiles fp ON fp.department_id = d.id
    GROUP BY d.id
    ORDER BY d.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Users & Access Management (With Filtering & Pagination)
$userSearch = trim($_GET['q'] ?? '');
$filterRole = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterDept = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
$filterCompleteness = trim($_GET['comp'] ?? '');

$userPage = max(1, (int)($_GET['page'] ?? 1));
$userLimit = 15;
$userOffset = ($userPage - 1) * $userLimit;

$userWhere = ["1=1"];
$userParams = [];

if (!empty($userSearch)) {
    $userWhere[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $userParams[] = "%{$userSearch}%";
    $userParams[] = "%{$userSearch}%";
}
if (!empty($filterRole) && in_array($filterRole, ['super_admin', 'admin', 'faculty'], true)) {
    $userWhere[] = "u.role = ?";
    $userParams[] = $filterRole;
}
if (!empty($filterStatus) && in_array($filterStatus, ['active', 'inactive'], true)) {
    $userWhere[] = "u.status = ?";
    $userParams[] = $filterStatus;
}
if ($filterDept > 0) {
    $userWhere[] = "fp.department_id = ?";
    $userParams[] = $filterDept;
}

$userWhereSql = implode(" AND ", $userWhere);

// Count total matching users
$countStmt = $db->prepare("
    SELECT COUNT(u.id)
    FROM users u
    LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
    WHERE {$userWhereSql}
");
$countStmt->execute($userParams);
$totalFilteredUsers = (int)$countStmt->fetchColumn();
$totalUserPages = max(1, (int)ceil($totalFilteredUsers / $userLimit));

// Fetch paginated users with profile & completeness indicators
$userSql = "
    SELECT u.*, fp.id as profile_id, fp.slug, fp.bio, fp.research_interests, fp.cabin, fp.phone,
           fp.orcid_id, fp.google_scholar_url, fp.scopus_id, d.name as dept_name, d.code as dept_code,
           (SELECT COUNT(*) FROM publications WHERE faculty_profile_id = fp.id) as pub_count,
           (SELECT COUNT(*) FROM education WHERE faculty_profile_id = fp.id) as edu_count
    FROM users u
    LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    WHERE {$userWhereSql}
    ORDER BY u.id DESC
    LIMIT {$userLimit} OFFSET {$userOffset}
";
$uStmt = $db->prepare($userSql);
$uStmt->execute($userParams);
$usersList = $uStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Delegations Query
$delegations = $db->query("
    SELECT fd.id as delegation_id, fd.created_at as granted_date,
           del_u.id as delegate_user_id, del_u.full_name as delegate_name, del_u.email as delegate_email, del_u.role as delegate_role,
           fac_u.id as faculty_user_id, fac_u.full_name as faculty_name, fac_u.email as faculty_email,
           fp.id as profile_id, fp.slug as profile_slug, fp.designation, d.name as dept_name
    FROM faculty_delegates fd
    JOIN users del_u ON fd.delegate_user_id = del_u.id
    JOIN users fac_u ON fd.faculty_user_id = fac_u.id
    JOIN faculty_profiles fp ON fp.user_id = fac_u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    ORDER BY fd.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Audit Trail (Searchable & Filterable)
$auditSearch = trim($_GET['audit_q'] ?? '');
$auditAction = trim($_GET['audit_action'] ?? '');
$auditPage = max(1, (int)($_GET['audit_page'] ?? 1));
$auditLimit = 20;
$auditOffset = ($auditPage - 1) * $auditLimit;

$auditWhere = ["1=1"];
$auditParams = [];

if (!empty($auditSearch)) {
    $auditWhere[] = "(u.full_name LIKE ? OR u.email LIKE ? OR al.details LIKE ?)";
    $auditParams[] = "%{$auditSearch}%";
    $auditParams[] = "%{$auditSearch}%";
    $auditParams[] = "%{$auditSearch}%";
}
if (!empty($auditAction)) {
    $auditWhere[] = "al.action = ?";
    $auditParams[] = $auditAction;
}

$auditWhereSql = implode(" AND ", $auditWhere);

$auditCountStmt = $db->prepare("
    SELECT COUNT(al.id)
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE {$auditWhereSql}
");
$auditCountStmt->execute($auditParams);
$totalAuditLogs = (int)$auditCountStmt->fetchColumn();
$totalAuditPages = max(1, (int)ceil($totalAuditLogs / $auditLimit));

$auditSql = "
    SELECT al.*, u.full_name as actor_name, u.email as actor_email, u.role as actor_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE {$auditWhereSql}
    ORDER BY al.id DESC
    LIMIT {$auditLimit} OFFSET {$auditOffset}
";
$aStmt = $db->prepare($auditSql);
$aStmt->execute($auditParams);
$auditLogs = $aStmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct actions for filter dropdown
$distinctActions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Super Admin Console — Institutional Research System';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Institutional Oversight Header -->
<div class="bg-oxford-navy text-white border-b border-scholar-border py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-[4px] bg-academic-gold/15 text-academic-gold border border-academic-gold/30 text-[11px] font-mono uppercase tracking-wider font-bold mb-2">
                    <i class="fa-solid fa-shield-halved text-[10px]"></i>
                    Central Institutional Oversight
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold font-serif leading-tight text-white">Super Administration Console</h1>
                <p class="text-xs text-slate-300 mt-1 max-w-2xl font-sans">
                    Governance console for user credentials, role enforcement, academic units, assistant delegation, and system-wide immutable audit trail.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="<?= url('assistant/index.php') ?>" class="btn-academic-secondary text-xs !py-2 !px-3 shadow-xs">
                    <i class="fa-solid fa-users-gear text-xs"></i>
                    <span>Assistant Portal</span>
                </a>
                <a href="<?= url('directory.php') ?>" target="_blank" class="btn-academic-secondary text-xs !py-2 !px-3 shadow-xs">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    <span>Public Directory</span>
                </a>
            </div>
        </div>

        <!-- Super Admin Sub-Navigation Rail -->
        <nav class="flex items-center gap-1 mt-6 pt-4 border-t border-slate-700/60 overflow-x-auto text-xs font-semibold scrollbar-none" aria-label="Console navigation">
            <a href="?tab=overview" 
               class="px-3.5 py-2 rounded-[6px] transition flex items-center gap-2 <?= $currentTab === 'overview' ? 'bg-white text-oxford-navy font-bold shadow-xs' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-chart-pie text-[11px]"></i>
                <span>Overview</span>
            </a>
            <a href="?tab=users" 
               class="px-3.5 py-2 rounded-[6px] transition flex items-center gap-2 <?= $currentTab === 'users' ? 'bg-white text-oxford-navy font-bold shadow-xs' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-users text-[11px]"></i>
                <span>Users & Access</span>
                <span class="px-1.5 py-0.2 rounded-full font-mono text-[10px] <?= $currentTab === 'users' ? 'bg-slate-100 text-oxford-navy' : 'bg-slate-800 text-slate-300' ?>"><?= $metrics['users'] ?></span>
            </a>
            <a href="?tab=departments" 
               class="px-3.5 py-2 rounded-[6px] transition flex items-center gap-2 <?= $currentTab === 'departments' ? 'bg-white text-oxford-navy font-bold shadow-xs' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-building-columns text-[11px]"></i>
                <span>Departments</span>
                <span class="px-1.5 py-0.2 rounded-full font-mono text-[10px] <?= $currentTab === 'departments' ? 'bg-slate-100 text-oxford-navy' : 'bg-slate-800 text-slate-300' ?>"><?= $metrics['departments'] ?></span>
            </a>
            <a href="?tab=delegations" 
               class="px-3.5 py-2 rounded-[6px] transition flex items-center gap-2 <?= $currentTab === 'delegations' ? 'bg-white text-oxford-navy font-bold shadow-xs' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-handshake-angle text-[11px]"></i>
                <span>Delegations</span>
                <span class="px-1.5 py-0.2 rounded-full font-mono text-[10px] <?= $currentTab === 'delegations' ? 'bg-slate-100 text-oxford-navy' : 'bg-slate-800 text-slate-300' ?>"><?= $metrics['delegations'] ?></span>
            </a>
            <a href="?tab=audit" 
               class="px-3.5 py-2 rounded-[6px] transition flex items-center gap-2 <?= $currentTab === 'audit' ? 'bg-white text-oxford-navy font-bold shadow-xs' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-clock-rotate-left text-[11px]"></i>
                <span>Audit Trail</span>
            </a>
        </nav>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- ================================================================= -->
    <!-- TAB 1: OVERVIEW & SYSTEM METRICS                                 -->
    <!-- ================================================================= -->
    <?php if ($currentTab === 'overview'): ?>
        
        <!-- KPI Ribbon -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 sm:gap-4">
            <div class="academic-card p-4">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-bold block">Departments</span>
                <div class="text-2xl font-bold font-mono text-oxford-navy mt-1"><?= $metrics['departments'] ?></div>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Academic Units</span>
            </div>
            <div class="academic-card p-4">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-bold block">Total Users</span>
                <div class="text-2xl font-bold font-mono text-oxford-navy mt-1"><?= $metrics['users'] ?></div>
                <span class="text-[11px] text-emerald-700 mt-0.5 block font-medium"><?= $metrics['active_users'] ?> Active accounts</span>
            </div>
            <div class="academic-card p-4">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-bold block">Inactive</span>
                <div class="text-2xl font-bold font-mono <?= $metrics['inactive_users'] > 0 ? 'text-amber-700' : 'text-slate-400' ?> mt-1"><?= $metrics['inactive_users'] ?></div>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Deactivated accounts</span>
            </div>
            <div class="academic-card p-4">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-bold block">Faculty Profiles</span>
                <div class="text-2xl font-bold font-mono text-oxford-navy mt-1"><?= $metrics['faculties'] ?></div>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Verified Scholars</span>
            </div>
            <div class="academic-card p-4">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-bold block">Publications</span>
                <div class="text-2xl font-bold font-mono text-oxford-blue mt-1"><?= $metrics['pubs'] ?></div>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Indexed Articles</span>
            </div>
            <div class="academic-card p-4">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-bold block">Funded Grants</span>
                <div class="text-xl font-bold font-mono text-emerald-800 mt-1"><?= format_currency_lakhs($metrics['grants_lakhs']) ?></div>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Sponsored Projects</span>
            </div>
            <div class="academic-card p-4 col-span-2 sm:col-span-1">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-bold block">Total Citations</span>
                <div class="text-2xl font-bold font-mono text-academic-gold mt-1"><?= number_format($metrics['citations']) ?></div>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Self-reported total</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Quick Management Shortcuts & System Health -->
            <div class="academic-card p-6 space-y-4">
                <h3 class="font-serif font-bold text-base text-oxford-navy flex items-center gap-2">
                    <i class="fa-solid fa-compass text-academic-gold"></i>
                    <span>Quick Administration Actions</span>
                </h3>
                <p class="text-xs text-slate-600 leading-relaxed font-sans">
                    Fast paths to critical operations across user permissions, academic unit structure, and research delegates.
                </p>

                <div class="space-y-2 pt-2">
                    <a href="?tab=users&status=inactive" class="flex items-center justify-between p-3 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-scholar-border text-xs text-oxford-navy font-semibold transition">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-user-xmark text-amber-600"></i>
                            <span>Review Inactive Accounts (<?= $metrics['inactive_users'] ?>)</span>
                        </span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                    </a>

                    <a href="?tab=departments" class="flex items-center justify-between p-3 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-scholar-border text-xs text-oxford-navy font-semibold transition">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-plus text-oxford-blue"></i>
                            <span>Create New Academic Department</span>
                        </span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                    </a>

                    <a href="?tab=delegations" class="flex items-center justify-between p-3 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-scholar-border text-xs text-oxford-navy font-semibold transition">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-handshake-angle text-emerald-700"></i>
                            <span>Active Assistant Delegations (<?= $metrics['delegations'] ?>)</span>
                        </span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                    </a>

                    <a href="?tab=audit" class="flex items-center justify-between p-3 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-scholar-border text-xs text-oxford-navy font-semibold transition">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-600"></i>
                            <span>View Security Audit Stream</span>
                        </span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                    </a>
                </div>
            </div>

            <!-- Recent Security Audit Highlights -->
            <div class="lg:col-span-2 academic-card p-6">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-scholar-border">
                    <div>
                        <h3 class="font-serif font-bold text-base text-oxford-navy flex items-center gap-2">
                            <i class="fa-solid fa-shield-halved text-academic-gold"></i>
                            <span>Recent Institutional Audit Stream</span>
                        </h3>
                        <p class="text-xs text-scholar-muted mt-0.5">Real-time trace of administrative, role, and profile mutations</p>
                    </div>
                    <a href="?tab=audit" class="text-xs font-semibold text-oxford-blue hover:underline">
                        View All
                    </a>
                </div>

                <div class="divide-y divide-slate-100 text-xs">
                    <?php 
                        $quickLogs = array_slice($auditLogs, 0, 6);
                        if (empty($quickLogs)): 
                    ?>
                        <p class="py-6 text-center text-slate-400 text-xs">No audit events logged yet.</p>
                    <?php else: ?>
                        <?php foreach ($quickLogs as $l): ?>
                            <div class="py-2.5 flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="academic-tag font-mono text-[9px] uppercase font-bold">
                                            <?= e($l['action']) ?>
                                        </span>
                                        <span class="font-semibold text-oxford-navy">
                                            <?= e($l['actor_name'] ?? 'System') ?>
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 mt-1"><?= e($l['details']) ?></p>
                                </div>
                                <span class="font-mono text-[10px] text-slate-400 flex-shrink-0"><?= date('M d, H:i', strtotime($l['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    <?php endif; ?>

    <!-- ================================================================= -->
    <!-- TAB 2: USERS & ACCESS GOVERNANCE                                 -->
    <!-- ================================================================= -->
    <?php if ($currentTab === 'users'): ?>
        
        <div class="academic-card overflow-hidden">
            
            <!-- Filters & Search Toolbar -->
            <div class="p-5 border-b border-scholar-border bg-slate-50/70">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="font-serif font-bold text-lg text-oxford-navy">User Accounts & Role Permissions</h2>
                        <p class="text-xs text-scholar-muted mt-0.5">Search accounts, manage authorization roles, toggle active status, and audit profile readiness.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-mono text-slate-500">
                            Total matching: <strong><?= $totalFilteredUsers ?></strong>
                        </span>
                        <button type="button" onclick="document.getElementById('createUserModal').classList.remove('hidden')" class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                            <i class="fa-solid fa-user-plus text-[11px]"></i>
                            <span>Create Account</span>
                        </button>
                    </div>
                </div>

                <!-- Create User Modal -->
                <div id="createUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-oxford-navy/50 backdrop-blur-xs hidden p-4">
                    <div class="academic-card max-w-md w-full p-6 bg-white shadow-2xl relative animate-fade-in border border-scholar-border rounded-xl">
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-scholar-border">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-oxford-navy text-white flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-user-plus"></i>
                                </div>
                                <h3 class="font-serif font-bold text-base text-oxford-navy">Create User Account</h3>
                            </div>
                            <button type="button" onclick="document.getElementById('createUserModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>

                        <form method="POST" action="<?= url('admin/index.php') ?>" class="space-y-4 text-xs">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="create_user">

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Full Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="full_name" required placeholder="e.g. John Doe / Dept Assistant" class="academic-input w-full text-xs">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Email Address <span class="text-rose-500">*</span></label>
                                <input type="email" name="email" required placeholder="e.g. assistant@institution.edu" class="academic-input w-full text-xs">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Initial Password <span class="text-rose-500">*</span></label>
                                <input type="password" name="password" required placeholder="Enter strong password" class="academic-input w-full text-xs">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Role <span class="text-rose-500">*</span></label>
                                    <select name="role" class="academic-input w-full text-xs">
                                        <option value="admin">Assistant (Admin)</option>
                                        <option value="faculty">Faculty</option>
                                        <option value="super_admin">Super Admin</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Account Status</label>
                                    <select name="status" class="academic-input w-full text-xs">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-scholar-border">
                                <button type="button" onclick="document.getElementById('createUserModal').classList.add('hidden')" class="btn-academic-secondary text-xs !py-1.5 !px-3">
                                    Cancel
                                </button>
                                <button type="submit" class="btn-academic-primary text-xs !py-1.5 !px-4">
                                    Create Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 text-xs">
                    <input type="hidden" name="tab" value="users">
                    
                    <!-- Search Input -->
                    <div class="lg:col-span-2 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" name="q" value="<?= e($userSearch) ?>"
                            placeholder="Search name or email address..."
                            class="academic-input pl-9 text-xs w-full">
                    </div>

                    <!-- Role Filter -->
                    <select name="role" class="academic-input text-xs">
                        <option value="">All Roles</option>
                        <option value="faculty" <?= $filterRole === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Assistant (Admin)</option>
                        <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>

                    <!-- Status Filter -->
                    <select name="status" class="academic-input text-xs">
                        <option value="">All Account Statuses</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                    </select>

                    <!-- Submit / Reset -->
                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn-academic-primary text-xs !py-2 !px-3.5 flex-1 justify-center">
                            Filter
                        </button>
                        <a href="?tab=users" class="btn-academic-secondary text-xs !py-2 !px-2.5" title="Reset Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Users Data Table -->
            <div class="overflow-x-auto">
                <table class="academic-table w-full text-left text-xs">
                    <thead>
                        <tr>
                            <th class="py-3 px-4">User & Profile</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Profile Completeness</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Update Permissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($usersList)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                    No accounts match the current search or filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usersList as $usr): ?>
                                <?php $comp = compute_completeness($usr); ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    
                                    <!-- User Name & Link -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-[6px] bg-slate-100 border border-scholar-border text-oxford-navy flex items-center justify-center font-bold font-serif text-xs flex-shrink-0">
                                                <?= strtoupper(substr($usr['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-oxford-navy"><?= e($usr['full_name']) ?></div>
                                                <?php if (!empty($usr['profile_id'])): ?>
                                                    <a href="<?= url('researchers/' . (!empty($usr['slug']) ? $usr['slug'] : $usr['profile_id'])) ?>" target="_blank" class="text-[11px] text-oxford-blue hover:underline inline-flex items-center gap-1 font-medium">
                                                        <span>Public Profile</span>
                                                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Email -->
                                    <td class="py-3.5 px-4 font-mono text-slate-600 whitespace-nowrap">
                                        <?= e($usr['email']) ?>
                                    </td>

                                    <!-- Department -->
                                    <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                                        <?= e($usr['dept_name'] ?? '—') ?>
                                    </td>

                                    <!-- Current Role Badge -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if ($usr['role'] === 'super_admin'): ?>
                                            <span class="academic-tag bg-rose-50 text-rose-800 border-rose-200 font-mono text-[10px] font-bold uppercase">
                                                Super Admin
                                            </span>
                                        <?php elseif ($usr['role'] === 'admin'): ?>
                                            <span class="academic-tag academic-tag-gold font-mono text-[10px] font-bold uppercase">
                                                Assistant (Admin)
                                            </span>
                                        <?php else: ?>
                                            <span class="academic-tag font-mono text-[10px] font-bold uppercase">
                                                Faculty
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Profile Completeness Indicator -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <?php if ($comp < 0): ?>
                                            <span class="text-slate-400 font-mono text-[11px]">System Account</span>
                                        <?php else: ?>
                                            <div class="flex items-center gap-2">
                                                <div class="w-20 bg-slate-100 rounded-full h-1.5 overflow-hidden border border-slate-200">
                                                    <div class="h-full <?= $comp >= 80 ? 'bg-emerald-600' : ($comp >= 50 ? 'bg-academic-gold' : 'bg-rose-500') ?>" style="width: <?= $comp ?>%;"></div>
                                                </div>
                                                <span class="font-mono text-[11px] font-bold <?= $comp >= 80 ? 'text-emerald-700' : ($comp >= 50 ? 'text-slate-700' : 'text-rose-600') ?>">
                                                    <?= $comp ?>%
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold <?= $usr['status'] === 'active' ? 'text-emerald-700' : 'text-slate-400' ?>">
                                            <span class="w-2 h-2 rounded-full <?= $usr['status'] === 'active' ? 'bg-emerald-600' : 'bg-slate-400' ?>"></span>
                                            <span class="capitalize"><?= e($usr['status']) ?></span>
                                        </span>
                                    </td>

                                    <!-- Action Form (With Self-Demotion Confirmation Safeguard) -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <form action="<?= url('admin/index.php') ?>" method="POST" class="inline-flex items-center gap-1.5"
                                              onsubmit="return confirm('Update role and account status for <?= addslashes(e($usr['full_name'])) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="target_user_id" value="<?= (int)$usr['id'] ?>">
                                            <input type="hidden" name="return_tab" value="users">

                                            <select name="new_role" class="academic-input !py-1 !px-2 text-xs" aria-label="Role for <?= e($usr['full_name']) ?>">
                                                <option value="faculty" <?= $usr['role'] === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                                <option value="admin" <?= $usr['role'] === 'admin' ? 'selected' : '' ?>>Assistant (Admin)</option>
                                                <option value="super_admin" <?= $usr['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                            </select>

                                            <select name="new_status" class="academic-input !py-1 !px-2 text-xs" aria-label="Status for <?= e($usr['full_name']) ?>">
                                                <option value="active" <?= $usr['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= $usr['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            </select>

                                            <button type="submit" class="btn-academic-secondary text-xs !py-1 !px-2.5 font-semibold shadow-xs">
                                                Save
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalUserPages > 1): ?>
                <div class="p-4 border-t border-scholar-border bg-slate-50/50 flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-mono">
                        Page <?= $userPage ?> of <?= $totalUserPages ?>
                    </span>
                    <div class="flex items-center gap-1">
                        <?php if ($userPage > 1): ?>
                            <a href="?tab=users&page=<?= $userPage - 1 ?>&q=<?= urlencode($userSearch) ?>&role=<?= urlencode($filterRole) ?>&status=<?= urlencode($filterStatus) ?>" class="btn-academic-secondary !py-1 !px-2.5 text-xs">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($userPage < $totalUserPages): ?>
                            <a href="?tab=users&page=<?= $userPage + 1 ?>&q=<?= urlencode($userSearch) ?>&role=<?= urlencode($filterRole) ?>&status=<?= urlencode($filterStatus) ?>" class="btn-academic-secondary !py-1 !px-2.5 text-xs">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>

    <!-- ================================================================= -->
    <!-- TAB 3: ACADEMIC DEPARTMENTS MANAGEMENT                           -->
    <!-- ================================================================= -->
    <?php if ($currentTab === 'departments'): ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Department List -->
            <div class="lg:col-span-2 academic-card p-6">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-scholar-border">
                    <div>
                        <h2 class="font-serif font-bold text-lg text-oxford-navy">Academic Departments</h2>
                        <p class="text-xs text-scholar-muted mt-0.5">Established academic divisions and constituent departmental units (<?= count($departments) ?>)</p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 text-xs">
                    <?php foreach ($departments as $d): ?>
                        <div class="py-4 space-y-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <span class="academic-tag font-mono font-bold text-xs bg-slate-100 text-oxford-navy border border-scholar-border">
                                        <?= e($d['code']) ?>
                                    </span>
                                    <span class="font-serif font-bold text-sm text-oxford-navy"><?= e($d['name']) ?></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-slate-500 font-mono text-xs"><?= (int)$d['faculty_count'] ?> faculty</span>
                                    <a href="<?= url('directory.php?dept=' . urlencode($d['code'])) ?>" target="_blank" class="text-oxford-blue hover:underline font-semibold inline-flex items-center gap-1">
                                        <span>View Roster</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                    </a>
                                </div>
                            </div>

                            <?php if (!empty($d['description'])): ?>
                                <p class="text-xs text-slate-600 leading-relaxed font-sans pl-1"><?= nl2br(e($d['description'])) ?></p>
                            <?php endif; ?>

                            <!-- Inline Edit / Safe Delete Form Drawer -->
                            <details class="text-[11px] pt-1">
                                <summary class="cursor-pointer text-oxford-blue hover:text-oxford-navy font-semibold inline-flex items-center gap-1 select-none">
                                    <i class="fa-solid fa-pen-to-square text-[10px]"></i> Edit Details
                                </summary>
                                <div class="mt-3 p-4 rounded-[8px] bg-slate-50 border border-scholar-border space-y-3">
                                    <form action="<?= url('admin/index.php') ?>" method="POST" class="space-y-3">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="edit_dept">
                                        <input type="hidden" name="dept_id" value="<?= (int)$d['id'] ?>">

                                        <div>
                                            <label class="block font-semibold text-oxford-navy mb-1">Department Name</label>
                                            <input type="text" name="name" required value="<?= e($d['name']) ?>" class="academic-input text-xs w-full">
                                        </div>

                                        <div>
                                            <label class="block font-semibold text-oxford-navy mb-1">Description / Research Scope</label>
                                            <textarea name="description" rows="2" class="academic-input text-xs w-full"><?= e($d['description']) ?></textarea>
                                        </div>

                                        <div class="flex items-center justify-between pt-1">
                                            <button type="submit" class="btn-academic-primary text-xs !py-1.5 !px-3 font-semibold">
                                                Update Department
                                            </button>
                                            <?php if ((int)$d['faculty_count'] === 0): ?>
                                                <button type="submit" form="delDeptForm_<?= (int)$d['id'] ?>" class="text-rose-700 hover:text-rose-900 font-semibold text-xs inline-flex items-center gap-1"
                                                        onclick="return confirm('Permanently remove this empty department?');">
                                                    <i class="fa-solid fa-trash text-[10px]"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="text-[10px] text-slate-400 font-mono" title="Cannot delete department with active faculty members">Protected (Has Faculty)</span>
                                            <?php endif; ?>
                                        </div>
                                    </form>

                                    <?php if ((int)$d['faculty_count'] === 0): ?>
                                        <form id="delDeptForm_<?= (int)$d['id'] ?>" action="<?= url('admin/index.php') ?>" method="POST" class="hidden">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_dept">
                                            <input type="hidden" name="dept_id" value="<?= (int)$d['id'] ?>">
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Add Department Form -->
            <div class="academic-card p-6">
                <h2 class="font-serif font-bold text-lg text-oxford-navy mb-1">Add Academic Department</h2>
                <p class="text-xs text-scholar-muted mb-5">Register a new academic division, school, or department.</p>

                <form action="<?= url('admin/index.php') ?>" method="POST" class="space-y-4 text-xs">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_dept">

                    <div>
                        <label class="block font-semibold text-oxford-navy mb-1.5 font-mono uppercase tracking-wider text-[11px]">
                            Department Code <span class="text-rose-600">*</span>
                        </label>
                        <input type="text" name="code" required placeholder="e.g. AI-ML / BIOTECH / MED"
                            class="academic-input w-full font-mono uppercase text-xs">
                        <span class="text-[10px] text-slate-400 mt-1 block">Short abbreviation for directory filtering and tags</span>
                    </div>

                    <div>
                        <label class="block font-semibold text-oxford-navy mb-1.5 font-mono uppercase tracking-wider text-[11px]">
                            Department Name <span class="text-rose-600">*</span>
                        </label>
                        <input type="text" name="name" required placeholder="e.g. Artificial Intelligence & Robotics"
                            class="academic-input w-full text-xs font-semibold">
                    </div>

                    <div>
                        <label class="block font-semibold text-oxford-navy mb-1.5 font-mono uppercase tracking-wider text-[11px]">
                            Description / Research Overview
                        </label>
                        <textarea name="description" rows="3" placeholder="Department research scope, laboratories, and specialized areas..."
                            class="academic-input w-full text-xs"></textarea>
                    </div>

                    <button type="submit" class="btn-academic-primary w-full py-2.5 text-xs font-semibold justify-center">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Create Department</span>
                    </button>
                </form>
            </div>

        </div>

    <?php endif; ?>

    <!-- ================================================================= -->
    <!-- TAB 4: DELEGATIONS (ASSISTANT -> FACULTY PROFILES MAPPING)        -->
    <!-- ================================================================= -->
    <?php if ($currentTab === 'delegations'): ?>
        
        <div class="academic-card overflow-hidden">
            <div class="p-6 border-b border-scholar-border bg-slate-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="font-serif font-bold text-lg text-oxford-navy">Active Profile Delegations</h2>
                    <p class="text-xs text-scholar-muted mt-0.5">Mapping of Department Research Assistants permitted to maintain scholar portfolios.</p>
                </div>
                <span class="academic-tag font-mono text-xs">
                    Total active grants: <strong><?= count($delegations) ?></strong>
                </span>
            </div>

            <?php if (empty($delegations)): ?>
                <div class="p-12 text-center text-slate-500 text-xs">
                    <i class="fa-solid fa-handshake-angle text-3xl text-slate-300 mb-2"></i>
                    <p class="font-serif text-sm font-bold text-oxford-navy">No active delegations recorded</p>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Faculty members can grant assistants profile edit access via their faculty dashboard under the Delegates tab.
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="academic-table w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th class="py-3 px-4">Authorized Delegate</th>
                                <th class="py-3 px-4">Delegate Role</th>
                                <th class="py-3 px-4">Managed Faculty Profile</th>
                                <th class="py-3 px-4">Department</th>
                                <th class="py-3 px-4">Authorized Date</th>
                                <th class="py-3 px-4 text-right">Revoke Access</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($delegations as $del): ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-semibold text-oxford-navy whitespace-nowrap">
                                        <div><?= e($del['delegate_name']) ?></div>
                                        <div class="text-[11px] font-mono text-slate-400 font-normal"><?= e($del['delegate_email']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="academic-tag font-mono text-[10px] uppercase font-bold bg-amber-50 text-amber-800 border-amber-200">
                                            <?= e($del['delegate_role']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap font-medium text-oxford-navy">
                                        <a href="<?= url('researchers/' . (!empty($del['profile_slug']) ? $del['profile_slug'] : $del['profile_id'])) ?>" target="_blank" class="text-oxford-blue hover:underline inline-flex items-center gap-1 font-semibold">
                                            <span><?= e($del['faculty_name']) ?></span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                        </a>
                                        <div class="text-[11px] text-slate-400"><?= e($del['designation'] ?? 'Faculty Scholar') ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                                        <?= e($del['dept_name'] ?? '—') ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-500 whitespace-nowrap">
                                        <?= date('M d, Y', strtotime($del['granted_date'])) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <form action="<?= url('admin/index.php') ?>" method="POST" class="inline m-0"
                                              onsubmit="return confirm('Revoke delegate access for <?= addslashes(e($del['delegate_name'])) ?> on <?= addslashes(e($del['faculty_name'])) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="revoke_delegation">
                                            <input type="hidden" name="delegation_id" value="<?= (int)$del['delegation_id'] ?>">
                                            <button type="submit" class="text-rose-700 hover:text-rose-900 font-semibold text-xs inline-flex items-center gap-1">
                                                <i class="fa-solid fa-ban text-[10px]"></i> Revoke
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <!-- ================================================================= -->
    <!-- TAB 5: AUDIT TRAIL LOGS                                          -->
    <!-- ================================================================= -->
    <?php if ($currentTab === 'audit'): ?>
        
        <div class="academic-card overflow-hidden">
            
            <!-- Audit Filter Toolbar -->
            <div class="p-5 border-b border-scholar-border bg-slate-50/70">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="font-serif font-bold text-lg text-oxford-navy">System Audit Trail</h2>
                        <p class="text-xs text-scholar-muted mt-0.5">Immutable event stream tracking security, permission alterations, and academic metadata transactions.</p>
                    </div>
                    <span class="text-xs font-mono text-slate-500">
                        Total logged: <strong><?= $totalAuditLogs ?></strong>
                    </span>
                </div>

                <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                    <input type="hidden" name="tab" value="audit">

                    <!-- Search Input -->
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" name="audit_q" value="<?= e($auditSearch) ?>"
                            placeholder="Search actor or details..."
                            class="academic-input pl-9 text-xs w-full">
                    </div>

                    <!-- Action Filter -->
                    <select name="audit_action" class="academic-input text-xs font-mono">
                        <option value="">All Logged Actions</option>
                        <?php foreach ($distinctActions as $act): ?>
                            <option value="<?= e($act) ?>" <?= $auditAction === $act ? 'selected' : '' ?>><?= e($act) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Filter / Reset -->
                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn-academic-primary text-xs !py-2 !px-3.5 flex-1 justify-center">
                            Filter Logs
                        </button>
                        <a href="?tab=audit" class="btn-academic-secondary text-xs !py-2 !px-2.5" title="Reset Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Audit Table -->
            <div class="overflow-x-auto">
                <table class="academic-table w-full text-left text-xs">
                    <thead>
                        <tr>
                            <th class="py-3 px-4">Timestamp</th>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Actor</th>
                            <th class="py-3 px-4">Transaction Details</th>
                            <th class="py-3 px-4 text-right">Client IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($auditLogs)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400 text-xs">
                                    No audit entries match the current filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                        <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="academic-tag font-mono text-[10px] uppercase font-bold">
                                            <?= e($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap font-medium text-oxford-navy">
                                        <div><?= e($log['actor_name'] ?? 'System') ?></div>
                                        <?php if (!empty($log['actor_email'])): ?>
                                            <div class="text-[10px] font-mono text-slate-400 font-normal"><?= e($log['actor_email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 max-w-md">
                                        <div class="font-sans leading-relaxed"><?= e($log['details'] ?? '—') ?></div>
                                        <?php if (!empty($log['target_type']) && !empty($log['target_id'])): ?>
                                            <span class="font-mono text-[10px] text-slate-400">Target: <?= e($log['target_type']) ?> #<?= (int)$log['target_id'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400 text-right whitespace-nowrap">
                                        <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalAuditPages > 1): ?>
                <div class="p-4 border-t border-scholar-border bg-slate-50/50 flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-mono">
                        Page <?= $auditPage ?> of <?= $totalAuditPages ?>
                    </span>
                    <div class="flex items-center gap-1">
                        <?php if ($auditPage > 1): ?>
                            <a href="?tab=audit&audit_page=<?= $auditPage - 1 ?>&audit_q=<?= urlencode($auditSearch) ?>&audit_action=<?= urlencode($auditAction) ?>" class="btn-academic-secondary !py-1 !px-2.5 text-xs">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($auditPage < $totalAuditPages): ?>
                            <a href="?tab=audit&audit_page=<?= $auditPage + 1 ?>&audit_q=<?= urlencode($auditSearch) ?>&audit_action=<?= urlencode($auditAction) ?>" class="btn-academic-secondary !py-1 !px-2.5 text-xs">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
