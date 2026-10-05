<?php
/**
 * Add New Research Publication Form
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
    die('Unauthorized to add publications to this profile.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title       = trim($_POST['title'] ?? '');
    $authors     = trim($_POST['authors'] ?? '');
    $type        = $_POST['publication_type'] ?? 'journal';
    $venue       = trim($_POST['journal_conference_name'] ?? '');
    $year        = (int)($_POST['publication_year'] ?? date('Y'));
    $volume      = trim($_POST['volume'] ?? '');
    $issue       = trim($_POST['issue'] ?? '');
    $pages       = trim($_POST['pages'] ?? '');
    $publisher   = trim($_POST['publisher'] ?? '');
    $doi         = trim($_POST['doi'] ?? '');
    $url         = trim($_POST['url'] ?? '');
    $indexing    = trim($_POST['indexing'] ?? '');
    $citations   = (int)($_POST['citation_count'] ?? 0);
    $abstract    = trim($_POST['abstract'] ?? '');

    if (empty($title) || empty($authors) || empty($venue) || empty($year)) {
        $error = 'Please fill out all mandatory fields: Title, Authors, Venue, and Publication Year.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO publications (
                    faculty_profile_id, title, authors, publication_type, journal_conference_name,
                    publication_year, volume, issue, pages, publisher, doi, url, indexing,
                    citation_count, abstract, created_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $title, $authors, $type, $venue,
                $year, $volume, $issue, $pages, $publisher, $doi, $url, $indexing,
                $citations, $abstract, user_id()
            ]);

            record_audit('publication_added', 'publications', (int)$db->lastInsertId(), "Added publication: {$title}");

            set_flash('success', 'Research publication added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            $error = 'Failed to add publication: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Research Publication';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-slate-200">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-iter-700 hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-slate-900 font-serif-title">Add Scholarly Publication</h1>
        <p class="text-xs text-slate-500 mt-0.5">Record journal articles, conference papers, or book chapters</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_publication.php?profile_id=' . $profileId) ?>" method="POST" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                Publication Title <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>"
                placeholder="e.g. Deep Transfer Learning Architecture for Early Detection of Diabetic Retinopathy"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                Authors List <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="authors" required value="<?= e($_POST['authors'] ?? '') ?>"
                placeholder="e.g. D. Singh, R. K. Patra, S. K. Mishra"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
            <span class="text-[11px] text-slate-400 mt-1 block">List authors separated by commas in standard citation order.</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Publication Type <span class="text-rose-500">*</span>
                </label>
                <select name="publication_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                    <option value="journal">Journal Article</option>
                    <option value="conference">Conference Paper</option>
                    <option value="book_chapter">Book Chapter</option>
                    <option value="book">Authored / Edited Book</option>
                    <option value="patent_publication">Patent Publication</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Journal / Conference Name <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="journal_conference_name" required value="<?= e($_POST['journal_conference_name'] ?? '') ?>"
                    placeholder="e.g. IEEE Transactions on Medical Imaging"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Publication Year <span class="text-rose-500">*</span></label>
                <input type="number" name="publication_year" required min="1970" max="2030" value="<?= e($_POST['publication_year'] ?? date('Y')) ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Volume</label>
                <input type="text" name="volume" value="<?= e($_POST['volume'] ?? '') ?>" placeholder="e.g. 43"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Issue</label>
                <input type="text" name="issue" value="<?= e($_POST['issue'] ?? '') ?>" placeholder="e.g. 4"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pages</label>
                <input type="text" name="pages" value="<?= e($_POST['pages'] ?? '') ?>" placeholder="e.g. 1120-1132"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Publisher</label>
                <input type="text" name="publisher" value="<?= e($_POST['publisher'] ?? '') ?>" placeholder="e.g. IEEE, Elsevier, Springer"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">DOI (Digital Object ID)</label>
                <input type="text" name="doi" value="<?= e($_POST['doi'] ?? '') ?>" placeholder="e.g. 10.1109/TMI.2024.3129841"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Indexing Category</label>
                <input type="text" name="indexing" value="<?= e($_POST['indexing'] ?? '') ?>" placeholder="e.g. SCI Q1 / Scopus"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Direct Paper URL</label>
                <input type="url" name="url" value="<?= e($_POST['url'] ?? '') ?>" placeholder="https://ieeexplore.ieee.org/document/..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Citations Count</label>
                <input type="number" name="citation_count" min="0" value="<?= e($_POST['citation_count'] ?? '0') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Abstract</label>
            <textarea name="abstract" rows="4" placeholder="Brief abstract summarizing findings..."
                class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs leading-relaxed"><?= e($_POST['abstract'] ?? '') ?></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-iter-800 hover:bg-iter-900 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Publication</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
