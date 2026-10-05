<?php
/**
 * Departmental Scholar — Faculty Delegates & Assistant Management
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Focus: High Legibility, Typographic Hierarchy, Refined Borders, Subdued Academic Elevation
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$db = Database::getConnection();

$pStmt = $db->prepare("SELECT id, user_id FROM faculty_profiles WHERE user_id = ?");
$pStmt->execute([user_id()]);
$faculty = $pStmt->fetch(PDO::FETCH_ASSOC);

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
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_delegate') {
    require_csrf();
    $delId = (int)($_POST['delegate_id'] ?? 0);
    if ($delId > 0) {
        $del = $db->prepare("DELETE FROM faculty_delegates WHERE id = ? AND faculty_user_id = ?");
        $del->execute([$delId, $facultyUserId]);
        record_audit('delegate_revoked', 'faculty_delegates', $delId, 'Revoked delegate access');
        set_flash('info', 'Delegate access revoked.');
    }
    redirect('dashboard/delegates.php');
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
$delegates = $delStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available assistants/admins for quick selection
$availStmt = $db->prepare("
    SELECT id, full_name, email, role FROM users 
    WHERE role IN ('admin', 'super_admin') AND id != ?
    ORDER BY full_name ASC
");
$availStmt->execute([$facultyUserId]);
$availableAssistants = $availStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manage Research Assistants & Delegates — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-slate hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Assistant & Delegate Access</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Authorize research assistants or departmental coordinators to curate your scholarly works on your behalf</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Authorized Delegates List -->
        <div class="lg:col-span-2 space-y-4">
            <div class="academic-card p-6 shadow-xs">
                <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-academic-gold"></i>
                    <span>Authorized Delegates (<?= count($delegates) ?>)</span>
                </h2>

                <?php if (empty($delegates)): ?>
                    <div class="p-8 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-[8px]">
                        <i class="fa-solid fa-user-lock text-3xl text-slate-300 mb-2"></i>
                        <p class="font-serif text-sm font-bold text-oxford-navy">No delegates assigned yet</p>
                        <p class="text-[11px] text-slate-400 mt-1">Grant edit permissions to registered research assistants using the form on the right.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($delegates as $del): ?>
                            <div class="py-3.5 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-[6px] bg-slate-100 text-oxford-navy border border-scholar-border flex items-center justify-center font-bold text-xs font-mono">
                                        <?= strtoupper(substr($del['full_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <h3 class="font-serif font-bold text-sm text-oxford-navy"><?= e($del['full_name']) ?></h3>
                                        <p class="text-[11px] text-slate-500 font-mono"><?= e($del['email']) ?></p>
                                        <span class="academic-tag font-mono text-[10px] mt-0.5">
                                            <?= e(str_replace('_', ' ', $del['role'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] text-slate-400 font-mono hidden sm:inline">
                                        Granted: <?= date('M Y', strtotime($del['granted_date'])) ?>
                                    </span>
                                    <form action="<?= url('dashboard/delegates.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Revoke delegate edit access for this user?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="revoke_delegate">
                                        <input type="hidden" name="delegate_id" value="<?= (int)$del['delegate_rel_id'] ?>">
                                        <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold px-2 py-1 rounded-[4px] hover:bg-rose-50 border border-transparent hover:border-rose-200 transition">
                                            Revoke
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Assign New Delegate Form -->
        <div class="space-y-4">
            <div class="academic-card p-6 shadow-xs">
                <h2 class="text-xs font-bold text-oxford-navy uppercase font-mono tracking-wider mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-academic-gold"></i>
                    <span>Grant Delegate Access</span>
                </h2>
                <p class="text-xs text-scholar-muted mb-4 leading-relaxed font-sans">
                    Enter the institutional email of a registered research assistant or coordinator.
                </p>

                <form action="<?= url('dashboard/delegates.php') ?>" method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_delegate">

                    <div>
                        <label class="academic-label">Assistant Email <span class="text-rose-600">*</span></label>
                        <input type="email" name="delegate_email" required
                            placeholder="assistant.cse@iter.ac.in"
                            class="academic-input text-xs">
                    </div>

                    <button type="submit" class="btn-academic-primary w-full text-xs shadow-xs !py-2.5">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                        <span>Authorize Delegate</span>
                    </button>
                </form>

                <?php if (!empty($availableAssistants)): ?>
                    <div class="mt-5 pt-4 border-t border-scholar-border">
                        <span class="text-[10px] font-mono text-slate-400 uppercase font-semibold block mb-2">Registered Coordinators</span>
                        <div class="space-y-1.5 max-h-40 overflow-y-auto">
                            <?php foreach ($availableAssistants as $ast): ?>
                                <button type="button" 
                                    onclick="document.querySelector('input[name=delegate_email]').value = '<?= e($ast['email']) ?>'"
                                    class="w-full text-left p-1.5 rounded-[4px] hover:bg-slate-50 text-xs flex items-center justify-between group transition">
                                    <span class="font-medium text-oxford-navy group-hover:text-oxford-slate truncate"><?= e($ast['full_name']) ?></span>
                                    <span class="font-mono text-[10px] text-slate-400 truncate ml-1"><?= e($ast['email']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
