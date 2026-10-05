<?php
/**
 * Add Patent / IP Form
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

$pageTitle = 'Add Patent / IP';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-slate-200">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-iter-700 hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-slate-900 font-serif-title">Add Patent / Invention</h1>
        <p class="text-xs text-slate-500 mt-0.5">Record patents filed, published, or granted by Indian or International patent offices</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_patent.php?profile_id=' . $profileId) ?>" method="POST" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                Invention / Patent Title <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>"
                placeholder="e.g. Reconfigurable Multi-Band Microstrip Patch Antenna for Satellite Ground Stations"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Patent Number / App No.
                </label>
                <input type="text" name="patent_number" value="<?= e($_POST['patent_number'] ?? '') ?>"
                    placeholder="e.g. IN202331019284"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Jurisdiction / Country
                </label>
                <input type="text" name="country" value="<?= e($_POST['country'] ?? 'India') ?>"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                Current Status
            </label>
            <select name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                <option value="granted">Granted</option>
                <option value="published">Published</option>
                <option value="filed">Filed</option>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Filing Date</label>
                <input type="date" name="filing_date" value="<?= e($_POST['filing_date'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Grant Date (if granted)</label>
                <input type="date" name="grant_date" value="<?= e($_POST['grant_date'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-iter-800 hover:bg-iter-900 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Patent</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
