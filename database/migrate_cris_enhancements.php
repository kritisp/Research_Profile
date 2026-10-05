<?php
/**
 * Migration & Enhancement: Academic Research Information System (CRIS) Schema
 * Run via CLI: php database/migrate_cris_enhancements.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden: Migration script can only be run from the command line interface.');
}

require_once __DIR__ . '/../config/database.php';

echo "========================================================\n";
echo "  ITER Research Profile - CRIS Schema Enhancement      \n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    echo "[1/4] Connected to database: " . DB_NAME . "\n";

    // 1. Create academic_experience table
    echo "[2/4] Ensuring academic_experience table exists... ";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `academic_experience` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `faculty_profile_id` INT NOT NULL,
            `position_title` VARCHAR(191) NOT NULL,
            `organization` VARCHAR(255) NOT NULL,
            `department` VARCHAR(191) NULL,
            `start_year` INT NULL,
            `end_year` INT NULL,
            `is_current` TINYINT(1) DEFAULT 0,
            `description` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_exp_profile` (`faculty_profile_id`),
            CONSTRAINT `fk_exp_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "DONE.\n";

    // 2. Add columns to faculty_profiles if they don't exist
    echo "[3/4] Checking and adding extended identifier columns to faculty_profiles... ";
    $existingCols = [];
    $stmt = $db->query("SHOW COLUMNS FROM faculty_profiles");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingCols[] = $row['Field'];
    }

    if (!in_array('semantic_scholar_url', $existingCols)) {
        $db->exec("ALTER TABLE faculty_profiles ADD COLUMN `semantic_scholar_url` VARCHAR(255) NULL AFTER `researchgate_url`");
        echo "(+semantic_scholar_url) ";
    }
    if (!in_array('dblp_url', $existingCols)) {
        $db->exec("ALTER TABLE faculty_profiles ADD COLUMN `dblp_url` VARCHAR(255) NULL AFTER `semantic_scholar_url`");
        echo "(+dblp_url) ";
    }
    if (!in_array('website_url', $existingCols)) {
        $db->exec("ALTER TABLE faculty_profiles ADD COLUMN `website_url` VARCHAR(255) NULL AFTER `dblp_url`");
        echo "(+website_url) ";
    }
    echo "DONE.\n";

    // 3. Add columns to publications if they don't exist
    echo "[4/4] Checking and adding Open Access / PDF columns to publications... ";
    $pubCols = [];
    $stmt = $db->query("SHOW COLUMNS FROM publications");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pubCols[] = $row['Field'];
    }

    if (!in_array('pdf_url', $pubCols)) {
        $db->exec("ALTER TABLE publications ADD COLUMN `pdf_url` VARCHAR(255) NULL AFTER `url`");
        echo "(+pdf_url) ";
    }
    if (!in_array('is_open_access', $pubCols)) {
        $db->exec("ALTER TABLE publications ADD COLUMN `is_open_access` TINYINT(1) DEFAULT 0 AFTER `pdf_url`");
        echo "(+is_open_access) ";
    }
    echo "DONE.\n";

    // Seed realistic appointments for Dr. Debabrata Singh if none exist
    $facStmt = $db->query("SELECT id FROM faculty_profiles WHERE id = 1 LIMIT 1");
    $facId = $facStmt->fetchColumn();
    if ($facId) {
        $chkExp = $db->prepare("SELECT COUNT(*) FROM academic_experience WHERE faculty_profile_id = ?");
        $chkExp->execute([$facId]);
        if ((int)$chkExp->fetchColumn() === 0) {
            $insertExp = $db->prepare("
                INSERT INTO academic_experience (faculty_profile_id, position_title, organization, department, start_year, end_year, is_current, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertExp->execute([$facId, 'Professor & Head of Department', 'Institute of Technical Education & Research (ITER), SOA University', 'Computer Science & Engineering', 2018, null, 1, 'Leading departmental research initiatives, curriculum modernization in Artificial Intelligence, and industry-sponsored labs.']);
            $insertExp->execute([$facId, 'Associate Professor', 'Institute of Technical Education & Research (ITER), SOA University', 'Computer Science & Engineering', 2013, 2018, 0, 'Supervised PG thesis projects in Machine Learning and led the Medical Image Computing Research Group.']);
            $insertExp->execute([$facId, 'Assistant Professor', 'Siksha \'O\' Anusandhan University', 'Computer Science & Engineering', 2008, 2013, 0, 'Taught undergraduate courses in Algorithms, Data Structures, and Computer Vision.']);
            $insertExp->execute([$facId, 'Postdoctoral Research Associate', 'Indian Institute of Technology (IIT), Kharagpur', 'Department of Computer Science & Engineering', 2006, 2008, 0, 'Research on scalable image feature extraction and biomedical signal classification.']);
            echo "Seeded 4 realistic academic career appointments for Faculty #1.\n";
        }
    }

    echo "\n========================================================\n";
    echo "  Migration Completed Successfully!                     \n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
