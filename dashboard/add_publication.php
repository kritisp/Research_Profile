<?php
/**
 * Departmental Scholar — Add Research Publication Form
 * Style: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Authority: design-system/departmental-scholar/MASTER.md
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
    abort(403, 'Unauthorized to add publications to this profile.');
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
    $pdf_url     = trim($_POST['pdf_url'] ?? '');
    $is_oa       = isset($_POST['is_open_access']) ? 1 : 0;
    $indexing    = trim($_POST['indexing'] ?? '');
    $citations   = (int)($_POST['citation_count'] ?? 0);
    $abstract    = trim($_POST['abstract'] ?? '');

    // Sanitize URL schemes
    if (!empty($url) && !preg_match('~^https?://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    if (!empty($pdf_url) && !preg_match('~^https?://~i', $pdf_url)) {
        $pdf_url = 'https://' . ltrim($pdf_url, '/');
    }

    if (empty($title) || empty($authors) || empty($venue) || empty($year)) {
        $error = 'Please fill out all mandatory fields: Title, Authors, Venue, and Publication Year.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO publications (
                    faculty_profile_id, title, authors, publication_type, journal_conference_name,
                    publication_year, volume, issue, pages, publisher, doi, url, pdf_url, is_open_access, indexing,
                    citation_count, abstract, created_by_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $profileId, $title, $authors, $type, $venue,
                $year, $volume, $issue, $pages, $publisher, $doi, $url, $pdf_url, $is_oa, $indexing,
                $citations, $abstract, user_id()
            ]);

            record_audit('publication_added', 'publications', (int)$db->lastInsertId(), "Added publication: {$title}");

            set_flash('success', 'Research publication added successfully.');
            redirect('dashboard/index.php');
        } catch (Exception $e) {
            error_log("Failed to add publication: " . $e->getMessage());
            $error = 'Failed to add publication due to a system error. Please verify your inputs and try again.';
        }
    }
}

$pageTitle = 'Add Research Publication — Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 pb-4 border-b border-scholar-border">
        <a href="<?= url('dashboard/index.php') ?>" class="text-xs text-oxford-blue hover:underline flex items-center gap-1 mb-1 font-semibold">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Dashboard
        </a>
        <h1 class="font-serif text-2xl font-bold text-oxford-navy">Add Scholarly Publication</h1>
        <p class="text-xs text-scholar-muted mt-0.5 font-sans">Record peer-reviewed journal articles, conference proceedings, or book chapters</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-[6px] bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= url('dashboard/add_publication.php?profile_id=' . $profileId) ?>" method="POST" class="academic-card p-6 sm:p-8 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Publication Title <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>"
                placeholder="e.g. Deep Transfer Learning Architecture for Early Detection of Diabetic Retinopathy"
                class="academic-input text-xs sm:text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                Authors List <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="authors" required value="<?= e($_POST['authors'] ?? '') ?>"
                placeholder="e.g. D. Singh, R. K. Patra, S. K. Mishra"
                class="academic-input text-xs sm:text-sm">
            <span class="text-[11px] text-slate-400 mt-1 block font-sans">List authors separated by commas in standard citation order.</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    Publication Type <span class="text-rose-600">*</span>
                </label>
                <select name="publication_type" class="academic-input text-xs font-medium">
                    <option value="journal">Journal Article</option>
                    <option value="conference">Conference Paper</option>
                    <option value="book_chapter">Book Chapter</option>
                    <option value="book">Authored / Edited Book</option>
                    <option value="patent_publication">Patent Publication</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-oxford-navy mb-1.5 font-mono">
                    Journal / Conference Name <span class="text-rose-600">*</span>
                </label>
                <input type="text" name="journal_conference_name" required value="<?= e($_POST['journal_conference_name'] ?? '') ?>"
                    placeholder="e.g. IEEE Transactions on Medical Imaging"
                    class="academic-input text-xs sm:text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Year <span class="text-rose-600">*</span></label>
                <input type="number" name="publication_year" required min="1970" max="2035" value="<?= e($_POST['publication_year'] ?? date('Y')) ?>"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Volume</label>
                <input type="text" name="volume" value="<?= e($_POST['volume'] ?? '') ?>" placeholder="e.g. 43"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Issue</label>
                <input type="text" name="issue" value="<?= e($_POST['issue'] ?? '') ?>" placeholder="e.g. 4"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Pages</label>
                <input type="text" name="pages" value="<?= e($_POST['pages'] ?? '') ?>" placeholder="e.g. 1120-1132"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Publisher</label>
                <input type="text" name="publisher" value="<?= e($_POST['publisher'] ?? '') ?>" placeholder="e.g. IEEE, Elsevier, Springer"
                    class="academic-input text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">DOI</label>
                <input type="text" name="doi" value="<?= e($_POST['doi'] ?? '') ?>" placeholder="e.g. 10.1109/TMI.2024.3129841"
                    class="academic-input text-xs font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Indexing Category</label>
                <input type="text" name="indexing" value="<?= e($_POST['indexing'] ?? '') ?>" placeholder="e.g. SCI Q1 / Scopus"
                    class="academic-input text-xs font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Direct Paper URL</label>
                <input type="url" name="url" value="<?= e($_POST['url'] ?? '') ?>" placeholder="https://ieeexplore.ieee.org/document/..."
                    class="academic-input text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Open Access Full-Text PDF URL</label>
                <input type="url" name="pdf_url" value="<?= e($_POST['pdf_url'] ?? '') ?>" placeholder="https://arxiv.org/pdf/... or repository link"
                    class="academic-input text-xs">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
            <div>
                <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Citation Count</label>
                <input type="number" name="citation_count" min="0" value="<?= e($_POST['citation_count'] ?? '0') ?>"
                    class="academic-input text-xs font-mono">
            </div>
            <div class="pt-3 sm:pt-4">
                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-oxford-navy select-none">
                    <input type="checkbox" name="is_open_access" value="1" <?= !empty($_POST['is_open_access']) ? 'checked' : '' ?>
                        class="rounded border-scholar-border text-oxford-blue focus:ring-oxford-blue w-4 h-4">
                    <span><i class="fa-solid fa-lock-open text-emerald-600 mr-1"></i> Mark as Open Access (freely accessible full-text)</span>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-oxford-navy mb-1 font-mono">Abstract</label>
            <textarea name="abstract" rows="4" placeholder="Brief abstract summarizing findings, methodology, and contributions..."
                class="academic-input text-xs leading-relaxed"><?= e($_POST['abstract'] ?? '') ?></textarea>
        </div>

        <div class="pt-4 border-t border-scholar-border flex items-center justify-end gap-3">
            <a href="<?= url('dashboard/index.php') ?>" class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                Cancel
            </a>
            <button type="submit" class="btn-academic-primary text-xs !py-2.5 !px-6 shadow-xs">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Save Publication</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
