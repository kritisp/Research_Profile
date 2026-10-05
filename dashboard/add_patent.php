<?php
/**
 * Departmental Scholar — Add Patent / IP Form
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
    abort(403, 'Unauthorized to add patents to this profile.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title        = trim($_POST['title'] ?? '');
    $patentNumber = trim($_POST['patent_number'] ?? '');
    $country      = trim($_POST['country'] ?? 'India');
    $status       = $_POST['status'] ?? 'granted';
    $filingDate   = !empty($_POST['filing_date']) ? $_POST['filing_date'] : null;
    $grantDate    = !empty($_POST['grant_date']) ? $_POST['grant_date'] : null;

    if (empty($title)) {
        $error = 'Patent title is required.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO patents (
                    faculty_profile_id, title, patent_number, country, status,
                    filing_date, grant_date, created_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $title, $patentNumber, $country, $status,
                $filingDate, $grantDate, user_id()
            ]);

            record_audit('patent_added', 'patents', (int)$db->lastInsertId(), "Added patent: {$title}");
            set_flash('success', 'Patent record added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            error_log("Failed to add patent: " . $e->getMessage());
            $error = 'Failed to add patent due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Add Patent / IP — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-blue hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Add Patent / Invention</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Record intellectual property filed, published, or granted by patent offices</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_patent.php?profile_id=' . $profileId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Patent Title / Invention Name <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>"
                placeholder="e.g. Automated Early Screening System for Retinal Disorders"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    Patent / Application Number
                </label>
                <input type="text" name="patent_number" value="<?= e($_POST['patent_number'] ?? '') ?>"
                    placeholder="e.g. 202431005892 A"
                    class="academic-input text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    Country / Jurisdiction
                </label>
                <input type="text" name="country" value="<?= e($_POST['country'] ?? 'India') ?>"
                    placeholder="e.g. India / United States"
                    class="academic-input text-xs">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Patent Status
            </label>
            <select name="status" class="academic-input text-xs font-medium">
                <option value="granted">Granted</option>
                <option value="published">Published</option>
                <option value="filed">Filed / Pending Examination</option>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Filing Date</label>
                <input type="date" name="filing_date" value="<?= e($_POST['filing_date'] ?? '') ?>"
                    class="academic-input text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Grant Date (if granted)</label>
                <input type="date" name="grant_date" value="<?= e($_POST['grant_date'] ?? '') ?>"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Patent Record</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
