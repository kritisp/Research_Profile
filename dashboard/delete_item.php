<?php
/**
 * Generic Delete Item Handler with Permission & CSRF Verification
 * Strictly enforces HTTP POST to comply with OWASP & CodeRabbit security gates.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Method Not Allowed. State-changing operations require HTTP POST.');
}

require_csrf();

$type = $_POST['type'] ?? '';
$id   = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid item identifier.');
    redirect('dashboard/index.php');
}

$db = Database::getConnection();

switch ($type) {
    case 'publication':
        $stmt = $db->prepare("SELECT faculty_profile_id, title FROM publications WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile((int)$item['faculty_profile_id'])) {
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
        if ($item && can_manage_faculty_profile((int)$item['faculty_profile_id'])) {
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
        if ($item && can_manage_faculty_profile((int)$item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM patents WHERE id = ?");
            $del->execute([$id]);
            record_audit('patent_deleted', 'patents', $id, "Deleted patent: {$item['title']}");
            set_flash('success', 'Patent deleted successfully.');
        } else {
            set_flash('danger', 'Unauthorized or patent does not exist.');
        }
        break;

    case 'award':
        $stmt = $db->prepare("SELECT faculty_profile_id, title FROM awards WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile((int)$item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM awards WHERE id = ?");
            $del->execute([$id]);
            record_audit('award_deleted', 'awards', $id, "Deleted award: {$item['title']}");
            set_flash('success', 'Award removed successfully.');
        } else {
            set_flash('danger', 'Unauthorized or award does not exist.');
        }
        break;

    case 'education':
        $stmt = $db->prepare("SELECT faculty_profile_id, degree FROM education WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile((int)$item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM education WHERE id = ?");
            $del->execute([$id]);
            record_audit('education_deleted', 'education', $id, "Deleted qualification: {$item['degree']}");
            set_flash('success', 'Qualification removed successfully.');
        } else {
            set_flash('danger', 'Unauthorized or qualification does not exist.');
        }
        break;

    case 'teaching':
        $stmt = $db->prepare("SELECT faculty_profile_id, course_title FROM teaching WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile((int)$item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM teaching WHERE id = ?");
            $del->execute([$id]);
            record_audit('teaching_deleted', 'teaching', $id, "Deleted course: {$item['course_title']}");
            set_flash('success', 'Course assignment removed successfully.');
        } else {
            set_flash('danger', 'Unauthorized or course does not exist.');
        }
        break;

    case 'experience':
        $stmt = $db->prepare("SELECT faculty_profile_id, position_title, organization FROM academic_experience WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item && can_manage_faculty_profile((int)$item['faculty_profile_id'])) {
            $del = $db->prepare("DELETE FROM academic_experience WHERE id = ?");
            $del->execute([$id]);
            record_audit('experience_deleted', 'academic_experience', $id, "Deleted appointment: {$item['position_title']} at {$item['organization']}");
            set_flash('success', 'Academic appointment removed successfully.');
        } else {
            set_flash('danger', 'Unauthorized or appointment does not exist.');
        }
        break;

    default:
        set_flash('danger', 'Invalid item type specified.');
        break;
}

redirect('dashboard/index.php');
