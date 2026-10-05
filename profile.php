<?php
/**
 * Departmental Scholar — Individual Academic Researcher Profile
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Focus: High Legibility, Typographic Hierarchy, Refined Borders, Subdued Academic Elevation
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

// Group publications by year for chronological bibliography & metrics
$pubsByYear = [];
$pubsGroupedByYear = [];
foreach ($publications as $p) {
    $y = (int)($p['publication_year'] ?? 0);
    $yearLabel = $y > 0 ? (string)$y : 'Preprints & Other Works';
    $pubsGroupedByYear[$yearLabel][] = $p;
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

<div id="profile-page-wrapper">

<?php
/* === FIXED PROFILE CARD (hidden; animated in on scroll) === */
$sidebarSalutation    = $faculty['salutation'] ? $faculty['salutation'] . ' ' : '';
$sidebarFullName      = $sidebarSalutation . $faculty['full_name'];
$sidebarResolvedPhoto = faculty_photo_url($faculty['photo_url'] ?? null);
$sbPubs               = count($publications);
$sbCitations          = (int)($faculty['total_citations'] ?? 0);
$sbHIndex             = (int)($faculty['h_index'] ?? 0);
$sbI10                = (int)($faculty['i10_index'] ?? 0);
$sbGrants             = count($projects);
$sbPatents            = count($patents);
$sbPhD                = (int)($faculty['phd_supervised'] ?? 0);
$sbGrantsAmt          = $totalGrantsAmount ?? 0; /* already computed earlier in profile.php */
$sbResearchTags       = [];
if (!empty($faculty['research_interests'])) {
    $sbResearchTags = array_slice(
        array_filter(array_map('trim', explode(',', $faculty['research_interests']))),
        0, 5
    );
}
?>
<aside id="profile-sidebar-fixed" aria-hidden="true" aria-label="Faculty profile card">

    <!-- Scrollable inner wrapper -->
    <div class="sidebar-inner">

        <!-- ── Dark header with avatar + identity ── -->
        <div class="sidebar-header">

            <!-- Avatar with animated glow ring -->
            <div class="sidebar-avatar-wrap">
                <?php if ($sidebarResolvedPhoto): ?>
                    <img src="<?= $sidebarResolvedPhoto ?>" alt="<?= e($faculty['full_name']) ?>"
                         onerror="this.style.display='none'; document.getElementById('sb-avatar-fallback').style.display='flex';">
                    <div id="sb-avatar-fallback" class="sidebar-avatar-placeholder" style="display:none;">
                        <i class="fa-solid fa-user-graduate" style="font-size:1.75rem;color:rgba(255,255,255,0.3);"></i>
                    </div>
                <?php else: ?>
                    <div class="sidebar-avatar-placeholder">
                        <i class="fa-solid fa-user-graduate" style="font-size:1.75rem;color:rgba(255,255,255,0.3);"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Dept tag -->
            <?php if (!empty($faculty['department_code'])): ?>
            <div class="sidebar-dept-tag"><?= e($faculty['department_code']) ?></div>
            <?php endif; ?>

            <!-- Name -->
            <div class="sidebar-name"><?= e($sidebarFullName) ?></div>

            <!-- Designation -->
            <div class="sidebar-designation" style="margin-top:0.25rem;"><?= e($faculty['designation']) ?></div>

            <!-- Institution -->
            <div class="sidebar-institution"><?= e($faculty['institution'] ?? 'ITER, SOA Deemed to be University') ?></div>

            <!-- Verified Faculty — boxed badge -->
            <div class="sidebar-verified-badge">
                <i class="fa-solid fa-circle-check" style="font-size:0.65rem;"></i>
                <span>Verified Faculty</span>
            </div>
        </div>

        <!-- ── 2×2 Metric Grid: Papers · Citations · h-index · i10-index ── -->
        <div class="sidebar-metrics">
            <div class="sidebar-metric-item">
                <div class="sidebar-metric-val"><?= $sbPubs ?></div>
                <div class="sidebar-metric-label">Publications</div>
            </div>
            <div class="sidebar-metric-item">
                <div class="sidebar-metric-val"><?= $sbCitations > 999 ? number_format($sbCitations/1000, 1) . 'k' : $sbCitations ?></div>
                <div class="sidebar-metric-label">Citations</div>
            </div>
            <div class="sidebar-metric-item">
                <div class="sidebar-metric-val"><?= $sbHIndex ?></div>
                <div class="sidebar-metric-label">h-index</div>
            </div>
            <div class="sidebar-metric-item">
                <div class="sidebar-metric-val"><?= $sbI10 ?></div>
                <div class="sidebar-metric-label">i10-index</div>
            </div>
        </div>

        <!-- ── Quick Stats strip: Grants · Patents · Ph.D ── -->
        <?php if ($sbGrants > 0 || $sbPatents > 0 || $sbPhD > 0): ?>
        <div class="sidebar-quick-stats">
            <?php if ($sbGrantsAmt > 0): ?>
            <div class="sidebar-stat-pill">
                <div class="sidebar-stat-val">
                    <?php
                    if ($sbGrantsAmt >= 100000) {
                        echo '₹' . number_format($sbGrantsAmt / 100000, 1) . 'L';
                    } elseif ($sbGrantsAmt >= 1000) {
                        echo '₹' . number_format($sbGrantsAmt / 1000, 0) . 'K';
                    } else {
                        echo '₹' . number_format($sbGrantsAmt);
                    }
                    ?>
                </div>
                <div class="sidebar-stat-label">Grants</div>
            </div>
            <?php elseif ($sbGrants > 0): ?>
            <div class="sidebar-stat-pill">
                <div class="sidebar-stat-val"><?= $sbGrants ?></div>
                <div class="sidebar-stat-label">Grants</div>
            </div>
            <?php endif; ?>

            <?php if ($sbGrants > 0 && ($sbPatents > 0 || $sbPhD > 0)): ?>
            <div class="sidebar-stat-divider"></div>
            <?php endif; ?>

            <?php if ($sbPatents > 0): ?>
            <div class="sidebar-stat-pill">
                <div class="sidebar-stat-val"><?= $sbPatents ?></div>
                <div class="sidebar-stat-label">Patents</div>
            </div>
            <?php endif; ?>

            <?php if ($sbPatents > 0 && $sbPhD > 0): ?>
            <div class="sidebar-stat-divider"></div>
            <?php endif; ?>

            <?php if ($sbPhD > 0): ?>
            <div class="sidebar-stat-pill">
                <div class="sidebar-stat-val"><?= $sbPhD ?></div>
                <div class="sidebar-stat-label">Ph.D.</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>



        <!-- ── Body: Highlights & Quick Links ── -->
        <div class="sidebar-body">

            <!-- Research Areas Tags (compact top 3) -->
            <?php if (!empty($sbResearchTags)): ?>
            <div class="sidebar-section-label">Research Focus</div>
            <div class="sidebar-tags">
                <?php foreach (array_slice($sbResearchTags, 0, 4) as $tag): ?>
                <span class="sidebar-tag"><?= e($tag) ?></span>
                <?php endforeach; ?>
            </div>
            <hr class="sidebar-divider">
            <?php endif; ?>

            <!-- Contact Information -->
            <?php if (!empty($faculty['email']) || !empty($faculty['cabin'])): ?>
            <div class="sidebar-section-label">Contact</div>
            <div class="space-y-1">
                <?php if (!empty($faculty['email'])): ?>
                <a href="mailto:<?= e($faculty['email']) ?>" class="sidebar-contact-row" title="<?= e($faculty['email']) ?>">
                    <div class="sidebar-contact-icon"><i class="fa-solid fa-envelope"></i></div>
                    <span class="sidebar-contact-text truncate"><?= e($faculty['email']) ?></span>
                </a>
                <?php endif; ?>

                <?php if (!empty($faculty['cabin'])): ?>
                <div class="sidebar-contact-row">
                    <div class="sidebar-contact-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <span class="sidebar-contact-text truncate"><?= e($faculty['cabin']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- External Scholarly IDs (grid of badge pills) -->
            <?php if ($hasRegistries): ?>
            <hr class="sidebar-divider">
            <div class="sidebar-section-label">Scholarly Profiles</div>
            <div class="sidebar-registries-grid">
                <?php if (!empty($faculty['orcid_id'])): $cleanOrcid = safe_orcid($faculty['orcid_id']); if ($cleanOrcid): ?>
                <a href="https://orcid.org/<?= $cleanOrcid ?>" target="_blank" rel="noopener noreferrer" class="sidebar-registry-badge" title="ORCID: <?= e($cleanOrcid) ?>">
                    <i class="fa-brands fa-orcid text-emerald-600"></i>
                    <span>ORCID</span>
                </a>
                <?php endif; endif; ?>

                <?php if (!empty($faculty['google_scholar_url'])): ?>
                <a href="<?= safe_url($faculty['google_scholar_url']) ?>" target="_blank" rel="noopener noreferrer" class="sidebar-registry-badge" title="Google Scholar">
                    <i class="fa-brands fa-google text-blue-600"></i>
                    <span>Scholar</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($faculty['scopus_id'])): ?>
                <span class="sidebar-registry-badge" title="Scopus Author ID: <?= e($faculty['scopus_id']) ?>">
                    <i class="fa-solid fa-database text-amber-600"></i>
                    <span>Scopus</span>
                </span>
                <?php endif; ?>

                <?php if (!empty($faculty['researchgate_url'])): ?>
                <a href="<?= safe_url($faculty['researchgate_url']) ?>" target="_blank" rel="noopener noreferrer" class="sidebar-registry-badge" title="ResearchGate">
                    <i class="fa-brands fa-researchgate text-teal-600"></i>
                    <span>RG</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($faculty['website_url'])): ?>
                <a href="<?= safe_url($faculty['website_url']) ?>" target="_blank" rel="noopener noreferrer" class="sidebar-registry-badge" title="Personal Website">
                    <i class="fa-solid fa-globe text-indigo-600"></i>
                    <span>Web</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.sidebar-body -->

    </div><!-- /.sidebar-inner -->

    <!-- ── Edit Profile CTA ── -->
    <?php if ($canEdit): ?>
    <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="sidebar-edit-btn no-print">
        <i class="fa-solid fa-pen-to-square"></i>
        <span>Edit Profile</span>
    </a>
    <?php endif; ?>

