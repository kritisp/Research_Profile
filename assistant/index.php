<?php
/**
 * Assistant / Research Coordinator Portal
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
        SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations,
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
    $assignedFaculties = $stmt->fetchAll();
} else {
    // Regular delegate / coordinator
    $sql = "
        SELECT fp.id as profile_id, fp.salutation, fp.designation, fp.photo_url, fp.total_citations,
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
    $assignedFaculties = $stmt->fetchAll();
}

$pageTitle = 'Assistant / Delegate Portal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-slate-900 text-white py-10 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-mono font-bold text-amber-300 uppercase tracking-widest">Delegated Administration</span>
                <h1 class="text-2xl sm:text-3xl font-bold font-serif-title mt-1">Research Assistant Portal</h1>
                <p class="text-xs text-slate-300 mt-1">
                    Manage research publications, grants, and achievements on behalf of your assigned ITER faculty members.
                </p>
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300">
                    <i class="fa-solid fa-user-check text-emerald-400"></i>
                    <span>Assigned: <?= count($assignedFaculties) ?> Faculty Profile<?= count($assignedFaculties) !== 1 ? 's' : '' ?></span>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-sm font-bold text-slate-800 uppercase font-mono tracking-wider">
            Select a Faculty Member to Manage
        </h2>
    </div>

    <?php if (empty($assignedFaculties)): ?>
        <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 text-slate-500">
            <i class="fa-solid fa-user-clock text-4xl text-slate-300 mb-3"></i>
            <h3 class="text-base font-bold text-slate-800">No faculty profiles assigned yet</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Faculty members can delegate access to you by adding your registered email (<code><?= e(current_user()['email']) ?></code>) in their dashboard under "Assistants / Delegates".
            </p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($assignedFaculties as $fac): ?>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                <?php if (!empty($fac['photo_url'])): ?>
                                    <img src="<?= safe_url($fac['photo_url']) ?>" alt="Avatar" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow min-w-0">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-iter-50 text-iter-800 mb-1">
                                    <?= e($fac['department_code'] ?? 'ITER') ?>
                                </span>
                                <h3 class="font-bold text-slate-900 text-sm truncate">
                                    <?= e($fac['salutation'] . ' ' . $fac['full_name']) ?>
                                </h3>
                                <p class="text-xs text-slate-500 truncate"><?= e($fac['designation']) ?></p>
                                <p class="text-[11px] text-slate-400 font-mono truncate"><?= e($fac['email']) ?></p>
                            </div>
                        </div>

                        <!-- Stats summary -->
                        <div class="mt-4 grid grid-cols-2 gap-2 text-center text-xs p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                            <div>
                                <span class="text-[10px] text-slate-400 font-mono block uppercase">Publications</span>
                                <span class="font-bold font-mono text-slate-900"><?= (int)$fac['pub_count'] ?></span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-mono block uppercase">Citations</span>
                                <span class="font-bold font-mono text-blue-700"><?= number_format($fac['total_citations']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                        <a href="<?= researcher_url(['slug' => $fac['slug'] ?? '', 'id' => $fac['profile_id']]) ?>" target="_blank"
                           class="text-xs text-slate-500 hover:text-slate-800 transition">
                            <i class="fa-solid fa-eye mr-1"></i> Public
                        </a>
                        <form action="<?= url('assistant/switch.php') ?>" method="POST" class="inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="profile_id" value="<?= (int)$fac['profile_id'] ?>">
                            <button type="submit"
                               class="px-3.5 py-1.5 rounded-lg bg-iter-800 hover:bg-iter-900 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
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
