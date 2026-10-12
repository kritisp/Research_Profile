<?php
/**
 * Departmental Scholar — Choose / Assign Faculty to Portfolio
 * Dedicated page with search, department filtering, minimal profile metrics, and one-click assignment.
 * Supports Super Admin multi-admin delegation and Admin self-management.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'super_admin']);

$db = Database::getConnection();
$currentUserId = user_id();
$isSuperAdmin = has_role('super_admin');

// Target Admin ID for assignment: either explicitly passed by Super Admin, or defaulting to current user
$adminId = $currentUserId;
$targetAdminUser = null;
if ($isSuperAdmin && isset($_REQUEST['admin_id']) && (int)$_REQUEST['admin_id'] > 0) {
    $requestedAdminId = (int)$_REQUEST['admin_id'];
    $aChk = $db->prepare("SELECT id, full_name, email, role FROM users WHERE id = ? AND role IN ('admin', 'super_admin') AND status = 'active'");
    $aChk->execute([$requestedAdminId]);
    $targetAdminUser = $aChk->fetch(PDO::FETCH_ASSOC);
    if ($targetAdminUser) {
        $adminId = $requestedAdminId;
    }
}

// Handle Assign Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_faculty') {
    require_csrf();
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $assigneeAdminId = ($isSuperAdmin && !empty($_POST['admin_id'])) ? (int)$_POST['admin_id'] : $adminId;

    if ($targetUserId > 0 && $targetUserId !== $assigneeAdminId) {
        $uChk = $db->prepare("SELECT id, full_name FROM users WHERE id = ? AND status = 'active'");
        $uChk->execute([$targetUserId]);
        $targetUser = $uChk->fetch(PDO::FETCH_ASSOC);

        if ($targetUser) {
            // Check if this faculty is already assigned to any administrator
            $delChk = $db->prepare("
                SELECT fd.delegate_user_id, u.full_name as admin_name 
                FROM faculty_delegates fd 
                JOIN users u ON fd.delegate_user_id = u.id 
                WHERE fd.faculty_user_id = ?
            ");
            $delChk->execute([$targetUserId]);
            $existingDel = $delChk->fetch(PDO::FETCH_ASSOC);

            if ($existingDel) {
                if ((int)$existingDel['delegate_user_id'] === $assigneeAdminId) {
                    set_flash('info', "{$targetUser['full_name']} is already in this portfolio.");
                } elseif ($isSuperAdmin) {
                    // Super Admin is permitted to transfer / reassign
                    $db->beginTransaction();
                    $db->prepare("DELETE FROM faculty_delegates WHERE faculty_user_id = ?")->execute([$targetUserId]);
                    $ins = $db->prepare("INSERT INTO faculty_delegates (faculty_user_id, delegate_user_id, granted_by) VALUES (?, ?, ?)");
                    $ins->execute([$targetUserId, $assigneeAdminId, $currentUserId]);
                    $db->commit();
                    record_audit('portfolio_reassigned', 'faculty_delegates', $targetUserId, "Super Admin reassigned faculty {$targetUser['full_name']} from {$existingDel['admin_name']} to admin ID {$assigneeAdminId}");
                    set_flash('success', "Reassigned {$targetUser['full_name']} to administrator portfolio.");
                } else {
                    // Regular admin: strictly blocked from assigning an already-assigned faculty
                    set_flash('danger', "Cannot assign {$targetUser['full_name']}: Already assigned to {$existingDel['admin_name']}. Each faculty profile can only be assigned to one administrator.");
                }
            } else {
                $ins = $db->prepare("INSERT INTO faculty_delegates (faculty_user_id, delegate_user_id, granted_by) VALUES (?, ?, ?)");
                $ins->execute([$targetUserId, $assigneeAdminId, $currentUserId]);
                record_audit('portfolio_claimed', 'faculty_delegates', $targetUserId, "Assigned faculty {$targetUser['full_name']} to admin ID {$assigneeAdminId}");
                set_flash('success', "Added {$targetUser['full_name']} to management portfolio.");
            }
        }
    }
    $redir = 'assistant/choose_faculty.php';
    if ($isSuperAdmin && $adminId !== $currentUserId) {
        $redir .= "?admin_id={$adminId}";
    }
    redirect($redir);
}

// Handle Unassign Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unclaim_faculty') {
    require_csrf();
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $assigneeAdminId = ($isSuperAdmin && !empty($_POST['admin_id'])) ? (int)$_POST['admin_id'] : $adminId;

    if ($targetUserId > 0) {
        $del = $db->prepare("DELETE FROM faculty_delegates WHERE faculty_user_id = ? AND delegate_user_id = ?");
        $del->execute([$targetUserId, $assigneeAdminId]);
        record_audit('portfolio_unclaimed', 'faculty_delegates', $targetUserId, "Removed faculty {$targetUserId} from admin ID {$assigneeAdminId} portfolio");
        set_flash('info', "Faculty removed from management portfolio.");
    }
    $redir = 'assistant/choose_faculty.php';
    if ($isSuperAdmin && $adminId !== $currentUserId) {
        $redir .= "?admin_id={$adminId}";
    }
    redirect($redir);
}

// Fetch currently claimed faculty user IDs by target admin
$cStmt = $db->prepare("SELECT faculty_user_id FROM faculty_delegates WHERE delegate_user_id = ?");
$cStmt->execute([$adminId]);
$myClaimedUserIds = $cStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all existing delegations across the institution to identify already-assigned faculties
$allDelStmt = $db->query("
    SELECT fd.faculty_user_id, fd.delegate_user_id, u.full_name as admin_name, u.email as admin_email
    FROM faculty_delegates fd
    JOIN users u ON fd.delegate_user_id = u.id
");
$allAssignments = [];
while ($delRow = $allDelStmt->fetch(PDO::FETCH_ASSOC)) {
    $allAssignments[(int)$delRow['faculty_user_id']] = $delRow;
}

// Fetch departments for filtering
$departments = $db->query("SELECT id, code, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Search & Filter parameters
$searchQ   = trim($_GET['q'] ?? '');
$deptFilter = (int)($_GET['dept_id'] ?? 0);

$where = ["u.status = 'active'", "u.id != ?"];
$params = [$adminId];

if (!empty($searchQ)) {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR fp.designation LIKE ?)";
    $params[] = "%{$searchQ}%";
    $params[] = "%{$searchQ}%";
    $params[] = "%{$searchQ}%";
}
if ($deptFilter > 0) {
    $where[] = "fp.department_id = ?";
    $params[] = $deptFilter;
}

$whereClause = implode(' AND ', $where);

// Fetch all active faculty scholars with minimal metrics
$sql = "
    SELECT fp.id as profile_id, fp.slug, fp.salutation, fp.designation, fp.photo_url, 
           fp.total_citations, fp.bio, fp.research_interests, fp.orcid_id, fp.cabin, fp.phone,
           u.id as user_id, u.full_name, u.email,
           d.id as department_id, d.name as department_name, d.code as department_code,
           COUNT(DISTINCT p.id) as pub_count
    FROM faculty_profiles fp
    JOIN users u ON fp.user_id = u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    LEFT JOIN publications p ON p.faculty_profile_id = fp.id
    WHERE {$whereClause}
    GROUP BY fp.id, u.id, d.id, d.name, d.code
    ORDER BY u.full_name ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$facultiesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compute completeness reports for listed faculties
foreach ($facultiesList as &$item) {
    $item['health'] = get_profile_completeness_report($item);
    $item['is_claimed'] = in_array((int)$item['user_id'], array_map('intval', $myClaimedUserIds), true);
    $item['assigned_to'] = $allAssignments[(int)$item['user_id']] ?? null;
}
unset($item);

// Super admin list of admins for quick switching
$allAdminsList = [];
if ($isSuperAdmin) {
    $allAdminsList = $db->query("SELECT id, full_name, email, role FROM users WHERE role IN ('admin', 'super_admin') AND status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Assign Faculty Scholars — Department Administrator Portal';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Masthead -->
<section class="bg-oxford-navy text-white py-8 border-b border-oxford-blue">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <a href="<?= url($isSuperAdmin && $targetAdminUser ? "assistant/index.php?tab=admins&admin_id={$adminId}" : 'assistant/index.php') ?>" 
                   class="text-xs text-amber-300 hover:text-white inline-flex items-center gap-1.5 font-semibold mb-2 transition">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Back to Portfolio Overview</span>
                </a>
                <h1 class="font-serif text-2xl sm:text-3xl font-bold text-white">
                    Assign Faculty to Portfolio
                    <?php if ($targetAdminUser): ?>
                        <span class="text-base text-amber-300 font-sans font-normal block sm:inline sm:ml-2">
                            (For Admin: <?= e($targetAdminUser['full_name']) ?>)
                        </span>
                    <?php endif; ?>
                </h1>
                <p class="text-xs text-slate-300 mt-1 font-sans">
                    Browse all institutional faculty profiles, filter by department or name, and assign scholars to portfolio oversight.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] bg-white/10 border border-white/15 text-xs font-mono text-slate-200">
                    <i class="fa-solid fa-user-check text-emerald-400"></i>
                    <span>Assigned in this Portfolio: <?= count($myClaimedUserIds) ?></span>
                </span>
            </div>
        </div>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Super Admin Target Admin Selector Strip -->
    <?php if ($isSuperAdmin): ?>
        <div class="academic-card p-3.5 bg-slate-50 border-l-4 border-l-oxford-navy flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-user-gear text-oxford-blue text-sm"></i>
                <span class="text-xs font-bold text-oxford-navy uppercase font-mono">Managing Assignments For:</span>
            </div>
            <form action="<?= url('assistant/choose_faculty.php') ?>" method="GET" class="flex items-center gap-2 m-0">
                <?php if (!empty($searchQ)): ?>
                    <input type="hidden" name="q" value="<?= e($searchQ) ?>">
                <?php endif; ?>
                <?php if ($deptFilter > 0): ?>
                    <input type="hidden" name="dept_id" value="<?= $deptFilter ?>">
                <?php endif; ?>
                <select name="admin_id" onchange="this.form.submit()" class="academic-input text-xs !py-1 !px-2.5">
                    <?php foreach ($allAdminsList as $adm): ?>
                        <option value="<?= (int)$adm['id'] ?>" <?= $adminId === (int)$adm['id'] ? 'selected' : '' ?>>
                            <?= e($adm['full_name']) ?> (<?= e(strtoupper($adm['role'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript>
                    <button type="submit" class="btn-academic-secondary text-xs !py-1 !px-2">Switch</button>
                </noscript>
            </form>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Controls -->
    <div class="academic-card p-4 shadow-xs bg-white">
        <form action="<?= url('assistant/choose_faculty.php') ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <?php if ($isSuperAdmin && $adminId !== $currentUserId): ?>
                <input type="hidden" name="admin_id" value="<?= $adminId ?>">
            <?php endif; ?>

            <div class="sm:col-span-6 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="q" value="<?= e($searchQ) ?>"
                       placeholder="Search by faculty name, designation, or email..."
                       class="academic-input pl-9 text-xs w-full">
            </div>

            <div class="sm:col-span-4">
                <select name="dept_id" class="academic-input text-xs w-full">
                    <option value="0">All Academic Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int)$dept['id'] ?>" <?= $deptFilter === (int)$dept['id'] ? 'selected' : '' ?>>
                            <?= e($dept['name']) ?> (<?= e($dept['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <button type="submit" class="btn-academic-primary text-xs !py-2 w-full justify-center">
                    <i class="fa-solid fa-filter text-[10px]"></i>
                    <span>Filter</span>
                </button>
                <?php if (!empty($searchQ) || $deptFilter > 0): ?>
                    <a href="<?= url('assistant/choose_faculty.php' . ($isSuperAdmin && $adminId !== $currentUserId ? "?admin_id={$adminId}" : '')) ?>" 
                       class="btn-academic-secondary text-xs !py-2 px-3" title="Clear Filters">
                        <i class="fa-solid fa-rotate-left text-[10px]"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Header -->
    <div class="flex items-center justify-between text-xs text-slate-500 font-sans px-1">
        <span>Found <strong><?= count($facultiesList) ?></strong> faculty profile(s)</span>
        <span class="font-mono text-[11px]">Click "Assign" to add directly to active oversight list</span>
    </div>

    <!-- Faculty Cards Grid -->
    <?php if (empty($facultiesList)): ?>
        <div class="academic-card p-12 text-center text-slate-500 shadow-xs">
            <i class="fa-solid fa-user-slash text-3xl text-slate-300 mb-2"></i>
            <h3 class="font-serif text-base font-bold text-oxford-navy">No faculty scholars matched your search</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Try adjusting your search query or clear the department filter to view all scholars.
            </p>
            <a href="<?= url('assistant/choose_faculty.php' . ($isSuperAdmin && $adminId !== $currentUserId ? "?admin_id={$adminId}" : '')) ?>" class="btn-academic-secondary text-xs mt-3 inline-flex">
                Reset Filters
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($facultiesList as $fac): ?>
                <?php 
                    $photo = faculty_photo_url($fac['photo_url'] ?? null);
                    $health = $fac['health'];
                    $healthColor = $health['score'] >= 85 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : ($health['score'] >= 60 ? 'text-amber-800 bg-amber-50 border-amber-200' : 'text-rose-800 bg-rose-50 border-rose-200');
                ?>
                <div class="academic-card p-5 flex flex-col justify-between group shadow-xs hover:border-oxford-navy/40 transition bg-white <?= $fac['is_claimed'] ? 'border-l-4 border-l-emerald-600' : '' ?>">
                    <div>
                        <!-- Header: Identity -->
                        <div class="flex items-start gap-3.5">
                            <div class="w-14 h-14 rounded-[6px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center shadow-xs">
                                <?php if ($photo): ?>
                                    <img src="<?= e($photo) ?>" alt="<?= e($fac['full_name']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow min-w-0">
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <span class="academic-tag font-mono text-[10px] font-bold">
                                        <?= e($fac['department_code'] ?? 'FACULTY') ?>
                                    </span>
                                    <span class="academic-tag font-mono text-[10px] font-bold border <?= $healthColor ?>">
                                        <?= $health['score'] ?>% Health
                                    </span>
                                </div>
                                <h3 class="font-serif font-bold text-oxford-navy text-base truncate">
                                    <?= e(($fac['salutation'] ? $fac['salutation'] . ' ' : '') . $fac['full_name']) ?>
                                </h3>
                                <p class="text-xs text-scholar-muted truncate font-medium"><?= e($fac['designation'] ?: 'Faculty Member') ?></p>
                                <p class="text-[11px] text-slate-400 font-mono truncate"><?= e($fac['email']) ?></p>
                            </div>
                        </div>

                        <!-- Data Completeness / Missing Fields Badges -->
                        <div class="mt-3.5 pt-3 border-t border-slate-100">
                            <?php if (empty($health['missing_items'])): ?>
                                <span class="text-emerald-700 text-[11px] font-semibold flex items-center gap-1">
                                    <i class="fa-solid fa-circle-check text-xs"></i> All essential records complete
                                </span>
                            <?php else: ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach (array_slice($health['missing_items'], 0, 3) as $m): ?>
                                        <span class="text-[10px] px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 font-medium inline-flex items-center gap-1">
                                            <i class="fa-solid fa-triangle-exclamation text-[8px]"></i>
                                            <?= e($m['label']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Minimal Metrics Summary -->
                        <div class="mt-3.5 grid grid-cols-2 gap-2 text-center text-xs p-2 bg-slate-50 rounded-[4px] border border-scholar-border">
                            <div>
                                <span class="text-[10px] text-slate-400 font-mono block uppercase">Publications</span>
                                <span class="font-bold font-mono text-oxford-navy"><?= (int)$fac['pub_count'] ?></span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-mono block uppercase">Citations</span>
                                <span class="font-bold font-mono text-oxford-navy"><?= number_format($fac['total_citations']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action Strip -->
                    <div class="mt-4 pt-3.5 border-t border-scholar-border flex items-center justify-between gap-2">
                        <a href="<?= researcher_url($fac) ?>" target="_blank"
                           class="btn-academic-secondary text-xs !py-1.5 !px-2.5 shadow-xs" title="View public researcher profile">
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            <span>Public View</span>
                        </a>

                        <?php if ($fac['is_claimed']): ?>
                            <form action="<?= url('assistant/choose_faculty.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Remove <?= e($fac['full_name']) ?> from portfolio?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unclaim_faculty">
                                <input type="hidden" name="target_user_id" value="<?= (int)$fac['user_id'] ?>">
                                <?php if ($isSuperAdmin): ?>
                                    <input type="hidden" name="admin_id" value="<?= $adminId ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn-academic-secondary text-xs !py-1.5 !px-3 !bg-emerald-50 !text-emerald-800 !border-emerald-300 hover:!bg-rose-50 hover:!text-rose-800 hover:!border-rose-300 shadow-xs flex items-center gap-1.5 group">
                                    <i class="fa-solid fa-check text-emerald-600 group-hover:hidden"></i>
                                    <i class="fa-solid fa-user-minus text-rose-600 hidden group-hover:inline"></i>
                                    <span class="group-hover:hidden">In Portfolio</span>
                                    <span class="hidden group-hover:inline">Remove</span>
                                </button>
                            </form>
                        <?php elseif (!empty($fac['assigned_to'])): ?>
                            <?php if ($isSuperAdmin): ?>
                                <form action="<?= url('assistant/choose_faculty.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Reassign <?= e($fac['full_name']) ?> from <?= e($fac['assigned_to']['admin_name']) ?> to this portfolio?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="claim_faculty">
                                    <input type="hidden" name="target_user_id" value="<?= (int)$fac['user_id'] ?>">
                                    <input type="hidden" name="admin_id" value="<?= $adminId ?>">
                                    <button type="submit" class="btn-academic-secondary text-xs !py-1.5 !px-2.5 !bg-amber-50 !border-amber-300 !text-amber-800 hover:!bg-amber-100 shadow-xs flex items-center gap-1.5" title="Currently assigned to <?= e($fac['assigned_to']['admin_name']) ?>. Click to transfer.">
                                        <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i>
                                        <span>Reassign (<?= e($fac['assigned_to']['admin_name']) ?>)</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="academic-tag !py-1.5 !px-2.5 bg-slate-100 text-slate-600 border-slate-300 shadow-xs flex items-center gap-1.5 cursor-not-allowed" title="Already assigned to <?= e($fac['assigned_to']['admin_name']) ?>">
                                    <i class="fa-solid fa-lock text-[10px] text-slate-500"></i>
                                    <span class="truncate max-w-[140px]">Already Assigned (<?= e($fac['assigned_to']['admin_name']) ?>)</span>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <form action="<?= url('assistant/choose_faculty.php') ?>" method="POST" class="inline m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="claim_faculty">
                                <input type="hidden" name="target_user_id" value="<?= (int)$fac['user_id'] ?>">
                                <?php if ($isSuperAdmin): ?>
                                    <input type="hidden" name="admin_id" value="<?= $adminId ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs bg-emerald-700 hover:bg-emerald-800 border-emerald-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Assign to Portfolio</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
