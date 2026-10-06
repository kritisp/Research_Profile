<?php
/**
 * Departmental Scholar — Add Teaching Course Assignment Form
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$db = Database::getConnection();

$profileId = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : 0;
if ($profileId <= 0) {
    $pStmt = $db->prepare("SELECT id FROM faculty_profiles WHERE user_id = ?");
    $pStmt->execute([user_id()]);
    $profileId = (int)$pStmt->fetchColumn();
}

if (!can_manage_faculty_profile($profileId)) {
    abort(403, 'Unauthorized to add teaching assignments to this profile.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $courseTitle  = trim($_POST['course_title'] ?? '');
    $courseCode   = trim($_POST['course_code'] ?? '');
    $rawLevel     = strtolower(trim($_POST['level'] ?? 'ug'));
    $level        = in_array($rawLevel, ['ug', 'pg', 'phd']) ? $rawLevel : 'ug';
    $academicYear = trim($_POST['academic_year'] ?? '');

    if (empty($courseTitle)) {
        $error = 'Course title is required.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO teaching (
                    faculty_profile_id, course_title, course_code, level, academic_year
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $courseTitle, $courseCode, $level, $academicYear
            ]);

            record_audit('teaching_added', 'teaching', (int)$db->lastInsertId(), "Added teaching: {$courseTitle}");
            set_flash('success', 'Teaching assignment added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            error_log("Failed to add teaching: " . $e->getMessage());
            $error = 'Failed to record teaching assignment. Please verify details and try again.';
        }
    }
}

$pageTitle = 'Add Teaching Assignment — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-slate hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Add Teaching Assignment</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Record academic courses, laboratories, and curriculum modules taught</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_teaching.php?profile_id=' . $profileId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5 shadow-xs">
        <?= csrf_field() ?>

        <div>
            <label class="academic-label">Course Title <span class="text-rose-600">*</span></label>
            <input type="text" name="course_title" required value="<?= e($_POST['course_title'] ?? '') ?>"
                placeholder="e.g. Design & Analysis of Algorithms"
                class="academic-input text-xs sm:text-sm font-semibold">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="academic-label">Course Code</label>
                <input type="text" name="course_code" value="<?= e($_POST['course_code'] ?? '') ?>"
                    placeholder="e.g. CSE-3001"
                    class="academic-input text-xs font-mono">
            </div>

            <div>
                <label class="academic-label">Academic Level</label>
                <select name="level" class="academic-input text-xs font-medium">
                    <option value="ug">Undergraduate (B.Tech)</option>
                    <option value="pg">Postgraduate (M.Tech/M.S.)</option>
                    <option value="phd">Doctoral (Ph.D.)</option>
                </select>
            </div>

            <div>
                <label class="academic-label">Academic Year / Term</label>
                <input type="text" name="academic_year" value="<?= e($_POST['academic_year'] ?? '2025–2026') ?>"
                    placeholder="e.g. 2025–2026"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Course Assignment</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
