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

// Fetch faculties assigned to this assistant
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
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] bg-white/10 border border-white/15 text-xs font-mono text-slate-200">
                    <i class="fa-solid fa-user-check text-emerald-400"></i>
                    <span>Assigned: <?= count($assignedFaculties) ?> Faculty Profile<?= count($assignedFaculties) !== 1 ? 's' : '' ?></span>
                </span>
            </div>
        </div>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider">
            Select an Assigned Faculty Profile to Manage
        </h2>
    </div>

    <?php if (empty($assignedFaculties)): ?>
        <div class="academic-card p-12 text-center text-slate-500">
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
                <div class="academic-card p-6 flex flex-col justify-between group">
                    <div>
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-[8px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center shadow-xs">
                                <?php if (!empty($fac['photo_url'])): ?>
                                    <img src="<?= safe_url($fac['photo_url']) ?>" alt="Avatar" class="w-full h-full object-cover">
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
                                <span class="font-bold font-mono text-oxford-blue"><?= number_format($fac['total_citations']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-scholar-border flex items-center justify-between gap-2">
                        <a href="<?= researcher_url($fac) ?>" target="_blank"
                           class="btn-academic-secondary text-xs !py-1.5 !px-3">
                            <i class="fa-solid fa-eye text-[10px]"></i>
                            <span>Public</span>
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