</aside>

<div id="profile-main-area">



<!-- Breadcrumbs Bar -->

<div class="bg-white border-b border-scholar-border py-2.5">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 font-sans">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2">
            <a href="<?= url() ?>" class="hover:text-oxford-navy transition">Home</a>
            <i class="fa-solid fa-chevron-right text-[9px] text-slate-400"></i>
            <a href="<?= url('directory.php') ?>" class="hover:text-oxford-navy transition">Faculty Directory</a>
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
            <button type="button" onclick="window.print()" class="btn-academic-secondary text-xs !py-1 !px-2.5 shadow-xs" title="Print Academic CV">
                <i class="fa-solid fa-print text-xs"></i>
                <span class="hidden sm:inline">Print CV</span>
            </button>
            <button type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Profile link copied to clipboard.');" class="btn-academic-secondary text-xs !py-1 !px-2.5 shadow-xs" title="Share Profile">
                <i class="fa-solid fa-share-nodes text-xs"></i>
                <span class="hidden sm:inline">Share</span>
            </button>
            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/edit_profile.php?id=' . $faculty['id']) ?>" class="btn-academic-primary text-xs !py-1 !px-3 shadow-xs">
                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                    <span>Edit Profile</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Faculty Hero Identity Section (Dignified Academic Masthead) -->
