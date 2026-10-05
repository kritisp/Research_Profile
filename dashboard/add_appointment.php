<?php
/**
 * Departmental Scholar — Add Academic Appointment / Career Experience Form
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
    abort(403, 'Unauthorized to add academic appointments to this profile.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $positionTitle = trim($_POST['position_title'] ?? '');
    $organization  = trim($_POST['organization'] ?? '');
    $department    = trim($_POST['department'] ?? '');
    $startYear     = !empty($_POST['start_year']) ? (int)$_POST['start_year'] : null;
    $isCurrent     = isset($_POST['is_current']) ? 1 : 0;
    $endYear       = $isCurrent ? null : (!empty($_POST['end_year']) ? (int)$_POST['end_year'] : null);
    $description   = trim($_POST['description'] ?? '');

    if (empty($positionTitle) || empty($organization)) {
        $error = 'Please fill out Position Title and Institution / Organization.';
    } elseif (!$isCurrent && !empty($endYear) && !empty($startYear) && $endYear < $startYear) {
        $error = 'End year cannot be earlier than start year.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO academic_experience (
                    faculty_profile_id, position_title, organization, department,
                    start_year, end_year, is_current, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $positionTitle, $organization, $department,
                $startYear, $endYear, $isCurrent, $description
            ]);

            record_audit('experience_added', 'academic_experience', (int)$db->lastInsertId(), "Added appointment: {$positionTitle} at {$organization}");
            set_flash('success', 'Academic appointment added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            error_log("Failed to add academic appointment: " . $e->getMessage());
            $error = 'Failed to record academic appointment. Please verify details and try again.';
        }
    }
}

$pageTitle = 'Add Academic Appointment — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-blue hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Add Academic Appointment</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Record faculty appointments, postdocs, visiting fellowships, or administrative roles</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_appointment.php?profile_id=' . $profileId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Position / Role Title <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="position_title" required value="<?= e($_POST['position_title'] ?? '') ?>"
                placeholder="e.g. Associate Professor, Postdoctoral Research Fellow, Department Head"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Institution / University / Organization <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="organization" required value="<?= e($_POST['organization'] ?? '') ?>"
                placeholder="e.g. Institute of Technical Education & Research (ITER), SOA University"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Department / Division / School
            </label>
            <input type="text" name="department" value="<?= e($_POST['department'] ?? '') ?>"
                placeholder="e.g. Department of Computer Science & Engineering"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    Start Year <span class="text-rose-600">*</span>
                </label>
                <input type="number" name="start_year" required min="1960" max="2035"
                    value="<?= e($_POST['start_year'] ?? date('Y')) ?>"
                    class="academic-input text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    End Year
                </label>
                <input type="number" name="end_year" min="1960" max="2035"
                    value="<?= e($_POST['end_year'] ?? '') ?>"
                    placeholder="Leave empty if present"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-oxford-navy select-none">
                <input type="checkbox" name="is_current" value="1" <?= !empty($_POST['is_current']) ? 'checked' : '' ?>
                    class="rounded border-scholar-border text-oxford-blue focus:ring-oxford-blue w-4 h-4">
                <span>This is a current active appointment</span>
            </label>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Key Responsibilities & Scope (Optional)
            </label>
            <textarea name="description" rows="3"
                placeholder="Overview of research labs led, administrative duties, or specialized academic programs handled..."
                class="academic-input text-xs leading-relaxed"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Appointment</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
