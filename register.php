<?php
/**
 * Registration Page for Faculty and Research Delegates
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard/index.php');
}

$db = Database::getConnection();

// Fetch active departments for dropdown
$deptStmt = $db->query("SELECT id, code, name FROM departments ORDER BY name ASC");
$departments = $deptStmt->fetchAll();

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
            $profileStmt = $db->prepare("
                INSERT INTO faculty_profiles (user_id, slug, department_id, institution, salutation, designation, is_verified) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $profileStmt->execute([$newUserId, $slug, $departmentId, $institution, $salutation, $designation]);

            $db->commit();

            // Fetch newly created user and log in
            $fetchStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $fetchStmt->execute([$newUserId]);
            $newUser = $fetchStmt->fetch();

            login_user($newUser);
            set_flash('success', 'Account registered successfully! Welcome to the Academic Research Portal.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Faculty registration error: " . $e->getMessage());
            $errors[] = 'Registration failed due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Create Account';
$activeNav = 'register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-12 bg-slate-50 flex flex-col justify-center">
    <div class="max-w-xl w-full mx-auto px-4">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-iter-900 text-white shadow-md ring-4 ring-iter-50 mb-3">
                <i class="fa-solid fa-id-badge text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 font-serif-title">Create Research Account</h1>
            <p class="text-xs text-slate-500 mt-1">Register as an ITER faculty member or departmental research coordinator</p>
        </div>

        <div class="bg-white py-8 px-6 sm:px-8 shadow-sm rounded-2xl border border-slate-200">
            <?php if (!empty($errors)): ?>
                <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <div class="font-semibold flex items-center gap-1.5 mb-1">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        <span>Please correct the following:</span>
                    </div>
                    <?php foreach ($errors as $err): ?>
                        <div class="pl-4 list-disc">• <?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= url('register.php') ?>" method="POST" class="space-y-4">
                <?= csrf_field() ?>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-slate-700 text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-graduation-cap text-iter-700 text-sm"></i>
                    <div>
                        <span class="font-bold text-slate-900 block">Faculty Scholar Registration</span>
                        <span class="text-[11px] text-slate-500">Public registration is for individual faculty members. Assistant and administrative accounts are assigned by department heads.</span>
                    </div>
                </div>

                <!-- Full Name -->
                <div>
                    <label for="full_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        Full Name (with title)
                    </label>
                    <input type="text" id="full_name" name="full_name" required
                        value="<?= e($_POST['full_name'] ?? '') ?>"
                        placeholder="e.g. Dr. Debabrata Singh"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                </div>

                <!-- College / Institution -->
                <div>
                    <label for="institution" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        College / Institution
                    </label>
                    <input type="text" id="institution" name="institution" required
                        value="<?= e($_POST['institution'] ?? 'ITER, SOA University') ?>"
                        placeholder="e.g. ITER, SOA University / IIT Bhubaneswar / NIT Rourkela"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                </div>

                <!-- Department Selector -->
                <div>
                    <label for="department_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        Department
                    </label>
                    <select id="department_id" name="department_id"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
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
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                        value="<?= e($_POST['email'] ?? '') ?>"
                        placeholder="e.g. yourname@iter.ac.in or gmail.com"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                </div>

                <!-- Password and Confirm Password Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Password
                        </label>
                        <input type="password" id="password" name="password" required minlength="6"
                            placeholder="At least 6 characters"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                    </div>
                    <div>
                        <label for="password_confirm" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Confirm Password
                        </label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                            placeholder="Re-type password"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit"
                        class="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-iter-800 hover:bg-iter-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-iter-500 transition">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span>Register & Continue</span>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-600">
                    Already registered?
                    <a href="<?= url('login.php') ?>" class="font-semibold text-iter-700 hover:text-iter-900 transition">
                        Sign In here
                    </a>
                </p>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