<header class="bg-white border-b border-scholar-border">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <div class="flex flex-col sm:flex-row items-start gap-6 sm:gap-8">
            
            <!-- Portrait Frame -->
            <div class="relative w-32 h-32 sm:w-36 sm:h-36 rounded-[6px] bg-slate-100 border border-scholar-border shadow-xs overflow-hidden flex-shrink-0 flex items-center justify-center">
                <?php $resolvedPhoto = faculty_photo_url($faculty['photo_url'] ?? null); ?>
                <?php if ($resolvedPhoto): ?>
                    <img src="<?= $resolvedPhoto ?>" alt="<?= e($faculty['full_name']) ?>" class="w-full h-full object-cover"
                         onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                    <div class="hidden text-slate-300 flex flex-col items-center justify-center w-full h-full">
                        <i class="fa-solid fa-user-graduate text-4xl"></i>
                    </div>
                <?php else: ?>
                    <div class="text-slate-300 flex flex-col items-center justify-center w-full h-full">
                        <i class="fa-solid fa-user-graduate text-4xl"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Faculty Metadata Details -->
            <div class="flex-grow min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="academic-tag font-mono text-[10px] font-bold">
                        <?= e($faculty['department_code'] ?? 'SCHOLAR') ?>
                    </span>
                    <span class="academic-tag academic-tag-green text-[10px] !py-0.5">
                        <i class="fa-solid fa-circle-check text-[10px]"></i> Verified Faculty
                    </span>
                    <?php if (!empty($faculty['cv_url'])): ?>
                        <a href="<?= safe_url($faculty['cv_url']) ?>" target="_blank" rel="noopener noreferrer" 
                           class="academic-tag hover:border-oxford-slate hover:text-oxford-navy text-[10px] transition">
                            <i class="fa-solid fa-file-pdf text-rose-600 text-[10px]"></i> Curriculum Vitae
                        </a>
                    <?php endif; ?>
                </div>

                <h1 class="font-serif text-2xl sm:text-4xl font-bold text-oxford-navy leading-tight">
                    <?= e(($faculty['salutation'] ? $faculty['salutation'] . ' ' : '') . $faculty['full_name']) ?>
                </h1>

                <p class="text-base sm:text-lg font-medium text-oxford-slate mt-1">
                    <?= e($faculty['designation']) ?>
                </p>

                <p class="text-xs sm:text-sm text-scholar-muted mt-0.5">
                    Department of <?= e($faculty['department_name']) ?> • <?= e($faculty['institution'] ?? 'Institute of Technical Education & Research, SOA Deemed to be University') ?>
                </p>

                <!-- Contact & Office Info -->
                <div class="mt-4 flex flex-wrap items-center gap-y-2 gap-x-5 text-xs text-slate-600 font-sans">
                    <?php if (!empty($faculty['cabin'])): ?>
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-door-open text-slate-400 text-xs"></i>
                            <span>Office: <strong><?= e($faculty['cabin']) ?></strong></span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($faculty['phone'])): ?>
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-phone text-slate-400 text-xs"></i>
                            <span class="font-mono"><?= e($faculty['phone']) ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($faculty['email'])): ?>
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-envelope text-slate-400 text-xs"></i>
                            <a href="mailto:<?= e($faculty['email']) ?>" class="text-oxford-slate hover:text-oxford-navy hover:underline font-medium">
                                <?= e($faculty['email']) ?>
                            </a>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Scholarly Identifiers Bar -->
                <?php
                    $hasRegistries = !empty($faculty['google_scholar_url']) || !empty($faculty['orcid_id']) || !empty($faculty['scopus_id']) 
                        || !empty($faculty['wos_id']) || !empty($faculty['researchgate_url']) || !empty($faculty['dblp_url']) 
                        || !empty($faculty['semantic_scholar_url']) || !empty($faculty['website_url']);
                ?>
                <?php if ($hasRegistries): ?>
                    <div class="mt-4 pt-3.5 border-t border-scholar-border-light flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-xs text-slate-400 font-semibold mr-1">External Registries:</span>
                        
                        <?php if (!empty($faculty['orcid_id'])): ?>
                            <?php $cleanOrcid = safe_orcid($faculty['orcid_id']); ?>
                            <?php if ($cleanOrcid): ?>
                                <a href="https://orcid.org/<?= $cleanOrcid ?>" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 font-mono text-[11px] transition">
                                    <i class="fa-brands fa-orcid text-emerald-600"></i>
                                    <span><?= e($cleanOrcid) ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($faculty['google_scholar_url'])): ?>
                            <a href="<?= safe_url($faculty['google_scholar_url']) ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-[11px] font-medium transition">
                                <i class="fa-brands fa-google text-blue-600"></i>
                                <span>Google Scholar</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($faculty['scopus_id'])): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-50 border border-slate-200 text-slate-800 text-[11px] font-medium">
                                <i class="fa-solid fa-database text-amber-700"></i>
                                <span class="font-mono">Scopus: <?= e($faculty['scopus_id']) ?></span>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($faculty['wos_id'])): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-50 border border-slate-200 text-slate-800 text-[11px] font-medium">
                                <i class="fa-solid fa-book-bookmark text-slate-700"></i>
                                <span class="font-mono">WoS: <?= e($faculty['wos_id']) ?></span>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($faculty['researchgate_url'])): ?>
                            <a href="<?= safe_url($faculty['researchgate_url']) ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-[11px] font-medium transition">
                                <i class="fa-brands fa-researchgate text-teal-700"></i>
                                <span>ResearchGate</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($faculty['dblp_url'])): ?>
                            <a href="<?= safe_url($faculty['dblp_url']) ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-[11px] font-medium transition">
                                <i class="fa-solid fa-code text-indigo-700"></i>
                                <span>DBLP</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($faculty['semantic_scholar_url'])): ?>
                            <a href="<?= safe_url($faculty['semantic_scholar_url']) ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-[11px] font-medium transition">
                                <i class="fa-solid fa-brain text-sky-700"></i>
                                <span>Semantic Scholar</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($faculty['website_url'])): ?>
                            <a href="<?= safe_url($faculty['website_url']) ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-[11px] font-medium transition">
                                <i class="fa-solid fa-globe text-slate-600"></i>
                                <span>Homepage</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</header>

<!-- Sticky In-Page Tab Navigation Bar -->
<nav class="academic-jump-nav no-print" aria-label="Profile Sections">
    <div class="w-full px-4 sm:px-6 lg:px-8 flex items-center justify-start lg:justify-center overflow-x-auto scrollbar-none gap-0">
        <a href="#overview" class="academic-jump-link">Overview</a>
        <a href="#impact" class="academic-jump-link">Academic Impact</a>
        <a href="#publications" class="academic-jump-link">
            Publications <span class="ml-1 text-xs opacity-60 font-mono">(<?= count($publications) ?>)</span>
        </a>
        <?php if (!empty($projects)): ?>
            <a href="#projects" class="academic-jump-link">Grants <span class="ml-1 text-xs opacity-60 font-mono">(<?= count($projects) ?>)</span></a>
        <?php endif; ?>
        <?php if (!empty($patents)): ?>
            <a href="#patents" class="academic-jump-link">Patents <span class="ml-1 text-xs opacity-60 font-mono">(<?= count($patents) ?>)</span></a>
        <?php endif; ?>
        <?php if (!empty($experience)): ?>
            <a href="#experience" class="academic-jump-link">Experience</a>
        <?php endif; ?>
        <?php if (!empty($education)): ?>
            <a href="#education" class="academic-jump-link">Education</a>
        <?php endif; ?>
        <?php if (!empty($teaching) || ((int)($faculty['phd_supervised'] ?? 0) > 0)): ?>
            <a href="#teaching" class="academic-jump-link">Teaching &amp; Mentorship</a>
        <?php endif; ?>
        <?php if (!empty($awards)): ?>
            <a href="#awards" class="academic-jump-link">Honors &amp; Awards</a>
        <?php endif; ?>
        <?php if (!empty($faculty['memberships']) || !empty($faculty['editorial_roles'])): ?>
            <a href="#service" class="academic-jump-link">Service &amp; Affiliations</a>
        <?php endif; ?>
    </div>
