<?php
/**
 * Departmental Scholar — Research Assistant & Delegate Portal
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'super_admin']);

$db = Database::getConnection();
$currentUserId = user_id();

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

// 2. Fetch faculties assigned to this assistant
if (has_role('super_admin')) {
    // Super admins can manage any faculty
    $sql = "
        SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations, fp.slug,
               u.id as user_id, u.full_name, u.email, d.name as department_name, d.code as department_code,
               COUNT(p.id) as pub_count
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
} else {
    // Regular delegate / coordinator
    $sql = "
        SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations, fp.slug,
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

$pageTitle = 'Research Assistant Portal — Delegated Management';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner -->
<section class="bg-oxford-navy text-white py-10 border-b border-oxford-blue">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-mono font-semibold text-amber-300 uppercase tracking-widest">Delegated Administration</span>
                <h1 class="font-serif text-2xl sm:text-3xl font-bold text-white mt-1">Research Assistant Portal</h1>
                <p class="text-xs text-slate-300 mt-1 font-sans">
                    Manage research publications, grants, and achievements on behalf of your assigned faculty scholars.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] bg-white/10 border border-white/15 text-xs font-mono text-slate-200">
                    <i class="fa-solid fa-user-check text-emerald-400"></i>
                    <span>Assigned: <?= count($assignedFaculties) ?> Faculty Profile<?= count($assignedFaculties) !== 1 ? 's' : '' ?></span>
                </span>
            </div>
        </div>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <!-- ========================================================= -->
    <!-- MANDATORY: YOUR OWN PERSONAL FACULTY PROFILE CARD         -->
    <!-- ========================================================= -->
    <div class="mb-10">
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
                You have full personal access to manage your own scholarly record and profile.
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
            <!-- Assistant has no faculty profile yet: Provide 1-click initialization card -->
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
                                You do not have an active personal faculty profile linked to your account yet. Initialize your scholarly record to list your publications, sponsored research grants, and student mentorship.
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
    <!-- ASSIGNED FACULTY PROFILES SECTION                         -->
    <!-- ========================================================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6 pt-6 border-t border-slate-200">
        <div>
            <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-users text-slate-500 text-xs"></i>
                <span>Assigned Faculty Profiles to Manage</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5 font-sans">
                Profiles delegated to you by other faculty members for administrative support and record keeping.
            </p>
        </div>
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-100 border border-slate-200 text-xs font-mono text-slate-700">
                <span><?= count($assignedFaculties) ?> Delegated</span>
            </span>
        </div>
    </div>

    <?php if (empty($assignedFaculties)): ?>
        <div class="academic-card p-12 text-center text-slate-500 shadow-xs">
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                <i class="fa-solid fa-user-clock text-xl"></i>
            </div>
            <h3 class="font-serif text-base font-bold text-oxford-navy">No faculty profiles assigned yet</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto font-sans">
                Faculty members can delegate profile edit access to you by adding your registered email (<code><?= e(current_user()['email']) ?></code>) in their dashboard under "Assistants / Delegates".
            </p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($assignedFaculties as $fac): ?>
                <?php $photo = faculty_photo_url($fac['photo_url'] ?? null); ?>
                <div class="academic-card p-6 flex flex-col justify-between group shadow-xs hover:border-oxford-navy/40 transition">
                    <div>
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-[6px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center shadow-xs">
                                <?php if ($photo): ?>
                                    <img src="<?= e($photo) ?>" alt="<?= e($fac['full_name']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow min-w-0">
                                <span class="academic-tag font-mono text-[10px] mb-1 font-bold">
                                    <?= e($fac['department_code'] ?? 'SCHOLAR') ?>
                                </span>
                                <h3 class="font-serif font-bold text-oxford-navy text-base truncate">
                                    <?= e(($fac['salutation'] ? $fac['salutation'] . ' ' : '') . $fac['full_name']) ?>
                                </h3>
                                <p class="text-xs text-scholar-muted truncate font-medium"><?= e($fac['designation']) ?></p>
                                <p class="text-[11px] text-slate-400 font-mono truncate"><?= e($fac['email']) ?></p>
                            </div>
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

                    <div class="mt-5 pt-4 border-t border-scholar-border flex items-center justify-between gap-2">
                        <a href="<?= researcher_url($fac) ?>" target="_blank"
                           class="btn-academic-secondary text-xs !py-1.5 !px-3">
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            <span>Public View</span>
                        </a>
                        <form action="<?= url('assistant/switch.php') ?>" method="POST" class="inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="profile_id" value="<?= (int)$fac['profile_id'] ?>">
                            <button type="submit"
                               class="btn-academic-primary text-xs !py-1.5 !px-3.5 shadow-xs">
                                <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                <span>Manage Profile</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
