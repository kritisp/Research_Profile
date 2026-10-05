<?php
/**
 * Authentication & Role-Based Access Control (RBAC) Library
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

/**
 * Check if a user is authenticated
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get current authenticated user details
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user ID
 */
function user_id(): ?int {
    return $_SESSION['user']['id'] ?? null;
}

/**
 * Get current user role ('super_admin', 'admin', 'faculty')
 */
function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Check if the user has a specific role or one of multiple roles
 */
function has_role($roles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $userRole = user_role();
    if (is_array($roles)) {
        return in_array($userRole, $roles, true);
    }
    return $userRole === $roles;
}

/**
 * Ensure user is logged in, otherwise redirect to login page
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        redirect('login.php');
    }
}

/**
 * Ensure user has required role, otherwise deny access
 */
function require_role($roles): void {
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        die('Error 403: You do not have permission to access this area.');
    }
}

/**
 * Log in a user securely
 */
function login_user(array $user): void {
    // Prevent session fixation
    session_regenerate_id(true);

    // Store essential user info in session
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'email'     => $user['email'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'status'    => $user['status'],
    ];

    record_audit('user_login', 'users', (int)$user['id'], 'User logged in successfully');
}

/**
 * Log out user and destroy session
 */
function logout_user(): void {
    if (is_logged_in()) {
        record_audit('user_logout', 'users', user_id(), 'User logged out');
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Verify whether current user can edit/manage a given faculty profile
 */
function can_manage_faculty_profile(int $facultyProfileId): bool {
    if (!is_logged_in()) {
        return false;
    }
    
    // Super admins can manage any profile
    if (has_role('super_admin')) {
        return true;
    }

    $db = Database::getConnection();

    // Faculty can manage their own profile
    if (has_role('faculty')) {
        $stmt = $db->prepare("SELECT id FROM faculty_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$facultyProfileId, user_id()]);
        return (bool)$stmt->fetch();
    }

    // Admins / Assistants can manage if delegated
    if (has_role('admin')) {
        $stmt = $db->prepare("
            SELECT fp.id 
            FROM faculty_profiles fp
            INNER JOIN faculty_delegates fd ON fd.faculty_user_id = fp.user_id
            WHERE fp.id = ? AND fd.delegate_user_id = ?
        ");
        $stmt->execute([$facultyProfileId, user_id()]);
        return (bool)$stmt->fetch();
    }

    return false;
}