</nav>


<!-- Main Vertical Scrollable Academic Content Container -->
<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-12">

    <!-- ========================================================= -->
    <!-- 1. RESEARCH OVERVIEW & TOPICS                             -->
    <!-- ========================================================= -->
    <section id="overview" class="scroll-mt-16">
        <h2 class="academic-section-title">
            <span>Research Overview</span>
        </h2>

        <?php if (!empty($faculty['bio'])): ?>
            <div class="text-sm sm:text-base text-slate-800 leading-relaxed font-sans space-y-4 max-w-4xl">
                <?= nl2br(e($faculty['bio'])) ?>
            </div>
        <?php else: ?>
            <p class="text-sm sm:text-base text-slate-700 leading-relaxed font-sans max-w-4xl">
                <?= e(($faculty['salutation'] ? $faculty['salutation'] . ' ' : '') . $faculty['full_name']) ?> serves as <?= e($faculty['designation']) ?> in the Department of <?= e($faculty['department_name']) ?> at <?= e($faculty['institution'] ?? 'ITER, SOA University') ?>, leading research initiatives, curriculum delivery, and postgraduate mentorship.
            </p>
        <?php endif; ?>

        <!-- Research Areas & Taxonomy -->
        <?php if (!empty($faculty['research_interests'])): ?>
            <div class="mt-6 pt-5 border-t border-scholar-border-light">
                <span class="text-xs font-semibold text-oxford-slate uppercase tracking-wider block mb-2.5 font-sans">
                    Research Areas & Specializations
                </span>
                <div class="flex flex-wrap gap-2">
                    <?php 
                        $tags = array_map('trim', explode(',', $faculty['research_interests']));
                        foreach ($tags as $tag):
                    ?>
                        <a href="<?= url('directory.php?q=' . urlencode($tag)) ?>" 
                           class="academic-tag text-xs font-medium hover:border-oxford-slate hover:text-oxford-navy transition">
                            <?= e($tag) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- ========================================================= -->
    <!-- 2. ACADEMIC IMPACT & METRICS (Editorial Typographic Flow) -->
    <!-- ========================================================= -->
    <section id="impact" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <h2 class="academic-section-title">
            <span>Academic Impact & Scholarly Metrics</span>
        </h2>

        <!-- Primary Impact Metrics: Numbers Are Information, Not Seven Identical Cards -->
        <div class="editorial-impact-grid mt-6">
            <div class="editorial-metric-item">
                <div class="editorial-metric-val"><?= count($publications) ?></div>
                <div class="editorial-metric-sub">Indexed Publications</div>
            </div>

            <div class="editorial-metric-item">
                <div class="editorial-metric-val text-oxford-slate"><?= number_format($faculty['total_citations']) ?></div>
                <div class="editorial-metric-sub">Citations (Self-reported)</div>
            </div>

            <div class="editorial-metric-item">
                <div class="editorial-metric-val"><?= (int)$faculty['h_index'] ?></div>
                <div class="editorial-metric-sub">Scholar h-index</div>
            </div>

            <div class="editorial-metric-item">
                <div class="editorial-metric-val"><?= (int)$faculty['i10_index'] ?></div>
                <div class="editorial-metric-sub">i10-index (≥10 citations)</div>
            </div>

            <div class="editorial-metric-item">
                <div class="editorial-metric-val text-emerald-800">
                    <?= $totalGrantsAmount > 0 ? '₹' . number_format($totalGrantsAmount, 1) . 'L' : count($projects) ?>
                </div>
                <div class="editorial-metric-sub"><?= count($projects) ?> Sponsored Grant<?= count($projects) !== 1 ? 's' : '' ?></div>
            </div>

            <div class="editorial-metric-item">
                <div class="editorial-metric-val text-amber-800"><?= count($patents) ?></div>
                <div class="editorial-metric-sub">Patents & Inventions</div>
            </div>

            <div class="editorial-metric-item">
                <div class="editorial-metric-val"><?= (int)($faculty['phd_supervised'] ?? 0) ?></div>
                <div class="editorial-metric-sub">Ph.D. Scholars Guided</div>
            </div>
        </div>

        <!-- Annual Publication Trajectory Chart -->
        <?php if (!empty($pubsByYear)): ?>
            <div class="mt-8 pt-6 border-t border-scholar-border-light">
                <div class="flex items-center justify-between mb-3 text-xs text-slate-500 font-sans">
                    <span class="font-semibold text-oxford-slate">Publication Trajectory (Works Published by Year)</span>
                    <span class="font-mono"><?= count($publications) ?> Total Indexed Works</span>
                </div>
                <?php $maxCount = max($pubsByYear); ?>
                <div class="flex items-end gap-2 h-20 pt-2 px-1">
                    <?php foreach ($pubsByYear as $yr => $cnt): ?>
                        <?php $barHeight = round(($cnt / $maxCount) * 100); ?>
                        <div class="flex-1 flex flex-col items-center gap-1 group relative">
                            <div class="absolute -top-7 hidden group-hover:flex items-center px-1.5 py-0.5 bg-oxford-navy text-white rounded-[4px] text-[10px] whitespace-nowrap z-10 shadow font-mono">
                                <?= $yr ?>: <?= $cnt ?> publication<?= $cnt > 1 ? 's' : '' ?>
                            </div>
                            <div class="w-full bg-slate-200 group-hover:bg-oxford-navy rounded-t-[2px] transition" style="height: <?= max(12, $barHeight) ?>%;"></div>
                            <span class="text-[10px] text-slate-500 font-mono"><?= substr((string)$yr, -2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Transparency Footnote -->
        <div class="text-[11px] text-slate-400 font-sans mt-4 text-right flex items-center justify-end gap-1.5">
            <i class="fa-solid fa-circle-info text-[10px]"></i>
            <span>Metrics are Self-reported • Last updated <?= !empty($faculty['updated_at']) ? date('M Y', strtotime($faculty['updated_at'])) : date('M Y') ?></span>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- 3. PUBLICATIONS & SCHOLARLY WORKS (Academic Bibliography)  -->
    <!-- ========================================================= -->
    <section id="publications" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="academic-section-title !mb-0">
                <span>Publications & Scholarly Works</span>
                <span class="text-sm font-sans font-normal text-slate-400">(<?= count($publications) ?>)</span>
            </h2>

            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/add_publication.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Publication</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($publications)): ?>
            <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-[6px]">
                <p class="font-serif text-sm font-semibold text-oxford-navy">No publications indexed yet</p>
                <p class="text-xs text-slate-500 mt-1">Scholarly articles, books, and conference proceedings will appear here once added.</p>
            </div>
        <?php else: ?>
            <!-- Filter Toolbar -->
            <div class="mb-6 p-3 bg-slate-50 rounded-[6px] border border-scholar-border flex flex-col sm:flex-row gap-3 items-center justify-between no-print">
                <div class="relative w-full sm:w-72">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" id="pubFilterInput" onkeyup="filterPublications()"
                        placeholder="Search title, venue, or year..."
                        class="academic-input pl-8 text-xs !py-1.5">
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <label for="pubTypeFilter" class="text-xs text-slate-600 font-medium whitespace-nowrap">Format:</label>
                    <select id="pubTypeFilter" onchange="filterPublications()" class="academic-input text-xs !py-1.5 max-w-xs">
                        <option value="">All Works</option>
                        <option value="journal_article">Journal Articles</option>
                        <option value="conference_paper">Conference Papers</option>
                        <option value="book_chapter">Book Chapters</option>
                        <option value="book">Books</option>
                        <option value="patent">Patents</option>
                    </select>
                </div>
            </div>

            <!-- Chronologically Grouped Academic Bibliography -->
            <div id="publicationsContainer" class="space-y-8">
                <?php foreach ($pubsGroupedByYear as $yearLabel => $yearPubs): ?>
                    <div class="pub-year-group">
                        <div class="flex items-center gap-3 mb-2 pb-1 border-b border-scholar-border">
                            <span class="font-serif font-bold text-lg text-oxford-navy"><?= e($yearLabel) ?></span>
                            <span class="text-xs text-slate-400 font-sans font-medium">— <?= count($yearPubs) ?> work<?= count($yearPubs) !== 1 ? 's' : '' ?></span>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <?php foreach ($yearPubs as $pub): ?>
                                <article class="pub-item academic-pub-entry" 
                                    data-title="<?= strtolower(e($pub['title'])) ?>"
                                    data-venue="<?= strtolower(e($pub['journal_conference_name'])) ?>"
                                    data-type="<?= strtolower(e($pub['publication_type'])) ?>">
                                    
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-grow space-y-1">
                                            <!-- Title -->
                                            <h3 class="font-serif text-base sm:text-lg font-bold text-oxford-navy leading-snug">
                                                <?php if (!empty($pub['doi'])): ?>
                                                    <a href="https://doi.org/<?= e($pub['doi']) ?>" target="_blank" rel="noopener noreferrer" 
                                                       class="hover:text-oxford-slate transition">
                                                        <?= e($pub['title']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e($pub['title']) ?>
                                                <?php endif; ?>
                                            </h3>

                                            <!-- Authors -->
                                            <p class="text-xs sm:text-sm text-slate-700 font-medium">
                                                <?= e($pub['authors']) ?>
                                            </p>

                                            <!-- Venue & Journal Reference (Italicized) -->
                                            <p class="text-xs text-slate-600 font-sans">
                                                <span class="italic text-oxford-slate font-serif"><?= e($pub['journal_conference_name']) ?></span><?php if (!empty($pub['volume'])): ?>, Vol. <span class="font-mono"><?= e($pub['volume']) ?></span><?php endif; ?><?php if (!empty($pub['pages'])): ?>, pp. <span class="font-mono"><?= e($pub['pages']) ?></span><?php endif; ?> (<?= e($pub['publication_year']) ?>)
                                            </p>

                                            <!-- Indexing Badges & Action Links -->
                                            <div class="pt-2 flex flex-wrap items-center gap-2 text-[11px]">
                                                <span class="academic-tag font-mono capitalize">
                                                    <?= e(str_replace('_', ' ', $pub['publication_type'])) ?>
                                                </span>

                                                <?php if (!empty($pub['is_open_access']) || !empty($pub['pdf_url'])): ?>
                                                    <span class="academic-tag academic-tag-oa">
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
                                                    class="inline-flex items-center gap-1 text-oxford-slate hover:text-oxford-navy font-semibold px-2 py-0.5 rounded-[4px] hover:bg-slate-100 transition no-print">
                                                    <i class="fa-solid fa-quote-left text-[10px]"></i>
                                                    <span>Cite</span>
                                                </button>
                                            </div>

                                            <?php if (!empty($pub['abstract'])): ?>
                                                <details class="text-xs text-slate-600 mt-2 bg-slate-50/70 p-2.5 rounded-[6px] border border-slate-200/60 group no-print">
                                                    <summary class="cursor-pointer font-semibold text-oxford-slate hover:text-oxford-navy flex items-center gap-1.5 select-none text-[11px]">
                                                        <i class="fa-solid fa-align-left text-[10px]"></i>
                                                        <span>View Abstract</span>
                                                    </summary>
                                                    <p class="mt-2 text-slate-700 leading-relaxed font-sans text-xs pt-1 border-t border-slate-200/60">
                                                        <?= nl2br(e($pub['abstract'])) ?>
                                                    </p>
                                                </details>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Citations Counter -->
                                        <div class="flex flex-col items-end flex-shrink-0 text-right">
                                            <span class="font-bold text-xs text-oxford-navy font-mono">
                                                <?= (int)$pub['citation_count'] > 0 ? (int)$pub['citation_count'] : '—' ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-sans">citations</span>
                                        </div>
                                    </div>

                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ========================================================= -->
    <!-- 4. RESEARCH & EXTRAMURAL FUNDING (Sponsored Grants)        -->
    <!-- ========================================================= -->
    <?php if (!empty($projects) || $canEdit): ?>
    <section id="projects" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="academic-section-title !mb-0">
                <span>Sponsored Research & Extramural Grants</span>
                <span class="text-sm font-sans font-normal text-slate-400">(<?= count($projects) ?>)</span>
            </h2>

            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/add_project.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Project</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($projects)): ?>
            <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-[6px]">
                <p class="font-serif text-sm font-semibold text-oxford-navy">No sponsored projects recorded</p>
                <p class="text-xs text-slate-500 mt-1">Extramural funding, government grants, and industry research awards will be listed here.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($projects as $proj): ?>
                    <div class="py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block px-2 py-0.5 rounded-[4px] text-[10px] font-bold uppercase tracking-wider <?= $proj['status'] === 'ongoing' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                        <?= e($proj['status']) ?>
                                    </span>
                                    <?php if (!empty($proj['role'])): ?>
                                        <span class="text-xs text-slate-500 font-medium">Role: <?= e($proj['role']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-serif text-base font-bold text-oxford-navy"><?= e($proj['title']) ?></h3>
                                <p class="text-xs text-slate-600 font-sans">
                                    <strong>Funding Agency:</strong> <?= e($proj['funding_agency']) ?>
                                    <?php if (!empty($proj['project_code'])): ?> | <strong>Sanction Code:</strong> <span class="font-mono"><?= e($proj['project_code']) ?></span><?php endif; ?>
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="text-sm font-bold text-oxford-navy font-mono">₹<?= number_format((float)$proj['amount_lakhs'], 2) ?> Lakhs</span>
                                <span class="text-[11px] text-slate-500 block font-mono mt-0.5">
                                    <?= e($proj['start_year'] ?? '') ?> — <?= e($proj['end_year'] ?? 'Present') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 5. INTELLECTUAL PROPERTY & PATENTS                         -->
    <!-- ========================================================= -->
    <?php if (!empty($patents) || $canEdit): ?>
    <section id="patents" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="academic-section-title !mb-0">
                <span>Patents & Intellectual Property</span>
                <span class="text-sm font-sans font-normal text-slate-400">(<?= count($patents) ?>)</span>
            </h2>

            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/add_patent.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Patent</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($patents)): ?>
            <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-[6px]">
                <p class="font-serif text-sm font-semibold text-oxford-navy">No patents or intellectual property recorded</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($patents as $pat): ?>
                    <div class="py-4 flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <span class="inline-block px-2 py-0.5 rounded-[4px] text-[10px] font-bold uppercase tracking-wider <?= $pat['status'] === 'granted' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-200 text-slate-800' ?>">
                                <?= e($pat['status']) ?>
                            </span>
                            <h3 class="font-serif text-base font-bold text-oxford-navy"><?= e($pat['title']) ?></h3>
                            <p class="text-xs text-slate-600 font-sans">
                                <strong>Application / Patent No:</strong> <span class="font-mono"><?= e($pat['patent_number'] ?? 'Pending') ?></span>
                                | <strong>Jurisdiction:</strong> <?= e($pat['country']) ?>
                            </p>
                        </div>
                        <?php if (!empty($pat['grant_date'])): ?>
                            <div class="text-right flex-shrink-0 text-xs text-slate-500 font-sans">
                                <span class="block text-[10px] text-slate-400 uppercase">Grant Date</span>
                                <span class="font-mono font-semibold text-oxford-navy"><?= e($pat['grant_date']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 6. ACADEMIC APPOINTMENTS & CAREER EXPERIENCE               -->
    <!-- ========================================================= -->
    <?php if (!empty($experience) || $canEdit): ?>
    <section id="experience" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="academic-section-title !mb-0">
                <span>Academic Appointments & Career History</span>
            </h2>

            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/add_appointment.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Appointment</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($experience)): ?>
            <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-[6px]">
                <p class="font-serif text-sm font-semibold text-oxford-navy">No academic appointments recorded</p>
            </div>
        <?php else: ?>
            <div class="academic-timeline">
                <?php foreach ($experience as $exp): ?>
                    <div class="timeline-item <?= !empty($exp['is_current']) ? 'is-current' : '' ?>">
                        <div class="timeline-dot"></div>
                        <div class="p-4 rounded-[6px] bg-slate-50 border border-scholar-border">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-1">
                                <h3 class="font-serif text-base font-bold text-oxford-navy"><?= e($exp['position_title']) ?></h3>
                                <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded-[4px] self-start sm:self-auto <?= !empty($exp['is_current']) ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-white text-slate-600 border border-scholar-border' ?>">
                                    <?= e($exp['start_year'] ?? '') ?> — <?= !empty($exp['is_current']) ? 'Present' : e($exp['end_year'] ?? 'Present') ?>
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-oxford-slate"><?= e($exp['organization']) ?></p>
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
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 7. EDUCATIONAL QUALIFICATIONS                             -->
    <!-- ========================================================= -->
    <?php if (!empty($education) || $canEdit): ?>
    <section id="education" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="academic-section-title !mb-0">
                <span>Educational Qualifications & Degrees</span>
            </h2>

            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/add_education.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Qualification</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($education)): ?>
            <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-[6px]">
                <p class="font-serif text-sm font-semibold text-oxford-navy">No degrees or educational credentials recorded</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($education as $edu): ?>
                    <div class="py-3.5 flex items-start justify-between gap-4">
                        <div class="space-y-0.5">
                            <h3 class="font-serif text-base font-bold text-oxford-navy"><?= e($edu['degree']) ?></h3>
                            <p class="text-xs sm:text-sm text-slate-700"><?= e($edu['institution']) ?></p>
                            <?php 
                                $spec = !empty($edu['specialization']) ? $edu['specialization'] : (!empty($edu['field_of_study']) ? $edu['field_of_study'] : '');
                                if (!empty($spec)): 
                            ?>
                                <p class="text-xs text-slate-500">Specialization: <?= e($spec) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($edu['year'])): ?>
                            <span class="px-2.5 py-0.5 rounded-[4px] bg-slate-100 border border-slate-200 text-xs font-mono font-bold text-oxford-navy">
                                <?= e($edu['year']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 8. TEACHING & MENTORSHIP                                  -->
    <!-- ========================================================= -->
    <?php if (!empty($teaching) || ((int)($faculty['phd_supervised'] ?? 0) > 0) || $canEdit): ?>
    <section id="teaching" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="academic-section-title !mb-0">
                <span>Teaching & Research Mentorship</span>
            </h2>

            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/add_teaching.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Course</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if ((int)($faculty['phd_supervised'] ?? 0) > 0): ?>
            <div class="mb-6 p-4 rounded-[6px] bg-slate-50 border border-scholar-border flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-[4px] bg-oxford-navy text-white flex items-center justify-center text-lg">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div>
                        <h3 class="font-serif text-sm font-bold text-oxford-navy">Doctoral Research Supervision</h3>
                        <p class="text-xs text-slate-600">Ph.D. Scholars Successfully Guided / Under Guidance</p>
                    </div>
                </div>
                <span class="text-xl font-bold font-mono text-oxford-navy px-3 py-1 bg-white rounded-[4px] border border-scholar-border">
                    <?= (int)$faculty['phd_supervised'] ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($teaching)): ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($teaching as $t): ?>
                    <div class="py-3 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-serif text-sm font-bold text-oxford-navy"><?= e($t['course_title'] ?? $t['course_name'] ?? 'Course Title') ?></h3>
                            <p class="text-xs text-slate-500 font-sans">
                                <span class="font-mono"><?= e($t['course_code'] ?? '') ?></span>
                                <?= !empty($t['level']) ? ' • ' . strtoupper(e($t['level'])) : '' ?>
                            </p>
                        </div>
                        <?php if (!empty($t['academic_year'])): ?>
                            <span class="text-xs font-mono px-2 py-0.5 rounded-[4px] bg-slate-100 border border-slate-200 text-slate-600">
                                <?= e($t['academic_year']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 9. HONORS & RECOGNITIONS                                  -->
    <!-- ========================================================= -->
    <?php if (!empty($awards) || $canEdit): ?>
    <section id="awards" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="academic-section-title !mb-0">
                <span>Honors, Awards & Recognitions</span>
            </h2>

            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/add_award.php?profile_id=' . $faculty['id']) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Award</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($awards)): ?>
            <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-[6px]">
                <p class="font-serif text-sm font-semibold text-oxford-navy">No honors or awards recorded</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($awards as $awd): ?>
                    <div class="py-3.5 flex items-start justify-between gap-4">
                        <div class="space-y-0.5">
                            <h3 class="font-serif text-sm sm:text-base font-bold text-oxford-navy"><?= e($awd['title']) ?></h3>
                            <p class="text-xs text-slate-700"><?= e($awd['awarding_body']) ?></p>
                            <?php if (!empty($awd['description'])): ?>
                                <p class="text-xs text-slate-500 mt-1 font-sans"><?= e($awd['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-[4px] bg-slate-100 border border-slate-200 text-xs font-mono font-bold text-oxford-navy">
                            <?= e($awd['year']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 10. ACADEMIC SERVICE & AFFILIATIONS                       -->
    <!-- ========================================================= -->
    <?php if (!empty($faculty['memberships']) || !empty($faculty['editorial_roles'])): ?>
    <section id="service" class="scroll-mt-16 pt-8 border-t border-scholar-border">
        <h2 class="academic-section-title">
            <span>Academic Service & Professional Affiliations</span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
            <?php if (!empty($faculty['memberships'])): ?>
                <div>
                    <h3 class="text-xs font-semibold text-oxford-slate uppercase tracking-wider mb-2.5 font-sans">
                        Professional Society Memberships
                    </h3>
                    <ul class="space-y-2">
                        <?php 
                            $memberships = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $faculty['memberships'])));
                            foreach ($memberships as $m): 
                        ?>
                            <li class="p-2.5 rounded-[4px] bg-slate-50 border border-scholar-border text-xs text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-certificate text-academic-gold text-xs flex-shrink-0"></i>
                                <span><?= e($m) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($faculty['editorial_roles'])): ?>
                <div>
                    <h3 class="text-xs font-semibold text-oxford-slate uppercase tracking-wider mb-2.5 font-sans">
                        Editorial Boards & Peer Review Service
                    </h3>
                    <ul class="space-y-2">
                        <?php 
                            $roles = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $faculty['editorial_roles'])));
                            foreach ($roles as $r): 
                        ?>
                            <li class="p-2.5 rounded-[4px] bg-slate-50 border border-scholar-border text-xs text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-pen-nib text-oxford-slate text-xs flex-shrink-0"></i>
                                <span><?= e($r) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- ========================================================= -->
