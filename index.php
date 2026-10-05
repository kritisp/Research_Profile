<?php
/**
 * ITER Research Portal - Homepage
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getConnection();

// Fetch summary metrics
$totalFaculties = (int)$db->query("SELECT COUNT(*) FROM faculty_profiles WHERE is_verified = 1")->fetchColumn();
$totalPubs      = (int)$db->query("SELECT COUNT(*) FROM publications")->fetchColumn();
$totalCitations = (int)$db->query("SELECT COALESCE(SUM(total_citations), 0) FROM faculty_profiles")->fetchColumn();
$totalProjects  = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Fetch all departments with faculty count
$deptStmt = $db->query("
    SELECT d.id, d.code, d.name, COUNT(fp.id) as faculty_count
    FROM departments d
    LEFT JOIN faculty_profiles fp ON fp.department_id = d.id AND fp.is_verified = 1
    GROUP BY d.id, d.code, d.name
    ORDER BY faculty_count DESC, d.name ASC
");
$departments = $deptStmt->fetchAll();

// Fetch prominent faculty profiles (ordered by citations or newest)
$facultyStmt = $db->query("
    SELECT fp.*, u.full_name, u.email, d.name as department_name, d.code as department_code
    FROM faculty_profiles fp
    JOIN users u ON fp.user_id = u.id
    LEFT JOIN departments d ON fp.department_id = d.id
    WHERE fp.is_verified = 1 AND u.status = 'active'
    ORDER BY fp.total_citations DESC, fp.id DESC
    LIMIT 6
");
$featuredFaculty = $facultyStmt->fetchAll();

$pageTitle = 'Home - Faculty Research Portal';
$activeNav = 'home';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/preloader.php';
?>

<!-- Hero Showcase Section -->
<div class="relative bg-gradient-to-b from-iter-950 via-iter-900 to-slate-900 text-white py-16 sm:py-24 overflow-hidden">
    <!-- Subtle Real Campus Gate Background Overlay -->
    <div class="absolute inset-0 bg-cover bg-center opacity-15 mix-blend-luminosity pointer-events-none" style="background-image: url('<?= url('assets/img/iter_gate.jpg') ?>');"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-transparent to-iter-950/80 pointer-events-none"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <!-- Badge -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-iter-800/80 border border-iter-700 text-amber-300 text-xs font-semibold tracking-wide uppercase mb-6">
            <i class="fa-solid fa-award"></i>
            <span>Excellence in Research & Scholarly Innovation</span>
        </div>

        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight font-serif-title max-w-4xl mx-auto leading-tight">
            Departmental Faculty Research & Scholarly Directory
        </h1>
        <p class="mt-4 text-base sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed">
            Discover cutting-edge publications, citation metrics, funded research grants, patents, and academic profiles of faculty researchers across collegiate departments and institutions.
        </p>

        <!-- Search Bar -->
        <div class="mt-8 max-w-2xl mx-auto">
            <form action="<?= url('directory.php') ?>" method="GET" class="relative flex items-center">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <input type="text" name="q" 
                        placeholder="Search faculty by name, topic (e.g. AI, VLSI, Renewable Energy)..." 
                        class="w-full pl-11 pr-28 py-3.5 bg-white text-slate-900 rounded-xl shadow-lg border-0 focus:ring-4 focus:ring-iter-400 focus:outline-none text-sm placeholder-slate-400">
                    <button type="submit" 
                        class="absolute right-2 top-2 bottom-2 px-5 bg-iter-700 hover:bg-iter-800 text-white font-medium text-xs rounded-lg transition flex items-center gap-1.5 shadow">
                        <span>Search</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </button>
                </div>
            </form>
            <div class="mt-2.5 flex flex-wrap justify-center items-center gap-2 text-xs text-slate-400">
                <span>Popular areas:</span>
                <a href="<?= url('directory.php?q=Machine+Learning') ?>" class="px-2 py-0.5 rounded bg-slate-800/60 hover:bg-slate-700 text-slate-300 transition">Machine Learning</a>
                <a href="<?= url('directory.php?q=Power+Systems') ?>" class="px-2 py-0.5 rounded bg-slate-800/60 hover:bg-slate-700 text-slate-300 transition">Power Systems</a>
                <a href="<?= url('directory.php?q=IoT') ?>" class="px-2 py-0.5 rounded bg-slate-800/60 hover:bg-slate-700 text-slate-300 transition">IoT</a>
                <a href="<?= url('directory.php?q=Nanotechnology') ?>" class="px-2 py-0.5 rounded bg-slate-800/60 hover:bg-slate-700 text-slate-300 transition">Nanotechnology</a>
            </div>
        </div>

        <!-- Institutional Metric Stats -->
        <div class="mt-14 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto pt-8 border-t border-iter-800/80">
            <div class="p-4 rounded-xl bg-white/5 backdrop-blur-sm border border-white/10">
                <div class="text-2xl sm:text-3xl font-bold font-mono text-amber-300"><?= number_format(count($departments)) ?></div>
                <div class="text-xs text-slate-300 mt-0.5 font-medium">Departments</div>
            </div>
            <div class="p-4 rounded-xl bg-white/5 backdrop-blur-sm border border-white/10">
                <div class="text-2xl sm:text-3xl font-bold font-mono text-white"><?= number_format($totalFaculties) ?></div>
                <div class="text-xs text-slate-300 mt-0.5 font-medium">Active Faculty</div>
            </div>
            <div class="p-4 rounded-xl bg-white/5 backdrop-blur-sm border border-white/10">
                <div class="text-2xl sm:text-3xl font-bold font-mono text-emerald-300"><?= number_format($totalPubs) ?></div>
                <div class="text-xs text-slate-300 mt-0.5 font-medium">Indexed Publications</div>
            </div>
            <div class="p-4 rounded-xl bg-white/5 backdrop-blur-sm border border-white/10">
                <div class="text-2xl sm:text-3xl font-bold font-mono text-cyan-300"><?= number_format($totalCitations) ?></div>
                <div class="text-xs text-slate-300 mt-0.5 font-medium">Recorded Citations</div>
            </div>
        </div>
    </div>
</div>

<!-- Departmental Grid Showcase -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 pb-4 border-b border-slate-200">
        <div>
            <span class="text-xs font-bold text-iter-700 uppercase tracking-wider">Academic Divisions</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 font-serif-title mt-1">Browse by Department</h2>
        </div>
        <a href="<?= url('departments.php') ?>" class="text-sm font-semibold text-iter-700 hover:text-iter-900 flex items-center gap-1 mt-2 sm:mt-0 transition">
            <span>View All Departments</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <?php foreach ($departments as $dept): ?>
            <a href="<?= url('directory.php?dept=' . urlencode($dept['code'])) ?>" 
               class="group p-5 bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-iter-400 transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2.5 py-1 rounded bg-iter-50 text-iter-800 text-xs font-mono font-bold group-hover:bg-iter-800 group-hover:text-white transition">
                            <?= e($dept['code']) ?>
                        </span>
                        <span class="text-xs text-slate-400 flex items-center gap-1">
                            <i class="fa-solid fa-users text-[11px]"></i>
                            <span><?= (int)$dept['faculty_count'] ?> faculty</span>
                        </span>
                    </div>
                    <h3 class="font-bold text-slate-800 text-sm leading-snug group-hover:text-iter-800 transition">
                        <?= e($dept['name']) ?>
                    </h3>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-medium text-slate-500 group-hover:text-iter-700">
                    <span>Explore Profiles</span>
                    <i class="fa-solid fa-chevron-right text-[10px] transform group-hover:translate-x-1 transition"></i>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Faculty Showcase (Google Scholar Style Highlights) -->
<?php if (!empty($featuredFaculty)): ?>
<div class="bg-slate-100/70 py-16 border-y border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 pb-4 border-b border-slate-200">
            <div>
                <span class="text-xs font-bold text-iter-700 uppercase tracking-wider">Faculty Spotlight</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 font-serif-title mt-1">Distinguished Researchers</h2>
            </div>
            <a href="<?= url('directory.php') ?>" class="text-sm font-semibold text-iter-700 hover:text-iter-900 flex items-center gap-1 mt-2 sm:mt-0 transition">
                <span>Browse Full Directory</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($featuredFaculty as $fac): ?>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-start gap-4">
                            <!-- Avatar -->
                            <div class="w-16 h-16 rounded-full bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center text-slate-400">
                                <?php if (!empty($fac['photo_url'])): ?>
                                    <img src="<?= safe_url($fac['photo_url']) ?>" alt="<?= e($fac['full_name']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie text-2xl text-slate-300"></i>
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow min-w-0">
                                <h3 class="font-bold text-slate-900 text-base leading-tight truncate">
                                    <a href="<?= researcher_url($fac) ?>" class="hover:text-iter-700 transition">
                                        <?= e($fac['salutation'] . ' ' . $fac['full_name']) ?>
                                    </a>
                                </h3>
                                <p class="text-xs text-slate-600 mt-0.5 truncate"><?= e($fac['designation']) ?></p>
                                <p class="text-xs text-iter-700 font-medium truncate"><?= e($fac['department_name'] ?? 'Faculty Member') ?></p>
                                <?php if (!empty($fac['institution'])): ?>
                                    <p class="text-[11px] text-slate-400 truncate flex items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-building-columns text-[10px] text-slate-400"></i>
                                        <span><?= e($fac['institution']) ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Metrics pill -->
                        <div class="mt-4 flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">Citations</span>
                                <span class="font-bold text-slate-900 font-mono"><?= number_format($fac['total_citations']) ?></span>
                            </div>
                            <div class="h-6 w-px bg-slate-200"></div>
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">h-index</span>
                                <span class="font-bold text-slate-900 font-mono"><?= (int)$fac['h_index'] ?></span>
                            </div>
                            <div class="h-6 w-px bg-slate-200"></div>
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase font-mono block">i10-index</span>
                                <span class="font-bold text-slate-900 font-mono"><?= (int)$fac['i10_index'] ?></span>
                            </div>
                        </div>

                        <!-- Research Interests tags -->
                        <?php if (!empty($fac['research_interests'])): ?>
                            <div class="mt-3 flex flex-wrap gap-1.5">
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
                        <a href="<?= researcher_url($fac) ?>" class="font-semibold text-iter-700 hover:text-iter-900 flex items-center gap-1 transition">
                            <span>View Full Profile</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                        <?php if (!empty($fac['orcid_id'])): ?>
                            <span class="inline-flex items-center gap-1 text-[11px] text-emerald-700 font-medium">
                                <i class="fa-brands fa-orcid text-emerald-600"></i> ORCID
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Call to action for faculty members -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="rounded-3xl bg-gradient-to-r from-iter-900 to-iter-800 text-white p-8 sm:p-12 shadow-xl flex flex-col md:flex-row items-center justify-between gap-8">
        <div class="space-y-3 max-w-2xl">
            <span class="inline-block px-3 py-1 rounded-full bg-iter-700/80 text-amber-300 text-xs font-semibold tracking-wider uppercase">
                Faculty & Scholar Onboarding
            </span>
            <h2 class="text-2xl sm:text-3xl font-bold font-serif-title">
                Are you a Faculty Member or Research Scholar?
            </h2>
            <p class="text-slate-300 text-sm leading-relaxed">
                Maintain your official academic research portfolio with automated citation tracking, Google Scholar and ORCID linking, sponsored grants logging, and NAAC/NIRF-ready reporting.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 flex-shrink-0 w-full md:w-auto">
            <a href="<?= url('register.php') ?>" class="px-6 py-3 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-900 font-bold text-sm text-center shadow transition">
                Create Faculty Profile
            </a>
            <a href="<?= url('login.php') ?>" class="px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm text-center border border-white/20 transition">
                Sign In to Portal
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
