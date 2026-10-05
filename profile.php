<?php
/**
 * Departmental Scholar — Individual Academic Researcher Profile
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getConnection();

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug      = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if ($profileId <= 0 && empty($slug)) {
    set_flash('warning', 'Profile not specified.');
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
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
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
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$faculty) {
    abort(404, 'The requested faculty research profile could not be found or is not yet verified.');
}

$profileId = (int)$faculty['id'];
$canEdit   = can_manage_faculty_profile($faculty['id']);

// 2. Fetch Publications
$pubStmt = $db->prepare("
    SELECT * FROM publications 
    WHERE faculty_profile_id = ? 
    ORDER BY publication_year DESC, id DESC
");
$pubStmt->execute([$profileId]);
$publications = $pubStmt->fetchAll(PDO::FETCH_ASSOC);

// Group publications by year for timeline/metrics
$pubsByYear = [];
foreach ($publications as $p) {
    $y = (int)($p['publication_year'] ?? 0);
    if ($y > 0) {
        $pubsByYear[$y] = ($pubsByYear[$y] ?? 0) + 1;
    }
}
ksort($pubsByYear);

// 3. Fetch Projects
$projStmt = $db->prepare("
    SELECT * FROM projects 
    WHERE faculty_profile_id = ? 
    ORDER BY start_year DESC, id DESC
");
$projStmt->execute([$profileId]);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Patents
$patStmt = $db->prepare("
    SELECT * FROM patents 
    WHERE faculty_profile_id = ? 
    ORDER BY filing_date DESC, id DESC
");
$patStmt->execute([$profileId]);
$patents = $patStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Awards
$awdStmt = $db->prepare("
    SELECT * FROM awards 
    WHERE faculty_profile_id = ? 
    ORDER BY year DESC, id DESC
");
$awdStmt->execute([$profileId]);
$awards = $awdStmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Fetch Education
$eduStmt = $db->prepare("
    SELECT * FROM education 
    WHERE faculty_profile_id = ? 
    ORDER BY year DESC
");
$eduStmt->execute([$profileId]);
$education = $eduStmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Fetch Teaching
$teachStmt = $db->prepare("
    SELECT * FROM teaching 
    WHERE faculty_profile_id = ? 
    ORDER BY academic_year DESC
");
$teachStmt->execute([$profileId]);
$teaching = $teachStmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Fetch Academic Appointments & Career History
$expStmt = $db->prepare("
    SELECT * FROM academic_experience 
    WHERE faculty_profile_id = ? 
    ORDER BY is_current DESC, COALESCE(end_year, 9999) DESC, start_year DESC, id DESC
");
$expStmt->execute([$profileId]);
$experience = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total grants value
$totalGrantsAmount = 0.0;
foreach ($projects as $proj) {
    $totalGrantsAmount += (float)($proj['amount_lakhs'] ?? 0);
}

$pageTitle = ($faculty['salutation'] ? $faculty['salutation'] . ' ' : '') . $faculty['full_name'];
$activeNav = 'directory';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs Bar -->
<div class="bg-white border-b border-scholar-border py-2.5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 font-sans">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2">
            <a href="<?= url() ?>" class="hover:text-oxford-navy transition">Home</a>
            <i class="fa-solid fa-chevron-right text-[9px] text-slate-400"></i>
            <a href="<?= url('directory.php') ?>" class="hover:text-oxford-navy transition">Directory</a>
            <i class="fa-solid fa-chevron-right text-[9px] text-slate-400"></i>
            <?php if (!empty($faculty['department_code'])): ?>
                <a href="<?= url('directory.php?dept=' . urlencode($faculty['department_code'])) ?>" class="hover:text-oxford-navy transition">
                    <?= e($faculty['department_code']) ?>
                </a>
                <i class="fa-solid fa-chevron-right text-[9px] text-slate-400"></i>
            <?php endif; ?>
            <span class="text-oxford-navy font-semibold truncate max-w-xs"><?= e($faculty['full_name']) ?></span>
        </nav>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs" title="Print Academic CV">
                <i class="fa-solid fa-print text-xs"></i>
                <span class="hidden sm:inline">Print Profile</span>
            </button>
            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                    <span>Edit Profile</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-8">

        <!-- ========================================================= -->
        <!-- LEFT COLUMN: Scholar Identity & Impact Ribbon             -->
        <!-- ========================================================= -->
        <aside class="md:col-span-5 lg:col-span-4 space-y-6">

            <!-- Scholar Hero Card -->
            <div class="academic-card p-6 text-center sm:text-left">
                <div class="flex flex-col sm:flex-row md:flex-col items-center sm:items-start md:items-center gap-5">
                    
                    <!-- Framed Portrait with Fail-Safe Fallback -->
                    <div class="relative w-36 h-36 rounded-[10px] bg-slate-100 border border-scholar-border shadow-sm overflow-hidden flex-shrink-0 flex items-center justify-center">
                        <?php $resolvedPhoto = faculty_photo_url($faculty['photo_url'] ?? null); ?>
                        <?php if ($resolvedPhoto): ?>
                            <img src="<?= $resolvedPhoto ?>" alt="<?= e($faculty['full_name']) ?>" class="w-full h-full object-cover"
                                 onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                            <div class="hidden text-slate-300 flex flex-col items-center justify-center w-full h-full">
                                <i class="fa-solid fa-user-graduate text-5xl"></i>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-300 flex flex-col items-center justify-center w-full h-full">
                                <i class="fa-solid fa-user-graduate text-5xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center sm:text-left md:text-center w-full">
                        <h1 class="font-serif text-2xl sm:text-3xl font-bold text-oxford-navy leading-tight">
                            <?= e(($faculty['salutation'] ? $faculty['salutation'] . ' ' : '') . $faculty['full_name']) ?>
                        </h1>
                        <p class="text-sm font-semibold text-oxford-blue mt-1"><?= e($faculty['designation']) ?></p>
                        <p class="text-xs text-scholar-muted mt-0.5 font-medium"><?= e($faculty['department_name']) ?></p>
                        <p class="text-xs text-slate-500 mt-0.5"><?= e($faculty['institution'] ?? 'Collegiate Academic Division') ?></p>

                        <!-- Verified Status Badge -->
                        <div class="mt-3.5 inline-flex items-center gap-1.5 academic-tag academic-tag-green text-xs font-semibold">
                            <i class="fa-solid fa-circle-check text-xs"></i>
                            <span>Verified Faculty Scholar</span>
                        </div>
                    </div>
                </div>

                <?php
                    $hasRegistries = !empty($faculty['google_scholar_url']) || !empty($faculty['orcid_id']) || !empty($faculty['scopus_id']) 
                        || !empty($faculty['wos_id']) || !empty($faculty['researchgate_url']) || !empty($faculty['dblp_url']) 
                        || !empty($faculty['semantic_scholar_url']) || !empty($faculty['website_url']) || !empty($faculty['cv_url']);
                ?>

                <?php if ($hasRegistries): ?>
                <!-- Academic Identifier Ribbon (Restrained Institutional Style) -->
                <div class="mt-6 pt-5 border-t border-scholar-border space-y-2 text-xs">
                    <div class="text-[10px] uppercase font-mono tracking-wider text-slate-400 mb-2 font-semibold">Scholarly Registries</div>
                    
                    <?php if (!empty($faculty['google_scholar_url'])): ?>
                        <a href="<?= safe_url($faculty['google_scholar_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 font-medium transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-brands fa-google text-blue-600"></i>
                                <span>Google Scholar</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['orcid_id'])): ?>
                        <?php $cleanOrcid = safe_orcid($faculty['orcid_id']); ?>
                        <?php if ($cleanOrcid): ?>
                            <a href="https://orcid.org/<?= $cleanOrcid ?>" target="_blank" rel="noopener noreferrer"
                               class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 font-medium transition">
                                <span class="flex items-center gap-2">
                                    <i class="fa-brands fa-orcid text-emerald-600"></i>
                                    <span class="font-mono text-[11px]">ORCID: <?= e($cleanOrcid) ?></span>
                                </span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($faculty['scopus_id'])): ?>
                        <div class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 border border-slate-200 text-slate-800 font-medium">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-database text-amber-700"></i>
                                <span class="font-mono text-[11px]">Scopus: <?= e($faculty['scopus_id']) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($faculty['wos_id'])): ?>
                        <div class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 border border-slate-200 text-slate-800 font-medium">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-book-bookmark text-slate-700"></i>
                                <span class="font-mono text-[11px]">Web of Science: <?= e($faculty['wos_id']) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($faculty['researchgate_url'])): ?>
                        <a href="<?= safe_url($faculty['researchgate_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 font-medium transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-brands fa-researchgate text-teal-700"></i>
                                <span>ResearchGate</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['dblp_url'])): ?>
                        <a href="<?= safe_url($faculty['dblp_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 font-medium transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-code text-indigo-700"></i>
                                <span>DBLP Bibliography</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['semantic_scholar_url'])): ?>
                        <a href="<?= safe_url($faculty['semantic_scholar_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 font-medium transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-brain text-sky-700"></i>
                                <span>Semantic Scholar</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['website_url'])): ?>
                        <a href="<?= safe_url($faculty['website_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="flex items-center justify-between p-2 rounded-[6px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 font-medium transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-globe text-slate-700"></i>
                                <span>Academic Website</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($faculty['cv_url'])): ?>
                        <a href="<?= safe_url($faculty['cv_url']) ?>" target="_blank" rel="noopener noreferrer" download
                           class="flex items-center justify-between p-2 rounded-[6px] bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-900 font-semibold transition mt-2">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-file-pdf text-rose-700 text-sm"></i>
                                <span>Curriculum Vitae (PDF)</span>
                            </span>
                            <i class="fa-solid fa-download text-[10px] text-slate-500"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php elseif ($canEdit): ?>
                <div class="mt-6 pt-4 border-t border-scholar-border text-xs text-center p-3 rounded-[6px] bg-slate-50 border border-dashed border-slate-200">
                    <span class="text-[11px] text-slate-500 block mb-1.5">No scholarly registries linked</span>
                    <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="btn-academic-secondary text-[11px] !py-1 !px-2.5 inline-flex items-center gap-1">
                        <i class="fa-solid fa-plus text-[10px]"></i> Link ORCID / Scholar
                    </a>
                </div>
                <?php endif; ?>

                <!-- Contact Metadata -->
                <div class="mt-5 pt-4 border-t border-scholar-border space-y-2 text-xs text-scholar-muted">
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

            <!-- Faculty Impact Ribbon (Transparent Self-Reported Metrics) -->
            <div class="academic-card p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif font-bold text-base text-oxford-navy flex items-center gap-2">
                        <i class="fa-solid fa-chart-line text-academic-gold"></i>
                        <span>Citation Impact</span>
                    </h2>
                    <span class="academic-tag font-mono text-[10px]">
                        Self-Reported
                    </span>
                </div>

                <div class="p-2.5 bg-slate-50 rounded-[6px] border border-scholar-border text-[11px] text-scholar-muted leading-relaxed">
                    <i class="fa-solid fa-circle-info text-oxford-blue mr-1"></i>
                    Self-reported by faculty scholar as of <strong><?= date('M Y', strtotime($faculty['updated_at'] ?? 'now')) ?></strong>.
                </div>

                <!-- Metrics Table -->
                <div class="border border-scholar-border rounded-[6px] overflow-hidden text-xs">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th class="text-right">Value</th>
                                <th class="text-right">Nature</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            <tr>
                                <td class="font-sans font-medium text-slate-700">Citations</td>
                                <td class="text-right font-bold text-oxford-navy"><?= number_format($faculty['total_citations']) ?></td>
                                <td class="text-right text-[11px] text-slate-500 font-sans">Self-reported</td>
                            </tr>
                            <tr>
                                <td class="font-sans font-medium text-slate-700">h-index</td>
                                <td class="text-right font-bold text-oxford-navy"><?= (int)$faculty['h_index'] ?></td>
                                <td class="text-right text-[11px] text-slate-500 font-sans">Self-reported</td>
                            </tr>
                            <tr>
                                <td class="font-sans font-medium text-slate-700">i10-index</td>
                                <td class="text-right font-bold text-oxford-navy"><?= (int)$faculty['i10_index'] ?></td>
                                <td class="text-right text-[11px] text-slate-500 font-sans">Self-reported</td>
                            </tr>
                            <tr>
                                <td class="font-sans font-medium text-slate-700">Indexed Works</td>
                                <td class="text-right font-bold text-oxford-navy"><?= count($publications) ?></td>
                                <td class="text-right text-[11px] text-emerald-700 font-sans">System-recorded</td>
                            </tr>
                            <?php if ($totalGrantsAmount > 0): ?>
                            <tr>
                                <td class="font-sans font-medium text-slate-700">Funded Grants</td>
                                <td class="text-right font-bold text-oxford-navy font-mono"><?= format_currency_lakhs($totalGrantsAmount) ?></td>
                                <td class="text-right text-[11px] text-emerald-700 font-sans">System-recorded</td>
                            </tr>
                            <?php endif; ?>
                            <?php if (count($patents) > 0): ?>
                            <tr>
                                <td class="font-sans font-medium text-slate-700">Patents / IP</td>
                                <td class="text-right font-bold text-oxford-navy font-mono"><?= count($patents) ?></td>
                                <td class="text-right text-[11px] text-emerald-700 font-sans">System-recorded</td>
                            </tr>
                            <?php endif; ?>
                            <?php if ((int)($faculty['phd_supervised'] ?? 0) > 0): ?>
                            <tr>
                                <td class="font-sans font-medium text-slate-700">PhD Supervised</td>
                                <td class="text-right font-bold text-oxford-navy font-mono"><?= (int)$faculty['phd_supervised'] ?></td>
                                <td class="text-right text-[11px] text-slate-500 font-sans">Self-reported</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Annual Publication Timeline Bar Chart -->
                <?php if (!empty($pubsByYear)): ?>
                    <div class="mt-5 pt-4 border-t border-scholar-border">
                        <div class="text-[11px] font-semibold text-slate-700 mb-3 flex items-center justify-between font-sans">
                            <span>Publication Output by Year</span>
                            <span class="text-[10px] text-slate-400 font-mono"><?= count($publications) ?> Total</span>
                        </div>
                        <?php $maxCount = max($pubsByYear); ?>
                        <div class="flex items-end gap-1.5 h-20 pt-2 px-1">
                            <?php foreach ($pubsByYear as $yr => $cnt): ?>
                                <?php $barHeight = round(($cnt / $maxCount) * 100); ?>
                                <div class="flex-1 flex flex-col items-center gap-1 group relative">
                                    <div class="absolute -top-7 hidden group-hover:flex items-center px-1.5 py-0.5 bg-oxford-navy text-white rounded-[4px] text-[10px] whitespace-nowrap z-10 shadow font-mono">
                                        <?= $yr ?>: <?= $cnt ?> work<?= $cnt > 1 ? 's' : '' ?>
                                    </div>
                                    <div class="w-full bg-slate-200 group-hover:bg-oxford-navy rounded-t-[2px] transition" style="height: <?= max(12, $barHeight) ?>%;"></div>
                                    <span class="text-[9px] text-slate-400 font-mono"><?= substr((string)$yr, -2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Research Interests Cloud -->
            <?php if (!empty($faculty['research_interests'])): ?>
                <div class="academic-card p-6">
                    <h3 class="font-serif font-bold text-base text-oxford-navy mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-tags text-academic-gold"></i>
                        <span>Research Areas</span>
                    </h3>
                    <div class="flex flex-wrap gap-1.5">
                        <?php 
                            $tags = array_map('trim', explode(',', $faculty['research_interests']));
                            foreach ($tags as $tag):
                        ?>
                            <a href="<?= url('directory.php?q=' . urlencode($tag)) ?>" 
                               class="academic-tag hover:border-oxford-blue hover:text-oxford-navy transition">
                                <?= e($tag) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($canEdit): ?>
                <div class="academic-card p-5 border-dashed border-slate-300 bg-slate-50/50 text-center">
                    <h3 class="font-serif font-bold text-sm text-oxford-navy mb-1 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-tags text-academic-gold"></i>
                        <span>Research Areas</span>
                    </h3>
                    <p class="text-xs text-slate-500 mb-3">Add keywords representing your research topics and expertise.</p>
                    <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1 !px-2.5 inline-flex">
                        <i class="fa-solid fa-plus text-[10px]"></i> Add Research Areas
                    </a>
                </div>
            <?php endif; ?>

        </aside>

        <!-- ========================================================= -->
        <!-- RIGHT COLUMN: Scholarly Portfolio Tabs                    -->
        <!-- ========================================================= -->
        <main class="md:col-span-7 lg:col-span-8 space-y-6">

            <!-- Biography / Research Statement -->
            <?php if (!empty($faculty['bio'])): ?>
                <div class="academic-card p-6 sm:p-7">
                    <h2 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-2 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-book-open-reader text-academic-gold"></i>
                        <span>Scholarly Biography & Research Overview</span>
                    </h2>
                    <p class="text-sm text-slate-700 leading-relaxed font-sans">
                        <?= nl2br(e($faculty['bio'])) ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="academic-card p-6 sm:p-7 <?= $canEdit ? 'border-dashed border-slate-300 bg-slate-50/50' : '' ?>">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-2 font-mono flex items-center gap-2">
                                <i class="fa-solid fa-book-open-reader text-academic-gold"></i>
                                <span>Scholarly Biography & Research Overview</span>
                            </h2>
                            <p class="text-sm text-slate-700 leading-relaxed font-sans">
                                <?= e(($faculty['salutation'] ? $faculty['salutation'] . ' ' : '') . $faculty['full_name']) ?> serves as <?= e($faculty['designation']) ?> in the Department of <?= e($faculty['department_name']) ?> at <?= e($faculty['institution'] ?? 'Collegiate Academic Division') ?>.
                            </p>
                        </div>
                        <?php if ($canEdit): ?>
                            <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 flex-shrink-0">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                <span>Edit Biography</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sticky Academic Navigation Rail & Content -->
            <div class="academic-card overflow-hidden">
                
                <!-- Tab Headers Rail -->
                <div class="flex border-b border-scholar-border bg-slate-50/80 overflow-x-auto text-xs font-semibold scrollbar-none" role="tablist" aria-label="Academic profile sections">
                    <button type="button" onclick="switchTab('publications')" id="tab-btn-publications" role="tab" aria-selected="true" aria-controls="tab-content-publications"
                        class="tab-btn px-5 py-3.5 border-b-2 border-oxford-navy text-oxford-navy bg-white font-bold whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-newspaper text-xs"></i>
                        <span>Articles & Papers</span>
                        <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-oxford-navy font-mono text-[11px]"><?= count($publications) ?></span>
                    </button>

                    <button type="button" onclick="switchTab('projects')" id="tab-btn-projects" role="tab" aria-selected="false" aria-controls="tab-content-projects"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-oxford-navy whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-hand-holding-dollar text-xs"></i>
                        <span>Sponsored Projects</span>
                        <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($projects) ?></span>
                    </button>

                    <button type="button" onclick="switchTab('patents')" id="tab-btn-patents" role="tab" aria-selected="false" aria-controls="tab-content-patents"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-oxford-navy whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-lightbulb text-xs"></i>
                        <span>Patents</span>
                        <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($patents) ?></span>
                    </button>

                    <?php if (!empty($awards) || $canEdit): ?>
                    <button type="button" onclick="switchTab('awards')" id="tab-btn-awards" role="tab" aria-selected="false" aria-controls="tab-content-awards"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-oxford-navy whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-trophy text-xs"></i>
                        <span>Honors & Awards</span>
                        <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($awards) ?></span>
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($experience) || $canEdit): ?>
                    <button type="button" onclick="switchTab('experience')" id="tab-btn-experience" role="tab" aria-selected="false" aria-controls="tab-content-experience"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-oxford-navy whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-briefcase text-xs"></i>
                        <span>Appointments</span>
                        <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($experience) ?></span>
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($education) || $canEdit): ?>
                    <button type="button" onclick="switchTab('education')" id="tab-btn-education" role="tab" aria-selected="false" aria-controls="tab-content-education"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-oxford-navy whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-graduation-cap text-xs"></i>
                        <span>Education</span>
                        <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($education) ?></span>
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($teaching) || ((int)($faculty['phd_supervised'] ?? 0) > 0) || $canEdit): ?>
                    <button type="button" onclick="switchTab('teaching')" id="tab-btn-teaching" role="tab" aria-selected="false" aria-controls="tab-content-teaching"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-oxford-navy whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-chalkboard-user text-xs"></i>
                        <span>Teaching & Mentorship</span>
                        <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-slate-700 font-mono text-[11px]"><?= count($teaching) ?></span>
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($faculty['memberships']) || !empty($faculty['editorial_roles']) || $canEdit): ?>
                    <button type="button" onclick="switchTab('service')" id="tab-btn-service" role="tab" aria-selected="false" aria-controls="tab-content-service"
                        class="tab-btn px-5 py-3.5 border-b-2 border-transparent text-slate-600 hover:text-oxford-navy whitespace-nowrap flex items-center gap-2 transition focus:outline-none">
                        <i class="fa-solid fa-award text-xs"></i>
                        <span>Service</span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- TAB 1: Publications Bibliography           -->
                <!-- ========================================== -->
                <div id="tab-content-publications" class="tab-pane p-6" role="tabpanel" aria-labelledby="tab-btn-publications">
                    
                    <!-- Search & Filter Controls -->
                    <div class="flex flex-col sm:flex-row gap-3 mb-6">
                        <div class="relative flex-grow">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" id="pubFilterInput" onkeyup="filterPublications()"
                                placeholder="Filter publications by title, venue, or keywords..."
                                class="academic-input pl-9 text-xs"
                                aria-label="Filter publications in this profile">
                        </div>
                        <select id="pubTypeFilter" onchange="filterPublications()"
                            class="academic-input sm:w-56 text-xs"
                            aria-label="Filter publication type">
                            <option value="">All Publication Types</option>
                            <option value="journal">Journals</option>
                            <option value="conference">Conferences</option>
                            <option value="book_chapter">Book Chapters / Books</option>
                        </select>
                    </div>

                    <?php if (empty($publications)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-file-circle-question text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No publications recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                No scholarly works or articles have been registered yet for this academic profile.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/add_publication.php') ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add First Publication</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Academic Bibliography List -->
                        <div class="divide-y divide-slate-100" id="publicationsList">
                            <?php foreach ($publications as $pub): ?>
                                <article class="pub-item py-4 first:pt-0 last:pb-0" 
                                         data-title="<?= strtolower(e($pub['title'])) ?>"
                                         data-venue="<?= strtolower(e($pub['journal_conference_name'])) ?>"
                                         data-type="<?= e($pub['publication_type']) ?>"
                                         data-year="<?= e($pub['publication_year']) ?>">
                                    
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-grow space-y-1">
                                            <!-- Paper Title (EB Garamond) -->
                                            <h3 class="font-serif text-base font-bold text-oxford-navy leading-snug">
                                                <?php if (!empty($pub['url'])): ?>
                                                    <a href="<?= safe_url($pub['url']) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-oxford-blue hover:underline transition">
                                                        <?= e($pub['title']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e($pub['title']) ?>
                                                <?php endif; ?>
                                            </h3>

                                            <!-- Authors (Plus Jakarta Sans) -->
                                            <p class="text-xs text-slate-700 font-medium">
                                                <?= e($pub['authors']) ?>
                                            </p>

                                            <!-- Venue & Journal Reference (Italicized) -->
                                            <p class="text-xs text-slate-600 font-sans">
                                                <span class="italic text-oxford-blue font-serif"><?= e($pub['journal_conference_name']) ?></span><?php if (!empty($pub['volume'])): ?>, Vol. <span class="font-mono"><?= e($pub['volume']) ?></span><?php endif; ?><?php if (!empty($pub['pages'])): ?>, pp. <span class="font-mono"><?= e($pub['pages']) ?></span><?php endif; ?> (<span class="font-mono"><?= e($pub['publication_year']) ?></span>)
                                            </p>

                                            <!-- Indexing Badges & Action Links -->
                                            <div class="pt-1.5 flex flex-wrap items-center gap-2 text-[11px]">
                                                <span class="academic-tag font-mono capitalize">
                                                    <?= e(str_replace('_', ' ', $pub['publication_type'])) ?>
                                                </span>

                                                <?php if (!empty($pub['is_open_access']) || !empty($pub['pdf_url'])): ?>
                                                    <span class="academic-tag academic-tag-oa font-mono">
                                                        <i class="fa-solid fa-lock-open text-[9px]"></i> Open Access
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($pub['indexing'])): ?>
                                                    <span class="academic-tag academic-tag-gold font-semibold">
                                                        <?= e($pub['indexing']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($pub['doi'])): ?>
                                                    <a href="https://doi.org/<?= e($pub['doi']) ?>" target="_blank" rel="noopener" class="text-slate-500 hover:text-oxford-navy transition font-mono">
                                                        <i class="fa-solid fa-link text-[10px]"></i> DOI: <?= e($pub['doi']) ?>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (!empty($pub['pdf_url'])): ?>
                                                    <a href="<?= safe_url($pub['pdf_url']) ?>" target="_blank" rel="noopener noreferrer" 
                                                       class="inline-flex items-center gap-1 text-rose-700 hover:text-rose-900 font-semibold px-2 py-0.5 rounded-[4px] bg-rose-50 border border-rose-200 transition text-[11px]">
                                                        <i class="fa-solid fa-file-pdf text-[11px]"></i>
                                                        <span>PDF</span>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- One-Click Citation Trigger -->
                                                <?php 
                                                    $citePayload = [
                                                        'title' => $pub['title'],
                                                        'authors' => $pub['authors'],
                                                        'venue' => $pub['journal_conference_name'],
                                                        'year' => $pub['publication_year'],
                                                        'volume' => $pub['volume'] ?? '',
                                                        'issue' => $pub['issue'] ?? '',
                                                        'pages' => $pub['pages'] ?? '',
                                                        'doi' => $pub['doi'] ?? '',
                                                    ];
                                                ?>
                                                <button type="button" 
                                                    data-cite-btn
                                                    data-publication="<?= htmlspecialchars(json_encode($citePayload), ENT_QUOTES, 'UTF-8') ?>"
                                                    class="inline-flex items-center gap-1 text-oxford-blue hover:text-oxford-navy font-semibold px-2 py-0.5 rounded-[4px] hover:bg-slate-100 transition">
                                                    <i class="fa-solid fa-quote-left text-[10px]"></i>
                                                    <span>Cite</span>
                                                </button>
                                            </div>

                                            <?php if (!empty($pub['abstract'])): ?>
                                                <details class="text-xs text-slate-600 mt-2 bg-slate-50/70 p-2.5 rounded-[6px] border border-slate-200/60 group">
                                                    <summary class="cursor-pointer font-semibold text-oxford-blue hover:text-oxford-navy flex items-center gap-1.5 select-none text-[11px]">
                                                        <i class="fa-solid fa-align-left text-[10px]"></i>
                                                        <span>View Abstract</span>
                                                    </summary>
                                                    <p class="mt-2 text-slate-700 leading-relaxed font-sans text-xs pt-1 border-t border-slate-200/60">
                                                        <?= nl2br(e($pub['abstract'])) ?>
                                                    </p>
                                                </details>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Citations Counter in JetBrains Mono -->
                                        <div class="flex flex-col items-end flex-shrink-0 text-right">
                                            <span class="font-bold text-xs text-oxford-navy font-mono">
                                                <?= (int)$pub['citation_count'] > 0 ? (int)$pub['citation_count'] : '—' ?>
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
                <!-- TAB 2: Sponsored Projects & Grants         -->
                <!-- ========================================== -->
                <div id="tab-content-projects" class="tab-pane hidden p-6" role="tabpanel" aria-labelledby="tab-btn-projects">
                    <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-4 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved text-academic-gold"></i>
                        <span>Extramural Grants & Sponsored Research Projects</span>
                    </h3>

                    <?php if (empty($projects)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-folder-open text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No funded projects recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                No sponsored research grants or external projects are currently registered.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/add_project.php') ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add Sponsored Project</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($projects as $proj): ?>
                                <div class="p-4 rounded-[8px] bg-slate-50 border border-scholar-border">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <span class="inline-block px-2 py-0.5 rounded-[4px] text-[10px] font-bold font-mono uppercase tracking-wider <?= $proj['status'] === 'ongoing' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                                <?= e($proj['status']) ?>
                                            </span>
                                            <h4 class="font-serif text-base font-bold text-oxford-navy mt-1.5"><?= e($proj['title']) ?></h4>
                                            <p class="text-xs text-slate-600 mt-1">
                                                <strong>Agency:</strong> <?= e($proj['funding_agency']) ?>
                                                <?php if (!empty($proj['project_code'])): ?> | <strong>Sanction Code:</strong> <span class="font-mono"><?= e($proj['project_code']) ?></span><?php endif; ?>
                                                <?php if (!empty($proj['role'])): ?> | <strong>Role:</strong> <?= e($proj['role']) ?><?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="text-[10px] text-slate-400 font-mono uppercase block">Sanctioned</span>
                                            <span class="text-sm font-bold text-oxford-navy font-mono">₹<?= number_format((float)$proj['amount_lakhs'], 2) ?> Lakhs</span>
                                            <span class="text-[11px] text-slate-500 block font-mono mt-1">
                                                <?= e($proj['start_year'] ?? '') ?> — <?= e($proj['end_year'] ?? 'Present') ?>
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
                <div id="tab-content-patents" class="tab-pane hidden p-6" role="tabpanel" aria-labelledby="tab-btn-patents">
                    <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-4 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-certificate text-academic-gold"></i>
                        <span>Patents & Intellectual Property Filings</span>
                    </h3>

                    <?php if (empty($patents)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-stamp text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No patents recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                No patent applications or granted intellectual property recorded.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/add_patent.php') ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add Patent Filing</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($patents as $pat): ?>
                                <div class="p-4 rounded-[8px] bg-slate-50 border border-scholar-border flex items-start justify-between gap-4">
                                    <div>
                                        <span class="inline-block px-2 py-0.5 rounded-[4px] text-[10px] font-bold font-mono uppercase tracking-wider <?= $pat['status'] === 'granted' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-200 text-slate-800' ?>">
                                            <?= e($pat['status']) ?>
                                        </span>
                                        <h4 class="font-serif text-base font-bold text-oxford-navy mt-1.5"><?= e($pat['title']) ?></h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            <strong>Application / Patent No:</strong> <span class="font-mono"><?= e($pat['patent_number'] ?? 'Pending') ?></span>
                                            | <strong>Country:</strong> <?= e($pat['country']) ?>
                                        </p>
                                    </div>
                                    <?php if (!empty($pat['grant_date'])): ?>
                                        <div class="text-right flex-shrink-0 text-xs text-slate-500">
                                            <span class="block text-[10px] text-slate-400 uppercase font-mono">Grant Date</span>
                                            <span class="font-mono font-semibold text-oxford-navy"><?= e($pat['grant_date']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- TAB: Academic Career & Appointments        -->
                <!-- ========================================== -->
                <?php if (!empty($experience) || $canEdit): ?>
                <div id="tab-content-experience" class="tab-pane hidden p-6" role="tabpanel" aria-labelledby="tab-btn-experience">
                    <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-5 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-briefcase text-academic-gold"></i>
                        <span>Academic Appointments & Professional Career</span>
                    </h3>
                    <?php if (empty($experience)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-briefcase text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No academic appointments recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                Faculty positions, fellowships, and academic leadership history have not yet been listed.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/add_appointment.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add Academic Appointment</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="academic-timeline">
                            <?php foreach ($experience as $exp): ?>
                                <div class="timeline-item <?= !empty($exp['is_current']) ? 'is-current' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="p-4 rounded-[8px] bg-slate-50 border border-scholar-border">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-1">
                                            <h4 class="font-serif text-base font-bold text-oxford-navy"><?= e($exp['position_title']) ?></h4>
                                            <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded-[4px] self-start sm:self-auto <?= !empty($exp['is_current']) ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-white text-slate-600 border border-scholar-border' ?>">
                                                <?= e($exp['start_year'] ?? '') ?> — <?= !empty($exp['is_current']) ? 'Present' : e($exp['end_year'] ?? 'Present') ?>
                                            </span>
                                        </div>
                                        <p class="text-xs font-semibold text-oxford-blue"><?= e($exp['organization']) ?></p>
                                        <?php if (!empty($exp['department'])): ?>
                                            <p class="text-xs text-slate-500 mt-0.5 font-medium"><?= e($exp['department']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($exp['description'])): ?>
                                            <p class="text-xs text-slate-600 mt-2 leading-relaxed font-sans"><?= nl2br(e($exp['description'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- TAB 4: Honors & Awards                     -->
                <!-- ========================================== -->
                <?php if (!empty($awards) || $canEdit): ?>
                <div id="tab-content-awards" class="tab-pane hidden p-6" role="tabpanel" aria-labelledby="tab-btn-awards">
                    <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-4 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-medal text-academic-gold"></i>
                        <span>Honors, Awards & Professional Recognitions</span>
                    </h3>
                    <?php if (empty($awards)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-trophy text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No honors or awards recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                Academic honors, medals, and society recognitions have not yet been listed.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/add_award.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add Honor or Award</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($awards as $awd): ?>
                                <div class="p-3.5 rounded-[8px] bg-slate-50 border border-scholar-border flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-serif text-sm font-bold text-oxford-navy"><?= e($awd['title']) ?></h4>
                                        <p class="text-xs text-slate-600 mt-0.5"><?= e($awd['awarding_body']) ?></p>
                                        <?php if (!empty($awd['description'])): ?>
                                            <p class="text-xs text-slate-500 mt-1"><?= e($awd['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-[4px] bg-white border border-scholar-border text-xs font-mono font-bold text-oxford-navy">
                                        <?= e($awd['year']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- TAB 5: Education & Qualifications          -->
                <!-- ========================================== -->
                <?php if (!empty($education) || $canEdit): ?>
                <div id="tab-content-education" class="tab-pane hidden p-6" role="tabpanel" aria-labelledby="tab-btn-education">
                    <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-4 font-mono flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap text-academic-gold"></i>
                        <span>Educational Background & Academic Credentials</span>
                    </h3>
                    <?php if (empty($education)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-graduation-cap text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No educational credentials recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                Doctoral, postgraduate, and collegiate degrees have not yet been listed.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/add_education.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add Qualification</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($education as $edu): ?>
                                <div class="p-3.5 rounded-[8px] bg-slate-50 border border-scholar-border flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-serif text-sm font-bold text-oxford-navy"><?= e($edu['degree']) ?></h4>
                                        <p class="text-xs text-slate-700 mt-0.5"><?= e($edu['institution']) ?></p>
                                        <?php 
                                            $spec = !empty($edu['specialization']) ? $edu['specialization'] : (!empty($edu['field_of_study']) ? $edu['field_of_study'] : '');
                                            if (!empty($spec)): 
                                        ?>
                                            <p class="text-xs text-slate-500 mt-0.5">Specialization: <?= e($spec) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($edu['year'])): ?>
                                        <span class="px-2.5 py-0.5 rounded-[4px] bg-white border border-scholar-border text-xs font-mono font-bold text-oxford-navy">
                                            <?= e($edu['year']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- TAB 6: Teaching & Mentorship               -->
                <!-- ========================================== -->
                <?php if (!empty($teaching) || ((int)($faculty['phd_supervised'] ?? 0) > 0) || $canEdit): ?>
                <div id="tab-content-teaching" class="tab-pane hidden p-6 space-y-6" role="tabpanel" aria-labelledby="tab-btn-teaching">
                    <?php if ((int)($faculty['phd_supervised'] ?? 0) > 0): ?>
                        <div class="p-4 rounded-[8px] bg-slate-50 border border-scholar-border flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-[6px] bg-oxford-navy text-white flex items-center justify-center text-lg">
                                    <i class="fa-solid fa-user-graduate"></i>
                                </div>
                                <div>
                                    <h4 class="font-serif text-sm font-bold text-oxford-navy">Doctoral Research Supervision</h4>
                                    <p class="text-xs text-slate-600">Ph.D. Scholars Successfully Guided / Under Guidance</p>
                                </div>
                            </div>
                            <span class="text-xl font-bold font-mono text-oxford-navy px-3 py-1 bg-white rounded-[6px] border border-scholar-border">
                                <?= (int)$faculty['phd_supervised'] ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($teaching)): ?>
                        <div class="py-10 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-chalkboard-user text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No course teaching assignments recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                Undergraduate, postgraduate, or doctoral courses have not yet been listed.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/add_teaching.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add Teaching Course</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div>
                            <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-3 font-mono flex items-center gap-2">
                                <i class="fa-solid fa-chalkboard text-academic-gold"></i>
                                <span>Courses Taught</span>
                            </h3>
                            <div class="space-y-2.5">
                                <?php foreach ($teaching as $t): ?>
                                    <div class="p-3 rounded-[8px] bg-slate-50 border border-scholar-border flex items-center justify-between gap-4">
                                        <div>
                                            <!-- Corrected Field Bug: course_title from database schema -->
                                            <h4 class="font-serif text-sm font-bold text-oxford-navy"><?= e($t['course_title'] ?? $t['course_name'] ?? 'Course Title') ?></h4>
                                            <p class="text-[11px] text-slate-500 font-mono">
                                                <?= e($t['course_code'] ?? '') ?> 
                                                <?= !empty($t['level']) ? '• ' . strtoupper(e($t['level'])) : '' ?>
                                            </p>
                                        </div>
                                        <?php if (!empty($t['academic_year'])): ?>
                                            <span class="text-[11px] font-mono px-2 py-0.5 rounded-[4px] bg-white border border-scholar-border text-slate-600">
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
                <!-- TAB 7: Service & Appointments              -->
                <!-- ========================================== -->
                <?php if (!empty($faculty['memberships']) || !empty($faculty['editorial_roles']) || $canEdit): ?>
                <div id="tab-content-service" class="tab-pane hidden p-6 space-y-6" role="tabpanel" aria-labelledby="tab-btn-service">
                    <?php if (empty($faculty['memberships']) && empty($faculty['editorial_roles'])): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="fa-solid fa-award text-xl"></i>
                            </div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy">No professional service recorded</h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                Professional memberships and editorial appointments have not yet been listed.
                            </p>
                            <?php if ($canEdit): ?>
                                <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs mt-3 inline-flex">
                                    <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                    <span>Edit Service & Roles</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($faculty['memberships'])): ?>
                            <div>
                                <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-3 font-mono flex items-center gap-2">
                                    <i class="fa-solid fa-id-card-clip text-academic-gold"></i>
                                    <span>Professional Memberships</span>
                                </h3>
                                <div class="space-y-2">
                                    <?php 
                                        $memberships = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $faculty['memberships'])));
                                        foreach ($memberships as $m): 
                                    ?>
                                        <div class="p-3 rounded-[8px] bg-slate-50 border border-scholar-border text-xs font-medium text-oxford-navy flex items-center gap-2">
                                            <i class="fa-solid fa-certificate text-academic-gold text-sm"></i>
                                            <span><?= e($m) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($faculty['editorial_roles'])): ?>
                            <div>
                                <h3 class="text-xs font-bold text-oxford-navy uppercase tracking-wider mb-3 font-mono flex items-center gap-2">
                                    <i class="fa-solid fa-pen-nib text-academic-gold"></i>
                                    <span>Editorial & Reviewer Appointments</span>
                                </h3>
                                <div class="space-y-2">
                                    <?php 
                                        $roles = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $faculty['editorial_roles'])));
                                        foreach ($roles as $r): 
                                    ?>
                                        <div class="p-3 rounded-[8px] bg-slate-50 border border-scholar-border text-xs font-medium text-oxford-navy flex items-center gap-2">
                                            <i class="fa-solid fa-book-journal-whills text-oxford-blue text-sm"></i>
                                            <span><?= e($r) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>

        </main>
    </div>
</div>

<!-- ========================================================= -->
<!-- ACADEMIC CITATION MODAL (APA 7, MLA 9, Chicago, Harvard, BibTeX) -->
<!-- ========================================================= -->
<div id="citationModal" class="scholar-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="citationModalTitle" aria-hidden="true">
    <div class="bg-white rounded-[12px] max-w-xl w-full p-6 sm:p-7 shadow-xl border border-scholar-border space-y-4">
        
        <!-- Header -->
        <div class="flex items-center justify-between pb-3 border-b border-scholar-border">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-[4px] bg-slate-100 flex items-center justify-center text-oxford-navy">
                    <i class="fa-solid fa-quote-left text-xs"></i>
                </div>
                <h3 id="citationModalTitle" class="font-serif text-lg font-bold text-oxford-navy">
                    Academic Citation
                </h3>
            </div>
            <button type="button" data-close-modal class="text-slate-400 hover:text-oxford-navy p-1 transition focus:outline-none" aria-label="Close citation modal">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Format Selector Tabs -->
        <div class="flex flex-wrap gap-1.5 p-1 bg-slate-100 rounded-[6px] text-xs font-semibold font-mono" role="tablist" aria-label="Citation style">
            <button type="button" data-citation-format="apa" role="tab" aria-selected="true" class="px-3 py-1.5 rounded-[4px] bg-oxford-navy text-white transition focus:outline-none">APA 7</button>
            <button type="button" data-citation-format="mla" role="tab" aria-selected="false" class="px-3 py-1.5 rounded-[4px] bg-slate-100 text-slate-700 hover:bg-slate-200 transition focus:outline-none">MLA 9</button>
            <button type="button" data-citation-format="chicago" role="tab" aria-selected="false" class="px-3 py-1.5 rounded-[4px] bg-slate-100 text-slate-700 hover:bg-slate-200 transition focus:outline-none">Chicago</button>
            <button type="button" data-citation-format="harvard" role="tab" aria-selected="false" class="px-3 py-1.5 rounded-[4px] bg-slate-100 text-slate-700 hover:bg-slate-200 transition focus:outline-none">Harvard</button>
            <button type="button" data-citation-format="bibtex" role="tab" aria-selected="false" class="px-3 py-1.5 rounded-[4px] bg-slate-100 text-slate-700 hover:bg-slate-200 transition focus:outline-none">BibTeX</button>
        </div>

        <!-- Citation Content Box -->
        <div class="relative">
            <div id="citationContentText" class="p-4 bg-slate-50 border border-scholar-border rounded-[6px] text-xs text-oxford-navy font-sans leading-relaxed select-all min-h-[90px] whitespace-pre-wrap"></div>
        </div>

        <!-- Actions -->
        <div class="pt-3 border-t border-scholar-border flex items-center justify-between">
            <span class="text-[11px] text-slate-400 font-sans">Verified Academic Citation</span>
            <div class="flex items-center gap-2">
                <button type="button" data-close-modal class="btn-academic-secondary text-xs !py-2 !px-3.5">
                    Close
                </button>
                <button type="button" id="copyCitationBtn" class="btn-academic-primary text-xs !py-2 !px-4 shadow-sm">
                    <i class="fa-regular fa-copy text-xs"></i>
                    <span>Copy Citation</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Tab Switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-oxford-navy', 'text-oxford-navy', 'bg-white', 'font-bold');
        btn.classList.add('border-transparent', 'text-slate-600');
        btn.setAttribute('aria-selected', 'false');
    });

    const activeContent = document.getElementById('tab-content-' + tabName);
    const activeBtn = document.getElementById('tab-btn-' + tabName);
    if (activeContent && activeBtn) {
        activeContent.classList.remove('hidden');
        activeBtn.classList.remove('border-transparent', 'text-slate-600');
        activeBtn.classList.add('border-oxford-navy', 'text-oxford-navy', 'bg-white', 'font-bold');
        activeBtn.setAttribute('aria-selected', 'true');
    }
}

// Client-side filtering of publications in profile
function filterPublications() {
    const q = (document.getElementById('pubFilterInput')?.value || '').toLowerCase();
    const type = (document.getElementById('pubTypeFilter')?.value || '').toLowerCase();
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
