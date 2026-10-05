<?php
/**
 * Departmental Scholar — Add Honor / Award Form
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
    abort(403, 'Unauthorized to add awards to this profile.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title        = trim($_POST['title'] ?? '');
    $awardingBody = trim($_POST['awarding_body'] ?? '');
    $year         = (int)($_POST['year'] ?? 0);
    $description  = trim($_POST['description'] ?? '');

    if (empty($title) || empty($awardingBody) || empty($year)) {
        $error = 'Please fill out Award Title, Awarding Body, and Year.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO awards (
                    faculty_profile_id, title, awarding_body, year, description, created_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $title, $awardingBody, $year, $description, user_id()
            ]);

            record_audit('award_added', 'awards', (int)$db->lastInsertId(), "Added award: {$title}");
            set_flash('success', 'Honor / Award added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            error_log("Failed to add award: " . $e->getMessage());
            $error = 'Failed to record award. Please verify details and try again.';
        }
    }
}

$pageTitle = 'Add Honor / Award — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-slate hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Add Honor or Award</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Record fellowship recognitions, best paper awards, and academic honors</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_award.php?profile_id=' . $profileId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5 shadow-xs">
        <?= csrf_field() ?>

        <div>
            <label class="academic-label">Award / Distinction Title <span class="text-rose-600">*</span></label>
            <input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>"
                placeholder="e.g. Best Research Paper Award, IEEE Senior Member Elevation"
                class="academic-input text-xs sm:text-sm font-semibold">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="academic-label">Awarding Body / Organization <span class="text-rose-600">*</span></label>
                <input type="text" name="awarding_body" required value="<?= e($_POST['awarding_body'] ?? '') ?>"
                    placeholder="e.g. IEEE Computer Society / Govt. of Odisha"
                    class="academic-input text-xs sm:text-sm">
            </div>

            <div>
                <label class="academic-label">Year <span class="text-rose-600">*</span></label>
                <input type="number" name="year" required min="1960" max="2035"
                    value="<?= e($_POST['year'] ?? date('Y')) ?>"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="academic-label">Description / Citation (Optional)</label>
            <textarea name="description" rows="3"
                placeholder="Brief citation or rationale for the recognition..."
                class="academic-input text-xs leading-relaxed"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Honor / Award</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
