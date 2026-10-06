<?php
/**
 * Departmental Scholar — Sync / Fetch Publications from ORCID
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orcid.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard/index.php');
}

require_csrf();

$profileId = (int)($_POST['profile_id'] ?? 0);
$orcidId   = trim($_POST['orcid_id'] ?? '');

$db = Database::getConnection();

// Default to logged in user's profile if not given
if ($profileId <= 0) {
    $pStmt = $db->prepare("SELECT id FROM faculty_profiles WHERE user_id = ?");
    $pStmt->execute([user_id()]);
    $profileId = (int)$pStmt->fetchColumn();
}

if (!can_manage_faculty_profile($profileId)) {
    abort(403, 'Unauthorized to manage this profile.');
}

// If orcid_id was not submitted in form, check existing profile record
if (empty($orcidId)) {
    $stmt = $db->prepare("SELECT orcid_id FROM faculty_profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    $orcidId = (string)$stmt->fetchColumn();
}

if (empty($orcidId)) {
    set_flash('error', 'Please provide an ORCID iD to import publications.');
    redirect('dashboard/index.php');
}

$res = sync_orcid_to_faculty($profileId, $orcidId, user_id());

if ($res['success']) {
    set_flash('success', $res['message']);
} else {
    set_flash('error', $res['message']);
}

$redirectUrl = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('dashboard/index.php');
header("Location: {$redirectUrl}");
exit;
