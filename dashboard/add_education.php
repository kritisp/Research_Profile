<?php
/**
 * Departmental Scholar — Add Educational Qualification Form
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
    abort(403, 'Unauthorized to add educational qualifications to this profile.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $degree       = trim($_POST['degree'] ?? '');
    $fieldOfStudy = trim($_POST['field_of_study'] ?? '');
    $institution  = trim($_POST['institution'] ?? '');
    $year         = (int)($_POST['year'] ?? 0);

    if (empty($degree) || empty($institution) || empty($year)) {
        $error = 'Please fill out Degree, Institution, and Year of completion.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO education (
                    faculty_profile_id, degree, specialization, institution, year
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $degree, $fieldOfStudy, $institution, $year
            ]);

            record_audit('education_added', 'education', (int)$db->lastInsertId(), "Added qualification: {$degree} from {$institution}");
            set_flash('success', 'Educational qualification added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            error_log("Failed to add education: " . $e->getMessage());
            $error = 'Failed to record qualification. Please verify details and try again.';
        }
    }
}

$pageTitle = 'Add Educational Qualification — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-slate hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Add Educational Qualification</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Record doctoral degrees, master's degrees, and academic credentials</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_education.php?profile_id=' . $profileId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5 shadow-xs">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="academic-label">Degree / Credential <span class="text-rose-600">*</span></label>
                <input type="text" name="degree" required value="<?= e($_POST['degree'] ?? '') ?>"
                    placeholder="e.g. Ph.D., M.Tech, M.S., B.Tech"
                    class="academic-input text-xs sm:text-sm font-semibold">
            </div>

            <div>
                <label class="academic-label">Field of Study / Discipline</label>
                <input type="text" name="field_of_study" value="<?= e($_POST['field_of_study'] ?? '') ?>"
                    placeholder="e.g. Computer Science & Engineering"
                    class="academic-input text-xs sm:text-sm">
            </div>
        </div>

        <div>
            <label class="academic-label">Awarding Institution / University <span class="text-rose-600">*</span></label>
            <input type="text" name="institution" required value="<?= e($_POST['institution'] ?? '') ?>"
                placeholder="e.g. Indian Institute of Technology (IIT) Kharagpur"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div>
            <label class="academic-label">Year of Completion / Award <span class="text-rose-600">*</span></label>
            <input type="number" name="year" required min="1950" max="2035"
                value="<?= e($_POST['year'] ?? date('Y')) ?>"
                class="academic-input text-xs font-mono max-w-xs">
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Qualification</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
