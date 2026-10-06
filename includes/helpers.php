<?php
/**
 * Global Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Escape HTML output to prevent XSS
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize URLs to prevent XSS via javascript: or data: schemes
 */
function safe_url(?string $url): string {
    if (empty($url)) {
        return '#';
    }
    $url = trim($url);
    // Allow http://, https://, root-relative (/path), or relative uploads/
    if (preg_match('~^(https?://|/[^/]|uploads/)~i', $url)) {
        if (str_starts_with($url, 'uploads/')) {
            return htmlspecialchars(url($url), ENT_QUOTES, 'UTF-8');
        }
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    return '#';
}

/**
 * Validate and sanitize ORCID identifier
 */
function safe_orcid(?string $orcid): string {
    if (empty($orcid)) {
        return '';
    }
    $orcid = trim($orcid);
    if (preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/i', $orcid)) {
        return htmlspecialchars($orcid, ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * Format faculty display name cleanly, avoiding duplicated titles (e.g. 'Dr. Dr.' or 'Prof. Dr. Dr.')
 */
function clean_faculty_display_name(?string $salutation, ?string $fullName): string {
    $salutation = trim($salutation ?? '');
    $fullName = trim($fullName ?? '');
    if (empty($fullName)) {
        return '';
    }
    // If full_name already starts with Prof., Dr., etc., strip them from full_name
    $strippedName = preg_replace('/^(Prof\.\s*|Dr\.\s*|Mr\.\s*|Ms\.\s*|Mrs\.\s*)+/i', '', $fullName);
    $strippedName = trim($strippedName);
    if (!empty($salutation)) {
        return $salutation . ' ' . $strippedName;
    }
    return $fullName;
}

/**
 * Generate clean researcher URL using slug with ID fallback
 */
function researcher_url(array $faculty): string {
    if (!empty($faculty['slug'])) {
        return url('researchers/' . urlencode($faculty['slug']));
    }
    return url('profile.php?id=' . (int)($faculty['id'] ?? 0));
}

/**
 * Standard HTTP Abort with safe branded UI
 */
function abort(int $code = 403, string $message = 'Access Denied'): void {
    http_response_code($code);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "[{$code}] {$message}\n");
        exit(1);
    }
    $title = match ($code) {
        400 => '400 Bad Request',
        403 => '403 Forbidden',
        404 => '404 Not Found',
        500 => '500 Internal Error',
        default => "{$code} Error",
    };
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center text-2xl">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-900"><?= htmlspecialchars($title) ?></h1>
            <p class="text-xs text-slate-600 leading-relaxed"><?= htmlspecialchars($message) ?></p>
            <div class="pt-2 flex items-center justify-center gap-3">
                <a href="<?= url() ?>" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition">
                    Return Home
                </a>
                <a href="javascript:history.back()" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-semibold hover:bg-slate-200 transition">
                    Go Back
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Handle secure file uploads for profile photos and CV documents
 * Enforces MIME checking, size limits, and non-executable storage.
 */
function handle_file_upload(array $file, string $type = 'photo'): string {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid file upload parameters.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed with error code ' . $file['error']);
    }

    $uploadDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Ensure uploads directory disables PHP script execution via .htaccess
    $htaccess = $uploadDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        $htaccessContent = "# Disable execution of scripts\n<FilesMatch \"\.(php|phtml|phar|pl|py|cgi|sh|exe)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\nOptions -ExecCGI\nphp_flag engine off\n";
        file_put_contents($htaccess, $htaccessContent);
    }

    // Subdirectory based on file type
    $subDir = ($type === 'cv') ? 'cvs' : 'avatars';
    $targetDir = $uploadDir . '/' . $subDir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Validate size
    $maxSize = ($type === 'cv') ? (8 * 1024 * 1024) : (3 * 1024 * 1024); // 8MB for CV, 3MB for photo
    if ($file['size'] > $maxSize) {
        throw new RuntimeException('File exceeds maximum allowed size (' . ($maxSize / 1024 / 1024) . 'MB).');
    }

    // Validate MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedPhotoMimes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp'
    ];
    $allowedCvMimes = [
        'pdf' => 'application/pdf'
    ];

    $allowedMap = ($type === 'cv') ? $allowedCvMimes : $allowedPhotoMimes;
    $ext = array_search($mime, $allowedMap, true);
    if ($ext === false) {
        throw new RuntimeException('Invalid file format. Allowed types: ' . implode(', ', array_keys($allowedMap)));
    }

    // Generate cryptographically random filename
    $filename = sprintf('%s_%s.%s', $type, bin2hex(random_bytes(16)), $ext);
    $destination = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return 'uploads/' . $subDir . '/' . $filename;
}