<!-- ACADEMIC CITATION MODAL (APA 7, MLA 9, Chicago, Harvard, BibTeX) -->
<!-- ========================================================= -->
<div id="citationModal" class="scholar-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="citationModalTitle" aria-hidden="true">
    <div class="bg-white rounded-[8px] max-w-xl w-full p-6 sm:p-7 shadow-xl border border-scholar-border space-y-4">
        
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
            <span class="text-[11px] text-slate-400 font-sans">Standard Academic Citation</span>
            <div class="flex items-center gap-2">
                <button type="button" data-close-modal class="btn-academic-secondary text-xs !py-1.5 !px-3">
                    Close
                </button>
                <button type="button" id="copyCitationBtn" class="btn-academic-primary text-xs !py-1.5 !px-3.5 shadow-sm">
                    <i class="fa-regular fa-copy text-xs"></i>
                    <span>Copy Citation</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
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

    // Hide empty year headers if all pubs under that year are hidden
    document.querySelectorAll('.pub-year-group').forEach(group => {
        const visiblePubs = group.querySelectorAll('.pub-item[style*="display: block"], .pub-item:not([style*="display: none"])');
        group.style.display = visiblePubs.length > 0 ? 'block' : 'none';
    });
}

// Academic Citation Generation Modal
let activeCitationData = null;
let currentCitationFormat = 'apa';

