<?php
/**
 * Switch into a Faculty Profile as a Delegate/Admin
 * Strictly enforces HTTP POST with CSRF verification.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Method Not Allowed. Switching profiles requires HTTP POST.');
}

require_csrf();

$profileId = isset($_POST['profile_id']) ? (int)$_POST['profile_id'] : 0;

if ($profileId > 0 && can_manage_faculty_profile($profileId)) {
    $_SESSION['active_faculty_profile_id'] = $profileId;
    record_audit('delegate_switched', 'faculty_profiles', $profileId, 'Assistant switched into profile');
    set_flash('info', 'Switched to faculty profile management mode.');
    redirect('dashboard/index.php');
} else {
    set_flash('danger', 'Unauthorized or invalid profile selected.');
    redirect('assistant/index.php');
}
