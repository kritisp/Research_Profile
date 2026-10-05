<?php
/**
 * ITER Academic Departments Overview
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
$departments = $deptStmt->fetchAll();

$pageTitle = 'Academic Departments';
$activeNav = 'departments';
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <span class="text-xs font-mono font-bold text-amber-300 uppercase tracking-widest">Academic Structure</span>
        <h1 class="text-3xl sm:text-4xl font-bold font-serif-title mt-2">ITER Departments</h1>
        <p class="text-xs sm:text-sm text-slate-300 mt-2 max-w-2xl leading-relaxed">
            Explore faculty research profiles, citation impacts, and scholarly publications across all academic engineering departments of ITER, SOA University Bhubaneswar.
        </p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($departments as $dept): ?>
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-3 py-1 rounded-lg bg-iter-900 text-white text-xs font-mono font-bold">
                            <?= e($dept['code']) ?>
                        </span>
                        <div class="flex items-center gap-3 text-xs text-slate-500 font-mono">
                            <span><i class="fa-solid fa-users text-iter-600 mr-1"></i><?= (int)$dept['faculty_count'] ?> Faculty</span>
                            <span>•</span>
                            <span><i class="fa-solid fa-file-lines text-iter-600 mr-1"></i><?= (int)$dept['publication_count'] ?> Papers</span>
                        </div>
                    </div>

                    <h2 class="text-lg font-bold text-slate-900 font-serif-title leading-snug">
                        <a href="<?= url('directory.php?dept=' . urlencode($dept['code'])) ?>" class="hover:text-iter-800 transition">
                            <?= e($dept['name']) ?>
                        </a>
                    </h2>

                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        <?= e($dept['description'] ?? 'Department of ' . $dept['name'] . ', ITER, SOA University.') ?>
                    </p>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-mono text-slate-500">
                        Total Citations: <strong class="text-slate-800"><?= number_format($dept['dept_citations']) ?></strong>
                    </span>
                    <a href="<?= url('directory.php?dept=' . urlencode($dept['code'])) ?>" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-iter-50 text-iter-800 hover:bg-iter-800 hover:text-white transition">
                        <span>Browse Faculty</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
