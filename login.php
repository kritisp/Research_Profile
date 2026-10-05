<?php
/**
 * Departmental Scholar — Faculty & Scholar Authentication
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    $u = current_user();
    if ($u['role'] === 'super_admin') {
        redirect('admin/index.php');
    } elseif ($u['role'] === 'admin') {
        redirect('assistant/index.php');
    } else {
        redirect('dashboard/index.php');
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your institutional email and password.';
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account is currently ' . htmlspecialchars($user['status']) . '. Please contact the system administrator.';
            } else {
                login_user($user);
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

                if ($user['role'] === 'super_admin') {
                    redirect('admin/index.php');
                } elseif ($user['role'] === 'admin') {
                    redirect('assistant/index.php');
                } else {
                    redirect('dashboard/index.php');
                }
            }
        } else {
            $error = 'Invalid email address or password. Please verify your credentials.';
        }
    }
}

$pageTitle = 'Sign In — Faculty & Researcher Portal';
$activeNav = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-14 sm:py-20 bg-slate-50 flex flex-col justify-center">
    <div class="max-w-md w-full mx-auto px-4">
        
        <!-- Header Masthead -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-[6px] bg-oxford-navy text-academic-gold shadow-xs border border-oxford-slate/30 mb-3">
                <i class="fa-solid fa-graduation-cap text-lg"></i>
            </div>
            <div class="inline-block text-[10px] font-mono uppercase tracking-widest text-oxford-slate font-semibold mb-1">Institutional Access</div>
            <h1 class="font-serif text-2xl sm:text-3xl font-bold tracking-tight text-oxford-navy">Portal Sign In</h1>
            <p class="text-xs text-scholar-muted mt-1 font-sans">Faculty Researchers, Academic Delegates & Administrators</p>
        </div>

        <!-- Form Card -->
        <div class="academic-card p-6 sm:p-8 shadow-xs">
            <?php if ($error): ?>
                <div class="mb-5 p-3.5 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm flex-shrink-0"></i>
                    <span class="font-medium"><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= url('login.php') ?>" method="POST" class="space-y-4">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="academic-label">Institutional Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-envelope text-xs"></i>
                        </span>
                        <input type="email" id="email" name="email" required autofocus
                            value="<?= e($_POST['email'] ?? '') ?>"
                            placeholder="scholar@iter.ac.in or name@university.edu"
                            class="academic-input pl-9 text-xs sm:text-sm">
                    </div>
                </div>

                <div>
                    <label for="password" class="academic-label">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-key text-xs"></i>
                        </span>
                        <input type="password" id="password" name="password" required
                            placeholder="••••••••••••"
                            class="academic-input pl-9 text-xs sm:text-sm">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-academic-primary w-full text-xs shadow-xs !py-2.5">
                        <span>Sign In</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-scholar-border text-center">
                <p class="text-xs text-scholar-muted">
                    New faculty member or researcher?
                    <a href="<?= url('register.php') ?>" class="font-semibold text-oxford-navy hover:underline transition">
                        Register profile here
                    </a>
                </p>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
