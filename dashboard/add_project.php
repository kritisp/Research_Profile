<?php
/**
 * Departmental Scholar — Add Funded Research Project Form
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Focus: High Legibility, Typographic Hierarchy, Refined Borders, Subdued Academic Elevation
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
    abort(403, 'Unauthorized to add projects to this profile.');
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
            error_log("Failed to add project: " . $e->getMessage());
            $error = 'Failed to add project due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Add Sponsored Research Project — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-slate hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Add Sponsored Research Grant</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Record extramural funding from SERB, DST, DRDO, AICTE, or industry partners</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_project.php?profile_id=' . $profileId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5 shadow-xs">
        <?= csrf_field() ?>

        <div>
            <label class="academic-label">Project Title <span class="text-rose-600">*</span></label>
            <input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>"
                placeholder="e.g. Edge-AI Framework for Autonomous Agricultural Monitoring"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="academic-label">Funding Agency <span class="text-rose-600">*</span></label>
                <input type="text" name="funding_agency" required value="<?= e($_POST['funding_agency'] ?? '') ?>"
                    placeholder="e.g. SERB / DST / AICTE / DRDO"
                    class="academic-input text-xs">
            </div>

            <div>
                <label class="academic-label">Project Sanction Code</label>
                <input type="text" name="project_code" value="<?= e($_POST['project_code'] ?? '') ?>"
                    placeholder="e.g. CRG/2023/004521"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="academic-label">Role in Project</label>
                <select name="role" class="academic-input text-xs font-medium">
                    <option value="pi">Principal Investigator (PI)</option>
                    <option value="co_pi">Co-Principal Investigator (Co-PI)</option>
                    <option value="coordinator">Program Coordinator</option>
                    <option value="mentor">Faculty Mentor</option>
                </select>
            </div>

            <div>
                <label class="academic-label">Grant Amount (in Lakhs INR)</label>
                <input type="number" step="0.01" min="0" name="amount_lakhs" value="<?= e($_POST['amount_lakhs'] ?? '0.00') ?>"
                    class="academic-input text-xs font-mono">
            </div>

            <div>
                <label class="academic-label">Project Status</label>
                <select name="status" class="academic-input text-xs font-medium">
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="academic-label">Start Year</label>
                <input type="number" name="start_year" min="1990" max="2035" value="<?= e($_POST['start_year'] ?? date('Y')) ?>"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="academic-label">End Year (or Expected)</label>
                <input type="number" name="end_year" min="1990" max="2035" value="<?= e($_POST['end_year'] ?? (date('Y') + 3)) ?>"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Project Record</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
