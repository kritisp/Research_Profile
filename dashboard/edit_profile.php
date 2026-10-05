<?php
/**
 * Edit Faculty Profile Settings & Scholarly Identifiers
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$db = Database::getConnection();

$profileId = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($profileId <= 0) {
    // Default to own profile
    $pStmt = $db->prepare("SELECT id FROM faculty_profiles WHERE user_id = ?");
    $pStmt->execute([user_id()]);
    $profileId = (int)$pStmt->fetchColumn();
}

if (!can_manage_faculty_profile($profileId)) {
    abort(403, 'Unauthorized to edit this profile.');
}

// Fetch Profile & User
$stmt = $db->prepare("
    SELECT fp.*, u.full_name, u.email 
    FROM faculty_profiles fp
    JOIN users u ON fp.user_id = u.id
    WHERE fp.id = ?
");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    set_flash('danger', 'Profile not found.');
    redirect('dashboard/index.php');
}

// Fetch departments for dropdown
$departments = $db->query("SELECT id, code, name FROM departments ORDER BY name ASC")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $salutation      = trim($_POST['salutation'] ?? 'Dr.');
    $fullName        = trim($_POST['full_name'] ?? '');
    $designation     = trim($_POST['designation'] ?? '');
    $institution     = trim($_POST['institution'] ?? 'ITER, SOA University');
    $departmentId    = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $cabin           = trim($_POST['cabin'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $photoUrl        = trim($_POST['photo_url'] ?? $profile['photo_url'] ?? '');
    $cvUrl           = trim($_POST['cv_url'] ?? $profile['cv_url'] ?? '');
    $bio             = trim($_POST['bio'] ?? '');
    $interests       = trim($_POST['research_interests'] ?? '');
    $scholarUrl      = trim($_POST['google_scholar_url'] ?? '');
    $orcid           = trim($_POST['orcid_id'] ?? '');
    $scopus          = trim($_POST['scopus_id'] ?? '');
    $researchgateUrl = trim($_POST['researchgate_url'] ?? '');
    $wosId           = trim($_POST['wos_id'] ?? '');
    $citations       = (int)($_POST['total_citations'] ?? 0);
    $hIndex          = (int)($_POST['h_index'] ?? 0);
    $i10Index        = (int)($_POST['i10_index'] ?? 0);
    $phdSupervised   = (int)($_POST['phd_supervised'] ?? 0);
    $memberships     = trim($_POST['memberships'] ?? '');
    $editorialRoles  = trim($_POST['editorial_roles'] ?? '');

    // Handle profile photo file upload
    try {
        if (!empty($_FILES['photo_file']) && $_FILES['photo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedPath = handle_file_upload($_FILES['photo_file'], 'photo');
            if ($uploadedPath) {
                $photoUrl = $uploadedPath;
            }
        }
    } catch (Exception $e) {
        $error = 'Photo upload error: ' . $e->getMessage();
    }

    // Handle CV file upload
    try {
        if (!empty($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedCv = handle_file_upload($_FILES['cv_file'], 'cv');
            if ($uploadedCv) {
                $cvUrl = $uploadedCv;
            }
        }
    } catch (Exception $e) {
        $error = 'CV upload error: ' . $e->getMessage();
    }

    // Validate external URL schemes (prevent javascript: or data: schemes)
    if (!empty($scholarUrl) && !preg_match('~^https?://~i', $scholarUrl)) {
        $scholarUrl = 'https://' . ltrim($scholarUrl, '/');
    }
    if (!empty($researchgateUrl) && !preg_match('~^https?://~i', $researchgateUrl)) {
        $researchgateUrl = 'https://' . ltrim($researchgateUrl, '/');
    }
    if (!empty($photoUrl) && !preg_match('~^(https?://|uploads/)~i', $photoUrl)) {
        $photoUrl = '';
    }
    if (!empty($cvUrl) && !preg_match('~^(https?://|uploads/)~i', $cvUrl)) {
        $cvUrl = '';
    }

    if (empty($fullName)) {
        $error = 'Full name is required.';
    } elseif (empty($error)) {
        try {
            $db->beginTransaction();

            // Update user full name
            $uUpdate = $db->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            $uUpdate->execute([$fullName, $profile['user_id']]);

            // Ensure unique slug exists
            $currentSlug = trim($profile['slug'] ?? '');
            if (empty($currentSlug)) {
                $baseSlug = slugify($fullName);
                $currentSlug = $baseSlug;
                $counter = 1;
                while (true) {
                    $chkSlug = $db->prepare("SELECT id FROM faculty_profiles WHERE slug = ? AND id != ?");
                    $chkSlug->execute([$currentSlug, $profileId]);
                    if (!$chkSlug->fetch()) {
                        break;
                    }
                    $currentSlug = $baseSlug . '-' . (++$counter);
                }
            }

            // Update faculty profile
            $pUpdate = $db->prepare("
                UPDATE faculty_profiles SET
                    department_id = ?,
                    institution = ?,
                    slug = ?,
                    salutation = ?,
                    designation = ?,
                    cabin = ?,
                    phone = ?,
                    photo_url = ?,
                    cv_url = ?,
                    bio = ?,
                    research_interests = ?,
                    google_scholar_url = ?,
                    orcid_id = ?,
                    scopus_id = ?,
                    researchgate_url = ?,
                    wos_id = ?,
                    total_citations = ?,
                    h_index = ?,
                    i10_index = ?,
                    phd_supervised = ?,
                    memberships = ?,
                    editorial_roles = ?
                WHERE id = ?
            ");
            $pUpdate->execute([
                $departmentId, $institution, $currentSlug, $salutation, $designation, $cabin, $phone,
                $photoUrl, $cvUrl, $bio, $interests, $scholarUrl, $orcid, $scopus,
                $researchgateUrl, $wosId,
                $citations, $hIndex, $i10Index, $phdSupervised, $memberships, $editorialRoles,
                $profileId
            ]);

            record_audit('profile_updated', 'faculty_profiles', $profileId, 'Profile metadata updated');

            $db->commit();
            set_flash('success', 'Profile updated successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Profile update error: " . $e->getMessage());
            $error = 'Profile update failed due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Edit Profile - ' . $profile['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-200">
        <div>
            <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-iter-700 hover:underline flex items-center gap-1 mb-1 font-semibold">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
            </a>
            <h1 class="text-2xl font-bold text-slate-900 font-serif-title">Edit Faculty Profile</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage your public information, academic identifiers, and citation counts</p>
        </div>
        <a href="<?= url('profile.php?id=' . $profile['id']) ?>" target="_blank" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
            Preview Public View
        </a>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/edit_profile.php?id=' . $profile['id']) ?>" method="POST" enctype="multipart/form-data" class="space-y-8">
        <?= csrf_field() ?>

        <!-- SECTION 1: Personal & Designation -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase font-mono tracking-wider border-b border-slate-100 pb-2">
                1. Academic Identity & Department
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Title / Salutation</label>
                    <select name="salutation" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                        <option value="Prof. Dr." <?= $profile['salutation'] === 'Prof. Dr.' ? 'selected' : '' ?>>Prof. Dr.</option>
                        <option value="Dr." <?= $profile['salutation'] === 'Dr.' ? 'selected' : '' ?>>Dr.</option>
                        <option value="Prof." <?= $profile['salutation'] === 'Prof.' ? 'selected' : '' ?>>Prof.</option>
                        <option value="Mr." <?= $profile['salutation'] === 'Mr.' ? 'selected' : '' ?>>Mr.</option>
                        <option value="Ms." <?= $profile['salutation'] === 'Ms.' ? 'selected' : '' ?>>Ms.</option>
                    </select>
                </div>

                <div class="sm:col-span-9">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" required value="<?= e($profile['full_name']) ?>"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-iter-500">
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Academic Designation</label>
                    <input type="text" name="designation" required value="<?= e($profile['designation']) ?>"
                        placeholder="e.g. Professor & Head / Associate Professor"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">College / Institution</label>
                    <input type="text" name="institution" required value="<?= e($profile['institution'] ?? 'ITER, SOA University') ?>"
                        placeholder="e.g. ITER, SOA University / IIT Bhubaneswar"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Department</label>
                    <select name="department_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= $profile['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?> (<?= e($dept['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Cabin / Office Room</label>
                    <input type="text" name="cabin" value="<?= e($profile['cabin'] ?? '') ?>"
                        placeholder="e.g. Block 1, Room 304, ITER"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Phone / Ext.</label>
                    <input type="text" name="phone" value="<?= e($profile['phone'] ?? '') ?>"
                        placeholder="e.g. +91 674 2350181"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">PhD Scholars Supervised / Guided</label>
                    <input type="number" name="phd_supervised" min="0" value="<?= (int)($profile['phd_supervised'] ?? 0) ?>"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Profile Photo (Upload file)</label>
                    <input type="file" name="photo_file" accept="image/jpeg,image/png,image/webp"
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:bg-iter-700 file:text-white hover:file:bg-iter-800">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Max 3MB (JPG, PNG, WebP). Or provide URL below:</span>
                    <input type="url" name="photo_url" value="<?= e($profile['photo_url'] ?? '') ?>"
                        placeholder="Or external URL: https://..."
                        class="w-full mt-1 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px]">
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Curriculum Vitae (CV / Resume)</label>
                    <input type="file" name="cv_file" accept="application/pdf"
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:bg-iter-700 file:text-white hover:file:bg-iter-800">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">PDF only, max 8MB. Or provide URL below:</span>
                    <input type="url" name="cv_url" value="<?= e($profile['cv_url'] ?? '') ?>"
                        placeholder="Or external PDF URL: https://..."
                        class="w-full mt-1 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px]">
                </div>
            </div>
        </div>

        <!-- SECTION 2: Scholar Identifiers & Metrics -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-sm font-bold text-slate-900 uppercase font-mono tracking-wider">
                    2. Academic IDs & Citation Indices
                </h2>
                <span class="text-[11px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 flex items-center gap-1 font-medium">
                    <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> Manually Maintained Metrics
                </span>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-slate-600 text-xs leading-relaxed">
                <i class="fa-solid fa-circle-info text-iter-600 mr-1"></i>
                <strong>Notice:</strong> Citation count, h-index, and i10-index are self-reported by faculty or designated assistants. Public profiles transparently state that metrics are manually maintained with a last-updated date.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Total Citations</label>
                    <input type="number" name="total_citations" min="0" value="<?= (int)$profile['total_citations'] ?>"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">h-Index</label>
                    <input type="number" name="h_index" min="0" value="<?= (int)$profile['h_index'] ?>"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">i10-Index</label>
                    <input type="number" name="i10_index" min="0" value="<?= (int)$profile['i10_index'] ?>"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Google Scholar Profile URL</label>
                    <input type="url" name="google_scholar_url" value="<?= e($profile['google_scholar_url'] ?? '') ?>"
                        placeholder="https://scholar.google.com/citations?user=..."
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">ResearchGate Profile URL</label>
                    <input type="url" name="researchgate_url" value="<?= e($profile['researchgate_url'] ?? '') ?>"
                        placeholder="https://www.researchgate.net/profile/..."
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">ORCID ID</label>
                    <input type="text" name="orcid_id" value="<?= e($profile['orcid_id'] ?? '') ?>"
                        placeholder="0000-0002-1825-0097"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
                </div>

                <div class="sm:col-span-1">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Scopus Author ID</label>
                    <input type="text" name="scopus_id" value="<?= e($profile['scopus_id'] ?? '') ?>"
                        placeholder="57194512340"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Web of Science ResearcherID</label>
                    <input type="text" name="wos_id" value="<?= e($profile['wos_id'] ?? '') ?>"
                        placeholder="e.g. A-1234-2020 or Web of Science ID"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
                </div>
            </div>
        </div>

        <!-- SECTION 3: Bio & Research Statement -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase font-mono tracking-wider border-b border-slate-100 pb-2">
                3. Biography & Research Interests
            </h2>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Research Interests (Comma separated tags)</label>
                <input type="text" name="research_interests" value="<?= e($profile['research_interests'] ?? '') ?>"
                    placeholder="Machine Learning, Computer Vision, Signal Processing, VLSI"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                <span class="text-[11px] text-slate-400 mt-1 block">Separate tags with commas. These become clickable badges on your profile and directory.</span>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Research Statement & Biography</label>
                <textarea name="bio" rows="5" placeholder="Provide an executive summary of your research trajectory, doctoral mentorship, and scholarly focus..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs leading-relaxed"><?= e($profile['bio'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- SECTION 4: Professional Memberships & Editorial Roles -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase font-mono tracking-wider border-b border-slate-100 pb-2">
                4. Professional Memberships & Editorial Roles
            </h2>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Professional Memberships (one per line or comma-separated)</label>
                <textarea name="memberships" rows="3" placeholder="Senior Member, IEEE&#10;Life Fellow, IETE&#10;Member, ACM"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs leading-relaxed"><?= e($profile['memberships'] ?? '') ?></textarea>
                <span class="text-[11px] text-slate-400 mt-1 block">e.g., IEEE, ACM, IETE, CSI, etc.</span>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Editorial & Reviewer Roles (one per line or comma-separated)</label>
                <textarea name="editorial_roles" rows="3" placeholder="Associate Editor, IEEE Transactions on Emerging Topics in Computing&#10;Reviewer, Nature Scientific Reports"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs leading-relaxed"><?= e($profile['editorial_roles'] ?? '') ?></textarea>
                <span class="text-[11px] text-slate-400 mt-1 block">Journals, conferences, or academic book editorial appointments.</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="<?= url('dashboard/index.php') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-iter-800 hover:bg-iter-900 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                <span>Save Profile Changes</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
