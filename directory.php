<?php
/**
 * Departmental Scholar - Faculty Directory & Search Portal
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getConnection();

// Fetch filter parameters
$q      = trim($_GET['q'] ?? '');
$dept   = trim($_GET['dept'] ?? '');
$inst   = trim($_GET['inst'] ?? '');
$sort   = trim($_GET['sort'] ?? 'citations');

// Fetch all departments for filter dropdown
$deptList = $db->query("SELECT code, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct institutions for filter dropdown
$institutionList = $db->query("
    SELECT DISTINCT institution 
    FROM faculty_profiles 
    WHERE institution IS NOT NULL AND TRIM(institution) != '' 
    ORDER BY institution ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Build dynamic search query with PDO prepared parameters
$sql = "
    SELECT fp.*, u.full_name, u.email, d.name as department_name, d.code as department_code,
           COUNT(p.id) as publication_count
    FROM faculty_profiles fp
    JOIN users u ON fp.user_id = u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    LEFT JOIN publications p ON p.faculty_profile_id = fp.id
    WHERE fp.is_verified = 1 AND u.status = 'active'
";
$params = [];

if (!empty($q)) {
    $sql .= " AND (u.full_name LIKE ? OR fp.research_interests LIKE ? OR fp.designation LIKE ?)";
    $term = '%' . $q . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($dept)) {
    $sql .= " AND d.code = ?";
    $params[] = $dept;
}

if (!empty($inst)) {
    $sql .= " AND fp.institution = ?";
    $params[] = $inst;
}

$sql .= " GROUP BY fp.id, u.full_name, u.email, d.name, d.code";

// Sort
if ($sort === 'name') {
    $sql .= " ORDER BY u.full_name ASC";
} elseif ($sort === 'pubs') {
    $sql .= " ORDER BY publication_count DESC, fp.total_citations DESC";
} else {
    $sql .= " ORDER BY fp.total_citations DESC, fp.id DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for filter chip links
function filter_url_without($key) {
    $params = $_GET;
    unset($params[$key]);
    return url('directory.php' . (!empty($params) ? '?' . http_build_query($params) : ''));
}

$hasActiveFilters = (!empty($q) || !empty($dept) || !empty($inst));

$pageTitle = 'Faculty Directory — Scholarly Profiles';
$activeNav = 'directory';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Directory Header Banner -->
<section class="bg-oxford-navy text-white py-10 sm:py-12 border-b border-oxford-blue">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <span class="text-xs font-mono font-semibold text-amber-300 uppercase tracking-widest">Collegiate Scholarly Directory</span>
            <h1 class="font-serif text-2xl sm:text-4xl font-normal text-white mt-1.5 leading-tight">Faculty Researchers</h1>
            <p class="text-xs sm:text-sm text-slate-300 mt-2 leading-relaxed font-sans">
                Browse verified academic scholars, publication bibliographies, citations, and research areas across institutional departments.
            </p>
        </div>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Unified Filter Panel -->
    <div class="academic-card p-4 sm:p-5 mb-8">
        <form action="<?= url('directory.php') ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            
            <!-- Keyword Search -->
            <div class="sm:col-span-4 relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" name="q" value="<?= e($q) ?>" 
                    placeholder="Search name or research topic..."
                    class="academic-input pl-9 text-xs sm:text-sm"
                    aria-label="Search scholars">
            </div>

            <!-- Institution Filter -->
            <div class="sm:col-span-3">
                <select name="inst" class="academic-input text-xs sm:text-sm" aria-label="Filter by institution">
                    <option value="">All Institutions</option>
                    <?php foreach ($institutionList as $instItem): ?>
                        <option value="<?= e($instItem) ?>" <?= $inst === $instItem ? 'selected' : '' ?>>
                            <?= e($instItem) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Department Filter -->
            <div class="sm:col-span-2">
                <select name="dept" class="academic-input text-xs sm:text-sm" aria-label="Filter by department">
                    <option value="">All Departments</option>
                    <?php foreach ($deptList as $d): ?>
                        <option value="<?= e($d['code']) ?>" <?= $dept === $d['code'] ? 'selected' : '' ?>>
                            <?= e($d['code']) ?> - <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sort By -->
            <div class="sm:col-span-2">
                <select name="sort" class="academic-input text-xs sm:text-sm" aria-label="Sort scholars">
                    <option value="citations" <?= $sort === 'citations' ? 'selected' : '' ?>>Most Citations</option>
                    <option value="pubs" <?= $sort === 'pubs' ? 'selected' : '' ?>>Most Publications</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                </select>
            </div>

            <!-- Filter Action -->
            <div class="sm:col-span-1">
                <button type="submit" class="btn-academic-primary w-full text-xs !py-2.5 !px-3 shadow-xs">
                    <span>Apply</span>
                </button>
            </div>
        </form>

        <!-- Active Filter Chip Cluster -->
        <?php if ($hasActiveFilters): ?>
            <div class="mt-4 pt-3 border-t border-scholar-border flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-slate-500 font-medium">Active criteria:</span>
                    <?php if (!empty($q)): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-100 text-oxford-navy border border-slate-300 font-sans">
                            <span>Keyword: <strong>"<?= e($q) ?>"</strong></span>
                            <a href="<?= filter_url_without('q') ?>" class="text-slate-400 hover:text-rose-600 transition" aria-label="Remove keyword filter">
                                <i class="fa-solid fa-xmark text-[11px]"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($dept)): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-100 text-oxford-navy border border-slate-300 font-sans">
                            <span>Department: <strong><?= e($dept) ?></strong></span>
                            <a href="<?= filter_url_without('dept') ?>" class="text-slate-400 hover:text-rose-600 transition" aria-label="Remove department filter">
                                <i class="fa-solid fa-xmark text-[11px]"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($inst)): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-slate-100 text-oxford-navy border border-slate-300 font-sans">
                            <span>Institution: <strong><?= e($inst) ?></strong></span>
                            <a href="<?= filter_url_without('inst') ?>" class="text-slate-400 hover:text-rose-600 transition" aria-label="Remove institution filter">
                                <i class="fa-solid fa-xmark text-[11px]"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                </div>
                <a href="<?= url('directory.php') ?>" class="text-rose-700 hover:text-rose-900 font-medium flex items-center gap-1 transition">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i>
                    <span>Reset All</span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Results Status Count -->
    <div class="flex items-center justify-between mb-6">
        <p class="text-xs font-mono font-semibold text-slate-500 uppercase tracking-wider">
            Showing <?= count($faculties) ?> Verified Researcher<?= count($faculties) !== 1 ? 's' : '' ?>
        </p>
    </div>

    <!-- Faculty Cards Grid -->
    <?php if (empty($faculties)): ?>
        <div class="academic-card p-12 text-center text-slate-500">
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                <i class="fa-solid fa-user-slash text-xl"></i>
            </div>
            <h3 class="font-serif text-lg font-bold text-oxford-navy">No faculty profiles found</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                No verified profiles match the specified filters. Try broadening your keywords or resetting department criteria.
            </p>
            <a href="<?= url('directory.php') ?>" class="mt-4 btn-academic-secondary text-xs">
                <span>View All Researchers</span>
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($faculties as $fac): ?>
                <div class="academic-card p-6 flex flex-col justify-between group">
                    <div>
                        <!-- Header with portrait -->
                        <div class="flex items-start gap-4">
                            <div class="w-16 h-16 rounded-[8px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center text-slate-400 shadow-xs">
                                <?php if (!empty($fac['photo_url'])): ?>
                                    <img src="<?= safe_url($fac['photo_url']) ?>" alt="<?= e($fac['full_name']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="academic-tag font-mono text-[10px] font-bold">
                                        <?= e($fac['department_code'] ?? 'RESEARCH') ?>
                                    </span>
                                    <span class="academic-tag academic-tag-green text-[10px] !py-0.5">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i> Verified
                                    </span>
                                </div>
                                <h3 class="font-serif font-bold text-oxford-navy text-base leading-tight truncate mt-1">
                                    <a href="<?= researcher_url($fac) ?>" class="hover:text-oxford-blue transition">
                                        <?= e($fac['salutation'] . ' ' . $fac['full_name']) ?>
                                    </a>
                                </h3>
                                <p class="text-xs text-scholar-muted mt-0.5 truncate font-medium"><?= e($fac['designation']) ?></p>
                                <p class="text-xs text-slate-500 truncate"><?= e($fac['department_name']) ?></p>
                                <?php if (!empty($fac['institution'])): ?>
                                    <p class="text-[11px] text-slate-400 truncate mt-1 flex items-center gap-1.5">
                                        <i class="fa-solid fa-building-columns text-[10px] text-slate-400"></i>
                                        <span><?= e($fac['institution']) ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Metrics Ribbon -->
                        <div class="mt-4 grid grid-cols-3 gap-2 p-2.5 rounded-[6px] bg-slate-50 border border-scholar-border text-center">
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">Citations</span>
                                <span class="font-bold text-oxford-navy font-mono text-sm"><?= number_format($fac['total_citations']) ?></span>
                            </div>
                            <div class="border-x border-slate-200">
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">h-index</span>
                                <span class="font-bold text-oxford-navy font-mono text-sm"><?= (int)$fac['h_index'] ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">Papers</span>
                                <span class="font-bold text-oxford-navy font-mono text-sm"><?= (int)$fac['publication_count'] ?></span>
                            </div>
                        </div>

                        <!-- Research Interests tags -->
                        <?php if (!empty($fac['research_interests'])): ?>
                            <div class="mt-3.5 flex flex-wrap gap-1.5">
                                <?php 
                                    $tags = array_map('trim', explode(',', $fac['research_interests']));
                                    foreach (array_slice($tags, 0, 3) as $tag):
                                ?>
                                    <span class="academic-tag text-[11px]">
                                        <?= e($tag) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer Action -->
                    <div class="mt-5 pt-4 border-t border-scholar-border flex items-center justify-between text-xs">
                        <a href="<?= researcher_url($fac) ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 group-hover:border-oxford-blue">
                            <span>View Profile</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>

                        <div class="flex items-center gap-2.5 text-slate-400 text-sm">
                            <?php if (!empty($fac['orcid_id'])): ?>
                                <?php $cleanOrcid = safe_orcid($fac['orcid_id']); ?>
                                <?php if ($cleanOrcid): ?>
                                    <a href="https://orcid.org/<?= $cleanOrcid ?>" target="_blank" rel="noopener noreferrer" title="ORCID Profile" class="text-slate-400 hover:text-emerald-700 transition">
                                        <i class="fa-brands fa-orcid"></i>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($fac['google_scholar_url'])): ?>
                                <a href="<?= safe_url($fac['google_scholar_url']) ?>" target="_blank" rel="noopener noreferrer" title="Google Scholar Profile" class="text-slate-400 hover:text-blue-700 transition">
                                    <i class="fa-brands fa-google"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($fac['scopus_id'])): ?>
                                <a href="https://www.scopus.com/authid/detail.uri?authorId=<?= urlencode($fac['scopus_id']) ?>" target="_blank" rel="noopener noreferrer" title="Scopus Profile" class="text-slate-400 hover:text-amber-700 transition">
                                    <i class="fa-solid fa-chart-line text-xs"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($fac['dblp_url'])): ?>
                                <a href="<?= safe_url($fac['dblp_url']) ?>" target="_blank" rel="noopener noreferrer" title="DBLP Profile" class="text-slate-400 hover:text-cyan-700 transition">
                                    <i class="fa-solid fa-book-open text-xs"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
