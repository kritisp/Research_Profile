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

    // 1. Get faculty user's full name to use as author fallback
    $uStmt = $db->prepare("
        SELECT u.full_name, fp.bio, fp.research_interests 
        FROM faculty_profiles fp 
        JOIN users u ON fp.user_id = u.id 
        WHERE fp.id = ?
    ");
    $uStmt->execute([$profileId]);
    $facultyInfo = $uStmt->fetch(PDO::FETCH_ASSOC);
    $facultyName = $facultyInfo['full_name'] ?? 'Faculty Member';

    // 2. Update ORCID ID and Bio if bio is currently empty
    $orcidBio = trim($data['person']['biography']['content'] ?? '');
    if (!empty($orcidBio) && empty($facultyInfo['bio'])) {
        $db->prepare("UPDATE faculty_profiles SET orcid_id = ?, bio = ? WHERE id = ?")->execute([$cleanId, $orcidBio, $profileId]);
    } else {
        $db->prepare("UPDATE faculty_profiles SET orcid_id = ? WHERE id = ?")->execute([$cleanId, $profileId]);
    }

    // 3. Process works / publications
    $workGroups = $data['activities-summary']['works']['group'] ?? [];
    $importedCount = 0;

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

        $importedCount++;
    }

    record_audit('orcid_synced', 'faculty_profiles', $profileId, "Synced ORCID {$cleanId}: imported {$importedCount} works");

    return [
        'success' => true,
        'count'   => $importedCount,
        'message' => "ORCID sync complete. Imported {$importedCount} new publication(s)."
    ];
}
