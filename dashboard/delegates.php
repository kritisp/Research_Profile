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

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Header with Breadcrumb & Summary Badge -->
    <div class="mb-8 pb-5 border-b border-scholar-border flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-slate hover:underline inline-flex items-center gap-1.5 mb-1.5 font-semibold transition">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
            </a>
            <h1 class="font-serif text-2xl sm:text-3xl font-bold text-oxford-navy">Assistant & Research Delegate Permissions</h1>
            <p class="text-xs text-scholar-muted mt-1 font-sans">Authorize departmental coordinators or research assistants to curate your publications and scholarly profile on your behalf</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-oxford-navy/5 text-oxford-navy text-xs font-mono font-semibold border border-oxford-navy/10">
                <i class="fa-solid fa-user-shield text-academic-gold text-xs"></i>
                <?= count($delegates) ?> Active Delegate<?= count($delegates) !== 1 ? 's' : '' ?>
            </span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-base flex-shrink-0"></i>
            <span class="font-medium"><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Left: Grant Delegate Access Form (5 cols on lg) -->
        <div class="lg:col-span-5 space-y-5">
            <div class="academic-card p-6 shadow-xs bg-white border border-scholar-border rounded-xl">
                <div class="flex items-center gap-2.5 pb-3 mb-4 border-b border-scholar-border-light">
                    <div class="w-8 h-8 rounded-lg bg-oxford-navy text-white flex items-center justify-center text-xs shadow-xs flex-shrink-0">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div>
                        <h2 class="font-serif font-bold text-base text-oxford-navy">Grant Delegate Access</h2>
                        <p class="text-[11px] text-slate-500 font-sans">Assign profile curation privileges to a verified assistant</p>
                    </div>
                </div>

                <!-- 1. Dropdown Menu to Choose from Registered Assistants -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5 font-sans">
                            <i class="fa-solid fa-list-check text-oxford-slate mr-1"></i> Quick Select from Registered List:
                        </label>
                        <select id="delegateSelect" onchange="onSelectDelegate(this)" class="academic-input text-xs w-full !py-2 bg-slate-50/50 hover:bg-white focus:bg-white transition">
                            <option value="">-- Choose from registered coordinators/assistants --</option>
                            <?php foreach ($availableAssistants as $ast): ?>
                                <option value="<?= e($ast['email']) ?>" data-name="<?= e($ast['full_name']) ?>" data-role="<?= e($ast['role']) ?>">
                                    <?= e($ast['full_name']) ?> (<?= e($ast['email']) ?>) — <?= ucfirst(str_replace('_', ' ', $ast['role'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Search Box for Filtering Registered Delegates -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5 font-sans">
                            <i class="fa-solid fa-magnifying-glass text-oxford-slate mr-1"></i> Or Search Registered Delegates:
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" id="delegateSearchInput" onkeyup="filterDelegates()" 
                                   placeholder="Type name or email to filter..." 
                                   class="academic-input pl-9 text-xs w-full !py-2">
                        </div>

                        <!-- Live Filterable List of Assistants -->
                        <?php if (!empty($availableAssistants)): ?>
                            <div class="mt-2.5 p-2 bg-slate-50/80 rounded-lg border border-slate-200 max-h-48 overflow-y-auto space-y-1" id="assistantsListContainer">
                                <?php foreach ($availableAssistants as $ast): ?>
                                    <div class="assistant-item flex items-center justify-between p-2 rounded-md bg-white hover:bg-blue-50/60 border border-slate-100 hover:border-blue-200 transition group text-xs"
                                         data-search="<?= strtolower(e($ast['full_name'] . ' ' . $ast['email'] . ' ' . $ast['role'])) ?>">
                                        <div class="flex items-center gap-2 truncate pr-2">
                                            <div class="w-6 h-6 rounded bg-oxford-navy/5 text-oxford-navy flex items-center justify-center font-bold text-[10px] flex-shrink-0">
                                                <?= strtoupper(substr($ast['full_name'], 0, 1)) ?>
                                            </div>
                                            <div class="truncate">
                                                <div class="font-semibold text-oxford-navy truncate text-xs"><?= e($ast['full_name']) ?></div>
                                                <div class="font-mono text-[10px] text-slate-400 truncate"><?= e($ast['email']) ?></div>
                                            </div>
                                        </div>
                                        <button type="button" 
                                                onclick="selectAssistantEmail('<?= addslashes(e($ast['email'])) ?>', '<?= addslashes(e($ast['full_name'])) ?>')"
                                                class="px-2 py-1 rounded bg-slate-100 group-hover:bg-oxford-navy text-oxford-navy group-hover:text-white text-[10px] font-semibold flex-shrink-0 transition">
                                            Select
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                                <div id="noSearchMatches" class="hidden p-3 text-center text-slate-400 text-xs font-sans">
                                    No registered delegates match your search query.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Final Submission Form with Email Box -->
                    <form action="<?= url('dashboard/delegates.php') ?>" method="POST" class="pt-3 border-t border-scholar-border-light space-y-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_delegate">

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1 font-sans">
                                Assistant Institutional Email <span class="text-rose-600">*</span>
                            </label>
                            <input type="email" id="delegateEmailInput" name="delegate_email" required
                                placeholder="e.g. assistant.cse@iter.ac.in"
                                class="academic-input text-xs w-full !py-2 font-mono">
                            <span class="text-[10px] text-slate-400 mt-1 block">Selected email will receive authorization to curate your profile</span>
                        </div>

                        <button type="submit" class="btn-academic-primary w-full text-xs shadow-xs !py-2.5 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-shield-halved text-xs"></i>
                            <span>Authorize Delegate Access</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Informational Security Note -->
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 text-xs space-y-1.5 font-sans">
                <div class="flex items-center gap-1.5 font-semibold text-oxford-navy">
                    <i class="fa-solid fa-circle-info text-oxford-slate"></i>
                    <span>Institutional Governance Safeguards</span>
                </div>
                <p class="text-[11px] leading-relaxed text-slate-500">
                    Authorized delegates are granted permission to add, edit, and format your publications, funded projects, and scholarly awards. Delegates cannot alter your account password, email, or core faculty identity settings.
                </p>
            </div>
        </div>

        <!-- Right: Authorized Delegates List (7 cols on lg) -->
        <div class="lg:col-span-7 space-y-4">
            <div class="academic-card p-6 shadow-xs bg-white border border-scholar-border rounded-xl">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-scholar-border-light">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-users-gear text-oxford-slate text-sm"></i>
                        <h2 class="font-serif font-bold text-base text-oxford-navy">
                            Authorized Delegates
                            <span class="text-xs font-sans font-normal text-slate-400">(<?= count($delegates) ?> Active)</span>
                        </h2>
                    </div>
                </div>

                <?php if (empty($delegates)): ?>
                    <div class="py-12 px-6 text-center text-slate-500 text-xs border border-dashed border-scholar-border rounded-xl bg-slate-50/50">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-lg">
                            <i class="fa-solid fa-user-lock"></i>
                        </div>
                        <p class="font-serif text-base font-bold text-oxford-navy">No delegates assigned yet</p>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 leading-relaxed">
                            Select a registered departmental coordinator or assistant from the left panel to grant profile maintenance permissions.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($delegates as $del): ?>
                            <div class="p-4 rounded-lg bg-slate-50/60 hover:bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-11 h-11 rounded-lg bg-oxford-navy text-white flex items-center justify-center font-bold text-sm font-mono shadow-xs flex-shrink-0">
                                        <?= strtoupper(substr($del['full_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="font-serif font-bold text-sm text-oxford-navy"><?= e($del['full_name']) ?></h3>
                                            <span class="academic-tag font-mono text-[9px] uppercase font-bold !py-0.5 !px-2 bg-emerald-50 text-emerald-800 border-emerald-200">
                                                <?= e(str_replace('_', ' ', $del['role'])) ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 font-mono mt-0.5"><?= e($del['email']) ?></p>
                                        <span class="text-[10px] text-slate-400 font-sans block mt-0.5">
                                            <i class="fa-regular fa-calendar-check text-[9px] mr-1"></i> Authorized on <?= date('M j, Y', strtotime($del['granted_date'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center sm:self-center gap-2 self-end">
                                    <form action="<?= url('dashboard/delegates.php') ?>" method="POST" class="inline m-0" onsubmit="return confirm('Revoke delegate curation access for <?= addslashes(e($del['full_name'])) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="revoke_delegate">
                                        <input type="hidden" name="delegate_id" value="<?= (int)$del['delegate_rel_id'] ?>">
                                        <button type="submit" class="inline-flex items-center gap-1.5 text-rose-700 hover:text-white text-xs font-semibold px-3 py-1.5 rounded-md hover:bg-rose-600 border border-rose-200 hover:border-rose-600 transition shadow-xs">
                                            <i class="fa-solid fa-user-xmark text-[11px]"></i>
                                            <span>Revoke Access</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- JavaScript for Delegate Selection & Filtering -->
<script>
function onSelectDelegate(selectEl) {
    const email = selectEl.value;
    const emailInput = document.getElementById('delegateEmailInput');
    if (emailInput && email) {
        emailInput.value = email;
        highlightEmailInput();
    }
}

function selectAssistantEmail(email, name) {
    const emailInput = document.getElementById('delegateEmailInput');
    const selectEl = document.getElementById('delegateSelect');
    if (emailInput) {
        emailInput.value = email;
        highlightEmailInput();
    }
    if (selectEl) {
        selectEl.value = email;
    }
}

function highlightEmailInput() {
    const emailInput = document.getElementById('delegateEmailInput');
    if (!emailInput) return;
    emailInput.focus();
    emailInput.classList.add('ring-2', 'ring-blue-500', 'border-blue-500');
    setTimeout(() => {
        emailInput.classList.remove('ring-2', 'ring-blue-500', 'border-blue-500');
    }, 1000);
}

function filterDelegates() {
    const query = (document.getElementById('delegateSearchInput').value || '').toLowerCase().trim();
    const items = document.querySelectorAll('.assistant-item');
    let visibleCount = 0;

    items.forEach(item => {
        const text = (item.getAttribute('data-search') || '').toLowerCase();
        if (!query || text.includes(query)) {
            item.classList.remove('hidden');
            visibleCount++;
        } else {
            item.classList.add('hidden');
        }
    });

    const noMatchesEl = document.getElementById('noSearchMatches');
    if (noMatchesEl) {
        if (visibleCount === 0 && query) {
            noMatchesEl.classList.remove('hidden');
        } else {
            noMatchesEl.classList.add('hidden');
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
