<?php
/**
 * Departmental Scholar — Edit Research Publication
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pubId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM publications WHERE id = ?");
$stmt->execute([$pubId]);
$pub = $stmt->fetch(PDO::FETCH_ASSOC);

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

$pageTitle = 'Edit Publication — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-blue hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Edit Publication</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Modify bibliographic metadata and citation counts</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/edit_publication.php?id=' . $pubId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Publication Title <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="title" required value="<?= e($pub['title']) ?>"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Authors List <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="authors" required value="<?= e($pub['authors']) ?>"
                class="academic-input text-xs sm:text-sm">
            <span class="text-[11px] text-slate-400 mt-1 block font-sans">List authors separated by commas in standard citation order.</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    Publication Type <span class="text-rose-600">*</span>
                </label>
                <select name="publication_type" class="academic-input text-xs font-medium">
                    <option value="journal" <?= $pub['publication_type'] === 'journal' ? 'selected' : '' ?>>Journal Article</option>
                    <option value="conference" <?= $pub['publication_type'] === 'conference' ? 'selected' : '' ?>>Conference Paper</option>
                    <option value="book_chapter" <?= $pub['publication_type'] === 'book_chapter' ? 'selected' : '' ?>>Book Chapter</option>
                    <option value="book" <?= $pub['publication_type'] === 'book' ? 'selected' : '' ?>>Authored / Edited Book</option>
                    <option value="patent_publication" <?= $pub['publication_type'] === 'patent_publication' ? 'selected' : '' ?>>Patent Publication</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    Journal / Conference Name <span class="text-rose-600">*</span>
                </label>
                <input type="text" name="journal_conference_name" required value="<?= e($pub['journal_conference_name']) ?>"
                    class="academic-input text-xs sm:text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Year <span class="text-rose-600">*</span></label>
                <input type="number" name="publication_year" required min="1970" max="2035" value="<?= e($pub['publication_year']) ?>"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Volume</label>
                <input type="text" name="volume" value="<?= e($pub['volume'] ?? '') ?>"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Issue</label>
                <input type="text" name="issue" value="<?= e($pub['issue'] ?? '') ?>"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Pages</label>
                <input type="text" name="pages" value="<?= e($pub['pages'] ?? '') ?>"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Publisher</label>
                <input type="text" name="publisher" value="<?= e($pub['publisher'] ?? '') ?>"
                    class="academic-input text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">DOI</label>
                <input type="text" name="doi" value="<?= e($pub['doi'] ?? '') ?>"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Indexing Category</label>
                <input type="text" name="indexing" value="<?= e($pub['indexing'] ?? '') ?>"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Direct Paper URL</label>
                <input type="url" name="url" value="<?= e($pub['url'] ?? '') ?>"
                    class="academic-input text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Citation Count</label>
                <input type="number" name="citation_count" min="0" value="<?= e($pub['citation_count'] ?? '0') ?>"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Abstract</label>
            <textarea name="abstract" rows="4"
                class="academic-input text-xs leading-relaxed"><?= e($pub['abstract'] ?? '') ?></textarea>
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                <span>Save Changes</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
