<?php
/**
 * Global Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
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
