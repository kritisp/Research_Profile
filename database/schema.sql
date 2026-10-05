-- =====================================================================
-- ITER Departmental Research Profile Portal Database Schema
-- Charset: utf8mb4, Engine: InnoDB
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Departments Table (ITER Academic Departments)
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(191) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table (Core Auth for Super Admin, Admin/Delegates, and Faculty)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(191) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'faculty') NOT NULL DEFAULT 'faculty',
    `status` ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Faculty Profiles (Detailed Academic Metadata)
DROP TABLE IF EXISTS `faculty_profiles`;
CREATE TABLE `faculty_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `department_id` INT NULL,
    `institution` VARCHAR(191) DEFAULT 'ITER, SOA University',
    `salutation` VARCHAR(20) DEFAULT 'Dr.',
    `designation` VARCHAR(100) DEFAULT 'Assistant Professor',
    `cabin` VARCHAR(100) NULL,
    `phone` VARCHAR(50) NULL,
    `photo_url` VARCHAR(255) NULL,
    `bio` TEXT NULL,
    `research_interests` TEXT NULL,
    `google_scholar_url` VARCHAR(255) NULL,
    `orcid_id` VARCHAR(50) NULL,
    `scopus_id` VARCHAR(50) NULL,
    `researchgate_url` VARCHAR(255) NULL,
    `slug` VARCHAR(191) NULL UNIQUE,
    `wos_id` VARCHAR(50) NULL,
    `total_citations` INT DEFAULT 0,
    `h_index` INT DEFAULT 0,
    `i10_index` INT DEFAULT 0,
    `phd_supervised` INT DEFAULT 0,
    `memberships` TEXT NULL,
    `editorial_roles` TEXT NULL,
    `metrics_source` VARCHAR(100) DEFAULT 'Faculty Self-Reported (Google Scholar / Scopus)',
    `cv_url` VARCHAR(255) NULL,
    `is_verified` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_dept` (`department_id`),
    INDEX `idx_institution` (`institution`),
    INDEX `idx_citations` (`total_citations`),
    CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_profile_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Faculty Delegates (Coordinators / Assistants permitted to update faculty profiles)
DROP TABLE IF EXISTS `faculty_delegates`;
CREATE TABLE `faculty_delegates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_user_id` INT NOT NULL,
    `delegate_user_id` INT NOT NULL,
    `granted_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_faculty_delegate` (`faculty_user_id`, `delegate_user_id`),
    CONSTRAINT `fk_del_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_delegate` FOREIGN KEY (`delegate_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Publications Table
DROP TABLE IF EXISTS `publications`;
CREATE TABLE `publications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_profile_id` INT NOT NULL,
    `title` TEXT NOT NULL,
    `authors` TEXT NOT NULL,
    `publication_type` ENUM('journal', 'conference', 'book_chapter', 'book', 'patent_publication') DEFAULT 'journal',
    `journal_conference_name` VARCHAR(255) NOT NULL,
    `publication_year` INT NOT NULL,
    `volume` VARCHAR(50) NULL,
    `issue` VARCHAR(50) NULL,
    `pages` VARCHAR(50) NULL,
    `publisher` VARCHAR(150) NULL,
    `doi` VARCHAR(150) NULL,
    `url` VARCHAR(255) NULL,
    `abstract` TEXT NULL,
    `indexing` VARCHAR(100) NULL,
    `citation_count` INT DEFAULT 0,
    `created_by_user_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pub_profile` (`faculty_profile_id`),
    INDEX `idx_pub_year` (`publication_year`),
    INDEX `idx_pub_type` (`publication_type`),
    CONSTRAINT `fk_pub_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pub_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Sponsored Research Projects & Grants Table
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_profile_id` INT NOT NULL,
    `title` TEXT NOT NULL,
    `funding_agency` VARCHAR(191) NOT NULL,
    `project_code` VARCHAR(100) NULL,
    `role` ENUM('pi', 'copi') DEFAULT 'pi',
    `amount_lakhs` DECIMAL(10,2) DEFAULT 0.00,
    `start_year` INT NULL,
    `end_year` INT NULL,
    `status` ENUM('ongoing', 'completed') DEFAULT 'ongoing',
    `created_by_user_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_proj_profile` (`faculty_profile_id`),
    INDEX `idx_proj_status` (`status`),
    CONSTRAINT `fk_proj_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_proj_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Patents Table
DROP TABLE IF EXISTS `patents`;
CREATE TABLE `patents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_profile_id` INT NOT NULL,
    `title` TEXT NOT NULL,
    `patent_number` VARCHAR(100) NULL,
    `country` VARCHAR(100) DEFAULT 'India',
    `status` ENUM('filed', 'published', 'granted') DEFAULT 'granted',
    `filing_date` DATE NULL,
    `grant_date` DATE NULL,
    `created_by_user_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pat_profile` (`faculty_profile_id`),
    CONSTRAINT `fk_pat_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pat_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Awards & Honors Table
DROP TABLE IF EXISTS `awards`;
CREATE TABLE `awards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_profile_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `awarding_body` VARCHAR(255) NOT NULL,
    `year` INT NOT NULL,
    `description` TEXT NULL,
    `created_by_user_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_awd_profile` (`faculty_profile_id`),
    CONSTRAINT `fk_awd_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_awd_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Education & Qualifications Table
DROP TABLE IF EXISTS `education`;
CREATE TABLE `education` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_profile_id` INT NOT NULL,
    `degree` VARCHAR(100) NOT NULL,
    `institution` VARCHAR(255) NOT NULL,
    `year` INT NULL,
    `specialization` VARCHAR(191) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_edu_profile` (`faculty_profile_id`),
    CONSTRAINT `fk_edu_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Teaching & Courses Table
DROP TABLE IF EXISTS `teaching`;
CREATE TABLE `teaching` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_profile_id` INT NOT NULL,
    `course_title` VARCHAR(191) NOT NULL,
    `course_code` VARCHAR(50) NULL,
    `level` ENUM('ug', 'pg', 'phd') DEFAULT 'ug',
    `academic_year` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_teach_profile` (`faculty_profile_id`),
    CONSTRAINT `fk_teach_profile` FOREIGN KEY (`faculty_profile_id`) REFERENCES `faculty_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Audit Logs Table (Full accountability for who modified what)
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(100) NULL,
    `target_id` INT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_action` (`action`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
