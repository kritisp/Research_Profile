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
    // Allow only http://, https://, or root-relative paths
    if (preg_match('~^(https?://|/[^/])~i', $url)) {
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
