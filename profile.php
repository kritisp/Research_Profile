<?php
/**
 * Individual Academic Faculty Profile (Google Scholar & Ivy League Aesthetic)
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getConnection();

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug      = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if ($profileId <= 0 && empty($slug)) {
    set_flash('warning', 'Profile not found.');
    redirect('directory.php');
}

// 1. Fetch Faculty Profile with User & Department Details (via slug or ID)
if (!empty($slug)) {
    $stmt = $db->prepare("
        SELECT fp.*, u.full_name, u.email, u.status as user_status, 
               d.name as department_name, d.code as department_code
        FROM faculty_profiles fp
        JOIN users u ON fp.user_id = u.id
        LEFT JOIN departments d ON fp.department_id = d.id
        WHERE fp.slug = ? AND fp.is_verified = 1
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $faculty = $stmt->fetch();
} else {
    $stmt = $db->prepare("
        SELECT fp.*, u.full_name, u.email, u.status as user_status, 
               d.name as department_name, d.code as department_code
        FROM faculty_profiles fp
        JOIN users u ON fp.user_id = u.id
        LEFT JOIN departments d ON fp.department_id = d.id
        WHERE fp.id = ? AND fp.is_verified = 1
        LIMIT 1
    ");
    $stmt->execute([$profileId]);
    $faculty = $stmt->fetch();
}

if (!$faculty) {
    abort(404, 'The requested faculty research profile could not be found or is not yet verified.');
}

$profileId = (int)$faculty['id'];

// Check if current user can edit this profile
$canEdit = can_manage_faculty_profile($faculty['id']);

// 2. Fetch Publications
$pubStmt = $db->prepare("
    SELECT * FROM publications 
    WHERE faculty_profile_id = ? 
    ORDER BY publication_year DESC, id DESC
");
$pubStmt->execute([$profileId]);
$publications = $pubStmt->fetchAll();

// Group publications by year for metrics / timeline
$pubsByYear = [];
foreach ($publications as $p) {
    $y = $p['publication_year'];
    $pubsByYear[$y] = ($pubsByYear[$y] ?? 0) + 1;
}
ksort($pubsByYear);

// 3. Fetch Research Grants / Projects
$projStmt = $db->prepare("
    SELECT * FROM projects 
    WHERE faculty_profile_id = ? 
    ORDER BY start_year DESC, id DESC
");
$projStmt->execute([$profileId]);
$projects = $projStmt->fetchAll();

// 4. Fetch Patents
$patStmt = $db->prepare("
    SELECT * FROM patents 
    WHERE faculty_profile_id = ? 
    ORDER BY filing_date DESC, id DESC
");
$patStmt->execute([$profileId]);
$patents = $patStmt->fetchAll();

// 5. Fetch Awards
$awdStmt = $db->prepare("
    SELECT * FROM awards 
    WHERE faculty_profile_id = ? 
    ORDER BY year DESC, id DESC
");
$awdStmt->execute([$profileId]);
$awards = $awdStmt->fetchAll();

// 6. Fetch Education
$eduStmt = $db->prepare("
    SELECT * FROM education 
    WHERE faculty_profile_id = ? 
    ORDER BY year DESC
");
$eduStmt->execute([$profileId]);
$education = $eduStmt->fetchAll();

// 7. Fetch Teaching
$teachStmt = $db->prepare("
    SELECT * FROM teaching 
    WHERE faculty_profile_id = ? 
    ORDER BY academic_year DESC
");
$teachStmt->execute([$profileId]);
$teaching = $teachStmt->fetchAll();

$pageTitle = $faculty['salutation'] . ' ' . $faculty['full_name'];
$activeNav = 'directory';
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-100/60 border-b border-slate-200 py-3">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
        <div class="flex items-center gap-2">
            <a href="<?= url() ?>" class="hover:text-iter-800 transition">Home</a>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
            <a href="<?= url('directory.php') ?>" class="hover:text-iter-800 transition">Faculty Directory</a>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
            <span class="text-slate-800 font-medium"><?= e($faculty['department_name'] ?? 'Faculty') ?></span>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition shadow-sm">
                <i class="fa-solid fa-print text-[11px]"></i>
                <span>Print CV / Profile</span>
            </button>
            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-iter-800 text-white hover:bg-iter-900 transition shadow-sm">
                    <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                    <span>Edit Profile Data</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- ========================================================= -->
        <!-- LEFT COLUMN: Scholar Info & Google Scholar Style Metrics   -->
        <!-- ========================================================= -->
        <aside class="lg:col-span-4 space-y-6">

            <!-- Profile Identity Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm text-center sm:text-left">
                <div class="flex flex-col sm:flex-row lg:flex-col items-center sm:items-start lg:items-center gap-5">
                    
                    <!-- Avatar Photo -->
                    <div class="relative w-36 h-36 rounded-2xl bg-slate-100 border-2 border-white shadow-md overflow-hidden flex-shrink-0 flex items-center justify-center ring-1 ring-slate-200">
                        <?php if (!empty($faculty['photo_url'])): ?>
                            <img src="<?= safe_url($faculty['photo_url']) ?>" alt="<?= e($faculty['full_name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center text-slate-300">
                                <i class="fa-solid fa-user-graduate text-5xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center sm:text-left lg:text-center w-full">
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-serif-title leading-tight">
                            <?= e($faculty['salutation'] . ' ' . $faculty['full_name']) ?>
                        </h1>
                        <p class="text-sm font-semibold text-iter-800 mt-1"><?= e($faculty['designation']) ?></p>
                        <p class="text-xs text-slate-600 mt-0.5">
                            <?= e($faculty['department_name']) ?>
                        </p>
                        <p class="text-xs text-slate-500 font-medium"><?= e($faculty['institution'] ?? 'Academic Faculty') ?></p>

                        <!-- Verified Badge -->
                        <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i>
                            <span>Verified Faculty Scholar</span>
                        </div>
                    </div>
                </div>

                <!-- Academic Identifiers -->
                <div class="mt-6 pt-5 border-t border-slate-100 space-y-2.5 text-xs">
                    <?php if (!empty($faculty['google_scholar_url'])): ?>
                        <a href="<?= safe_url($faculty['google_scholar_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="flex items-center justify-between p-2 rounded-lg bg-blue-50/60 hover:bg-blue-100/60 text-blue-900 font-medium transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-brands fa-google text-blue-600"></i>
                                <span>Google Scholar Profile</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-blue-500"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['researchgate_url'])): ?>
                        <a href="<?= safe_url($faculty['researchgate_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="flex items-center justify-between p-2 rounded-lg bg-teal-50/60 hover:bg-teal-100/60 text-teal-900 font-medium transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-brands fa-researchgate text-teal-600"></i>
                                <span>ResearchGate Profile</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-teal-500"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['orcid_id'])): ?>
                        <?php $cleanOrcid = safe_orcid($faculty['orcid_id']); ?>
                        <?php if ($cleanOrcid): ?>
                            <a href="https://orcid.org/<?= $cleanOrcid ?>" target="_blank" rel="noopener noreferrer"
                               class="flex items-center justify-between p-2 rounded-lg bg-emerald-50/60 hover:bg-emerald-100/60 text-emerald-900 font-medium transition">
                                <span class="flex items-center gap-2">
                                    <i class="fa-brands fa-orcid text-emerald-600"></i>
                                    <span>ORCID: <?= e($cleanOrcid) ?></span>
                                </span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-emerald-500"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($faculty['scopus_id'])): ?>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-amber-50/60 text-amber-900 font-medium">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-database text-amber-600"></i>
                                <span>Scopus ID: <?= e($faculty['scopus_id']) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($faculty['wos_id'])): ?>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-purple-50/60 text-purple-900 font-medium">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-book-bookmark text-purple-600"></i>
                                <span>Web of Science: <?= e($faculty['wos_id']) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($faculty['cv_url'])): ?>
                        <a href="<?= safe_url($faculty['cv_url']) ?>" target="_blank" rel="noopener noreferrer" download
                           class="flex items-center justify-between p-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-file-pdf text-rose-600 text-sm"></i>
                                <span>Download Full CV (PDF)</span>
                            </span>
                            <i class="fa-solid fa-download text-[10px] text-slate-500"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['phd_supervised']) && (int)$faculty['phd_supervised'] > 0): ?>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-800 font-medium">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-user-graduate text-iter-700"></i>
                                <span>Doctoral Guidance:</span>
                            </span>
                            <span class="font-mono font-bold text-iter-900"><?= (int)$faculty['phd_supervised'] ?> PhD Guided</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Contact Details -->
                <div class="mt-5 pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-600">
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-envelope text-slate-400 mt-0.5"></i>
                        <span class="break-all"><?= e($faculty['email']) ?></span>
                    </div>
                    <?php if (!empty($faculty['cabin'])): ?>
                        <div class="flex items-start gap-2.5">
                            <i class="fa-solid fa-location-dot text-slate-400 mt-0.5"></i>
                            <span><?= e($faculty['cabin']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($faculty['phone'])): ?>
                        <div class="flex items-start gap-2.5">
                            <i class="fa-solid fa-phone text-slate-400 mt-0.5"></i>
                            <span><?= e($faculty['phone']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Citation Indices Box (Honest & Transparent Academic Metrics) -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-chart-line text-iter-700"></i>
                        <span>Citation Indices</span>
                    </h2>
                    <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-800 font-semibold" title="Self-reported metrics tracked by faculty">
                        Manually Maintained
                    </span>
                </div>

                <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-600 leading-relaxed">
                    <i class="fa-solid fa-circle-info text-iter-600 mr-1"></i>
                    Manually reported by faculty member as of <strong><?= date('M Y', strtotime($faculty['updated_at'] ?? 'now')) ?></strong>.
                </div>

                <!-- Metrics Table -->
                <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-mono text-[10px]">
                            <tr>
                                <th class="py-2.5 px-3 font-semibold">Metric</th>
                                <th class="py-2.5 px-3 text-right font-semibold">Value</th>
                                <th class="py-2.5 px-3 text-right font-semibold">Reporting Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                            <tr>
                                <td class="py-2.5 px-3 font-sans font-medium text-slate-700">Citations</td>
                                <td class="py-2.5 px-3 text-right font-bold text-slate-900"><?= number_format($faculty['total_citations']) ?></td>
                                <td class="py-2.5 px-3 text-right text-[11px] text-slate-500 font-sans">Self-reported</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3 font-sans font-medium text-slate-700">h-index</td>
                                <td class="py-2.5 px-3 text-right font-bold text-slate-900"><?= (int)$faculty['h_index'] ?></td>
                                <td class="py-2.5 px-3 text-right text-[11px] text-slate-500 font-sans">Self-reported</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-3 font-sans font-medium text-slate-700">i10-index</td>
                                <td class="py-2.5 px-3 text-right font-bold text-slate-900"><?= (int)$faculty['i10_index'] ?></td>
                                <td class="py-2.5 px-3 text-right text-[11px] text-slate-500 font-sans">Self-reported</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mini Publication Output Bar Chart -->
                <?php if (!empty($pubsByYear)): ?>
                    <div class="mt-6 pt-5 border-t border-slate-100">
                        <div class="text-[11px] font-semibold text-slate-700 mb-3 flex items-center justify-between">
                            <span>Publication Output by Year</span>
                            <span class="text-[10px] text-slate-400 font-mono"><?= count($publications) ?> Total</span>
                        </div>
                        <?php 
                            $maxCount = max($pubsByYear);
                        ?>
                        <div class="flex items-end gap-1.5 h-20 pt-2 px-1">
                            <?php foreach ($pubsByYear as $yr => $cnt): ?>
                                <?php $barHeight = round(($cnt / $maxCount) * 100); ?>
                                <div class="flex-1 flex flex-col items-center gap-1 group relative">
                                    <!-- Tooltip -->
                                    <div class="absolute -top-7 hidden group-hover:flex items-center px-1.5 py-0.5 bg-slate-900 text-white rounded text-[10px] whitespace-nowrap z-10 shadow">
                                        <?= $yr ?>: <?= $cnt ?> pub<?= $cnt > 1 ? 's' : '' ?>
                                    </div>
                                    <div class="w-full bg-iter-200 group-hover:bg-iter-700 rounded-t transition" style="height: <?= max(12, $barHeight) ?>%;"></div>
                                    <span class="text-[9px] text-slate-400 font-mono"><?= substr((string)$yr, -2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Research Interests Cloud -->
            <?php if (!empty($faculty['research_interests'])): ?>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                    <h3 class="font-bold text-slate-900 text-sm mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-tags text-iter-700"></i>
                        <span>Research Interests</span>
                    </h3>
                    <div class="flex flex-wrap gap-1.5">
                        <?php 
                            $tags = array_map('trim', explode(',', $faculty['research_interests']));
                            foreach ($tags as $tag):
                        ?>
                            <a href="<?= url('directory.php?q=' . urlencode($tag)) ?>" 
                               class="inline-block px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-iter-50 border border-slate-200 hover:border-iter-300 text-slate-700 hover:text-iter-800 text-xs font-medium transition">
                                <?= e($tag) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </aside>

        <!-- ========================================================= -->
        <!-- RIGHT COLUMN: Scholarly Record Tabs (Google Scholar Style) -->
        <!-- ========================================================= -->
        <main class="lg:col-span-8 space-y-6">

            <!-- Biography / Research Statement -->
            <?php if (!empty($faculty['bio'])): ?>
                <div class="bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-book-open-reader text-iter-700"></i>
                        <span>Research Statement & Biography</span>
                    </h2>
                    <p class="text-sm text-slate-700 leading-relaxed">
                        <?= nl2br(e($faculty['bio'])) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs Bar -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" x-data="{ tab: 'publications' }">
                
                <!-- Tab Headers -->
                <div class="flex border-b border-slate-200 bg-slate-50/70 overflow-x-auto text-xs font-semibold">
                    <button type="button" onclick="switchTab('publications')" id="tab-btn-publications"
                        class="tab-btn px-5 py-3.5 border-b-2 border-iter-800 text-iter-800 bg-white font-bold whitespace-nowrap flex items-center gap-2 transition">
                        <i class="fa-solid fa-newspaper text-xs"></i>
                        <span>Articles & Papers</span>
                        <span class="px-2 py-0.5 rounded-full bg-iter-50 text-iter-900 font-mono text-[11px]"><?= count($publications) ?></span>
                    </button>

                    <button type="button" onclick="switchTab('projects')" id="tab-btn-projects"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-slate-900 whitespace-nowrap flex items-center gap-2 transition">
                        <i class="fa-solid fa-hand-holding-dollar text-xs"></i>
                        <span>Sponsored Projects</span>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($projects) ?></span>
                    </button>

                    <button type="button" onclick="switchTab('patents')" id="tab-btn-patents"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-slate-900 whitespace-nowrap flex items-center gap-2 transition">
                        <i class="fa-solid fa-lightbulb text-xs"></i>
                        <span>Patents</span>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($patents) ?></span>
                    </button>

                    <?php if (!empty($awards)): ?>
                    <button type="button" onclick="switchTab('awards')" id="tab-btn-awards"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-slate-900 whitespace-nowrap flex items-center gap-2 transition">
                        <i class="fa-solid fa-trophy text-xs"></i>
                        <span>Awards</span>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($awards) ?></span>
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($education)): ?>
                    <button type="button" onclick="switchTab('education')" id="tab-btn-education"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-slate-900 whitespace-nowrap flex items-center gap-2 transition">
                        <i class="fa-solid fa-graduation-cap text-xs"></i>
                        <span>Education</span>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($education) ?></span>
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($teaching) || ((int)($faculty['phd_supervised'] ?? 0) > 0)): ?>
                    <button type="button" onclick="switchTab('teaching')" id="tab-btn-teaching"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-slate-900 whitespace-nowrap flex items-center gap-2 transition">
                        <i class="fa-solid fa-chalkboard-user text-xs"></i>
                        <span>Teaching & Mentorship</span>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($teaching) ?></span>
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($faculty['memberships']) || !empty($faculty['editorial_roles'])): ?>
                    <button type="button" onclick="switchTab('service')" id="tab-btn-service"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-slate-900 whitespace-nowrap flex items-center gap-2 transition">
                        <i class="fa-solid fa-award text-xs"></i>
                        <span>Roles & Service</span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- TAB 1: Publications List (Scholar Format)  -->
                <!-- ========================================== -->
                <div id="tab-content-publications" class="tab-pane p-6">
                    
                    <!-- Search & Filter Controls -->
                    <div class="flex flex-col sm:flex-row gap-3 mb-6">
                        <div class="relative flex-grow">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" id="pubFilterInput" onkeyup="filterPublications()"
                                placeholder="Filter publications by title, venue, or keywords..."
                                class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                        </div>
                        <select id="pubTypeFilter" onchange="filterPublications()"
                            class="px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-700 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                            <option value="">All Publication Types</option>
                            <option value="journal">Journals</option>
                            <option value="conference">Conferences</option>
                            <option value="book_chapter">Book Chapters / Books</option>
                        </select>
                    </div>

                    <?php if (empty($publications)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <i class="fa-solid fa-file-circle-question text-3xl text-slate-300 mb-2"></i>
                            <p>No publications recorded yet for this profile.</p>
                        </div>
                    <?php else: ?>
                        <!-- Publications Table/Feed -->
                        <div class="divide-y divide-slate-100" id="publicationsList">
                            <?php foreach ($publications as $pub): ?>
                                <article class="pub-item py-4 first:pt-0 last:pb-0" 
                                         data-title="<?= strtolower(e($pub['title'])) ?>"
                                         data-venue="<?= strtolower(e($pub['journal_conference_name'])) ?>"
                                         data-type="<?= e($pub['publication_type']) ?>"
                                         data-year="<?= e($pub['publication_year']) ?>">
                                    
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-grow space-y-1">
                                            <!-- Title -->
                                            <h3 class="text-sm font-semibold text-slate-900 leading-snug">
                                                <?php if (!empty($pub['url'])): ?>
                                                    <a href="<?= safe_url($pub['url']) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-iter-700 hover:underline transition">
                                                        <?= e($pub['title']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e($pub['title']) ?>
                                                <?php endif; ?>
                                            </h3>

                                            <!-- Authors -->
                                            <p class="text-xs text-slate-600">
                                                <?= e($pub['authors']) ?>
                                            </p>

                                            <!-- Venue & Metadata -->
                                            <p class="text-xs text-scholar-green italic">
                                                <?= e($pub['journal_conference_name']) ?><?php if (!empty($pub['volume'])): ?>, Vol. <?= e($pub['volume']) ?><?php endif; ?><?php if (!empty($pub['pages'])): ?>, pp. <?= e($pub['pages']) ?><?php endif; ?> (<?= e($pub['publication_year']) ?>)
                                            </p>

                                            <!-- Badges & Links -->
                                            <div class="pt-1.5 flex flex-wrap items-center gap-2 text-[11px]">
                                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-mono capitalize">
                                                    <?= e(str_replace('_', ' ', $pub['publication_type'])) ?>
                                                </span>

                                                <?php if (!empty($pub['indexing'])): ?>
                                                    <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 font-medium">
                                                        <?= e($pub['indexing']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($pub['doi'])): ?>
                                                    <a href="https://doi.org/<?= e($pub['doi']) ?>" target="_blank" rel="noopener" class="text-slate-500 hover:text-iter-800 transition">
                                                        <i class="fa-solid fa-link text-[10px]"></i> DOI: <?= e($pub['doi']) ?>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- One-Click Citation Trigger -->
                                                <button type="button" 
                                                    onclick="openCiteModal(<?= htmlspecialchars(json_encode($pub), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="inline-flex items-center gap-1 text-slate-500 hover:text-iter-800 font-medium px-1.5 py-0.5 rounded hover:bg-slate-100 transition">
                                                    <i class="fa-solid fa-quote-left text-[10px]"></i>
                                                    <span>Cite</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Citations & Year Sidebar on Item -->
                                        <div class="flex flex-col items-end flex-shrink-0 text-right">
                                            <span class="font-bold text-xs text-slate-800 font-mono">
                                                <?= (int)$pub['citation_count'] > 0 ? (int)$pub['citation_count'] : '-' ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-mono">citations</span>
                                            <span class="mt-2 text-xs font-semibold text-slate-500 font-mono">
                                                <?= e($pub['publication_year']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- ========================================== -->
                <!-- TAB 2: Sponsored Research & Grants         -->
                <!-- ========================================== -->
                <div id="tab-content-projects" class="tab-pane hidden p-6">
                    <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved text-iter-700"></i>
                        <span>Extramural Grants & Funded Research Projects</span>
                    </h3>

                    <?php if (empty($projects)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <i class="fa-solid fa-folder-open text-3xl text-slate-300 mb-2"></i>
                            <p>No funded research projects recorded.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($projects as $proj): ?>
                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $proj['status'] === 'ongoing' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                                <?= e($proj['status']) ?>
                                            </span>
                                            <h4 class="text-sm font-bold text-slate-900 mt-1.5"><?= e($proj['title']) ?></h4>
                                            <p class="text-xs text-slate-600 mt-1">
                                                <strong>Funding Agency:</strong> <?= e($proj['funding_agency']) ?>
                                                <?php if (!empty($proj['project_code'])): ?> | <strong>Sanction Code:</strong> <?= e($proj['project_code']) ?><?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="text-xs text-slate-400 font-mono block">Sanctioned</span>
                                            <span class="text-sm font-bold text-emerald-700 font-mono">₹<?= number_format((float)$proj['amount_lakhs'], 2) ?> Lakhs</span>
                                            <span class="text-[11px] text-slate-500 block font-mono mt-1">
                                                <?= e($proj['start_year'] ?? '') ?> - <?= e($proj['end_year'] ?? 'Present') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- TAB 3: Patents & IP                        -->
                <!-- ========================================== -->
                <div id="tab-content-patents" class="tab-pane hidden p-6">
                    <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-certificate text-iter-700"></i>
                        <span>Patents & Intellectual Property</span>
                    </h3>

                    <?php if (empty($patents)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <i class="fa-solid fa-stamp text-3xl text-slate-300 mb-2"></i>
                            <p>No patents recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($patents as $pat): ?>
                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-start justify-between gap-4">
                                    <div>
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $pat['status'] === 'granted' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' ?>">
                                            <?= e($pat['status']) ?>
                                        </span>
                                        <h4 class="text-sm font-bold text-slate-900 mt-1.5"><?= e($pat['title']) ?></h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            <strong>Patent No / Application:</strong> <?= e($pat['patent_number'] ?? 'Pending') ?>
                                            | <strong>Country:</strong> <?= e($pat['country']) ?>
                                        </p>
                                    </div>
                                    <?php if (!empty($pat['grant_date'])): ?>
                                        <div class="text-right flex-shrink-0 text-xs text-slate-500">
                                            <span class="block text-[10px] text-slate-400 uppercase font-mono">Grant Date</span>
                                            <span class="font-mono font-semibold text-slate-800"><?= e($pat['grant_date']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- TAB 4: Honors & Awards                     -->
                <!-- ========================================== -->
                <?php if (!empty($awards)): ?>
                <div id="tab-content-awards" class="tab-pane hidden p-6">
                    <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-medal text-iter-700"></i>
                        <span>Honors, Awards & Recognitions</span>
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($awards as $awd): ?>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900"><?= e($awd['title']) ?></h4>
                                    <p class="text-xs text-slate-600 mt-0.5"><?= e($awd['awarding_body']) ?></p>
                                    <?php if (!empty($awd['description'])): ?>
                                        <p class="text-xs text-slate-500 mt-1"><?= e($awd['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2 py-1 rounded bg-white border border-slate-200 text-xs font-mono font-bold text-slate-700">
                                    <?= e($awd['year']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- TAB 5: Education & Qualifications          -->
                <!-- ========================================== -->
                <?php if (!empty($education)): ?>
                <div id="tab-content-education" class="tab-pane hidden p-6">
                    <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap text-iter-700"></i>
                        <span>Educational Background & Qualifications</span>
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($education as $edu): ?>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900"><?= e($edu['degree']) ?></h4>
                                    <p class="text-xs text-slate-700 mt-0.5"><?= e($edu['institution']) ?></p>
                                    <?php if (!empty($edu['field_of_study'])): ?>
                                        <p class="text-xs text-slate-500 mt-0.5">Specialization: <?= e($edu['field_of_study']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($edu['year'])): ?>
                                    <span class="px-2.5 py-1 rounded bg-white border border-slate-200 text-xs font-mono font-bold text-slate-700">
                                        <?= e($edu['year']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- TAB 6: Teaching & Mentorship               -->
                <!-- ========================================== -->
                <?php if (!empty($teaching) || ((int)($faculty['phd_supervised'] ?? 0) > 0)): ?>
                <div id="tab-content-teaching" class="tab-pane hidden p-6 space-y-6">
                    <?php if ((int)($faculty['phd_supervised'] ?? 0) > 0): ?>
                        <div class="p-4 rounded-xl bg-iter-50 border border-iter-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-iter-800 text-white flex items-center justify-center text-lg">
                                    <i class="fa-solid fa-user-graduate"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900">Doctoral Research Supervision</h4>
                                    <p class="text-xs text-slate-600">Ph.D. Scholars Successfully Guided / Under Guidance</p>
                                </div>
                            </div>
                            <span class="text-xl font-bold font-mono text-iter-900 px-3 py-1 bg-white rounded-lg border border-iter-200">
                                <?= (int)$faculty['phd_supervised'] ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($teaching)): ?>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-chalkboard text-iter-700"></i>
                                <span>Courses Taught</span>
                            </h3>
                            <div class="space-y-2.5">
                                <?php foreach ($teaching as $t): ?>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-4">
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900"><?= e($t['course_name']) ?></h4>
                                            <p class="text-[11px] text-slate-500 font-mono">
                                                <?= e($t['course_code'] ?? '') ?> 
                                                <?= !empty($t['level']) ? '• ' . strtoupper(e($t['level'])) : '' ?>
                                            </p>
                                        </div>
                                        <?php if (!empty($t['academic_year'])): ?>
                                            <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-600">
                                                <?= e($t['academic_year']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- TAB 7: Professional Service & Memberships  -->
                <!-- ========================================== -->
                <?php if (!empty($faculty['memberships']) || !empty($faculty['editorial_roles'])): ?>
                <div id="tab-content-service" class="tab-pane hidden p-6 space-y-6">
                    <?php if (!empty($faculty['memberships'])): ?>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-id-card-clip text-iter-700"></i>
                                <span>Professional Memberships</span>
                            </h3>
                            <div class="space-y-2">
                                <?php 
                                    $memberships = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $faculty['memberships'])));
                                    foreach ($memberships as $m): 
                                ?>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 flex items-center gap-2">
                                        <i class="fa-solid fa-certificate text-iter-600 text-sm"></i>
                                        <span><?= e($m) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($faculty['editorial_roles'])): ?>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-pen-nib text-iter-700"></i>
                                <span>Editorial & Reviewer Appointments</span>
                            </h3>
                            <div class="space-y-2">
                                <?php 
                                    $roles = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $faculty['editorial_roles'])));
                                    foreach ($roles as $r): 
                                ?>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 flex items-center gap-2">
                                        <i class="fa-solid fa-book-journal-whills text-emerald-600 text-sm"></i>
                                        <span><?= e($r) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>

        </main>
    </div>
</div>

<!-- ========================================================= -->
<!-- CITATION MODAL (Google Scholar Style: APA, IEEE, BibTeX)   -->
<!-- ========================================================= -->
<div id="citationModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2 font-serif-title">
                <i class="fa-solid fa-quote-left text-iter-700"></i>
                <span>Cite this publication</span>
            </h3>
            <button onclick="closeCiteModal()" class="text-slate-400 hover:text-slate-700 transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="space-y-4 text-xs">
            <!-- APA -->
            <div>
                <div class="flex items-center justify-between mb-1 font-semibold text-slate-700 uppercase font-mono text-[10px]">
                    <span>APA Format</span>
                    <button onclick="copyCitation('cite-apa')" class="text-iter-700 hover:underline">Copy APA</button>
                </div>
                <div id="cite-apa" class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 select-all font-sans leading-relaxed"></div>
            </div>

            <!-- IEEE -->
            <div>
                <div class="flex items-center justify-between mb-1 font-semibold text-slate-700 uppercase font-mono text-[10px]">
                    <span>IEEE Format</span>
                    <button onclick="copyCitation('cite-ieee')" class="text-iter-700 hover:underline">Copy IEEE</button>
                </div>
                <div id="cite-ieee" class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 select-all font-sans leading-relaxed"></div>
            </div>

            <!-- BibTeX -->
            <div>
                <div class="flex items-center justify-between mb-1 font-semibold text-slate-700 uppercase font-mono text-[10px]">
                    <span>BibTeX Entry</span>
                    <button onclick="copyCitation('cite-bibtex')" class="text-iter-700 hover:underline">Copy BibTeX</button>
                </div>
                <pre id="cite-bibtex" class="p-3 bg-slate-900 text-slate-100 rounded-lg font-mono text-[11px] overflow-x-auto select-all leading-relaxed"></pre>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end">
            <button onclick="closeCiteModal()" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Tab Switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-iter-800', 'text-iter-800', 'bg-white', 'font-bold');
        btn.classList.add('border-transparent', 'text-slate-600');
    });

    const activeContent = document.getElementById('tab-content-' + tabName);
    const activeBtn = document.getElementById('tab-btn-' + tabName);
    if (activeContent && activeBtn) {
        activeContent.classList.remove('hidden');
        activeBtn.classList.remove('border-transparent', 'text-slate-600');
        activeBtn.classList.add('border-iter-800', 'text-iter-800', 'bg-white', 'font-bold');
    }
}

// Client-side filtering of publications
function filterPublications() {
    const q = document.getElementById('pubFilterInput').value.toLowerCase();
    const type = document.getElementById('pubTypeFilter').value.toLowerCase();
    const items = document.querySelectorAll('.pub-item');

    items.forEach(item => {
        const title = item.getAttribute('data-title') || '';
        const venue = item.getAttribute('data-venue') || '';
        const pType = item.getAttribute('data-type') || '';

        const matchesQuery = !q || title.includes(q) || venue.includes(q);
        const matchesType = !type || pType === type;

        if (matchesQuery && matchesType) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// Citation Modal Logic
function openCiteModal(pub) {
    const apa = `${pub.authors} (${pub.publication_year}). ${pub.title}. <i>${pub.journal_conference_name}</i>${pub.volume ? ', ' + pub.volume : ''}${pub.pages ? ', ' + pub.pages : ''}.${pub.doi ? ' https://doi.org/' + pub.doi : ''}`;
    const ieee = `${pub.authors}, "${pub.title}," <i>${pub.journal_conference_name}</i>${pub.volume ? ', vol. ' + pub.volume : ''}${pub.issue ? ', no. ' + pub.issue : ''}${pub.pages ? ', pp. ' + pub.pages : ''}, ${pub.publication_year}.`;
    
    const key = (pub.authors.split(',')[0].trim().replace(/\s+/g, '') + pub.publication_year).toLowerCase();
    const bibtex = `@article{${key},\n  title={${pub.title}},\n  author={${pub.authors}},\n  journal={${pub.journal_conference_name}},\n  year={${pub.publication_year}},\n  volume={${pub.volume || ''}},\n  pages={${pub.pages || ''}},\n  doi={${pub.doi || ''}}\n}`;

    document.getElementById('cite-apa').innerHTML = apa;
    document.getElementById('cite-ieee').innerHTML = ieee;
    document.getElementById('cite-bibtex').textContent = bibtex;

    const modal = document.getElementById('citationModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeCiteModal() {
    const modal = document.getElementById('citationModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function copyCitation(elementId) {
    const text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Citation copied to clipboard!');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
