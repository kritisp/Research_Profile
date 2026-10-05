<?php
/**
 * Switch into a Faculty Profile as a Delegate/Admin
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'super_admin']);

$token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    abort(403, 'Invalid or expired CSRF security token.');
}

$profileId = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : 0;

if ($profileId > 0 && can_manage_faculty_profile($profileId)) {
    $_SESSION['active_faculty_profile_id'] = $profileId;
    record_audit('delegate_switched', 'faculty_profiles', $profileId, 'Assistant switched into profile');
    set_flash('info', 'Switched to faculty profile management mode.');
    redirect('dashboard/index.php');
} else {
    set_flash('danger', 'Unauthorized or invalid profile selected.');
    redirect('assistant/index.php');
}
