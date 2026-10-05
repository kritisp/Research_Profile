<?php
/**
 * Switch back to Assistant / Delegate Portal
 * Strictly enforces HTTP POST with CSRF verification.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Method Not Allowed. Exiting delegate mode requires HTTP POST.');
}

require_csrf();

unset($_SESSION['active_faculty_profile_id']);
set_flash('info', 'Exited faculty management mode. Returned to assistant portal.');

if (has_role('super_admin')) {
    redirect('admin/index.php');
} else {
    redirect('assistant/index.php');
}
