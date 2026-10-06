<?php
/**
 * Departmental Scholar — ORCID Integration Service
 * Uses ORCID Public API v3.0 to fetch public researcher profiles and publications.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Clean and format ORCID string
 */
function clean_orcid_input(?string $input): string {
    if (empty($input)) return '';
    $clean = trim($input);
    // Strip url prefix if user pasted full URL (https://orcid.org/0000-0002-...)
    $clean = preg_replace('~^https?://(www\.)?orcid\.org/~i', '', $clean);
    if (preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/i', $clean)) {
        return strtoupper($clean);
    }
    return '';
}

/**
 * Fetch public ORCID record from ORCID Public API v3.0
 */
function fetch_orcid_record(string $orcidId): ?array {
    $cleanId = clean_orcid_input($orcidId);
    if (empty($cleanId)) return null;

    $url = "https://pub.orcid.org/v3.0/{$cleanId}/record";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: DepartmentalScholar/1.0 (Faculty Research Repository)'
        ],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
    }
    return null;
}

/**
 * Sync / Import ORCID profile metadata and publications into faculty record
 */
function sync_orcid_to_faculty(int $profileId, string $orcidId, int $userId): array {
    $cleanId = clean_orcid_input($orcidId);
    if (empty($cleanId)) {
        return ['success' => false, 'count' => 0, 'message' => 'Invalid ORCID format. Expected 16 characters (e.g. 0000-0002-1825-0097).'];
    }

    $data = fetch_orcid_record($cleanId);
    if (!$data) {
        return ['success' => false, 'count' => 0, 'message' => "Unable to fetch ORCID public record for {$cleanId}. Please check the ID or public visibility."];
    }

    $db = Database::getConnection();

    // 1. Get current faculty profile info
    $uStmt = $db->prepare("
        SELECT u.full_name, fp.bio, fp.research_interests, fp.scopus_id, fp.website_url 
        FROM faculty_profiles fp 
        JOIN users u ON fp.user_id = u.id 
        WHERE fp.id = ?
    ");
    $uStmt->execute([$profileId]);
    $facultyInfo = $uStmt->fetch(PDO::FETCH_ASSOC);
    $facultyName = $facultyInfo['full_name'] ?? 'Faculty Member';

    // 2. Extract profile metadata from ORCID person section
    $orcidBio = trim($data['person']['biography']['content'] ?? '');

    // Extract keywords -> research interests
    $keywords = [];
    foreach ($data['person']['keywords']['keyword'] ?? [] as $kw) {
        if (!empty($kw['content'])) $keywords[] = trim($kw['content']);
    }
    $orcidKeywords = implode(', ', array_slice($keywords, 0, 10));

    // Extract Scopus Author ID
    $orcidScopusId = '';
    foreach ($data['person']['external-identifiers']['external-identifier'] ?? [] as $eid) {
        if (stripos($eid['external-id-type'] ?? '', 'scopus') !== false) {
            $orcidScopusId = trim($eid['external-id-value'] ?? '');
            break;
        }
    }

    // Extract Researcher URL / Website
    $orcidWebsite = '';
    foreach ($data['person']['researcher-urls']['researcher-url'] ?? [] as $rurl) {
        $u = safe_url($rurl['url']['value'] ?? '');
        if ($u !== '#' && !empty($u)) {
            $orcidWebsite = $u;
            break;
        }
    }

    // Update faculty profile: only overwrite empty fields
    $bio = (!empty($orcidBio) && empty($facultyInfo['bio'])) ? $orcidBio : ($facultyInfo['bio'] ?? null);
    $interests = (!empty($orcidKeywords) && empty($facultyInfo['research_interests'])) ? $orcidKeywords : ($facultyInfo['research_interests'] ?? null);
    $scopus = (!empty($orcidScopusId) && empty($facultyInfo['scopus_id'])) ? $orcidScopusId : ($facultyInfo['scopus_id'] ?? null);
    $website = (!empty($orcidWebsite) && empty($facultyInfo['website_url'])) ? $orcidWebsite : ($facultyInfo['website_url'] ?? null);

    $db->prepare("
        UPDATE faculty_profiles 
        SET orcid_id = ?, bio = ?, research_interests = ?, scopus_id = ?, website_url = ? 
        WHERE id = ?
    ")->execute([$cleanId, $bio, $interests, $scopus, $website, $profileId]);

    // 3. Process works / publications
    $workGroups = $data['activities-summary']['works']['group'] ?? [];
    $importedPubCount = 0;

    foreach ($workGroups as $group) {
        $summaries = $group['work-summary'] ?? [];
        if (empty($summaries)) continue;
        $work = $summaries[0];

        $title = trim($work['title']['title']['value'] ?? '');
        if (empty($title)) continue;

        // Extract DOI & URL
        $doi = '';
        $url = safe_url($work['url']['value'] ?? '');
        if ($url === '#') $url = '';

        $externalIds = $work['external-ids']['external-id'] ?? [];
        foreach ($externalIds as $eid) {
            $eidType = strtolower($eid['external-id-type'] ?? '');
            if ($eidType === 'doi') {
                $doi = safe_doi($eid['external-id-value'] ?? '');
                if (!empty($doi) && empty($url)) {
                    $url = "https://doi.org/{$doi}";
                }
                break;
            }
        }

        // Check if publication already exists for this faculty (by DOI or exact title)
        if (!empty($doi)) {
            $chk = $db->prepare("SELECT id FROM publications WHERE faculty_profile_id = ? AND doi = ? LIMIT 1");
            $chk->execute([$profileId, $doi]);
            if ($chk->fetch()) continue;
        } else {
            $chk = $db->prepare("SELECT id FROM publications WHERE faculty_profile_id = ? AND title = ? LIMIT 1");
            $chk->execute([$profileId, $title]);
            if ($chk->fetch()) continue;
        }

        // Venue / Journal name
        $venue = trim($work['journal-title']['value'] ?? $work['title']['subtitle']['value'] ?? 'Scholarly Journal / Proceedings');

        // Publication Year
        $year = (int)($work['publication-date']['year']['value'] ?? date('Y'));
        if ($year < 1970 || $year > 2035) $year = (int)date('Y');

        // Publication Type
        $rawType = strtolower($work['type'] ?? 'journal-article');
        $pubType = match (true) {
            str_contains($rawType, 'conference') => 'conference',
            str_contains($rawType, 'book-chapter') => 'book_chapter',
            str_contains($rawType, 'book') => 'book',
            default => 'journal',
        };

        // Insert publication
        $ins = $db->prepare("
            INSERT INTO publications (
                faculty_profile_id, title, authors, publication_type, journal_conference_name,
                publication_year, doi, url, created_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $profileId,
            $title,
            $facultyName,
            $pubType,
            $venue,
            $year,
            $doi ?: null,
            $url ?: null,
            $userId
        ]);

        $importedPubCount++;
    }

    // 4. Process employments -> academic_experience
    $importedExpCount = 0;
    $employmentGroups = $data['activities-summary']['employments']['affiliation-group'] ?? [];
    foreach ($employmentGroups as $grp) {
        $summaries = $grp['summaries'] ?? [];
        foreach ($summaries as $s) {
            $emp = $s['employment-summary'] ?? null;
            if (!$emp) continue;
            $pos = trim($emp['role-title'] ?? '');
            $org = trim($emp['organization']['name'] ?? '');
            if (empty($pos) || empty($org)) continue;

            $dept = trim($emp['department-name'] ?? '');
            $startYr = (int)($emp['start-date']['year']['value'] ?? 0) ?: null;
            $endYr = (int)($emp['end-date']['year']['value'] ?? 0) ?: null;
            $isCurr = empty($endYr) ? 1 : 0;

            $chk = $db->prepare("SELECT id FROM academic_experience WHERE faculty_profile_id = ? AND position_title = ? AND organization = ? LIMIT 1");
            $chk->execute([$profileId, $pos, $org]);
            if ($chk->fetch()) continue;

            $insExp = $db->prepare("
                INSERT INTO academic_experience (faculty_profile_id, position_title, organization, department, start_year, end_year, is_current)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insExp->execute([$profileId, $pos, $org, $dept ?: null, $startYr, $endYr, $isCurr]);
            $importedExpCount++;
        }
    }

    // 5. Process educations -> education
    $importedEduCount = 0;
    $eduGroups = $data['activities-summary']['educations']['affiliation-group'] ?? [];
    foreach ($eduGroups as $grp) {
        $summaries = $grp['summaries'] ?? [];
        foreach ($summaries as $s) {
            $edu = $s['education-summary'] ?? null;
            if (!$edu) continue;
            $degree = trim($edu['role-title'] ?? 'Degree');
            $inst = trim($edu['organization']['name'] ?? '');
            if (empty($inst)) continue;

            $spec = trim($edu['department-name'] ?? '');
            $yr = (int)($edu['end-date']['year']['value'] ?? $edu['start-date']['year']['value'] ?? 0) ?: null;

            $chk = $db->prepare("SELECT id FROM education WHERE faculty_profile_id = ? AND degree = ? AND institution = ? LIMIT 1");
            $chk->execute([$profileId, $degree, $inst]);
            if ($chk->fetch()) continue;

            $insEdu = $db->prepare("
                INSERT INTO education (faculty_profile_id, degree, institution, year, specialization)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insEdu->execute([$profileId, $degree, $inst, $yr, $spec ?: null]);
            $importedEduCount++;
        }
    }

    $summaryParts = [];
    if ($importedPubCount > 0) $summaryParts[] = "{$importedPubCount} publication(s)";
    if ($importedExpCount > 0) $summaryParts[] = "{$importedExpCount} appointment(s)";
    if ($importedEduCount > 0) $summaryParts[] = "{$importedEduCount} qualification(s)";
    $summaryText = !empty($summaryParts) ? implode(', ', $summaryParts) : 'profile synced (no new entries)';

    record_audit('orcid_synced', 'faculty_profiles', $profileId, "Synced ORCID {$cleanId}: {$summaryText}");

    return [
        'success' => true,
        'count'   => $importedPubCount,
        'message' => "ORCID sync complete: {$summaryText}."
    ];
}