function generateCitation(data, format) {
    const authors = data.authors || 'Author';
    const year = data.year || 'n.d.';
    const title = data.title || 'Untitled';
    const venue = data.venue || 'Journal';
    const vol = data.volume ? ` ${data.volume}` : '';
    const iss = data.issue ? `(${data.issue})` : '';
    const pp = data.pages ? `, pp. ${data.pages}` : '';
    const doi = data.doi ? ` https://doi.org/${data.doi}` : '';

    switch (format) {
        case 'apa':
            return `${authors} (${year}). ${title}. ${venue},${vol}${iss}${pp}.${doi}`;
        case 'mla':
            return `${authors}. "${title}." ${venue}${vol ? ', vol.' + vol : ''}${iss ? ', no.' + iss : ''}, ${year}${pp ? ', pp. ' + data.pages : ''}.${doi}`;
        case 'chicago':
            return `${authors}. "${title}." ${venue}${vol} (${year})${pp}.${doi}`;
        case 'harvard':
            return `${authors}, ${year}. ${title}. ${venue},${vol}${iss}${pp}.${doi}`;
        case 'bibtex':
            const cleanKey = (authors.split(/[\s,]+/)[0] || 'article') + year;
            return `@article{${cleanKey},\n  author = {${authors}},\n  title = {${title}},\n  journal = {${venue}},\n  year = {${year}},\n  volume = {${data.volume || ''}},\n  pages = {${data.pages || ''}},\n  doi = {${data.doi || ''}}\n}`;
        default:
            return `${authors} (${year}). ${title}. ${venue}.`;
    }
}

