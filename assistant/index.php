<?php
/**
 * Departmental Scholar — Research Assistant & Department Admin Portal
 * Super Admin Multi-Admin Overview & Faculty Delegation System
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: Master CRIS Architecture
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'super_admin']);

$db = Database::getConnection();
$currentUserId = user_id();
$isSuperAdmin = has_role('super_admin');

// Active tab for Super Admin: 'admins' (Admin & Assistant View) vs 'all_faculties' (Institutional Faculty View)
$activeTab = $_GET['tab'] ?? ($isSuperAdmin ? 'admins' : 'portfolio');
if (!in_array($activeTab, ['admins', 'all_faculties', 'portfolio'], true)) {
    $activeTab = $isSuperAdmin ? 'admins' : 'portfolio';
}

// Optional filter: viewing a specific admin's portfolio as Super Admin
$viewAdminId = $isSuperAdmin && isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;

// 1. Fetch current user's OWN faculty profile (Mandatory Personal Profile)
$ownProfileSql = "
    SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations, fp.slug,
           fp.h_index, fp.i10_index, fp.bio, fp.is_verified,
           u.id as user_id, u.full_name, u.email, d.name as department_name, d.code as department_code,
           COUNT(DISTINCT p.id) as pub_count,
           COUNT(DISTINCT proj.id) as project_count,
           COUNT(DISTINCT pat.id) as patent_count
    FROM faculty_profiles fp
    JOIN users u ON fp.user_id = u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    LEFT JOIN publications p ON p.faculty_profile_id = fp.id
    LEFT JOIN projects proj ON proj.faculty_profile_id = fp.id
    LEFT JOIN patents pat ON pat.faculty_profile_id = fp.id
    WHERE fp.user_id = ?
    GROUP BY fp.id, u.id, d.name, d.code
";
$ownStmt = $db->prepare($ownProfileSql);
$ownStmt->execute([$currentUserId]);
$ownFaculty = $ownStmt->fetch(PDO::FETCH_ASSOC);

// Handle creating own profile if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_own_profile') {
    require_csrf();
    $checkStmt = $db->prepare("SELECT id FROM faculty_profiles WHERE user_id = ? LIMIT 1");
    $checkStmt->execute([$currentUserId]);
    $existingPid = (int)$checkStmt->fetchColumn();

    if (!$existingPid) {
        $ins = $db->prepare("INSERT INTO faculty_profiles (user_id, salutation, designation, is_verified) VALUES (?, 'Dr.', 'Assistant Professor', 1)");
        $ins->execute([$currentUserId]);
        $existingPid = (int)$db->lastInsertId();
        record_audit('profile_created', 'faculty_profiles', $existingPid, 'User created their own personal faculty profile');
        set_flash('success', 'Your personal faculty profile has been created successfully!');
    }
    $_SESSION['active_faculty_profile_id'] = $existingPid;
    redirect('dashboard/edit_profile.php?id=' . $existingPid);
}

// Handle claiming faculty into admin management portfolio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_faculty') {
    require_csrf();
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $assigneeAdminId = ($isSuperAdmin && !empty($_POST['admin_id'])) ? (int)$_POST['admin_id'] : $currentUserId;

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
                    // Super Admin reassigns cleanly
                    $db->beginTransaction();
                    $db->prepare("DELETE FROM faculty_delegates WHERE faculty_user_id = ?")->execute([$targetUserId]);
                    $ins = $db->prepare("INSERT INTO faculty_delegates (faculty_user_id, delegate_user_id, granted_by) VALUES (?, ?, ?)");
                    $ins->execute([$targetUserId, $assigneeAdminId, $currentUserId]);
                    $db->commit();
                    record_audit('portfolio_reassigned', 'faculty_delegates', $targetUserId, "Super Admin reassigned faculty {$targetUser['full_name']} from {$existingDel['admin_name']} to admin ID {$assigneeAdminId}");
                    set_flash('success', "Reassigned {$targetUser['full_name']} to administrator portfolio.");
                } else {
                    set_flash('danger', "Cannot assign {$targetUser['full_name']}: Already assigned to {$existingDel['admin_name']}. Each faculty member can only be managed by one administrator.");
                }
            } else {
                $ins = $db->prepare("INSERT INTO faculty_delegates (faculty_user_id, delegate_user_id, granted_by) VALUES (?, ?, ?)");
                $ins->execute([$targetUserId, $assigneeAdminId, $currentUserId]);
                record_audit('portfolio_claimed', 'faculty_delegates', $targetUserId, "Assigned faculty {$targetUser['full_name']} to admin ID {$assigneeAdminId}");
                set_flash('success', "Added {$targetUser['full_name']} to active management portfolio.");
            }
        }
    }
    $redirUrl = 'assistant/index.php';
    if ($isSuperAdmin && $viewAdminId > 0) {
        $redirUrl .= "?tab=admins&admin_id={$viewAdminId}";
    } elseif ($isSuperAdmin && $activeTab === 'all_faculties') {
        $redirUrl .= "?tab=all_faculties";
    }
    redirect($redirUrl);
}

// Handle unclaiming faculty from admin management portfolio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unclaim_faculty') {
    require_csrf();
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $assigneeAdminId = ($isSuperAdmin && !empty($_POST['admin_id'])) ? (int)$_POST['admin_id'] : ($isSuperAdmin ? 0 : $currentUserId);

    if ($targetUserId > 0) {
        if ($assigneeAdminId > 0) {
            $del = $db->prepare("DELETE FROM faculty_delegates WHERE faculty_user_id = ? AND delegate_user_id = ?");
            $del->execute([$targetUserId, $assigneeAdminId]);
            record_audit('portfolio_unclaimed', 'faculty_delegates', $targetUserId, "Removed faculty {$targetUserId} from admin ID {$assigneeAdminId} portfolio");
        } elseif ($isSuperAdmin) {
            $del = $db->prepare("DELETE FROM faculty_delegates WHERE faculty_user_id = ?");
            $del->execute([$targetUserId]);
            record_audit('portfolio_unclaimed', 'faculty_delegates', $targetUserId, "Super Admin removed delegation assignment for faculty {$targetUserId}");
        }
        set_flash('info', "Faculty profile removed from management portfolio.");
    }
    $redirUrl = 'assistant/index.php';
    if ($isSuperAdmin && $viewAdminId > 0) {
        $redirUrl .= "?tab=admins&admin_id={$viewAdminId}";
    } elseif ($isSuperAdmin && $activeTab === 'all_faculties') {
        $redirUrl .= "?tab=all_faculties";
    }
    redirect($redirUrl);
}

// =========================================================================
// DATA FETCHING ACCORDING TO ROLES & VIEWS
// =========================================================================

// If Super Admin: Fetch all Admins & Research Assistants with their portfolios
$adminList = [];
$selectedAdmin = null;

if ($isSuperAdmin) {
    $adminsSql = "
        SELECT u.id as user_id, u.full_name, u.email, u.role, u.status,
               fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.slug,
               d.name as department_name, d.code as department_code,
               (SELECT COUNT(*) FROM faculty_delegates WHERE delegate_user_id = u.id) as assigned_faculty_count
        FROM users u
        LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
        LEFT JOIN departments d ON fp.department_id = d.id
        WHERE u.role IN ('admin', 'super_admin') AND u.status = 'active'
        ORDER BY CASE WHEN u.role = 'super_admin' THEN 1 ELSE 2 END, u.full_name ASC
    ";
    $adminList = $db->query($adminsSql)->fetchAll(PDO::FETCH_ASSOC);

    // If an admin is selected, find their details
    if ($viewAdminId > 0) {
        foreach ($adminList as $a) {
            if ((int)$a['user_id'] === $viewAdminId) {
                $selectedAdmin = $a;
                break;
            }
        }
    }
}

// Fetch faculty profiles based on mode
if ($isSuperAdmin) {
    if ($activeTab === 'all_faculties') {
        // Institutional Faculty View: All active faculty profiles
        $sql = "
            SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations, fp.slug,
                   fp.bio, fp.research_interests, fp.orcid_id, fp.cabin, fp.phone,
                   u.id as user_id, u.full_name, u.email, d.name as department_name, d.code as department_code,
                   COUNT(p.id) as pub_count,
                   (
                       SELECT GROUP_CONCAT(CONCAT(adm.full_name, ' (', adm.email, ')') SEPARATOR ', ')
                       FROM faculty_delegates fd_sub
                       JOIN users adm ON fd_sub.delegate_user_id = adm.id
                       WHERE fd_sub.faculty_user_id = u.id
                   ) as assigned_admins_names
            FROM faculty_profiles fp
            JOIN users u ON fp.user_id = u.id
            LEFT JOIN departments d ON fp.department_id = d.id
            LEFT JOIN publications p ON p.faculty_profile_id = fp.id
            WHERE u.status = 'active'
            GROUP BY fp.id, u.id, d.name, d.code
            ORDER BY u.full_name ASC
        ";
        $stmt = $db->query($sql);
        $assignedFaculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($ownFaculty) {
            $assignedFaculties = array_values(array_filter($assignedFaculties, function($f) use ($ownFaculty) {
                return (int)$f['profile_id'] !== (int)$ownFaculty['profile_id'];
            }));
        }
    } elseif ($viewAdminId > 0) {
        // Super admin drilled down into a specific admin's panel
        $sql = "
            SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations, fp.slug,
                   fp.bio, fp.research_interests, fp.orcid_id, fp.cabin, fp.phone,
                   u.id as user_id, u.full_name, u.email, d.name as department_name, d.code as department_code,
                   COUNT(p.id) as pub_count
            FROM faculty_delegates fd
            JOIN users u ON fd.faculty_user_id = u.id
            JOIN faculty_profiles fp ON fp.user_id = u.id
            LEFT JOIN departments d ON fp.department_id = d.id
            LEFT JOIN publications p ON p.faculty_profile_id = fp.id
            WHERE fd.delegate_user_id = ? AND u.status = 'active'
            GROUP BY fp.id, u.id, d.name, d.code
            ORDER BY u.full_name ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$viewAdminId]);
        $assignedFaculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Super admin default on 'admins' tab: no direct faculties shown here; admin cards are shown
        $assignedFaculties = [];
    }
} else {
    // Regular admin: their own claimed portfolio
    $sql = "
        SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations, fp.slug,
               fp.bio, fp.research_interests, fp.orcid_id, fp.cabin, fp.phone,
               u.id as user_id, u.full_name, u.email, d.name as department_name, d.code as department_code,
               COUNT(p.id) as pub_count
        FROM faculty_delegates fd
        JOIN users u ON fd.faculty_user_id = u.id
        JOIN faculty_profiles fp ON fp.user_id = u.id
        LEFT JOIN departments d ON fp.department_id = d.id
        LEFT JOIN publications p ON p.faculty_profile_id = fp.id
        WHERE fd.delegate_user_id = ? AND u.status = 'active'
        GROUP BY fp.id, u.id, d.name, d.code
        ORDER BY u.full_name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$currentUserId]);
    $assignedFaculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Compute live health & completeness reports for assigned faculty
$needingAttentionCount = 0;
$completeCount = 0;
$totalScoreSum = 0;

foreach ($assignedFaculties as &$fac) {
    $fac['health'] = get_profile_completeness_report($fac);
    $totalScoreSum += $fac['health']['score'];
    if ($fac['health']['score'] < 100 || $fac['health']['has_critical']) {
        $needingAttentionCount++;
    } else {
        $completeCount++;
    }
}
unset($fac);

$avgHealth = !empty($assignedFaculties) ? round($totalScoreSum / count($assignedFaculties)) : 100;

// Also calculate overall institutional statistics if Super Admin
$totalInstitutionalFaculty = 0;
$totalAdminsCount = 0;
if ($isSuperAdmin) {
    $totalInstitutionalFaculty = (int)$db->query("SELECT COUNT(*) FROM faculty_profiles fp JOIN users u ON fp.user_id = u.id WHERE u.status = 'active'")->fetchColumn();
    $totalAdminsCount = count($adminList);
}

$pageTitle = 'Department Administrator Portal - Faculty Portfolio & Analytics';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner -->
<section class="bg-oxford-navy text-white py-10 border-b border-oxford-blue">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-mono font-semibold text-amber-300 uppercase tracking-widest">
                    <?= $isSuperAdmin ? 'Central Institutional Governance' : 'Department Administration' ?>
                </span>
                <h1 class="font-serif text-2xl sm:text-3xl font-bold text-white mt-1">
                    <?= $isSuperAdmin ? 'Administrative & Faculty Portfolios' : 'Faculty Portfolio & Oversight' ?>
                </h1>
                <p class="text-xs text-slate-300 mt-1 font-sans">
                    <?= $isSuperAdmin 
                        ? 'Supervise all departmental admins, inspect individual admin portfolios, and oversee completeness audits across all faculties.' 
                        : 'Select and oversee faculty scholars, monitor missing/delayed records, and maintain institutional research compliance.' 
                    ?>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($isSuperAdmin): ?>
                    <a href="<?= url('admin/index.php') ?>"
                       class="btn-academic-secondary text-xs !py-2 !px-3 shadow-xs inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                        <span>Super Admin Console</span>
                    </a>
                <?php endif; ?>
                <a href="<?= url('assistant/choose_faculty.php' . ($isSuperAdmin && $viewAdminId > 0 ? "?admin_id={$viewAdminId}" : '')) ?>"
                   class="btn-academic-primary text-xs !py-2 !px-4 bg-emerald-600 hover:bg-emerald-700 border-emerald-600 shadow-xs inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>Assign / Choose Faculty</span>
                </a>
            </div>
        </div>

        <!-- Super Admin Mode: Top Level Navigation Tabs -->
        <?php if ($isSuperAdmin): ?>
            <div class="flex items-center gap-2 mt-8 pt-4 border-t border-slate-700/60 overflow-x-auto text-xs font-semibold">
                <a href="<?= url('assistant/index.php?tab=admins') ?>"
                   class="px-3.5 py-2 rounded-[6px] transition flex items-center gap-2 <?= ($activeTab === 'admins' && $viewAdminId === 0) ? 'bg-white text-oxford-navy font-bold shadow-xs' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                    <i class="fa-solid fa-users-gear text-[11px]"></i>
                    <span>Admins &amp; Assistants View</span>
                    <span class="px-1.5 py-0.2 rounded-full font-mono text-[10px] <?= ($activeTab === 'admins' && $viewAdminId === 0) ? 'bg-slate-100 text-oxford-navy' : 'bg-slate-800 text-slate-300' ?>">
                        <?= $totalAdminsCount ?>
                    </span>
                </a>

                <a href="<?= url('assistant/index.php?tab=all_faculties') ?>"
                   class="px-3.5 py-2 rounded-[6px] transition flex items-center gap-2 <?= $activeTab === 'all_faculties' ? 'bg-white text-oxford-navy font-bold shadow-xs' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                    <i class="fa-solid fa-graduation-cap text-[11px]"></i>
                    <span>Faculty-Wise View (All Scholars)</span>
                    <span class="px-1.5 py-0.2 rounded-full font-mono text-[10px] <?= $activeTab === 'all_faculties' ? 'bg-slate-100 text-oxford-navy' : 'bg-slate-800 text-slate-300' ?>">
                        <?= $totalInstitutionalFaculty ?>
                    </span>
                </a>

                <?php if ($viewAdminId > 0 && $selectedAdmin): ?>
                    <span class="text-slate-500 font-mono text-xs">/</span>
                    <span class="px-3 py-1.5 rounded-[6px] bg-amber-400/20 text-amber-300 border border-amber-300/30 flex items-center gap-1.5 font-medium">
                        <i class="fa-solid fa-user-shield text-[10px]"></i>
                        <span>Inspecting Admin: <strong><?= e($selectedAdmin['full_name']) ?></strong></span>
                        <a href="<?= url('assistant/index.php?tab=admins') ?>" class="ml-1 text-slate-300 hover:text-white" title="Close admin panel inspect mode">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </a>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">

    <!-- ========================================================= -->
    <!-- MANDATORY: YOUR OWN PERSONAL FACULTY PROFILE CARD         -->
    <!-- ========================================================= -->
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div class="flex items-center gap-2.5">
                <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-address-card text-oxford-blue text-sm"></i>
                    <span>Your Own Faculty Profile</span>
                </h2>
                <span class="academic-tag bg-emerald-50 text-emerald-800 border-emerald-200 font-mono text-[10px] font-bold uppercase">
                    Primary Identity
                </span>
            </div>
            <span class="text-xs text-slate-500 font-sans">
                You retain full personal access to manage your own scholarly profile and achievements.
            </span>
        </div>

        <?php if ($ownFaculty): ?>
            <?php 
                $ownPhoto = faculty_photo_url($ownFaculty['photo_url'] ?? null); 
                $ownFullName = clean_faculty_display_name($ownFaculty['salutation'] ?? '', $ownFaculty['full_name'] ?? '');
            ?>
            <div class="academic-card p-6 border-l-4 border-l-oxford-navy shadow-xs hover:border-oxford-navy/50 transition bg-white">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    
                    <!-- Left: Identity & Details -->
                    <div class="flex items-start sm:items-center gap-4 min-w-0">
                        <div class="w-16 h-16 rounded-[8px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center shadow-xs">
                            <?php if ($ownPhoto): ?>
                                <img src="<?= e($ownPhoto) ?>" alt="<?= e($ownFaculty['full_name']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-user-graduate text-3xl text-slate-300"></i>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="academic-tag font-mono text-[10px] font-bold">
                                    <?= e($ownFaculty['department_code'] ?? 'FACULTY') ?>
                                </span>
                                <span class="academic-tag academic-tag-green text-[10px] !py-0.5">
                                    <i class="fa-solid fa-circle-check text-[10px]"></i> Verified Faculty
                                </span>
                                <span class="text-slate-400 font-mono text-[11px] hidden sm:inline">•</span>
                                <span class="text-slate-500 font-mono text-xs truncate max-w-xs"><?= e($ownFaculty['email']) ?></span>
                            </div>
                            <h3 class="font-serif font-bold text-oxford-navy text-xl sm:text-2xl leading-tight">
                                <?= e($ownFullName) ?>
                            </h3>
                            <p class="text-xs text-scholar-muted mt-1 font-sans">
                                <?= e($ownFaculty['designation'] ?: 'Faculty Member') ?> • <?= e($ownFaculty['department_name'] ?? 'Academic Division') ?> • ITER, SOA Deemed to be University
                            </p>
                        </div>
                    </div>

                    <!-- Center / Right: Scholarly Metrics Strip -->
                    <div class="flex items-center gap-3 sm:gap-6 bg-slate-50/80 p-3 sm:px-5 sm:py-3 rounded-[6px] border border-scholar-border self-start lg:self-auto flex-shrink-0">
                        <div class="text-center">
                            <span class="text-[10px] text-slate-400 font-mono block uppercase">Publications</span>
                            <span class="font-bold font-mono text-oxford-navy text-base sm:text-lg"><?= (int)$ownFaculty['pub_count'] ?></span>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="text-center">
                            <span class="text-[10px] text-slate-400 font-mono block uppercase">Citations</span>
                            <span class="font-bold font-mono text-oxford-navy text-base sm:text-lg"><?= number_format($ownFaculty['total_citations']) ?></span>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="text-center">
                            <span class="text-[10px] text-slate-400 font-mono block uppercase">Grants</span>
                            <span class="font-bold font-mono text-oxford-navy text-base sm:text-lg"><?= (int)$ownFaculty['project_count'] ?></span>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="text-center">
                            <span class="text-[10px] text-slate-400 font-mono block uppercase">Patents</span>
                            <span class="font-bold font-mono text-oxford-navy text-base sm:text-lg"><?= (int)$ownFaculty['patent_count'] ?></span>
                        </div>
                    </div>

                    <!-- Right: Direct Action Buttons -->
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 flex-shrink-0 border-t lg:border-t-0 pt-4 lg:pt-0 border-slate-100">
                        <a href="<?= researcher_url($ownFaculty) ?>" target="_blank"
                           class="btn-academic-secondary text-xs !py-2 !px-3 shadow-xs" title="View your public profile as seen by students and peers">
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] mr-1.5"></i>
                            <span>Public View</span>
                        </a>

                        <form action="<?= url('assistant/switch.php') ?>" method="POST" class="inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="profile_id" value="<?= (int)$ownFaculty['profile_id'] ?>">
                            <button type="submit"
                               class="btn-academic-primary text-xs !py-2 !px-4 shadow-xs flex items-center gap-1.5"
                               title="Open your personal faculty dashboard to add publications, grants, and achievements">
                                <i class="fa-solid fa-sliders text-xs"></i>
                                <span>Manage Your Profile</span>
                            </button>
                        </form>

                        <a href="<?= url('dashboard/edit_profile.php?id=' . (int)$ownFaculty['profile_id']) ?>"
                           class="btn-academic-secondary text-xs !py-2 !px-3 shadow-xs" title="Edit your biography, research tags, and scholarly registry IDs">
                            <i class="fa-solid fa-pen-to-square text-[10px] mr-1.5"></i>
                            <span>Edit Details</span>
                        </a>
                    </div>

                </div>
            </div>
        <?php else: ?>
            <div class="academic-card p-6 border-dashed border-2 border-slate-300 bg-slate-50/70">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-[6px] bg-white border border-scholar-border text-oxford-navy flex items-center justify-center font-bold text-xl flex-shrink-0 shadow-xs">
                            <i class="fa-solid fa-user-graduate text-slate-400"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-serif font-bold text-oxford-navy text-base">Your Personal Faculty Profile</h3>
                                <span class="text-[10px] font-mono uppercase bg-slate-200 text-slate-700 px-2 py-0.5 rounded">Not Initialized</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 max-w-xl font-sans">
                                Initialize your scholarly profile to list your publications, grants, and career appointments.
                            </p>
                        </div>
                    </div>
                    <form action="<?= url('assistant/index.php') ?>" method="POST" class="m-0 flex-shrink-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_own_profile">
                        <button type="submit" class="btn-academic-primary text-xs !py-2 !px-4 shadow-xs flex items-center gap-2">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Initialize Your Faculty Profile</span>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ========================================================= -->
    <!-- SUPER ADMIN: ADMINS & ASSISTANTS OVERVIEW CARDS           -->
    <!-- ========================================================= -->
    <?php if ($isSuperAdmin && $activeTab === 'admins' && $viewAdminId === 0): ?>
        <div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pt-6 border-t border-slate-200">
                <div>
                    <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-user-shield text-slate-500 text-xs"></i>
                        <span>Department Admins &amp; Research Assistants</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5 font-sans">
                        Administrators empowered to oversee assigned faculty portfolios. Click on any admin card to inspect and manage their respective panel.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-100 border border-slate-200 text-xs font-mono text-slate-700">
                        <span><?= count($adminList) ?> Administrative Officers</span>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($adminList as $adm): ?>
                    <?php 
                        $admPhoto = faculty_photo_url($adm['photo_url'] ?? null);
                        $isSuper = $adm['role'] === 'super_admin';
                    ?>
                    <div class="academic-card p-6 flex flex-col justify-between group shadow-xs hover:border-oxford-navy/50 transition bg-white border-t-4 <?= $isSuper ? 'border-t-oxford-navy' : 'border-t-emerald-600' ?>">
                        <div>
                            <!-- Header / Info -->
                            <div class="flex items-start gap-3.5">
                                <div class="w-14 h-14 rounded-[6px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center shadow-xs">
                                    <?php if ($admPhoto): ?>
                                        <img src="<?= e($admPhoto) ?>" alt="<?= e($adm['full_name']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fa-solid <?= $isSuper ? 'fa-shield-halved text-oxford-blue' : 'fa-user-tie text-slate-300' ?> text-2xl"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-grow">
                                    <div class="flex items-center justify-between gap-1 mb-1">
                                        <span class="academic-tag font-mono text-[10px] font-bold <?= $isSuper ? 'bg-oxford-navy text-white' : 'bg-emerald-50 text-emerald-800 border-emerald-200' ?>">
                                            <?= $isSuper ? 'SUPER ADMIN' : 'DEPARTMENT ADMIN' ?>
                                        </span>
                                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                            ID #<?= (int)$adm['user_id'] ?>
                                        </span>
                                    </div>
                                    <h3 class="font-serif font-bold text-oxford-navy text-base truncate">
                                        <?= e($adm['full_name']) ?>
                                    </h3>
                                    <p class="text-xs text-scholar-muted truncate font-medium">
                                        <?= e($adm['designation'] ?: ($isSuper ? 'Central Administrator' : 'Research Assistant')) ?>
                                    </p>
                                    <p class="text-[11px] text-slate-400 font-mono truncate"><?= e($adm['email']) ?></p>
                                </div>
                            </div>

                            <!-- Assignment Metric -->
                            <div class="mt-4 pt-3.5 border-t border-slate-100">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-500 font-medium">Managed Portfolio:</span>
                                    <span class="font-bold font-mono text-oxford-navy text-sm">
                                        <?= (int)$adm['assigned_faculty_count'] ?> Faculty Scholars
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1 font-sans">
                                    <?= e($adm['department_name'] ?? 'University Department') ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action -->
                        <div class="mt-5 pt-4 border-t border-scholar-border flex items-center justify-between gap-2">
                            <span class="text-[11px] text-slate-400 font-mono">
                                <?= $adm['status'] === 'active' ? '● Active' : '○ Inactive' ?>
                            </span>

                            <div class="flex items-center gap-2">
                                <a href="<?= url('assistant/choose_faculty.php?admin_id=' . (int)$adm['user_id']) ?>"
                                   class="btn-academic-secondary text-xs !py-1.5 !px-2.5 shadow-xs" title="Assign faculties for this admin">
                                    <i class="fa-solid fa-user-plus text-[10px] mr-1"></i>
                                    <span>Assign</span>
                                </a>

                                <a href="<?= url('assistant/index.php?tab=admins&admin_id=' . (int)$adm['user_id']) ?>"
                                   class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs flex items-center gap-1.5">
                                    <span>Inspect Panel</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- PORTFOLIO ANALYTICS KPI SUMMARY (Shown when viewing faculty) -->
    <!-- ========================================================= -->
    <?php if (!$isSuperAdmin || $activeTab === 'all_faculties' || ($activeTab === 'admins' && $viewAdminId > 0)): ?>
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-slate-400 text-xs"></i>
                    <span>Portfolio Completeness & Compliance Overview</span>
                </h2>
                <span class="text-xs text-slate-400 font-mono">Real-time health audits</span>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="academic-card p-4 shadow-xs border-l-4 border-l-oxford-navy">
                    <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Managed Faculty</span>
                    <div class="text-2xl font-bold font-mono text-oxford-navy mt-1"><?= count($assignedFaculties) ?></div>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">
                        <?= ($isSuperAdmin && $viewAdminId > 0 && $selectedAdmin) ? "Under " . e($selectedAdmin['full_name']) : "Under supervision" ?>
                    </span>
                </div>

                <div class="academic-card p-4 shadow-xs border-l-4 border-l-rose-500">
                    <span class="text-[10px] font-mono font-bold uppercase text-rose-500 block">Action Required</span>
                    <div class="text-2xl font-bold font-mono text-rose-600 mt-1"><?= $needingAttentionCount ?></div>
                    <span class="text-[11px] text-rose-700/80 mt-0.5 block">Missing bio, works, or recent output</span>
                </div>

                <div class="academic-card p-4 shadow-xs border-l-4 border-l-emerald-500">
                    <span class="text-[10px] font-mono font-bold uppercase text-emerald-600 block">Fully Up to Date</span>
                    <div class="text-2xl font-bold font-mono text-emerald-700 mt-1"><?= $completeCount ?></div>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">All essential records complete</span>
                </div>

                <div class="academic-card p-4 shadow-xs border-l-4 border-l-academic-gold">
                    <span class="text-[10px] font-mono font-bold uppercase text-slate-500 block">Average Compliance</span>
                    <div class="text-2xl font-bold font-mono text-oxford-navy mt-1"><?= $avgHealth ?>%</div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                        <div class="bg-oxford-slate h-full rounded-full" style="width: <?= $avgHealth ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- ASSIGNED FACULTY PROFILES SECTION                         -->
        <!-- ========================================================= -->
        <div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pt-6 border-t border-slate-200">
                <div>
                    <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-users text-slate-500 text-xs"></i>
                        <span>
                            <?php if ($isSuperAdmin && $viewAdminId > 0 && $selectedAdmin): ?>
                                Faculty Assigned Under: <span class="text-emerald-700"><?= e($selectedAdmin['full_name']) ?></span>
                            <?php elseif ($isSuperAdmin && $activeTab === 'all_faculties'): ?>
                                Faculty-Wise Master Overview (All Institutional Scholars)
                            <?php else: ?>
                                Faculty Profiles in Your Portfolio
                            <?php endif; ?>
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5 font-sans">
                        Scholars assigned for profile administration, publication entry, and data completeness tracking.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?= url('assistant/choose_faculty.php' . ($isSuperAdmin && $viewAdminId > 0 ? "?admin_id={$viewAdminId}" : '')) ?>"
                       class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span><?= ($isSuperAdmin && $viewAdminId > 0) ? 'Assign Faculty to this Admin' : 'Add Faculty' ?></span>
                    </a>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-100 border border-slate-200 text-xs font-mono text-slate-700">
                        <span><?= count($assignedFaculties) ?> Active</span>
                    </span>
                </div>
            </div>

            <?php if (empty($assignedFaculties)): ?>
                <div class="academic-card p-12 text-center text-slate-500 shadow-xs">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                        <i class="fa-solid fa-users text-xl"></i>
                    </div>
                    <h3 class="font-serif text-base font-bold text-oxford-navy">
                        <?= ($isSuperAdmin && $viewAdminId > 0) ? 'No faculty profiles assigned to this administrator yet' : 'No faculty profiles in your portfolio yet' ?>
                    </h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto font-sans">
                        Click <strong>"Assign / Choose Faculty"</strong> to view all institutional scholars, filter by department, and choose profiles to manage.
                    </p>
                    <a href="<?= url('assistant/choose_faculty.php' . ($isSuperAdmin && $viewAdminId > 0 ? "?admin_id={$viewAdminId}" : '')) ?>"
                       class="btn-academic-primary text-xs mt-4 inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span>Choose Faculty to Manage</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($assignedFaculties as $fac): ?>
                        <?php 
                            $photo = faculty_photo_url($fac['photo_url'] ?? null); 
                            $health = $fac['health'];
                            $healthColor = $health['score'] >= 85 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : ($health['score'] >= 60 ? 'text-amber-800 bg-amber-50 border-amber-200' : 'text-rose-800 bg-rose-50 border-rose-200');
                        ?>
                        <div class="academic-card p-6 flex flex-col justify-between group shadow-xs hover:border-oxford-navy/40 transition bg-white">
                            <div>
                                <!-- Header: Identity -->
                                <div class="flex items-start gap-4">
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
                                                <?= e($fac['department_code'] ?? 'SCHOLAR') ?>
                                            </span>
                                            <span class="academic-tag font-mono text-[10px] font-bold border <?= $healthColor ?>">
                                                <?= $health['score'] ?>% Health
                                            </span>
                                        </div>
                                        <h3 class="font-serif font-bold text-oxford-navy text-base truncate">
                                            <?= e(($fac['salutation'] ? $fac['salutation'] . ' ' : '') . $fac['full_name']) ?>
                                        </h3>
                                        <p class="text-xs text-scholar-muted truncate font-medium"><?= e($fac['designation']) ?></p>
                                        <p class="text-[11px] text-slate-400 font-mono truncate"><?= e($fac['email']) ?></p>
                                    </div>
                                </div>

                                <!-- Assigned Admin Badge (If Master Overview) -->
                                <?php if ($isSuperAdmin && $activeTab === 'all_faculties'): ?>
                                    <div class="mt-3 pt-2.5 border-t border-slate-100 text-[11px]">
                                        <span class="text-slate-400 font-mono uppercase text-[9px] block">Assigned Admin(s):</span>
                                        <?php if (!empty($fac['assigned_admins_names'])): ?>
                                            <span class="font-medium text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded inline-block mt-0.5 truncate max-w-full" title="<?= e($fac['assigned_admins_names']) ?>">
                                                <i class="fa-solid fa-user-check text-[10px] mr-1"></i>
                                                <?= e($fac['assigned_admins_names']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="font-mono text-slate-400 text-[10px] italic">Unassigned (Direct Super Admin control)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Missing / Overdue Fields Alert Strip -->
                                <div class="mt-4 pt-3 border-t border-slate-100">
                                    <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 block mb-1.5">
                                        Data Completeness & Audit:
                                    </span>
                                    <?php if (empty($health['missing_items'])): ?>
                                        <div class="p-2 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] flex items-center gap-1.5 font-medium">
                                            <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                            <span>All essential records up to date</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-wrap gap-1.5">
                                            <?php foreach ($health['missing_items'] as $item): ?>
                                                <?php 
                                                    $badgeStyle = match ($item['severity']) {
                                                        'danger' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                                                        default => 'bg-slate-50 text-slate-700 border-slate-200'
                                                    };
                                                ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium border <?= $badgeStyle ?>"
                                                      title="Action needed by administrator">
                                                    <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                                                    <span><?= e($item['label']) ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Stats summary -->
                                <div class="mt-4 grid grid-cols-2 gap-2 text-center text-xs p-2.5 bg-slate-50 rounded-[6px] border border-scholar-border">
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

                            <!-- Card Footer Action Buttons -->
                            <div class="mt-5 pt-4 border-t border-scholar-border flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <a href="<?= researcher_url($fac) ?>" target="_blank"
                                       class="btn-academic-secondary text-xs !py-1.5 !px-2.5 shadow-xs" title="Public profile view">
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                    </a>

                                    <?php if ($activeTab !== 'all_faculties' || !empty($fac['assigned_admins_names'])): ?>
                                        <form action="<?= url('assistant/index.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Remove assignment for <?= e($fac['full_name']) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unclaim_faculty">
                                            <input type="hidden" name="target_user_id" value="<?= (int)$fac['user_id'] ?>">
                                            <?php if ($isSuperAdmin && $viewAdminId > 0): ?>
                                                <input type="hidden" name="admin_id" value="<?= $viewAdminId ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="btn-academic-secondary text-xs !py-1.5 !px-2.5 text-rose-700 hover:text-rose-900 shadow-xs" title="Remove assignment">
                                                <i class="fa-solid fa-user-minus text-[10px]"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <form action="<?= url('assistant/switch.php') ?>" method="POST" class="inline m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="profile_id" value="<?= (int)$fac['profile_id'] ?>">
                                    <button type="submit"
                                       class="btn-academic-primary text-xs !py-1.5 !px-3.5 shadow-xs flex items-center gap-1.5"
                                       title="Manage publications, grants, and fill missing details for this faculty">
                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                        <span>Manage & Fill Details</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
