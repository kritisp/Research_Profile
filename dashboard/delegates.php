<?php
/**
 * Faculty Delegates & Assistant Management
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$db = Database::getConnection();

$pStmt = $db->prepare("SELECT id, user_id FROM faculty_profiles WHERE user_id = ?");
$pStmt->execute([user_id()]);
$faculty = $pStmt->fetch();

if (!$faculty) {
    set_flash('danger', 'Faculty profile not found.');
    redirect('dashboard/index.php');
}

$facultyUserId = (int)$faculty['user_id'];
$error = null;

// Handle Add Delegate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_delegate') {
    require_csrf();

    $email = trim($_POST['delegate_email'] ?? '');
    if (empty($email)) {
        $error = 'Please enter the assistant/coordinator email.';
    } else {
        $uStmt = $db->prepare("SELECT id, full_name, role FROM users WHERE email = ? LIMIT 1");
        $uStmt->execute([$email]);
        $targetUser = $uStmt->fetch();

        if (!$targetUser) {
            $error = 'No registered user found with that email address. Please ask them to register an Assistant/Coordinator account first.';
        } elseif ((int)$targetUser['id'] === $facultyUserId) {
            $error = 'You cannot assign yourself as your own delegate.';
        } else {
            $ins = $db->prepare("INSERT IGNORE INTO faculty_delegates (faculty_user_id, delegate_user_id, granted_by) VALUES (?, ?, ?)");
            $ins->execute([$facultyUserId, (int)$targetUser['id'], user_id()]);

            record_audit('delegate_granted', 'faculty_delegates', (int)$targetUser['id'], "Granted profile edit access to {$targetUser['full_name']}");
            set_flash('success', "Access granted to {$targetUser['full_name']} ({$email}). They can now update your research publications and achievements.");
            redirect('dashboard/delegates.php');
        }
    }
}

// Handle Revoke Delegate
if (isset($_GET['revoke']) && isset($_GET['csrf_token'])) {
    if (verify_csrf_token($_GET['csrf_token'])) {
        $delId = (int)$_GET['revoke'];
        $del = $db->prepare("DELETE FROM faculty_delegates WHERE id = ? AND faculty_user_id = ?");
        $del->execute([$delId, $facultyUserId]);
        record_audit('delegate_revoked', 'faculty_delegates', $delId, 'Revoked delegate access');
        set_flash('info', 'Delegate access revoked.');
        redirect('dashboard/delegates.php');
    }
}

// Fetch currently authorized delegates
$delStmt = $db->prepare("
    SELECT fd.id as delegate_rel_id, fd.created_at as granted_date, u.id as user_id, u.full_name, u.email, u.role
    FROM faculty_delegates fd
    JOIN users u ON fd.delegate_user_id = u.id
    WHERE fd.faculty_user_id = ?
    ORDER BY fd.created_at DESC
");
$delStmt->execute([$facultyUserId]);
$delegates = $delStmt->fetchAll();

// Fetch available assistants/admins for quick selection
$availStmt = $db->prepare("
    SELECT id, full_name, email, role FROM users 
    WHERE role IN ('admin', 'super_admin') AND id != ?
    ORDER BY full_name ASC
");
$availStmt->execute([$facultyUserId]);
$availableAssistants = $availStmt->fetchAll();

$pageTitle = 'Manage Research Assistants & Delegates';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-slate-200">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-iter-700 hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-slate-900 font-serif-title">Assistant & Delegate Access</h1>
        <p class="text-xs text-slate-500 mt-0.5">Authorize trusted research scholars or departmental coordinators to update your publications and achievements on your behalf</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Current Delegates List -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 uppercase font-mono tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-iter-700"></i>
                    <span>Authorized Delegates (<?= count($delegates) ?>)</span>
                </h2>

                <?php if (empty($delegates)): ?>
                    <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-slate-200 rounded-xl">
                        <i class="fa-solid fa-user-lock text-3xl text-slate-300 mb-2"></i>
                        <p class="font-semibold text-slate-700">No delegates assigned yet</p>
                        <p class="text-[11px] text-slate-400 mt-1">You can grant edit permissions to departmental research assistants using the form on the right.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($delegates as $del): ?>
                            <div class="py-3.5 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-iter-100 text-iter-800 flex items-center justify-center font-bold text-xs">
                                        <?= strtoupper(substr($del['full_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-bold text-slate-900"><?= e($del['full_name']) ?></h3>
                                        <p class="text-[11px] text-slate-500 font-mono"><?= e($del['email']) ?></p>
                                        <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded bg-slate-100 text-[10px] text-slate-600 font-mono uppercase">
                                            <?= e($del['role']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] text-slate-400 font-mono hidden sm:inline">
                                        Granted: <?= date('M d, Y', strtotime($del['granted_date'])) ?>
                                    </span>
                                    <a href="<?= url('dashboard/delegates.php?revoke=' . $del['delegate_rel_id'] . '&csrf_token=' . csrf_token()) ?>"
                                       onclick="return confirm('Revoke edit access for this assistant?');"
                                       class="px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg transition border border-rose-200">
                                        Revoke
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Grant New Delegate Form -->
        <div class="space-y-4">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 uppercase font-mono tracking-wider mb-3">
                    Grant New Access
                </h2>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                    Enter the registered email of your research assistant, PhD scholar, or departmental coordinator.
                </p>

                <form action="<?= url('dashboard/delegates.php') ?>" method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_delegate">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Select from Registered Assistants</label>
                        <select onchange="if(this.value) document.getElementById('delegate_email').value = this.value;"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs mb-2">
                            <option value="">-- Quick Select --</option>
                            <?php foreach ($availableAssistants as $asst): ?>
                                <option value="<?= e($asst['email']) ?>">
                                    <?= e($asst['full_name']) ?> (<?= e($asst['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Or Enter Email Address</label>
                        <input type="email" id="delegate_email" name="delegate_email" required
                            placeholder="assistant@iter.ac.in"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                    </div>

                    <button type="submit" class="w-full py-2.5 px-4 bg-iter-800 hover:bg-iter-900 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-user-check text-xs"></i>
                        <span>Authorize Delegate</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