document.addEventListener('click', function(e) {
    const citeBtn = e.target.closest('[data-cite-btn]');
    if (citeBtn) {
        try {
            activeCitationData = JSON.parse(citeBtn.getAttribute('data-publication'));
            currentCitationFormat = 'apa';
            renderCitationModal();
            const modal = document.getElementById('citationModal');
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
        } catch (err) {
            console.error('Failed to parse publication data:', err);
        }
    }

    if (e.target.closest('[data-close-modal]') || e.target.classList.contains('scholar-modal-backdrop')) {
        const modal = document.getElementById('citationModal');
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }
});

function renderCitationModal() {
    if (!activeCitationData) return;
    const box = document.getElementById('citationContentText');
    box.textContent = generateCitation(activeCitationData, currentCitationFormat);

    document.querySelectorAll('[data-citation-format]').forEach(btn => {
        const fmt = btn.getAttribute('data-citation-format');
        if (fmt === currentCitationFormat) {
            btn.className = 'px-3 py-1.5 rounded-[4px] bg-oxford-navy text-white transition focus:outline-none';
            btn.setAttribute('aria-selected', 'true');
        } else {
            btn.className = 'px-3 py-1.5 rounded-[4px] bg-slate-100 text-slate-700 hover:bg-slate-200 transition focus:outline-none';
            btn.setAttribute('aria-selected', 'false');
        }
    });
}

