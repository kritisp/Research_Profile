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


