<?php
/**
 * Faculty Dashboard - Main Control Center
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// If super_admin or admin, redirect to their specific portal unless acting as faculty
$u = current_user();
if ($u['role'] === 'super_admin' && empty($_SESSION['active_faculty_profile_id'])) {
    redirect('admin/index.php');
} elseif ($u['role'] === 'admin' && empty($_SESSION['active_faculty_profile_id'])) {
    redirect('assistant/index.php');
}

$db = Database::getConnection();

// Determine which faculty profile is being managed
if (!empty($_SESSION['active_faculty_profile_id'])) {
    $profileId = (int)$_SESSION['active_faculty_profile_id'];
} else {
    // Current user's own faculty profile
    $pStmt = $db->prepare("SELECT id FROM faculty_profiles WHERE user_id = ? LIMIT 1");
    $pStmt->execute([user_id()]);
    $profileId = (int)$pStmt->fetchColumn();

    // If faculty profile doesn't exist yet, create one
    if (!$profileId) {
        $ins = $db->prepare("INSERT INTO faculty_profiles (user_id, salutation, designation, is_verified) VALUES (?, 'Dr.', 'Assistant Professor', 1)");
        $ins->execute([user_id()]);
        $profileId = (int)$db->lastInsertId();
    }
}

// Security verify
if (!can_manage_faculty_profile($profileId)) {
    abort(403, 'You do not have authorization to manage this faculty profile.');
}

// Fetch Profile Data
$stmt = $db->prepare("
    SELECT fp.*, u.full_name, u.email, d.name as department_name, d.code as department_code
    FROM faculty_profiles fp
    JOIN users u ON fp.user_id = u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    WHERE fp.id = ?
");
$stmt->execute([$profileId]);
$faculty = $stmt->fetch();

// Fetch Publications
$pubStmt = $db->prepare("SELECT * FROM publications WHERE faculty_profile_id = ? ORDER BY publication_year DESC, id DESC");
$pubStmt->execute([$profileId]);
$publications = $pubStmt->fetchAll();

// Fetch Projects
$projStmt = $db->prepare("SELECT * FROM projects WHERE faculty_profile_id = ? ORDER BY start_year DESC");
$projStmt->execute([$profileId]);
$projects = $projStmt->fetchAll();

// Fetch Patents
$patStmt = $db->prepare("SELECT * FROM patents WHERE faculty_profile_id = ? ORDER BY filing_date DESC");
$patStmt->execute([$profileId]);
$patents = $patStmt->fetchAll();

// Fetch Awards
$awdStmt = $db->prepare("SELECT * FROM awards WHERE faculty_profile_id = ? ORDER BY year DESC");
$awdStmt->execute([$profileId]);
$awards = $awdStmt->fetchAll();

// Fetch Education
$eduStmt = $db->prepare("SELECT * FROM education WHERE faculty_profile_id = ? ORDER BY year DESC");
$eduStmt->execute([$profileId]);
$education = $eduStmt->fetchAll();

// Fetch Teaching
$teachStmt = $db->prepare("SELECT * FROM teaching WHERE faculty_profile_id = ? ORDER BY academic_year DESC");
$teachStmt->execute([$profileId]);
$teaching = $teachStmt->fetchAll();

// Fetch Active Delegates
$delStmt = $db->prepare("
    SELECT fd.*, u.full_name, u.email 
    FROM faculty_delegates fd
    JOIN users u ON fd.delegate_user_id = u.id
    WHERE fd.faculty_user_id = ?
");
$delStmt->execute([$faculty['user_id']]);
$delegates = $delStmt->fetchAll();

$pageTitle = 'Faculty Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                    <?php if (!empty($faculty['photo_url'])): ?>
                        <img src="<?= safe_url($faculty['photo_url']) ?>" alt="Avatar" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-user-tie text-2xl text-slate-400"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($_SESSION['active_faculty_profile_id'])): ?>
                        <span class="inline-block px-2 py-0.5 rounded bg-amber-100 text-amber-900 text-[10px] font-mono font-bold uppercase tracking-wider mb-1">
                            <i class="fa-solid fa-user-gear mr-1"></i> Managing as Delegate/Admin
                        </span>
                    <?php endif; ?>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-serif-title leading-tight">
                        <?= e($faculty['salutation'] . ' ' . $faculty['full_name']) ?>
                    </h1>
                    <p class="text-xs text-slate-500">
                        <?= e($faculty['designation']) ?> • <?= e($faculty['department_name'] ?? 'Academic Faculty') ?> • <?= e($faculty['institution'] ?? 'ITER, SOA University') ?>
                    </p>
                </div>
            </div>

            <!-- Profile Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="<?= researcher_url($faculty) ?>" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-eye text-[11px]"></i>
                    <span>View Public Profile</span>
                </a>
                <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-white border border-slate-300 text-slate-800 hover:bg-slate-50 transition shadow-sm">
                    <i class="fa-solid fa-sliders text-[11px]"></i>
                    <span>Profile Settings</span>
                </a>
                <a href="<?= url('dashboard/add_publication.php?profile_id=' . $faculty['id']) ?>"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-iter-800 text-white hover:bg-iter-900 transition shadow-sm">
                    <i class="fa-solid fa-plus text-[11px]"></i>
                    <span>Add Publication</span>
                </a>
                <?php if (!empty($_SESSION['active_faculty_profile_id'])): ?>
                    <form action="<?= url('assistant/switch_back.php') ?>" method="POST" class="inline m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="px-3 py-2 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 transition inline-flex items-center gap-1">
                            <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Exit Delegate Mode
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- KPI Metric Cards (Google Scholar Sync) -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Total Publications</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= count($publications) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Articles & Chapters</span>
        </div>

        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Recorded Citations</span>
            <div class="text-2xl font-bold font-mono text-blue-700 mt-1"><?= number_format($faculty['total_citations']) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Google Scholar / Scopus</span>
        </div>

        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">h-Index</span>
            <div class="text-2xl font-bold font-mono text-slate-900 mt-1"><?= (int)$faculty['h_index'] ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Scholarly Impact</span>
        </div>

        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Funded Projects</span>
            <div class="text-2xl font-bold font-mono text-emerald-700 mt-1"><?= count($projects) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Extramural Grants</span>
        </div>

        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Patents / IP</span>
            <div class="text-2xl font-bold font-mono text-amber-700 mt-1"><?= count($patents) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Filed & Granted</span>
        </div>
    </div>

    <!-- Quick Navigation Sub-Bar -->
    <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
        <a href="#section-publications" class="text-xs font-semibold text-iter-800 bg-iter-50 px-3 py-1.5 rounded-lg">
            Publications (<?= count($publications) ?>)
        </a>
        <a href="#section-projects" class="text-xs font-semibold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition">
            Sponsored Projects (<?= count($projects) ?>)
        </a>
        <a href="#section-patents" class="text-xs font-semibold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition">
            Patents (<?= count($patents) ?>)
        </a>
        <a href="<?= url('dashboard/delegates.php?profile_id=' . $faculty['id']) ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition">
            <i class="fa-solid fa-users-gear mr-1 text-slate-400"></i> Assistants / Delegates (<?= count($delegates) ?>)
        </a>
    </div>

    <!-- SECTION 1: Publications Management -->
    <div id="section-publications" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-900 text-base font-serif-title">Research Publications & Articles</h2>
                <p class="text-xs text-slate-500 mt-0.5">Manage journal papers, conference proceedings, and book chapters</p>
            </div>
            <a href="<?= url('dashboard/add_publication.php?profile_id=' . $faculty['id']) ?>" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-iter-800 text-white hover:bg-iter-900 transition">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Publication</span>
            </a>
        </div>

        <?php if (empty($publications)): ?>
            <div class="p-12 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-newspaper text-3xl text-slate-300 mb-2"></i>
                <p>No research publications recorded yet.</p>
                <a href="<?= url('dashboard/add_publication.php?profile_id=' . $faculty['id']) ?>" class="mt-3 inline-block font-semibold text-iter-700 hover:underline">
                    Add your first publication
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-slate-500 font-mono text-[11px] uppercase">
                        <tr>
                            <th class="py-3 px-4 font-semibold">Title & Authors</th>
                            <th class="py-3 px-4 font-semibold">Type</th>
                            <th class="py-3 px-4 font-semibold">Venue / Journal</th>
                            <th class="py-3 px-4 font-semibold text-center">Year</th>
                            <th class="py-3 px-4 font-semibold text-center">Citations</th>
                            <th class="py-3 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($publications as $pub): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 max-w-md">
                                    <div class="font-semibold text-slate-900 leading-snug line-clamp-2"><?= e($pub['title']) ?></div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 truncate"><?= e($pub['authors']) ?></div>
                                    <?php if (!empty($pub['indexing'])): ?>
                                        <span class="inline-block mt-1 px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 text-[10px] font-medium border border-blue-200">
                                            <?= e($pub['indexing']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 font-mono capitalize">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[11px]">
                                        <?= e(str_replace('_', ' ', $pub['publication_type'])) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 italic text-slate-600 max-w-xs truncate">
                                    <?= e($pub['journal_conference_name']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-semibold text-slate-800">
                                    <?= e($pub['publication_year']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-slate-900">
                                    <?= (int)$pub['citation_count'] ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="<?= url('dashboard/edit_publication.php?id=' . $pub['id']) ?>" 
                                           class="p-1.5 text-slate-500 hover:text-iter-800 hover:bg-slate-100 rounded transition" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="POST" action="<?= url('dashboard/delete_item.php') ?>" class="inline" onsubmit="return confirm('Are you sure you want to delete this publication?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="type" value="publication">
                                            <input type="hidden" name="id" value="<?= $pub['id'] ?>">
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition" title="Delete">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 2: Sponsored Research Projects -->
    <div id="section-projects" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-900 text-base font-serif-title">Sponsored Research Grants & Projects</h2>
                <p class="text-xs text-slate-500 mt-0.5">Funded grants from SERB, DST, DRDO, AICTE, and industry sponsors</p>
            </div>
            <a href="<?= url('dashboard/add_project.php?profile_id=' . $faculty['id']) ?>" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-iter-800 text-white hover:bg-iter-900 transition">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Funded Project</span>
            </a>
        </div>

        <?php if (empty($projects)): ?>
            <div class="p-8 text-center text-slate-500 text-xs">
                <p>No sponsored research projects recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-3">
                <?php foreach ($projects as $proj): ?>
                    <div class="flex items-start justify-between gap-4 p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <div>
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono <?= $proj['status'] === 'ongoing' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                <?= e($proj['status']) ?>
                            </span>
                            <h3 class="text-xs font-bold text-slate-900 mt-1"><?= e($proj['title']) ?></h3>
                            <p class="text-[11px] text-slate-600 mt-0.5">
                                Agency: <strong><?= e($proj['funding_agency']) ?></strong> | Role: <span class="uppercase font-mono font-bold"><?= e($proj['role']) ?></span>
                            </p>
                        </div>
                        <div class="text-right flex items-center gap-3">
                            <div>
                                <span class="text-xs font-bold text-emerald-700 font-mono">₹<?= number_format((float)$proj['amount_lakhs'], 2) ?> Lakhs</span>
                                <span class="block text-[10px] text-slate-400 font-mono"><?= e($proj['start_year'] ?? '') ?> - <?= e($proj['end_year'] ?? 'Present') ?></span>
                            </div>
                            <form method="POST" action="<?= url('dashboard/delete_item.php') ?>" class="inline" onsubmit="return confirm('Delete this project?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="project">
                                <input type="hidden" name="id" value="<?= $proj['id'] ?>">
                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 3: Patents -->
    <div id="section-patents" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-900 text-base font-serif-title">Patents & Intellectual Property</h2>
                <p class="text-xs text-slate-500 mt-0.5">Patents filed, published, and granted</p>
            </div>
            <a href="<?= url('dashboard/add_patent.php?profile_id=' . $faculty['id']) ?>" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-iter-800 text-white hover:bg-iter-900 transition">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Patent</span>
            </a>
        </div>

        <?php if (empty($patents)): ?>
            <div class="p-8 text-center text-slate-500 text-xs">
                <p>No patents recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-3">
                <?php foreach ($patents as $pat): ?>
                    <div class="flex items-start justify-between gap-4 p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <div>
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono <?= $pat['status'] === 'granted' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= e($pat['status']) ?>
                            </span>
                            <h3 class="text-xs font-bold text-slate-900 mt-1"><?= e($pat['title']) ?></h3>
                            <p class="text-[11px] text-slate-600 mt-0.5">
                                Patent No: <strong><?= e($pat['patent_number'] ?? 'Pending') ?></strong> (<?= e($pat['country']) ?>)
                            </p>
                        </div>
                        <div class="text-right flex items-center gap-3">
                            <span class="text-xs font-mono text-slate-500"><?= e($pat['grant_date'] ?? $pat['filing_date'] ?? '') ?></span>
                            <form method="POST" action="<?= url('dashboard/delete_item.php') ?>" class="inline" onsubmit="return confirm('Delete this patent?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="patent">
                                <input type="hidden" name="id" value="<?= $pat['id'] ?>">
                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 4: Honors & Awards -->
    <?php if (!empty($awards)): ?>
    <div id="section-awards" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200">
            <h2 class="font-bold text-slate-900 text-base font-serif-title">Honors, Awards & Recognitions</h2>
            <p class="text-xs text-slate-500 mt-0.5">Academic honors and prestigious recognitions</p>
        </div>
        <div class="divide-y divide-slate-100 p-5 space-y-3">
            <?php foreach ($awards as $awd): ?>
                <div class="flex items-start justify-between gap-4 p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900"><?= e($awd['title']) ?></h3>
                        <p class="text-[11px] text-slate-600 mt-0.5"><?= e($awd['awarding_body']) ?></p>
                        <?php if (!empty($awd['description'])): ?>
                            <p class="text-[11px] text-slate-500 mt-0.5"><?= e($awd['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right flex items-center gap-3">
                        <span class="text-xs font-mono font-bold text-slate-700"><?= e($awd['year']) ?></span>
                        <form method="POST" action="<?= url('dashboard/delete_item.php') ?>" class="inline" onsubmit="return confirm('Remove this award?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="award">
                            <input type="hidden" name="id" value="<?= $awd['id'] ?>">
                            <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTION 5: Education & Teaching Overview -->
    <?php if (!empty($education) || !empty($teaching)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php if (!empty($education)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
            <h3 class="font-bold text-slate-900 text-sm font-serif-title flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-iter-700"></i>
                <span>Educational Qualifications</span>
            </h3>
            <div class="space-y-2">
                <?php foreach ($education as $edu): ?>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-slate-900 block"><?= e($edu['degree']) ?></span>
                            <span class="text-slate-500 text-[11px]"><?= e($edu['institution']) ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-slate-600 text-[11px]"><?= e($edu['year']) ?></span>
                            <form method="POST" action="<?= url('dashboard/delete_item.php') ?>" class="inline" onsubmit="return confirm('Delete this qualification?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="education">
                                <input type="hidden" name="id" value="<?= $edu['id'] ?>">
                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete">
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($teaching)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
            <h3 class="font-bold text-slate-900 text-sm font-serif-title flex items-center gap-2">
                <i class="fa-solid fa-chalkboard-user text-iter-700"></i>
                <span>Courses & Teaching</span>
            </h3>
            <div class="space-y-2">
                <?php foreach ($teaching as $t): ?>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-slate-900 block"><?= e($t['course_name']) ?></span>
                            <span class="text-slate-500 text-[11px] font-mono"><?= e($t['course_code'] ?? '') ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-slate-600 text-[11px]"><?= e($t['academic_year'] ?? '') ?></span>
                            <form method="POST" action="<?= url('dashboard/delete_item.php') ?>" class="inline" onsubmit="return confirm('Remove this course?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="teaching">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete">
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
