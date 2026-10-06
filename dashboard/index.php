<?php
/**
 * Departmental Scholar — Faculty Dashboard
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Focus: High Legibility, Typographic Hierarchy, Refined Borders, Subdued Academic Elevation
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

// Security verify ownership or delegation
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
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Publications
$pubStmt = $db->prepare("SELECT * FROM publications WHERE faculty_profile_id = ? ORDER BY publication_year DESC, id DESC");
$pubStmt->execute([$profileId]);
$publications = $pubStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Projects
$projStmt = $db->prepare("SELECT * FROM projects WHERE faculty_profile_id = ? ORDER BY start_year DESC, id DESC");
$projStmt->execute([$profileId]);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Patents
$patStmt = $db->prepare("SELECT * FROM patents WHERE faculty_profile_id = ? ORDER BY filing_date DESC, id DESC");
$patStmt->execute([$profileId]);
$patents = $patStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Awards
$awdStmt = $db->prepare("SELECT * FROM awards WHERE faculty_profile_id = ? ORDER BY year DESC, id DESC");
$awdStmt->execute([$profileId]);
$awards = $awdStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Education
$eduStmt = $db->prepare("SELECT * FROM education WHERE faculty_profile_id = ? ORDER BY year DESC");
$eduStmt->execute([$profileId]);
$education = $eduStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Teaching
$teachStmt = $db->prepare("SELECT * FROM teaching WHERE faculty_profile_id = ? ORDER BY academic_year DESC");
$teachStmt->execute([$profileId]);
$teaching = $teachStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Academic Experience / Appointments
$expStmt = $db->prepare("SELECT * FROM academic_experience WHERE faculty_profile_id = ? ORDER BY start_year DESC, is_current DESC, id DESC");
$expStmt->execute([$profileId]);
$experience = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Delegates
$delStmt = $db->prepare("
    SELECT fd.*, u.full_name, u.email 
    FROM faculty_delegates fd
    JOIN users u ON fd.delegate_user_id = u.id
    WHERE fd.faculty_user_id = ?
");
$delStmt->execute([$faculty['user_id']]);
$delegates = $delStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate profile completeness score
$completenessFields = [
    'photo_url' => !empty($faculty['photo_url']),
    'bio' => !empty($faculty['bio']),
    'department_id' => !empty($faculty['department_id']),
    'research_interests' => !empty($faculty['research_interests']),
    'orcid_id' => !empty($faculty['orcid_id']),
    'google_scholar_url' => !empty($faculty['google_scholar_url']),
    'publications' => count($publications) > 0,
    'education' => count($education) > 0,
];
$completedCount = count(array_filter($completenessFields));
$completenessPercent = round(($completedCount / count($completenessFields)) * 100);

$pageTitle = 'Faculty Dashboard — ' . $faculty['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Dashboard Masthead -->
<div class="bg-white border-b border-scholar-border shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-[8px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center shadow-xs">
                    <?php $photo = faculty_photo_url($faculty['photo_url'] ?? null); ?>
                    <?php if ($photo): ?>
                        <img src="<?= $photo ?>" alt="Avatar" class="w-full h-full object-cover"
                             onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                        <div class="hidden text-slate-300 flex items-center justify-center w-full h-full">
                            <i class="fa-solid fa-user-graduate text-2xl"></i>
                        </div>
                    <?php else: ?>
                        <i class="fa-solid fa-user-graduate text-2xl text-slate-300"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <?php if (!empty($_SESSION['active_faculty_profile_id'])): ?>
                            <span class="academic-tag academic-tag-gold font-mono text-[10px] font-bold uppercase mb-1">
                                <i class="fa-solid fa-user-gear mr-1"></i> Active Delegate Mode
                            </span>
                        <?php endif; ?>
                        <span class="academic-tag academic-tag-green text-[10px] !py-0.5">
                            <i class="fa-solid fa-circle-check text-[10px]"></i> Verified
                        </span>
                    </div>
                    <h1 class="font-serif text-xl sm:text-2xl font-bold text-oxford-navy leading-tight mt-0.5">
                        <?= e(($faculty['salutation'] ? $faculty['salutation'] . ' ' : '') . $faculty['full_name']) ?>
                    </h1>
                    <p class="text-xs text-scholar-muted mt-0.5 font-sans">
                        <?= e($faculty['designation']) ?> • <?= e($faculty['department_name'] ?? 'Faculty Division') ?> • <?= e($faculty['institution'] ?? 'ITER, SOA University') ?>
                    </p>
                </div>
            </div>

            <!-- Profile Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= researcher_url($faculty) ?>" target="_blank"
                   class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    <span>Public View</span>
                </a>
                <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>"
                   class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-sliders text-[10px]"></i>
                    <span>Profile Settings</span>
                </a>
                <a href="<?= url('dashboard/add_publication.php?profile_id=' . $faculty['id']) ?>"
                   class="btn-academic-primary text-xs !py-1.5 !px-3.5 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Publication</span>
                </a>
                <?php if (!empty($_SESSION['active_faculty_profile_id'])): ?>
                    <form action="<?= url('assistant/switch_back.php') ?>" method="POST" class="inline m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-academic-secondary text-xs !py-1.5 !px-3 !bg-rose-50 !text-rose-800 !border-rose-200 hover:!bg-rose-100 shadow-xs">
                            <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i>
                            <span>Exit Delegate Mode</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>

        <!-- Profile Completeness Meter -->
        <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-3">
                <span class="text-slate-500 font-medium">Profile Completeness:</span>
                <div class="w-36 h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                    <div class="h-full bg-oxford-slate transition-all duration-300" style="width: <?= $completenessPercent ?>%;"></div>
                </div>
                <span class="font-mono font-bold text-oxford-navy"><?= $completenessPercent ?>%</span>
            </div>
            <?php if ($completenessPercent < 100): ?>
                <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="text-oxford-slate hover:underline font-semibold flex items-center gap-1">
                    <span>Complete your academic identifiers and bio</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            <?php else: ?>
                <span class="text-emerald-700 font-semibold flex items-center gap-1">
                    <i class="fa-solid fa-circle-check text-xs"></i>
                    <span>All essential portfolio items completed</span>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="academic-card p-4 shadow-xs">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Publications</span>
            <div class="text-2xl font-bold font-mono text-oxford-navy mt-1"><?= count($publications) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Articles & Chapters</span>
        </div>

        <div class="academic-card p-4 shadow-xs">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Citations</span>
            <div class="text-2xl font-bold font-mono text-oxford-slate mt-1"><?= number_format($faculty['total_citations']) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Self-Reported Metric</span>
        </div>

        <div class="academic-card p-4 shadow-xs">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">h-Index</span>
            <div class="text-2xl font-bold font-mono text-oxford-navy mt-1"><?= (int)$faculty['h_index'] ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Scholarly Impact</span>
        </div>

        <div class="academic-card p-4 shadow-xs">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Grants</span>
            <div class="text-2xl font-bold font-mono text-emerald-700 mt-1"><?= count($projects) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Sponsored Projects</span>
        </div>

        <div class="academic-card p-4 shadow-xs col-span-2 sm:col-span-1">
            <span class="text-[10px] font-mono font-bold uppercase text-slate-400 block">Patents / IP</span>
            <div class="text-2xl font-bold font-mono text-amber-700 mt-1"><?= count($patents) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Filed & Granted</span>
        </div>
    </div>

    <!-- Quick Navigation Sub-Bar with Right-Aligned Add Delegate Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-scholar-border pb-3 text-xs font-semibold">
        <!-- Left: In-page Section Navigation -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="#section-publications" class="academic-tag !bg-oxford-navy !text-white !border-oxford-navy px-3 py-1.5 shadow-xs">
                Publications (<?= count($publications) ?>)
            </a>
            <a href="#section-projects" class="academic-tag hover:border-oxford-slate px-3 py-1.5 transition">
                Projects (<?= count($projects) ?>)
            </a>
            <a href="#section-patents" class="academic-tag hover:border-oxford-slate px-3 py-1.5 transition">
                Patents (<?= count($patents) ?>)
            </a>
            <a href="#section-experience" class="academic-tag hover:border-oxford-slate px-3 py-1.5 transition">
                Appointments (<?= count($experience) ?>)
            </a>
            <a href="#section-education" class="academic-tag hover:border-oxford-slate px-3 py-1.5 transition">
                Education (<?= count($education) ?>)
            </a>
            <a href="#section-teaching" class="academic-tag hover:border-oxford-slate px-3 py-1.5 transition">
                Teaching (<?= count($teaching) ?>)
            </a>
            <a href="#section-awards" class="academic-tag hover:border-oxford-slate px-3 py-1.5 transition">
                Awards (<?= count($awards) ?>)
            </a>
        </div>

        <!-- Right: Glowing / Highlighted Add Delegate/Assistant Button -->
        <div class="flex-shrink-0">
            <a href="<?= url('dashboard/delegates.php?profile_id=' . $faculty['id']) ?>" 
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-md text-xs font-semibold text-white bg-gradient-to-r from-blue-600 via-indigo-600 to-oxford-navy shadow-xs hover:shadow-md hover:from-blue-700 hover:to-indigo-800 transition-all duration-200 border border-blue-400/40 ring-2 ring-blue-500/20 hover:ring-blue-500/50 group">
                <i class="fa-solid fa-user-plus text-[11px] text-blue-200 group-hover:scale-110 transition-transform"></i>
                <span>Add Delegate / Assistant</span>
                <?php if (count($delegates) > 0): ?>
                    <span class="px-1.5 py-0.2 rounded-full bg-white/20 text-[10px] font-mono"><?= count($delegates) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- SECTION 1: Publications Management -->
    <div id="section-publications" class="academic-card overflow-hidden shadow-xs">
        <div class="p-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h2 class="font-serif font-bold text-oxford-navy text-lg">Publications & Research Papers</h2>
                <p class="text-xs text-scholar-muted mt-0.5">Peer-reviewed journal articles, conference papers, and book chapters</p>
            </div>
            <a href="<?= url('dashboard/add_publication.php?profile_id=' . $faculty['id']) ?>" 
               class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Publication</span>
            </a>
        </div>

        <?php if (empty($publications)): ?>
            <div class="p-12 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-newspaper text-3xl text-slate-300 mb-2"></i>
                <p class="font-serif text-sm font-bold text-oxford-navy">No publications recorded</p>
                <p class="text-xs text-slate-500 mt-1">Add your first publication to build your scholarly record.</p>
                <a href="<?= url('dashboard/add_publication.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                    Add Publication
                </a>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th class="py-3 px-4">Title & Authors</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Venue</th>
                            <th class="py-3 px-4 text-center">Year</th>
                            <th class="py-3 px-4 text-center">Citations</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($publications as $pub): ?>
                            <tr>
                                <td class="py-3 px-4 max-w-md">
                                    <div class="font-semibold text-oxford-navy leading-snug line-clamp-2"><?= e($pub['title']) ?></div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 truncate"><?= e($pub['authors']) ?></div>
                                    <?php if (!empty($pub['indexing'])): ?>
                                        <span class="academic-tag academic-tag-gold text-[10px] mt-1">
                                            <?= e($pub['indexing']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 capitalize font-mono text-[11px]">
                                    <?= e(str_replace('_', ' ', $pub['publication_type'])) ?>
                                </td>
                                <td class="py-3 px-4 max-w-xs truncate italic text-slate-600 font-serif">
                                    <?= e($pub['journal_conference_name']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-semibold">
                                    <?= e($pub['publication_year']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-oxford-navy">
                                    <?= (int)$pub['citation_count'] ?>
                                </td>
                                <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                                    <a href="<?= url('dashboard/edit_publication.php?id=' . $pub['id']) ?>" 
                                       class="text-oxford-slate hover:text-oxford-navy font-semibold text-xs">
                                        Edit
                                    </a>
                                    <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Are you sure you want to delete this publication?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="type" value="publication">
                                        <input type="hidden" name="id" value="<?= (int)$pub['id'] ?>">
                                        <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold ml-2">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card Stack View -->
            <div class="md:hidden divide-y divide-slate-100 p-4 space-y-4">
                <?php foreach ($publications as $pub): ?>
                    <div class="pt-3 first:pt-0 space-y-2">
                        <div class="font-serif font-bold text-sm text-oxford-navy leading-snug"><?= e($pub['title']) ?></div>
                        <div class="text-xs text-slate-600"><?= e($pub['authors']) ?></div>
                        <div class="text-xs text-oxford-slate italic font-serif"><?= e($pub['journal_conference_name']) ?> (<?= e($pub['publication_year']) ?>)</div>
                        <div class="flex items-center justify-between text-xs pt-1">
                            <span class="font-mono text-slate-500">Citations: <strong><?= (int)$pub['citation_count'] ?></strong></span>
                            <div class="flex items-center gap-3">
                                <a href="<?= url('dashboard/edit_publication.php?id=' . $pub['id']) ?>" class="font-semibold text-oxford-slate">Edit</a>
                                <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Delete this publication?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="type" value="publication">
                                    <input type="hidden" name="id" value="<?= (int)$pub['id'] ?>">
                                    <button type="submit" class="text-rose-600 font-semibold">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 2: Sponsored Research Projects -->
    <div id="section-projects" class="academic-card overflow-hidden shadow-xs">
        <div class="p-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h2 class="font-serif font-bold text-oxford-navy text-lg">Sponsored Research Projects & Grants</h2>
                <p class="text-xs text-scholar-muted mt-0.5">Extramural funding from government agencies, industry, and foundations</p>
            </div>
            <a href="<?= url('dashboard/add_project.php?profile_id=' . $faculty['id']) ?>" 
               class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Project</span>
            </a>
        </div>

        <?php if (empty($projects)): ?>
            <div class="p-10 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-folder-open text-2xl text-slate-300 mb-2"></i>
                <p class="font-serif text-sm font-bold text-oxford-navy">No funded projects logged</p>
                <a href="<?= url('dashboard/add_project.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                    Add Sponsored Project
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-4">
                <?php foreach ($projects as $proj): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pt-3 first:pt-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="academic-tag font-mono text-[10px] font-bold uppercase <?= $proj['status'] === 'ongoing' ? 'academic-tag-green' : '' ?>">
                                    <?= e($proj['status']) ?>
                                </span>
                                <h3 class="font-serif font-bold text-base text-oxford-navy"><?= e($proj['title']) ?></h3>
                            </div>
                            <p class="text-xs text-slate-600 mt-1">
                                <strong>Agency:</strong> <?= e($proj['funding_agency']) ?>
                                <?php if (!empty($proj['project_code'])): ?> | <strong>Code:</strong> <span class="font-mono"><?= e($proj['project_code']) ?></span><?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center sm:flex-col sm:items-end justify-between sm:justify-start gap-2 flex-shrink-0">
                            <span class="font-bold text-sm text-oxford-navy font-mono">₹<?= number_format((float)$proj['amount_lakhs'], 2) ?> Lakhs</span>
                            <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Delete this project?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="project">
                                <input type="hidden" name="id" value="<?= (int)$proj['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 3: Patents & IP -->
    <div id="section-patents" class="academic-card overflow-hidden shadow-xs">
        <div class="p-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h2 class="font-serif font-bold text-oxford-navy text-lg">Patents & Intellectual Property</h2>
                <p class="text-xs text-scholar-muted mt-0.5">National and international filed or granted patents</p>
            </div>
            <a href="<?= url('dashboard/add_patent.php?profile_id=' . $faculty['id']) ?>" 
               class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Patent</span>
            </a>
        </div>

        <?php if (empty($patents)): ?>
            <div class="p-10 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-stamp text-2xl text-slate-300 mb-2"></i>
                <p class="font-serif text-sm font-bold text-oxford-navy">No patents recorded</p>
                <a href="<?= url('dashboard/add_patent.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                    Add Patent
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-4">
                <?php foreach ($patents as $pat): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pt-3 first:pt-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="academic-tag font-mono text-[10px] font-bold uppercase <?= $pat['status'] === 'granted' ? 'academic-tag-gold' : '' ?>">
                                    <?= e($pat['status']) ?>
                                </span>
                                <h3 class="font-serif font-bold text-base text-oxford-navy"><?= e($pat['title']) ?></h3>
                            </div>
                            <p class="text-xs text-slate-600 mt-1">
                                <strong>No:</strong> <span class="font-mono"><?= e($pat['patent_number'] ?? 'Pending') ?></span>
                                | <strong>Country:</strong> <?= e($pat['country']) ?>
                            </p>
                        </div>
                        <div class="flex items-center sm:flex-col sm:items-end justify-between sm:justify-start gap-2 flex-shrink-0">
                            <?php if (!empty($pat['grant_date'])): ?>
                                <span class="text-xs font-mono text-slate-500">Granted: <?= e($pat['grant_date']) ?></span>
                            <?php endif; ?>
                            <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Delete this patent?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="patent">
                                <input type="hidden" name="id" value="<?= (int)$pat['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 4: Academic Appointments & Career History -->
    <div id="section-experience" class="academic-card overflow-hidden shadow-xs">
        <div class="p-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h2 class="font-serif font-bold text-oxford-navy text-lg">Academic Appointments & Career History</h2>
                <p class="text-xs text-scholar-muted mt-0.5">Faculty positions, postdoctoral fellowships, and academic leadership roles</p>
            </div>
            <a href="<?= url('dashboard/add_appointment.php?profile_id=' . $faculty['id']) ?>" 
               class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Appointment</span>
            </a>
        </div>

        <?php if (empty($experience)): ?>
            <div class="p-10 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-briefcase text-2xl text-slate-300 mb-2"></i>
                <p class="font-serif text-sm font-bold text-oxford-navy">No academic appointments recorded</p>
                <a href="<?= url('dashboard/add_appointment.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                    Add Appointment
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-4">
                <?php foreach ($experience as $exp): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pt-3 first:pt-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <?php if ($exp['is_current']): ?>
                                    <span class="academic-tag academic-tag-gold font-mono text-[10px] font-bold uppercase">
                                        Current
                                    </span>
                                <?php endif; ?>
                                <h3 class="font-serif font-bold text-base text-oxford-navy"><?= e($exp['position_title']) ?></h3>
                            </div>
                            <p class="text-xs text-slate-700 mt-1 font-medium">
                                <?= e($exp['organization']) ?>
                                <?php if (!empty($exp['department'])): ?>
                                    <span class="text-slate-400 font-normal">•</span> <?= e($exp['department']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($exp['description'])): ?>
                                <p class="text-xs text-slate-500 mt-1"><?= nl2br(e($exp['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center sm:flex-col sm:items-end justify-between sm:justify-start gap-2 flex-shrink-0">
                            <span class="text-xs font-mono font-semibold text-oxford-navy">
                                <?= e($exp['start_year']) ?> — <?= $exp['is_current'] ? 'Present' : e($exp['end_year'] ?? '') ?>
                            </span>
                            <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Remove this appointment record?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="experience">
                                <input type="hidden" name="id" value="<?= (int)$exp['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 5: Education & Academic Credentials -->
    <div id="section-education" class="academic-card overflow-hidden shadow-xs">
        <div class="p-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h2 class="font-serif font-bold text-oxford-navy text-lg">Education & Qualifications</h2>
                <p class="text-xs text-scholar-muted mt-0.5">Doctoral, postgraduate, and undergraduate degrees</p>
            </div>
            <a href="<?= url('dashboard/add_education.php?profile_id=' . $faculty['id']) ?>" 
               class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Qualification</span>
            </a>
        </div>

        <?php if (empty($education)): ?>
            <div class="p-10 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-graduation-cap text-2xl text-slate-300 mb-2"></i>
                <p class="font-serif text-sm font-bold text-oxford-navy">No qualifications recorded</p>
                <a href="<?= url('dashboard/add_education.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                    Add Qualification
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-4">
                <?php foreach ($education as $edu): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pt-3 first:pt-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="academic-tag font-mono text-[10px] font-bold">
                                    <?= e($edu['year']) ?>
                                </span>
                                <h3 class="font-serif font-bold text-base text-oxford-navy"><?= e($edu['degree']) ?></h3>
                            </div>
                            <p class="text-xs text-slate-700 mt-1 font-medium">
                                <?= e($edu['institution']) ?>
                                <?php 
                                    $spec = !empty($edu['specialization']) ? $edu['specialization'] : (!empty($edu['field_of_study']) ? $edu['field_of_study'] : '');
                                    if (!empty($spec)): 
                                ?>
                                    <span class="text-slate-400 font-normal">•</span> <?= e($spec) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center sm:flex-col sm:items-end justify-between sm:justify-start gap-2 flex-shrink-0">
                            <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Remove this qualification?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="education">
                                <input type="hidden" name="id" value="<?= (int)$edu['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 6: Teaching & Course Assignments -->
    <div id="section-teaching" class="academic-card overflow-hidden shadow-xs">
        <div class="p-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h2 class="font-serif font-bold text-oxford-navy text-lg">Teaching & Instruction</h2>
                <p class="text-xs text-scholar-muted mt-0.5">Undergraduate, postgraduate, and doctoral course assignments</p>
            </div>
            <a href="<?= url('dashboard/add_teaching.php?profile_id=' . $faculty['id']) ?>" 
               class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Teaching</span>
            </a>
        </div>

        <?php if (empty($teaching)): ?>
            <div class="p-10 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-chalkboard-user text-2xl text-slate-300 mb-2"></i>
                <p class="font-serif text-sm font-bold text-oxford-navy">No teaching courses recorded</p>
                <a href="<?= url('dashboard/add_teaching.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                    Add Teaching Assignment
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-4">
                <?php foreach ($teaching as $teach): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pt-3 first:pt-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="academic-tag academic-tag-gold font-mono text-[10px] font-bold">
                                    <?= e($teach['level']) ?>
                                </span>
                                <h3 class="font-serif font-bold text-base text-oxford-navy"><?= e($teach['course_title']) ?></h3>
                            </div>
                            <p class="text-xs text-slate-600 mt-1">
                                <?php if (!empty($teach['course_code'])): ?>
                                    <strong>Code:</strong> <span class="font-mono"><?= e($teach['course_code']) ?></span> |
                                <?php endif; ?>
                                <strong>Academic Year:</strong> <?= e($teach['academic_year'] ?? 'Current') ?>
                            </p>
                        </div>
                        <div class="flex items-center sm:flex-col sm:items-end justify-between sm:justify-start gap-2 flex-shrink-0">
                            <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Remove this course?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="teaching">
                                <input type="hidden" name="id" value="<?= (int)$teach['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 7: Honors & Awards -->
    <div id="section-awards" class="academic-card overflow-hidden shadow-xs">
        <div class="p-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
            <div>
                <h2 class="font-serif font-bold text-oxford-navy text-lg">Honors, Awards & Distinctions</h2>
                <p class="text-xs text-scholar-muted mt-0.5">Professional awards, best paper recognitions, and society elevations</p>
            </div>
            <a href="<?= url('dashboard/add_award.php?profile_id=' . $faculty['id']) ?>" 
               class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Add Award</span>
            </a>
        </div>

        <?php if (empty($awards)): ?>
            <div class="p-10 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-trophy text-2xl text-slate-300 mb-2"></i>
                <p class="font-serif text-sm font-bold text-oxford-navy">No awards recorded</p>
                <a href="<?= url('dashboard/add_award.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                    Add Honor or Award
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 p-5 space-y-4">
                <?php foreach ($awards as $awd): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pt-3 first:pt-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="academic-tag font-mono text-[10px] font-bold">
                                    <?= e($awd['year']) ?>
                                </span>
                                <h3 class="font-serif font-bold text-base text-oxford-navy"><?= e($awd['title']) ?></h3>
                            </div>
                            <p class="text-xs text-slate-700 mt-1 font-medium">
                                <?= e($awd['awarding_body']) ?>
                            </p>
                            <?php if (!empty($awd['description'])): ?>
                                <p class="text-xs text-slate-500 mt-1"><?= nl2br(e($awd['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center sm:flex-col sm:items-end justify-between sm:justify-start gap-2 flex-shrink-0">
                            <form action="<?= url('dashboard/delete_item.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Remove this award?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="award">
                                <input type="hidden" name="id" value="<?= (int)$awd['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
