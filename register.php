<?php
/**
 * Departmental Scholar — Faculty Registration Portal
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/orcid.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard/index.php');
}

$db = Database::getConnection();

// Fetch active departments for dropdown
$deptStmt = $db->query("SELECT id, code, name FROM departments ORDER BY name ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName       = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    // CRITICAL: Public registration strictly creates normal Faculty accounts only.
    // Privileged accounts (Admin / Super Admin) must be created/assigned by authorized administrators.
    $role           = 'faculty';
    $institution    = trim($_POST['institution'] ?? 'ITER, SOA University');
    $departmentId   = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $password       = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($fullName)) {
        $errors[] = 'Full name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid institutional email is required.';
    }
    if (empty($departmentId)) {
        $errors[] = 'Please select your academic department.';
    }
    if (empty($institution)) {
        $errors[] = 'Please specify your college or institution.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Check if email already exists
    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $errors[] = 'An account with this email address already exists. Please sign in.';
        }
    }

    // Insert user & profile
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, status) VALUES (?, ?, ?, 'faculty', 'active')");
            $userStmt->execute([$email, $passwordHash, $fullName]);
            $newUserId = (int)$db->lastInsertId();

            // Generate unique slug for faculty member
            $baseSlug = slugify($fullName);
            $slug = $baseSlug;
            $counter = 1;
            while (true) {
                $chk = $db->prepare("SELECT id FROM faculty_profiles WHERE slug = ?");
                $chk->execute([$slug]);
                if (!$chk->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . (++$counter);
            }

            $salutation = 'Dr.';
            $designation = 'Assistant Professor';
            $orcidId = clean_orcid_input($_POST['orcid_id'] ?? '');
            $profileStmt = $db->prepare("
                INSERT INTO faculty_profiles (user_id, slug, department_id, institution, salutation, designation, orcid_id, is_verified) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $profileStmt->execute([$newUserId, $slug, $departmentId, $institution, $salutation, $designation, $orcidId ?: null]);
            $newProfileId = (int)$db->lastInsertId();

            $db->commit();

            // Auto-sync ORCID public works if supplied on registration
            $syncMsg = '';
            if (!empty($orcidId) && $newProfileId > 0) {
                try {
                    $syncRes = sync_orcid_to_faculty($newProfileId, $orcidId, $newUserId);
                    if ($syncRes['success'] && $syncRes['count'] > 0) {
                        $syncMsg = " Auto-imported {$syncRes['count']} publications from ORCID.";
                    }
                } catch (Exception $oe) {
                    error_log("Initial ORCID sync failed: " . $oe->getMessage());
                }
            }

            // Fetch newly created user and log in
            $fetchStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $fetchStmt->execute([$newUserId]);
            $newUser = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            login_user($newUser);
            set_flash('success', 'Account registered successfully!' . $syncMsg . ' Welcome to the Academic Research Portal.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Faculty registration error: " . $e->getMessage());
            $errors[] = 'Registration failed due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Create Faculty Account — Scholarly Portal';
$activeNav = 'register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-14 sm:py-20 bg-slate-50 flex flex-col justify-center">
    <div class="max-w-xl w-full mx-auto px-4">
        
        <!-- Header Banner -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-[6px] bg-oxford-navy text-academic-gold shadow-xs border border-oxford-slate/30 mb-3">
                <i class="fa-solid fa-id-badge text-lg"></i>
            </div>
            <div class="inline-block text-[10px] font-mono uppercase tracking-widest text-oxford-slate font-semibold mb-1">Academic Roster</div>
            <h1 class="font-serif text-2xl sm:text-3xl font-bold tracking-tight text-oxford-navy">Create Faculty Profile</h1>
            <p class="text-xs text-scholar-muted mt-1 font-sans">Register as a faculty researcher or scholar in the academic repository</p>
        </div>

        <div class="academic-card p-6 sm:p-8 shadow-xs">
            <?php if (!empty($errors)): ?>
                <div class="mb-5 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <div class="font-semibold flex items-center gap-1.5 mb-1">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        <span>Please correct the following:</span>
                    </div>
                    <?php foreach ($errors as $err): ?>
                        <div class="pl-4 list-disc font-medium">• <?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= url('register.php') ?>" method="POST" class="space-y-4">
                <?= csrf_field() ?>

                <!-- Notice -->
                <div class="p-3 bg-slate-50 rounded-[6px] border border-scholar-border text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-graduation-cap text-academic-gold text-base flex-shrink-0"></i>
                    <div>
                        <span class="font-bold text-oxford-navy block">Faculty Scholar Registration</span>
                        <span class="text-[11px] text-scholar-muted">Registration provisions standard Faculty accounts. Administrative roles are assigned by department heads.</span>
                    </div>
                </div>

                <!-- Full Name -->
                <div>
                    <label for="full_name" class="academic-label">Full Name (with title)</label>
                    <input type="text" id="full_name" name="full_name" required
                        value="<?= e($_POST['full_name'] ?? '') ?>"
                        placeholder="e.g. Dr. Debabrata Singh"
                        class="academic-input text-xs sm:text-sm">
                </div>

                <!-- College / Institution -->
                <div>
                    <label for="institution" class="academic-label">College / Institution</label>
                    <input type="text" id="institution" name="institution" required
                        value="<?= e($_POST['institution'] ?? 'ITER, SOA University') ?>"
                        placeholder="e.g. ITER, SOA University / IIT Bhubaneswar"
                        class="academic-input text-xs sm:text-sm">
                </div>

                <!-- Department Selector -->
                <div>
                    <label for="department_id" class="academic-label">Academic Department</label>
                    <select id="department_id" name="department_id" class="academic-input text-xs sm:text-sm" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?> (<?= e($dept['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Institutional Email -->
                <div>
                    <label for="email" class="academic-label">Institutional Email Address</label>
                    <input type="email" id="email" name="email" required
                        value="<?= e($_POST['email'] ?? '') ?>"
                        placeholder="e.g. yourname@iter.ac.in or university.edu"
                        class="academic-input text-xs sm:text-sm">
                </div>

                <!-- ORCID iD (Optional) -->
                <div>
                    <div class="flex items-center justify-between">
                        <label for="orcid_id" class="academic-label !mb-0">ORCID iD <span class="text-slate-400 font-normal font-sans">(Optional)</span></label>
                        <span class="text-[10px] text-emerald-700 font-medium inline-flex items-center gap-1">
                            <i class="fa-brands fa-orcid"></i>
                            <span>Auto-imports research output</span>
                        </span>
                    </div>
                    <input type="text" id="orcid_id" name="orcid_id"
                        value="<?= e($_POST['orcid_id'] ?? '') ?>"
                        placeholder="0000-0002-1825-0097"
                        class="academic-input text-xs sm:text-sm font-mono mt-1">
                    <span class="text-[10px] text-slate-400 mt-0.5 block font-sans">Optional: Provide your 16-character ORCID iD to automatically import public publications.</span>
                </div>

                <!-- Password and Confirm Password Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="academic-label">Password</label>
                        <input type="password" id="password" name="password" required minlength="6"
                            placeholder="At least 6 characters"
                            class="academic-input text-xs sm:text-sm">
                    </div>
                    <div>
                        <label for="password_confirm" class="academic-label">Confirm Password</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                            placeholder="Re-type password"
                            class="academic-input text-xs sm:text-sm">
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit" class="btn-academic-primary w-full text-xs shadow-xs !py-2.5">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span>Register & Continue to Dashboard</span>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-scholar-border text-center">
                <p class="text-xs text-scholar-muted">
                    Already registered as a faculty researcher?
                    <a href="<?= url('login.php') ?>" class="font-semibold text-oxford-navy hover:underline transition">
                        Sign In here
                    </a>
                </p>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
