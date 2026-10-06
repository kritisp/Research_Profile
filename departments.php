<?php
/**
 * Departmental Scholar - Academic Departments Overview
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Focus: High Legibility, Typographic Hierarchy, Refined Borders, Subdued Academic Elevation
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getConnection();

$deptStmt = $db->query("
    SELECT d.*, 
           COUNT(DISTINCT fp.id) as faculty_count,
           COUNT(DISTINCT p.id) as publication_count,
           COALESCE(SUM(fp.total_citations), 0) as dept_citations
    FROM departments d
    LEFT JOIN faculty_profiles fp ON fp.department_id = d.id AND fp.is_verified = 1
    LEFT JOIN publications p ON p.faculty_profile_id = fp.id
    GROUP BY d.id
    ORDER BY faculty_count DESC, d.name ASC
");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Academic Departments — Faculty Profiles';
$activeNav = 'departments';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Banner -->
<section class="bg-oxford-navy text-white py-10 sm:py-12 border-b border-oxford-blue/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <span class="text-xs font-semibold text-amber-300 uppercase tracking-widest font-sans">University Academic Structure</span>
        <h1 class="font-serif text-2xl sm:text-4xl font-normal text-white mt-1.5 leading-tight">Academic Departments</h1>
        <p class="text-xs sm:text-sm text-slate-300 mt-2 max-w-2xl leading-relaxed font-sans">
            Explore departmental research output, publication volumes, and faculty directories across university departments.
        </p>
    </div>
</section>

<!-- Departments Grid -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($departments as $dept): ?>
            <div class="academic-card p-6 flex flex-col justify-between group hover:border-oxford-slate transition shadow-xs">
                <div>
                    <!-- Header with code & metrics -->
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2.5 py-1 rounded-[4px] bg-oxford-navy text-white text-xs font-mono font-bold">
                            <?= e($dept['code']) ?>
                        </span>
                        <div class="flex items-center gap-3 text-xs text-slate-500 font-sans">
                            <span><i class="fa-solid fa-users text-oxford-slate mr-1"></i><?= (int)$dept['faculty_count'] ?> Faculty</span>
                            <span>•</span>
                            <span><i class="fa-solid fa-file-lines text-oxford-slate mr-1"></i><?= (int)$dept['publication_count'] ?> Works</span>
                        </div>
                    </div>

                    <h2 class="font-serif font-bold text-xl text-oxford-navy leading-snug group-hover:text-oxford-blue transition">
                        <a href="<?= url('directory.php?dept=' . urlencode($dept['code'])) ?>">
                            <?= e($dept['name']) ?>
                        </a>
                    </h2>

                    <p class="text-xs text-scholar-muted mt-2.5 leading-relaxed font-sans">
                        <?= e($dept['description'] ?? 'Academic division of ' . $dept['name'] . ', fostering peer-reviewed research, doctoral mentoring, and scientific advancement.') ?>
                    </p>
                </div>

                <div class="mt-6 pt-4 border-t border-scholar-border flex items-center justify-between">
                    <span class="text-xs text-slate-500 font-sans">
                        Citations: <strong class="text-oxford-navy font-serif font-bold text-sm"><?= number_format($dept['dept_citations']) ?></strong>
                    </span>
                    <a href="<?= url('directory.php?dept=' . urlencode($dept['code'])) ?>" 
                       class="btn-academic-secondary text-xs !py-1.5 !px-3 group-hover:border-oxford-slate">
                        <span>Browse Faculty</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