/**
 * Generate full URL based on application BASE_URL
 */
function url(string $path = ''): string {
    $trimmed = ltrim($path, '/');
    return rtrim(BASE_URL, '/') . ($trimmed ? '/' . $trimmed : '');
}

/**
 * Redirect to a given application path
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

/**
 * Set a flash message in the session
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_messages'][] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message,
    ];
}

/**
 * Retrieve and clear flash messages
 */
function get_flashes(): array {
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

/**
 * Convert string into clean URL-friendly slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text ?: 'n-a');
}

/**
 * Record an action to the audit logs
 */
function record_audit(string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void {
    try {
        $db = Database::getConnection();
        $userId = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $targetType, $targetId, $details, $ip]);
    } catch (Exception $e) {
        // Fail silently so audit failures do not break the main transaction
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Validate and clean a DOI string
 */
function safe_doi(?string $doi): string {
    if (empty($doi)) {
        return '';
    }
    $doi = trim($doi);
    // Remove leading https://doi.org/ or http://dx.doi.org/ if present
    $doi = preg_replace('~^https?://(dx\.)?doi\.org/~i', '', $doi);
    if (preg_match('~^10\.\d{4,9}/[-._;()/:A-Za-z0-9]+$~', $doi)) {
        return htmlspecialchars($doi, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($doi, ENT_QUOTES, 'UTF-8');
}

/**
 * Convert DOI to full official resolver URL
 */
function doi_url(?string $doi): string {
    $clean = safe_doi($doi);
    return $clean ? 'https://doi.org/' . $clean : '';
}

/**
 * Format INR Lakhs nicely into human-readable currency
 */
function format_currency_lakhs(float $amountLakhs): string {
    if ($amountLakhs >= 100) {
        $crores = $amountLakhs / 100;
        return '₹' . number_format($crores, 2) . ' Cr';
    }
    return '₹' . number_format($amountLakhs, 2) . ' Lakhs';
}

/**
 * Safely resolve faculty photo URL, verifying local existence on disk
 * Returns null if empty or if local upload file does not exist.
 */
function faculty_photo_url(?string $photoPath): ?string {
    if (empty($photoPath)) {
        return null;
    }
    $trimmed = trim($photoPath);
    if (preg_match('~^https?://~i', $trimmed)) {
        return safe_url($trimmed);
    }
    $cleanPath = ltrim($trimmed, '/');
    $localFile = dirname(__DIR__) . '/' . $cleanPath;
    if (file_exists($localFile) && !is_dir($localFile)) {
        return url($cleanPath);
    }
    return null;
}

/**
 * Clean & normalize author/faculty name for indexing and matching
 */
function normalize_name_tokens(string $name): string {
    $clean = strtolower($name);
    $clean = preg_replace('/[.,\-\'\"]+/', ' ', $clean);
    $clean = preg_replace('/^(dr|prof|er|mr|ms|mrs)\s+/i', '', trim($clean));
    return preg_replace('/\s+/', ' ', trim($clean));
}

/**
 * Generate permutation lookup keys for a faculty profile (initials, variations, full name)
 */
function generate_faculty_match_keys(array $faculty): array {
    $keys = [];
    $rawName = $faculty['full_name'] ?? '';
    $normalized = normalize_name_tokens($rawName);
    if (empty($normalized)) return [];
    
    $keys[$normalized] = true;

    if (!empty($faculty['salutation'])) {
        $keys[normalize_name_tokens($faculty['salutation'] . ' ' . $rawName)] = true;
    }

    $parts = explode(' ', $normalized);
    $count = count($parts);

    if ($count === 1) {
        $keys[$parts[0]] = true;
    } elseif ($count === 2) {
        $first = $parts[0];
        $last = $parts[1];
        if (!empty($first) && !empty($last)) {
            $keys[$first[0] . ' ' . $last] = true;
            $keys[$last . ' ' . $first[0]] = true;
        }
    } elseif ($count >= 3) {
        $first = $parts[0];
        $last = $parts[$count - 1];
        $middles = array_slice($parts, 1, $count - 2);
        
        $initials = [$first[0]];
        foreach ($middles as $m) {
            if (!empty($m)) $initials[] = $m[0];
        }
        $keys[implode(' ', $initials) . ' ' . $last] = true;
        $keys[implode('', $initials) . ' ' . $last] = true;
        $keys[$first[0] . ' ' . $last] = true;
        $keys[$first . ' ' . $last] = true;
        $keys[$last . ' ' . implode(' ', $initials)] = true;
    }

    return array_keys($keys);
}

/**
 * Cached lookup index of active faculty profiles mapped to match keys
 */
function get_faculty_author_index(): array {
    static $index = null;
    if ($index !== null) {
        return $index;
    }
    $index = [];
    try {
        $db = Database::getConnection();
        $faculties = $db->query("
            SELECT fp.id, fp.slug, u.full_name, fp.salutation 
            FROM faculty_profiles fp 
            JOIN users u ON fp.user_id = u.id 
            WHERE u.status = 'active'
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($faculties as $f) {
            $keys = generate_faculty_match_keys($f);
            foreach ($keys as $k) {
                if (!isset($index[$k])) {
                    $index[$k] = $f;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Failed to build faculty author index: " . $e->getMessage());
    }
    return $index;
}

/**
 * Render publication author list with bold, clickable names.
 * Local faculty members link directly to their profile; external collaborators trigger a sleek "Profile not available" popup.
 */
function render_interactive_authors(?string $authorsString): string {
    if (empty($authorsString)) {
        return '';
    }
    $index = get_faculty_author_index();

    // Support both comma-separated and semicolon-separated author strings
    $delimiter = (strpos($authorsString, ';') !== false) ? ';' : ',';
    $rawAuthors = explode($delimiter, $authorsString);
    $rendered = [];

    foreach ($rawAuthors as $raw) {
        $author = trim($raw);
        if ($author === '') continue;

        $normalized = normalize_name_tokens($author);
        $matched = $index[$normalized] ?? null;

        $safeName = htmlspecialchars($author, ENT_QUOTES, 'UTF-8');
        $jsEscaped = htmlspecialchars(addslashes($author), ENT_QUOTES, 'UTF-8');

        if ($matched) {
            $url = htmlspecialchars(researcher_url($matched), ENT_QUOTES, 'UTF-8');
            $fullName = htmlspecialchars($matched['full_name'], ENT_QUOTES, 'UTF-8');
            $rendered[] = '<a href="' . $url . '" class="font-bold text-oxford-navy hover:text-[#0969da] hover:underline decoration-1 underline-offset-2 transition-colors cursor-pointer" title="View ' . $fullName . '\'s Faculty Profile">' . $safeName . '</a>';
        } else {
            $rendered[] = '<button type="button" onclick="showProfileNotFoundModal(\'' . $jsEscaped . '\')" class="font-bold text-slate-800 hover:text-oxford-navy cursor-pointer hover:underline decoration-dotted underline-offset-2 transition-colors inline-block bg-transparent border-0 p-0 text-inherit font-inherit text-left" title="Click to view co-author profile">' . $safeName . '</button>';
        }
    }

    return implode(', ', $rendered);
}

/**
 * Safely resolve primary URL for a publication (DOI resolver, Direct paper URL, PDF, or Google Scholar search fallback)
 */
function publication_target_url(array $pub): array {
    if (!empty($pub['doi'])) {
        $doi = safe_doi($pub['doi']);
        if ($doi) {
            return [
                'url' => 'https://doi.org/' . $doi,
                'type' => 'doi',
                'label' => 'Official DOI Resolver'
            ];
        }
    }
    if (!empty($pub['url'])) {
        $u = safe_url($pub['url']);
        if ($u && $u !== '#') {
            return [
                'url' => $u,
                'type' => 'direct',
                'label' => 'Direct Paper URL'
            ];
        }
    }
    if (!empty($pub['pdf_url'])) {
        $pdf = safe_url($pub['pdf_url']);
        if ($pdf && $pdf !== '#') {
            return [
                'url' => $pdf,
                'type' => 'pdf',
                'label' => 'Full-Text PDF'
            ];
        }
    }
    $title = trim($pub['title'] ?? '');
    return [
        'url' => 'https://scholar.google.com/scholar?q=' . urlencode('"' . $title . '"'),
        'type' => 'scholar',
        'label' => 'Find on Google Scholar'
    ];
}

/**
 * Target URL for patents (Google Patents lookup)
 */
function patent_target_url(array $patent): array {
    $num = trim($patent['patent_number'] ?? '');
    $title = trim($patent['title'] ?? '');
    $query = $num ?: $title;
    return [
        'url' => 'https://patents.google.com/?q=' . urlencode($query),
        'label' => 'Google Patents'
    ];
}

/**
 * Standard genuine academic indexing categories and groupings
 */
function get_academic_indexing_categories(): array {
    return [
        'High Impact & Global Core' => [
            'SCI / SCIE (Clarivate Web of Science)',
            'Scopus (Elsevier)',
            'Web of Science (WoS Core Collection)',
            'SCI Q1 / Scopus',
            'SCI Q2 / Scopus',
            'SCIE / Scopus',
        ],
        'Scopus Quartiles (Elsevier / Scimago)' => [
            'Scopus Q1',
            'Scopus Q2',
            'Scopus Q3',
            'Scopus Q4',
        ],
        'Clarivate JCR Quartiles & Emerging' => [
            'SCI Q1',
            'SCI Q2',
            'SCI Q3',
            'SCI Q4',
            'ESCI (Emerging Sources Citation Index)',
        ],
        'Computer Science, Engineering & Medical' => [
            'IEEE Xplore Digital Library',
            'ACM Digital Library',
            'DBLP Computer Science Bibliography',
            'PubMed / MEDLINE (NLM / NIH)',
        ],
        'National UGC & Accreditations (India / NIRF)' => [
            'UGC CARE Group I',
            'UGC CARE Group II',
        ],
        'Open Access & Management Disciplines' => [
            'DOAJ (Directory of Open Access Journals)',
            'ABDC Journal Quality List',
            'Peer-Reviewed / Refereed Journal',
        ],
    ];
}

/**
 * Render genuine academic indexing badges
 */
function render_indexing_badges(?string $indexingString): string {
    if (empty($indexingString)) {
        return '';
    }
    // Check if contains multiple separated by comma or slash
    $parts = preg_split('~[,/]~', $indexingString);
    if (count($parts) > 1 && !preg_match('~Q[1-4]~i', $indexingString)) {
        $tokens = array_map('trim', $parts);
    } else {
        $tokens = [trim($indexingString)];
    }

    $html = [];
    foreach ($tokens as $token) {
        if ($token === '') continue;
        $lower = strtolower($token);
        
        $badgeClass = 'academic-tag font-semibold';
        $icon = 'fa-solid fa-bookmark';

        if (str_contains($lower, 'scopus')) {
            $badgeClass = 'academic-tag academic-tag-gold font-semibold';
            $icon = 'fa-solid fa-certificate';
        } elseif (str_contains($lower, 'sci') || str_contains($lower, 'wos') || str_contains($lower, 'web of science') || str_contains($lower, 'clarivate')) {
            $badgeClass = 'academic-tag academic-tag-navy font-semibold';
            $icon = 'fa-solid fa-award';
        } elseif (str_contains($lower, 'ugc')) {
            $badgeClass = 'academic-tag academic-tag-green font-semibold';
            $icon = 'fa-solid fa-shield-check';
        } elseif (str_contains($lower, 'ieee') || str_contains($lower, 'acm') || str_contains($lower, 'dblp')) {
            $badgeClass = 'academic-tag font-semibold bg-indigo-50 text-indigo-900 border-indigo-200';
            $icon = 'fa-solid fa-network-wired';
        } elseif (str_contains($lower, 'pubmed') || str_contains($lower, 'medline')) {
            $badgeClass = 'academic-tag font-semibold bg-rose-50 text-rose-900 border-rose-200';
            $icon = 'fa-solid fa-heart-pulse';
        } elseif (str_contains($lower, 'doaj') || str_contains($lower, 'abdc')) {
            $badgeClass = 'academic-tag font-semibold bg-teal-50 text-teal-900 border-teal-200';
            $icon = 'fa-solid fa-globe';
        }

        $html[] = '<span class="' . $badgeClass . '"><i class="' . $icon . ' text-[9px] mr-1 opacity-80"></i>' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return implode(' ', $html);
}



