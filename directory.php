<?php
/**
 * Faculty Directory & Search Portal
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
$deptList = $db->query("SELECT code, name FROM departments ORDER BY name ASC")->fetchAll();

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
$faculties = $stmt->fetchAll();

$pageTitle = 'Faculty Directory';
$activeNav = 'directory';
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-900 text-white py-10 sm:py-12 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <span class="text-xs font-mono font-bold text-amber-300 uppercase tracking-widest">Departmental Scholarly Directory</span>
            <h1 class="text-2xl sm:text-4xl font-bold font-serif-title mt-1.5 leading-tight">Faculty & Researchers</h1>
            <p class="text-xs sm:text-sm text-slate-300 mt-2 leading-relaxed">
                Browse verified academic profiles, publication records, and citation metrics across collegiate departments and academic institutions.
            </p>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm mb-8">
        <form action="<?= url('directory.php') ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            
            <!-- Query Search -->
            <div class="sm:col-span-4 relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" name="q" value="<?= e($q) ?>" 
                    placeholder="Search by faculty name or research topic..."
                    class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
            </div>

            <!-- Institution Filter -->
            <div class="sm:col-span-3">
                <select name="inst" 
                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-700 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
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
                <select name="dept" 
                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-700 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
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
                <select name="sort" 
                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-700 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                    <option value="citations" <?= $sort === 'citations' ? 'selected' : '' ?>>Most Citations</option>
                    <option value="pubs" <?= $sort === 'pubs' ? 'selected' : '' ?>>Most Publications</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="sm:col-span-1">
                <button type="submit" 
                    class="w-full py-2.5 px-3 bg-iter-800 hover:bg-iter-900 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center justify-center gap-1">
                    <span>Filter</span>
                </button>
            </div>
        </form>

        <?php if (!empty($q) || !empty($dept) || !empty($inst)): ?>
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Showing filtered results: 
                    <?= !empty($q) ? '<strong>"' . e($q) . '"</strong> ' : '' ?>
                    <?= !empty($inst) ? 'at <strong>' . e($inst) . '</strong> ' : '' ?>
                    <?= !empty($dept) ? 'in <strong>' . e($dept) . '</strong>' : '' ?>
                </span>
                <a href="<?= url('directory.php') ?>" class="text-rose-600 hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i> Reset filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Results Header -->
    <div class="flex items-center justify-between mb-6">
        <p class="text-xs font-mono font-semibold text-slate-500 uppercase tracking-wider">
            Found <?= count($faculties) ?> Faculty Researcher<?= count($faculties) !== 1 ? 's' : '' ?>
        </p>
    </div>

    <!-- Faculty Cards Grid -->
    <?php if (empty($faculties)): ?>
        <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 text-slate-500">
            <i class="fa-solid fa-user-slash text-4xl text-slate-300 mb-3"></i>
            <h3 class="text-base font-bold text-slate-800">No faculty members found</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                No profiles matched your search criteria. Try using different keywords or clear the department filter.
            </p>
            <a href="<?= url('directory.php') ?>" class="mt-4 inline-block px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold hover:bg-slate-200 transition">
                View All Faculty
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($faculties as $fac): ?>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-start gap-4">
                            <!-- Avatar -->
                            <div class="w-16 h-16 rounded-full bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center text-slate-400">
                                <?php if (!empty($fac['photo_url'])): ?>
                                    <img src="<?= e($fac['photo_url']) ?>" alt="<?= e($fac['full_name']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow min-w-0">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-iter-50 text-iter-800 mb-1">
                                    <?= e($fac['department_code'] ?? 'SCHOLAR') ?>
                                </span>
                                <h3 class="font-bold text-slate-900 text-base leading-tight truncate">
                                    <a href="<?= url('profile.php?id=' . $fac['id']) ?>" class="hover:text-iter-700 transition">
                                        <?= e($fac['salutation'] . ' ' . $fac['full_name']) ?>
                                    </a>
                                </h3>
                                <p class="text-xs text-slate-600 mt-0.5 truncate"><?= e($fac['designation']) ?></p>
                                <p class="text-xs text-slate-500 truncate"><?= e($fac['department_name']) ?></p>
                                <?php if (!empty($fac['institution'])): ?>
                                    <p class="text-[11px] text-slate-400 truncate mt-1 flex items-center gap-1.5">
                                        <i class="fa-solid fa-building-columns text-[10px] text-slate-400"></i>
                                        <span><?= e($fac['institution']) ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Metrics pill -->
                        <div class="mt-4 flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                            <div class="text-center flex-1">
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">Citations</span>
                                <span class="font-bold text-slate-900 font-mono"><?= number_format($fac['total_citations']) ?></span>
                            </div>
                            <div class="h-6 w-px bg-slate-200"></div>
                            <div class="text-center flex-1">
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">h-index</span>
                                <span class="font-bold text-slate-900 font-mono"><?= (int)$fac['h_index'] ?></span>
                            </div>
                            <div class="h-6 w-px bg-slate-200"></div>
                            <div class="text-center flex-1">
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">Papers</span>
                                <span class="font-bold text-slate-900 font-mono"><?= (int)$fac['publication_count'] ?></span>
                            </div>
                        </div>

                        <!-- Research Interests tags -->
                        <?php if (!empty($fac['research_interests'])): ?>
                            <div class="mt-3.5 flex flex-wrap gap-1.5">
                                <?php 
                                    $tags = array_map('trim', explode(',', $fac['research_interests']));
                                    foreach (array_slice($tags, 0, 3) as $tag):
                                ?>
                                    <span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-medium">
                                        <?= e($tag) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                        <a href="<?= url('profile.php?id=' . $fac['id']) ?>" class="font-semibold text-iter-700 hover:text-iter-900 flex items-center gap-1 transition">
                            <span>View Academic Profile</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>

                        <div class="flex items-center gap-2 text-slate-400 text-sm">
                            <?php if (!empty($fac['orcid_id'])): ?>
                                <a href="https://orcid.org/<?= e($fac['orcid_id']) ?>" target="_blank" rel="noopener" title="ORCID" class="hover:text-emerald-600 transition">
                                    <i class="fa-brands fa-orcid"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($fac['google_scholar_url'])): ?>
                                <a href="<?= e($fac['google_scholar_url']) ?>" target="_blank" rel="noopener" title="Google Scholar" class="hover:text-blue-600 transition">
                                    <i class="fa-brands fa-google"></i>
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