document.querySelectorAll('[data-citation-format]').forEach(btn => {
    btn.addEventListener('click', function() {
        currentCitationFormat = this.getAttribute('data-citation-format');
        renderCitationModal();
    });
});

document.getElementById('copyCitationBtn')?.addEventListener('click', function() {
    const text = document.getElementById('citationContentText').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = this.innerHTML;
        this.innerHTML = '<i class="fa-solid fa-check text-xs"></i><span>Copied!</span>';
        setTimeout(() => { this.innerHTML = originalHtml; }, 2000);
    });
});

// =====================================================
// PROFILE CARD — SCROLL-DRIVEN ANIMATION + TAB LOGIC
// =====================================================
(function () {
    const sidebar     = document.getElementById('profile-sidebar-fixed');
    const mainArea    = document.getElementById('profile-main-area');
    const heroSection = document.querySelector('#profile-main-area header');
    const jumpNav     = document.querySelector('.academic-jump-nav');
    const jumpLinks   = document.querySelectorAll('.academic-jump-link');
    const sections    = document.querySelectorAll('main > section[id]');

    if (!sidebar || !mainArea || !heroSection || !jumpNav) return;

    // ─── 1. Card position: always sits just below the sticky tab nav ─────
    function updateCardPosition() {
        // The jump nav is sticky; getBoundingClientRect() gives its actual viewport position
        const navRect  = jumpNav.getBoundingClientRect();
        const cardTop  = navRect.bottom + 8;   // 8px breathing room below tab bar
        const cardMaxH = window.innerHeight - cardTop - 10;  // 10px from bottom

        sidebar.style.top       = cardTop + 'px';
        sidebar.style.maxHeight = cardMaxH + 'px';
    }

    // ─── 2. Sidebar reveal on scroll ─────────────────────────────────────
    let lastSidebarState = false;

    function updateSidebar() {
        const heroBottom = heroSection.getBoundingClientRect().bottom;
        const shouldShow = heroBottom < 55;   // trigger once hero is ~scrolled off

        if (shouldShow !== lastSidebarState) {
            lastSidebarState = shouldShow;
            if (shouldShow) {
                updateCardPosition();  // set position before animating in
                sidebar.classList.add('sidebar-visible');
                sidebar.setAttribute('aria-hidden', 'false');
                mainArea.classList.add('sidebar-pushed');
            } else {
                sidebar.classList.remove('sidebar-visible');
                sidebar.setAttribute('aria-hidden', 'true');
                mainArea.classList.remove('sidebar-pushed');
            }
        }

        // Keep updating position while visible (tab nav height can change on wrap)
        if (lastSidebarState) {
            updateCardPosition();
        }
    }

    // ─── 3. Tab active state on scroll ───────────────────────────────────
    function updateActiveTabs() {
        const navH   = jumpNav.offsetHeight || 48;
        const offset = window.scrollY + navH + 20;

        let activeId = null;
        sections.forEach(sec => {
            if (offset >= sec.offsetTop) {
                activeId = sec.getAttribute('id');
            }
        });

        jumpLinks.forEach(link => {
            const href = link.getAttribute('href') || '';
            const id   = href.replace('#', '');
            link.classList.toggle('active', id === activeId);
        });
    }

    // ─── 4. Tab click: smooth scroll with correct offset ─────────────────
    jumpLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const href     = this.getAttribute('href') || '';
            const targetId = href.replace('#', '');
            const target   = document.getElementById(targetId);
            if (!target) return;

            // Offset = global nav (64px) + tab nav height + small gap
            const offset    = 64 + jumpNav.offsetHeight + 8;
            const targetTop = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top: targetTop, behavior: 'smooth' });

            jumpLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // ─── 5. Combined scroll + resize handler ─────────────────────────────
    window.addEventListener('scroll',  () => { updateSidebar(); updateActiveTabs(); }, { passive: true });
    window.addEventListener('resize',  () => { if (lastSidebarState) updateCardPosition(); }, { passive: true });

    // Initial run
    updateSidebar();
    updateActiveTabs();
})();
</script>


</div><!-- /#profile-main-area -->
</div><!-- /#profile-page-wrapper -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
