<?php
/**
 * Departmental Scholar - Academic Research Portal Homepage
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Focus: High Legibility, Typographic Hierarchy, Refined Borders, Subdued Academic Elevation
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getConnection();

// Fetch summary metrics (genuine database counts)
$totalFaculties = (int)$db->query("SELECT COUNT(*) FROM faculty_profiles WHERE is_verified = 1")->fetchColumn();
$totalPubs      = (int)$db->query("SELECT COUNT(*) FROM publications")->fetchColumn();
$totalCitations = (int)$db->query("SELECT COALESCE(SUM(total_citations), 0) FROM faculty_profiles")->fetchColumn();
$totalProjects  = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Fetch all departments with faculty and publication count
$deptStmt = $db->query("
    SELECT d.id, d.code, d.name, 
           COUNT(DISTINCT fp.id) as faculty_count,
           COUNT(DISTINCT p.id) as pub_count
    FROM departments d
    LEFT JOIN faculty_profiles fp ON fp.department_id = d.id AND fp.is_verified = 1
    LEFT JOIN publications p ON p.faculty_profile_id = fp.id
    GROUP BY d.id, d.code, d.name
    ORDER BY faculty_count DESC, pub_count DESC, d.name ASC
");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

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
$featuredFaculty = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch top publication preview for each featured faculty member
$topPubsByFaculty = [];
if (!empty($featuredFaculty)) {
    $facIds = array_map('intval', array_column($featuredFaculty, 'id'));
    $inClause = implode(',', $facIds);
    $pubsStmt = $db->query("
        SELECT p.faculty_profile_id, p.title, p.journal_conference_name as venue, p.publication_year as year, p.citation_count as citations
        FROM publications p
        WHERE p.faculty_profile_id IN ($inClause)
        ORDER BY p.citation_count DESC, p.publication_year DESC
    ");
    while ($row = $pubsStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($topPubsByFaculty[$row['faculty_profile_id']])) {
            $topPubsByFaculty[$row['faculty_profile_id']] = $row;
        }
    }
}

$pageTitle = 'Home — Faculty Research Directory & Repository';
$activeNav = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Academic Hero Section -->
<section class="relative bg-oxford-navy text-white py-16 sm:py-20 border-b border-oxford-blue/60 overflow-hidden">
    <!-- Subtle institutional watermarking -->
    <div class="absolute inset-0 opacity-[0.07] bg-center bg-cover pointer-events-none mix-blend-luminosity" style="background-image: url('<?= url('assets/img/iter_campus.jpg') ?>');"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-oxford-dark/80 via-transparent to-oxford-navy pointer-events-none"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <!-- Academic Pill -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-[4px] bg-white/10 border border-white/15 text-amber-300 text-xs font-mono tracking-wider uppercase mb-5">
            <i class="fa-solid fa-graduation-cap text-xs"></i>
            <span>Institutional Research Information System</span>
        </div>

        <!-- Headline in EB Garamond Serif -->
        <h1 class="font-serif text-3xl sm:text-5xl lg:text-6xl font-normal tracking-tight text-white max-w-4xl mx-auto leading-tight">
            Faculty Research & Scholarly Directory
        </h1>
        <p class="mt-3.5 text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed font-sans font-normal">
            Explore peer-reviewed publications, citation metrics, extramural grants, and intellectual property across collegiate academic departments.
        </p>

        <!-- Focused Search Form -->
        <div class="mt-9 max-w-2xl mx-auto">
            <form action="<?= url('directory.php') ?>" method="GET" class="relative flex items-center shadow-lg">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </div>
                    <input type="text" name="q" 
                        placeholder="Search scholars by name, research area, or topic (e.g. Machine Learning, VLSI)..." 
                        class="w-full pl-11 pr-28 py-3.5 bg-white text-scholar-text rounded-[6px] border border-slate-200 focus:outline-none focus:ring-2 focus:ring-oxford-slate text-sm placeholder-slate-400 shadow-xs"
                        aria-label="Search scholars by name or research topic">
                    <button type="submit" 
                        class="absolute right-1.5 top-1.5 bottom-1.5 px-4 btn-academic-gold text-xs shadow-xs">
                        <span>Search</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </button>
                </div>
            </form>
            <div class="mt-3 flex flex-wrap justify-center items-center gap-2 text-xs text-slate-300 font-sans">
                <span class="text-slate-400">Popular topics:</span>
                <a href="<?= url('directory.php?q=Machine+Learning') ?>" class="px-2 py-0.5 rounded-[4px] bg-white/10 hover:bg-white/20 text-slate-200 transition">Machine Learning</a>
                <a href="<?= url('directory.php?q=Deep+Learning') ?>" class="px-2 py-0.5 rounded-[4px] bg-white/10 hover:bg-white/20 text-slate-200 transition">Deep Learning</a>
                <a href="<?= url('directory.php?q=Power+Systems') ?>" class="px-2 py-0.5 rounded-[4px] bg-white/10 hover:bg-white/20 text-slate-200 transition">Power Systems</a>
                <a href="<?= url('directory.php?q=Medical+Imaging') ?>" class="px-2 py-0.5 rounded-[4px] bg-white/10 hover:bg-white/20 text-slate-200 transition">Medical Imaging</a>
            </div>
        </div>

        <!-- Institutional Impact Metrics Ribbon -->
        <div class="mt-12 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto pt-7 border-t border-white/10 text-left">
            <div class="p-4 rounded-[6px] bg-white/5 border border-white/10">
                <div class="text-xs font-sans text-slate-300 font-medium">Departments</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold text-white mt-1"><?= number_format(count($departments)) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Academic Divisions</div>
            </div>
            <div class="p-4 rounded-[6px] bg-white/5 border border-white/10">
                <div class="text-xs font-sans text-slate-300 font-medium">Active Faculty</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold text-white mt-1"><?= number_format($totalFaculties) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Verified Scholars</div>
            </div>
            <div class="p-4 rounded-[6px] bg-white/5 border border-white/10">
                <div class="text-xs font-sans text-slate-300 font-medium">Indexed Works</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold text-amber-300 mt-1"><?= number_format($totalPubs) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Journals & Conferences</div>
            </div>
            <div class="p-4 rounded-[6px] bg-white/5 border border-white/10">
                <div class="text-xs font-sans text-slate-300 font-medium">Citations Recorded</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold text-emerald-300 mt-1"><?= number_format($totalCitations) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Peer Citations</div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Researchers Showcase -->
<?php if (!empty($featuredFaculty)): ?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 pb-3.5 border-b border-scholar-border">
        <div>
            <span class="text-xs font-semibold text-academic-gold uppercase tracking-wider font-sans">Scholarly Directory</span>
            <h2 class="font-serif text-2xl sm:text-3xl font-bold text-oxford-navy mt-1">Distinguished Researchers</h2>
        </div>
        <a href="<?= url('directory.php') ?>" class="text-xs font-semibold text-oxford-slate hover:text-oxford-navy flex items-center gap-1.5 mt-2 sm:mt-0 transition group">
            <span>Browse Full Directory</span>
            <i class="fa-solid fa-arrow-right text-[10px] transform group-hover:translate-x-1 transition"></i>
        </a>
    </div>

    <!-- 3-Column Prestigious Desktop Layout -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($featuredFaculty as $fac): ?>
            <div class="academic-card p-6 flex flex-col justify-between group hover:border-oxford-slate transition">
                <div>
                    <!-- Portrait & Title Header -->
                    <div class="flex items-start gap-4">
                        <div class="w-16 h-16 rounded-[6px] bg-slate-100 border border-scholar-border overflow-hidden flex-shrink-0 flex items-center justify-center text-slate-400 shadow-xs">
                            <?php $photo = faculty_photo_url($fac['photo_url'] ?? null); ?>
                            <?php if ($photo): ?>
                                <img src="<?= $photo ?>" alt="<?= e($fac['full_name']) ?>" class="w-full h-full object-cover"
                                     onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                                <div class="hidden text-slate-300 flex items-center justify-center w-full h-full">
                                    <i class="fa-solid fa-user-graduate text-2xl"></i>
                                </div>
                            <?php else: ?>
                                <i class="fa-solid fa-user-graduate text-2xl text-slate-300"></i>
                            <?php endif; ?>
                        </div>

                        <div class="flex-grow min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <h3 class="font-serif font-bold text-oxford-navy text-lg leading-tight truncate">
                                    <a href="<?= researcher_url($fac) ?>" class="hover:text-oxford-blue transition">
                                        <?= e($fac['salutation'] . ' ' . $fac['full_name']) ?>
                                    </a>
                                </h3>
                                <span class="academic-tag academic-tag-green text-[10px] !py-0.5">
                                    <i class="fa-solid fa-circle-check text-[10px]"></i> Verified
                                </span>
                            </div>
                            <p class="text-xs text-scholar-muted mt-1 truncate font-medium"><?= e($fac['designation']) ?></p>
                            <p class="text-xs text-oxford-slate font-medium truncate"><?= e($fac['department_name'] ?? 'Faculty Researcher') ?></p>
                            <?php if (!empty($fac['institution'])): ?>
                                <p class="text-[11px] text-slate-400 truncate flex items-center gap-1 mt-0.5">
                                    <i class="fa-solid fa-building-columns text-[10px]"></i>
                                    <span><?= e($fac['institution']) ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="mt-4 grid grid-cols-3 gap-2 p-2.5 rounded-[6px] bg-slate-50 border border-scholar-border text-center">
                        <div>
                            <span class="text-slate-500 text-xs font-sans block">Citations</span>
                            <span class="font-bold text-oxford-navy font-serif text-base"><?= number_format($fac['total_citations']) ?></span>
                        </div>
                        <div class="border-x border-slate-200">
                            <span class="text-slate-500 text-xs font-sans block">h-index</span>
                            <span class="font-bold text-oxford-navy font-serif text-base"><?= (int)$fac['h_index'] ?></span>
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs font-sans block">i10-index</span>
                            <span class="font-bold text-oxford-navy font-serif text-base"><?= (int)$fac['i10_index'] ?></span>
                        </div>
                    </div>

                    <!-- Research Interests Tags -->
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

                    <!-- Selected Publication Preview -->
                    <?php if (isset($topPubsByFaculty[$fac['id']])): ?>
                        <?php $topPub = $topPubsByFaculty[$fac['id']]; ?>
                        <div class="mt-4 p-2.5 rounded-[6px] bg-slate-50/70 border border-slate-200/70 text-xs">
                            <div class="text-[10px] font-mono text-slate-400 uppercase tracking-wide flex items-center justify-between">
                                <span>Recent Highlight</span>
                                <?php if (!empty($topPub['year'])): ?>
                                    <span class="font-mono"><?= (int)$topPub['year'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="font-serif text-slate-800 text-xs font-semibold leading-snug line-clamp-1 mt-1">
                                "<?= e($topPub['title']) ?>"
                            </div>
                            <?php if (!empty($topPub['venue'])): ?>
                                <div class="text-[11px] text-slate-500 italic truncate mt-0.5">
                                    <?= e($topPub['venue']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer Card Action -->
                <div class="mt-5 pt-3.5 border-t border-scholar-border flex items-center justify-between text-xs">
                    <a href="<?= researcher_url($fac) ?>" class="btn-academic-secondary text-xs !py-1 !px-2.5 group-hover:border-oxford-slate">
                        <span>View Scholarly Profile</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                    <?php if (!empty($fac['orcid_id'])): ?>
                        <span class="inline-flex items-center gap-1 text-[11px] text-emerald-800 font-mono font-medium">
                            <i class="fa-brands fa-orcid text-emerald-600"></i> ORCID
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Academic Departments Grid -->
<section class="bg-slate-100/60 py-14 border-t border-scholar-border">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 pb-3.5 border-b border-scholar-border">
            <div>
                <span class="text-xs font-semibold text-academic-gold uppercase tracking-wider font-mono">Academic Divisions</span>
                <h2 class="font-serif text-2xl sm:text-3xl font-bold text-oxford-navy mt-1">Collegiate Departments</h2>
            </div>
            <a href="<?= url('departments.php') ?>" class="text-xs font-semibold text-oxford-slate hover:text-oxford-navy flex items-center gap-1.5 mt-2 sm:mt-0 transition group">
                <span>View All Departments</span>
                <i class="fa-solid fa-arrow-right text-[10px] transform group-hover:translate-x-1 transition"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php foreach ($departments as $dept): ?>
                <a href="<?= url('directory.php?dept=' . urlencode($dept['code'])) ?>" 
                   class="academic-card p-5 hover:border-oxford-slate transition flex flex-col justify-between group">
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="px-2 py-0.5 rounded-[4px] bg-slate-100 text-oxford-navy text-xs font-mono font-bold group-hover:bg-oxford-navy group-hover:text-white transition">
                                <?= e($dept['code']) ?>
                            </span>
                            <span class="text-xs text-slate-400 font-mono">
                                <?= (int)$dept['faculty_count'] ?> faculty
                            </span>
                        </div>
                        <h3 class="font-serif font-bold text-oxford-navy text-base leading-snug group-hover:text-oxford-blue transition">
                            <?= e($dept['name']) ?>
                        </h3>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-medium text-slate-500 group-hover:text-oxford-blue">
                        <span class="font-mono text-[11px] text-slate-400"><?= (int)$dept['pub_count'] ?> publications</span>
                        <i class="fa-solid fa-chevron-right text-[10px] transform group-hover:translate-x-1 transition"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Call to Action for Faculty Onboarding -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="rounded-[8px] bg-oxford-navy text-white p-8 sm:p-10 shadow-sm border border-oxford-blue flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="space-y-2.5 max-w-2xl">
            <span class="inline-block px-2 py-0.5 rounded-[4px] bg-white/10 text-amber-300 text-xs font-mono tracking-wider uppercase">
                Faculty Portal
            </span>
            <h2 class="font-serif text-2xl sm:text-3xl font-bold">
                Are you an Institutional Researcher or Faculty Scholar?
            </h2>
            <p class="text-slate-300 text-xs sm:text-sm leading-relaxed font-sans">
                Maintain your official academic research portfolio with Google Scholar and ORCID linking, sponsored grants tracking, and NAAC/NIRF-ready reporting.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 flex-shrink-0 w-full md:w-auto">
            <a href="<?= url('register.php') ?>" class="btn-academic-gold text-xs !py-2.5 !px-4 text-center">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Register Faculty Profile</span>
            </a>
            <a href="<?= url('login.php') ?>" class="btn-academic-secondary !bg-white/10 !text-white !border-white/20 hover:!bg-white/20 text-xs !py-2.5 !px-4 text-center">
                <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                <span>Sign In to Portal</span>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
