<?php
/**
 * Add Funded Research Project Form
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
    http_response_code(403);
    die('Unauthorized to add projects to this profile.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title       = trim($_POST['title'] ?? '');
    $agency      = trim($_POST['funding_agency'] ?? '');
    $code        = trim($_POST['project_code'] ?? '');
    $role        = $_POST['role'] ?? 'pi';
    $amount      = (float)($_POST['amount_lakhs'] ?? 0.0);
    $startYear   = !empty($_POST['start_year']) ? (int)$_POST['start_year'] : null;
    $endYear     = !empty($_POST['end_year']) ? (int)$_POST['end_year'] : null;
    $status      = $_POST['status'] ?? 'ongoing';

    if (empty($title) || empty($agency)) {
        $error = 'Project Title and Funding Agency are required.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO projects (
                    faculty_profile_id, title, funding_agency, project_code, role,
                    amount_lakhs, start_year, end_year, status, created_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $title, $agency, $code, $role,
                $amount, $startYear, $endYear, $status, user_id()
            ]);

            record_audit('project_added', 'projects', (int)$db->lastInsertId(), "Added research project: {$title}");
            set_flash('success', 'Funded research grant/project added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            $error = 'Failed to add project: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Sponsored Research Project';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-slate-200">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-iter-700 hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-slate-900 font-serif-title">Add Sponsored Research Grant</h1>
        <p class="text-xs text-slate-500 mt-0.5">Record extramural funding from SERB, DST, DRDO, AICTE, or industry</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_project.php?profile_id=' . $profileId) ?>" method="POST" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                Project Title <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>"
                placeholder="e.g. Design of Edge-AI Embedded IoT Devices for Automated Crop Disease Diagnostic"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Funding Agency / Sponsor <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="funding_agency" required value="<?= e($_POST['funding_agency'] ?? '') ?>"
                    placeholder="e.g. SERB / DST / AICTE / DRDO"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Sanction / Project Code
                </label>
                <input type="text" name="project_code" value="<?= e($_POST['project_code'] ?? '') ?>"
                    placeholder="e.g. CRG/2023/004812"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Role</label>
                <select name="role" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                    <option value="pi">Principal Investigator (PI)</option>
                    <option value="copi">Co-Investigator (Co-PI)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Sanctioned Amount (₹ Lakhs)
                </label>
                <input type="number" step="0.01" min="0" name="amount_lakhs" value="<?= e($_POST['amount_lakhs'] ?? '0.00') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono font-bold">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Status</label>
                <select name="status" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Start Year</label>
                <input type="number" name="start_year" min="1990" max="2035" value="<?= e($_POST['start_year'] ?? date('Y')) ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">End Year (or Expected)</label>
                <input type="number" name="end_year" min="1990" max="2035" value="<?= e($_POST['end_year'] ?? (date('Y') + 3)) ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-iter-800 hover:bg-iter-900 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Project</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
