<?php
/**
 * Multi-Role Authentication Login Page
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
        $user = $stmt->fetch();

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

$pageTitle = 'Sign In to Research Portal';
$activeNav = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-12 sm:py-16 bg-slate-50 flex flex-col justify-center">
    <div class="max-w-md w-full mx-auto px-4">
        
        <!-- Header Card -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-iter-900 text-white shadow-md ring-4 ring-iter-50 mb-3">
                <i class="fa-solid fa-lock text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 font-serif-title">Portal Sign In</h1>
            <p class="text-xs text-slate-500 mt-1">ITER Faculty, Research Delegates & Administrators</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white py-8 px-6 sm:px-8 shadow-sm rounded-2xl border border-slate-200">
            <?php if ($error): ?>
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= url('login.php') ?>" method="POST" class="space-y-4">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        Institutional Email
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-envelope text-xs"></i>
                        </span>
                        <input type="email" id="email" name="email" required autofocus
                            value="<?= e($_POST['email'] ?? '') ?>"
                            placeholder="name@iter.ac.in or name@soa.ac.in"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                            Password
                        </label>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-key text-xs"></i>
                        </span>
                        <input type="password" id="password" name="password" required
                            placeholder="••••••••••••"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none transition">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-iter-800 hover:bg-iter-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-iter-500 transition">
                        <span>Sign In</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-600">
                    New faculty member or research assistant?
                    <a href="<?= url('register.php') ?>" class="font-semibold text-iter-700 hover:text-iter-900 transition">
                        Register here
                    </a>
                </p>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
