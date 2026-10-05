<?php
/**
 * Edit Research Publication
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pubId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM publications WHERE id = ?");
$stmt->execute([$pubId]);
$pub = $stmt->fetch();

if (!$pub) {
    set_flash('danger', 'Publication not found.');
    redirect('dashboard/index.php');
}

if (!can_manage_faculty_profile($pub['faculty_profile_id'])) {
    abort(403, 'Unauthorized to edit this publication.');
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

    // Sanitize URL scheme
    if (!empty($url) && !preg_match('~^https?://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    if (empty($title) || empty($authors) || empty($venue) || empty($year)) {
        $error = 'Please fill out all mandatory fields: Title, Authors, Venue, and Publication Year.';
    } else {
        try {
            $updateStmt = $db->prepare("
                UPDATE publications SET
                    title = ?, authors = ?, publication_type = ?, journal_conference_name = ?,
                    publication_year = ?, volume = ?, issue = ?, pages = ?, publisher = ?,
                    doi = ?, url = ?, indexing = ?, citation_count = ?, abstract = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $title, $authors, $type, $venue,
                $year, $volume, $issue, $pages, $publisher,
                $doi, $url, $indexing, $citations, $abstract, $pubId
            ]);

            record_audit('publication_updated', 'publications', $pubId, "Updated publication: {$title}");

            set_flash('success', 'Publication updated successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            error_log("Failed to update publication: " . $e->getMessage());
            $error = 'Failed to update publication due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Edit Publication';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-slate-200">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-iter-700 hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-slate-900 font-serif-title">Edit Publication</h1>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/edit_publication.php?id=' . $pubId) ?>" method="POST" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                Publication Title <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="title" required value="<?= e($pub['title']) ?>"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                Authors List <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="authors" required value="<?= e($pub['authors']) ?>"
                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Publication Type <span class="text-rose-500">*</span>
                </label>
                <select name="publication_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                    <option value="journal" <?= $pub['publication_type'] === 'journal' ? 'selected' : '' ?>>Journal Article</option>
                    <option value="conference" <?= $pub['publication_type'] === 'conference' ? 'selected' : '' ?>>Conference Paper</option>
                    <option value="book_chapter" <?= $pub['publication_type'] === 'book_chapter' ? 'selected' : '' ?>>Book Chapter</option>
                    <option value="book" <?= $pub['publication_type'] === 'book' ? 'selected' : '' ?>>Authored / Edited Book</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    Journal / Conference Name <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="journal_conference_name" required value="<?= e($pub['journal_conference_name']) ?>"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:ring-2 focus:ring-iter-500 focus:bg-white focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Publication Year</label>
                <input type="number" name="publication_year" required min="1970" max="2030" value="<?= (int)$pub['publication_year'] ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Volume</label>
                <input type="text" name="volume" value="<?= e($pub['volume'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Issue</label>
                <input type="text" name="issue" value="<?= e($pub['issue'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pages</label>
                <input type="text" name="pages" value="<?= e($pub['pages'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Publisher</label>
                <input type="text" name="publisher" value="<?= e($pub['publisher'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">DOI</label>
                <input type="text" name="doi" value="<?= e($pub['doi'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Indexing</label>
                <input type="text" name="indexing" value="<?= e($pub['indexing'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Direct Paper URL</label>
                <input type="url" name="url" value="<?= e($pub['url'] ?? '') ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Citations Count</label>
                <input type="number" name="citation_count" min="0" value="<?= (int)$pub['citation_count'] ?>"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Abstract</label>
            <textarea name="abstract" rows="4" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs leading-relaxed"><?= e($pub['abstract'] ?? '') ?></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-iter-800 hover:bg-iter-900 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                <span>Update Publication</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
