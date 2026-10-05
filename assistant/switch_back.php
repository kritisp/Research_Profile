<?php
/**
 * Switch back to Assistant / Delegate Portal
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'super_admin']);

unset($_SESSION['active_faculty_profile_id']);
set_flash('info', 'Exited faculty management mode. Returned to assistant portal.');

if (has_role('super_admin')) {
    redirect('admin/index.php');
} else {
    redirect('assistant/index.php');
}
