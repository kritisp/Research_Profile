<?php
/**
 * Generic Delete Item Handler with Permission & CSRF Verification
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$type  = $_GET['type'] ?? '';
$id    = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!verify_csrf_token($token)) {
    http_response_code(403);
    die('Invalid CSRF token.');
}

$db = Database::getConnection();

switch ($type) {
    case 'publication':
        $stmt = $db->prepare("SELECT faculty_profile_id, title FROM publications WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile($item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM publications WHERE id = ?");
            $del->execute([$id]);
            record_audit('publication_deleted', 'publications', $id, "Deleted publication: {$item['title']}");
            set_flash('success', 'Publication deleted successfully.');
        } else {
            set_flash('danger', 'Unauthorized or publication does not exist.');
        }
        break;

    case 'project':
        $stmt = $db->prepare("SELECT faculty_profile_id, title FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile($item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM projects WHERE id = ?");
            $del->execute([$id]);
            record_audit('project_deleted', 'projects', $id, "Deleted project: {$item['title']}");
            set_flash('success', 'Research project deleted successfully.');
        } else {
            set_flash('danger', 'Unauthorized or project does not exist.');
        }
        break;

    case 'patent':
        $stmt = $db->prepare("SELECT faculty_profile_id, title FROM patents WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile($item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM patents WHERE id = ?");
            $del->execute([$id]);
            record_audit('patent_deleted', 'patents', $id, "Deleted patent: {$item['title']}");
            set_flash('success', 'Patent deleted successfully.');
        } else {
            set_flash('danger', 'Unauthorized or patent does not exist.');
        }
        break;

    default:
        set_flash('danger', 'Invalid item type specified.');
        break;
}

redirect('dashboard/index.php');
