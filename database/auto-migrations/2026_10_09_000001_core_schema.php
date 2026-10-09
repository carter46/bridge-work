<?php
/**
 * Core schema: admins, settings, categories, jobs, applications, audit log and rate-limit tables.
 * Every statement is CREATE TABLE IF NOT EXISTS, so re-running is safe.
 */

return [
    'id' => '2026_10_09_000001_core_schema',
    'description' => 'Create core tables (admins, settings, categories, jobs, applications, audit and rate limits)',
    'up' => function (PDO $db) {
        $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `admins` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(120) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('owner','recruiter') NOT NULL DEFAULT 'recruiter',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_admin_email` (`email`)
        )" . $suffix);

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(100) NOT NULL,
            `setting_value` MEDIUMTEXT DEFAULT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`setting_key`)
        )" . $suffix);

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(80) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `description` VARCHAR(500) DEFAULT NULL,
            `icon` VARCHAR(60) NOT NULL DEFAULT 'work',
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
            `previous_slugs` VARCHAR(500) DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_category_slug` (`slug`)
        )" . $suffix);

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `jobs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ref_code` VARCHAR(30) NOT NULL,
            `title` VARCHAR(160) NOT NULL,
            `company_name` VARCHAR(160) DEFAULT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            `employment_types` SET('full_time','part_time','contract','temporary') NOT NULL DEFAULT '',
            `work_arrangement` ENUM('remote','hybrid','onsite','unspecified') NOT NULL DEFAULT 'unspecified',
            `applicant_region` ENUM('worldwide','uk_europe','uk_only','unspecified') NOT NULL DEFAULT 'unspecified',
            `location_text` VARCHAR(160) DEFAULT NULL,
            `schedule` ENUM('fixed','flexible','unspecified') NOT NULL DEFAULT 'unspecified',
            `schedule_note` VARCHAR(160) DEFAULT NULL,
            `pay_min` DECIMAL(12,2) DEFAULT NULL,
            `pay_max` DECIMAL(12,2) DEFAULT NULL,
            `pay_currency` CHAR(3) NOT NULL DEFAULT 'USD',
            `pay_period` ENUM('hour','month','year') NOT NULL DEFAULT 'hour',
            `skills` VARCHAR(200) DEFAULT NULL,
            `icon` VARCHAR(60) DEFAULT NULL,
            `badge` VARCHAR(60) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `employer_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `status` ENUM('active','draft','closed') NOT NULL DEFAULT 'draft',
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` INT NOT NULL DEFAULT 0,
            `posted_at` DATETIME DEFAULT NULL,
            `source_text` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_job_ref` (`ref_code`),
            KEY `idx_job_status_category` (`status`, `category_id`),
            KEY `idx_job_featured` (`is_featured`),
            CONSTRAINT `fk_jobs_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
        )" . $suffix);

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `applications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_id` INT UNSIGNED DEFAULT NULL,
            `role_title` VARCHAR(160) DEFAULT NULL,
            `fullname` VARCHAR(160) DEFAULT NULL,
            `email` VARCHAR(190) DEFAULT NULL,
            `phone` VARCHAR(60) DEFAULT NULL,
            `is_adult` TINYINT(1) DEFAULT NULL,
            `residence_country` VARCHAR(100) DEFAULT NULL,
            `residence_country_code` CHAR(2) DEFAULT NULL,
            `right_to_work` ENUM('yes','no','not_sure') DEFAULT NULL,
            `work_type` VARCHAR(30) DEFAULT NULL,
            `cv_stored_name` VARCHAR(80) DEFAULT NULL,
            `cv_original_name` VARCHAR(255) DEFAULT NULL,
            `cv_mime` VARCHAR(100) DEFAULT NULL,
            `cv_size` INT UNSIGNED DEFAULT NULL,
            `consent_at` DATETIME DEFAULT NULL,
            `status` ENUM('new','reviewed','shortlisted','rejected','hired') NOT NULL DEFAULT 'new',
            `admin_notes` TEXT DEFAULT NULL,
            `keep_flag` TINYINT(1) NOT NULL DEFAULT 0,
            `retain_until` DATETIME DEFAULT NULL,
            `anonymised_at` DATETIME DEFAULT NULL,
            `notify_status` ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
            `confirm_status` ENUM('pending','sent','failed','skipped','disabled') NOT NULL DEFAULT 'pending',
            `last_mail_error` VARCHAR(500) DEFAULT NULL,
            `utm_source` VARCHAR(255) DEFAULT NULL,
            `utm_medium` VARCHAR(255) DEFAULT NULL,
            `utm_campaign` VARCHAR(255) DEFAULT NULL,
            `utm_term` VARCHAR(255) DEFAULT NULL,
            `utm_content` VARCHAR(255) DEFAULT NULL,
            `utm_landing` VARCHAR(255) DEFAULT NULL,
            `source_page` VARCHAR(255) DEFAULT NULL,
            `ip_hash` CHAR(64) DEFAULT NULL,
            `form_version` VARCHAR(20) NOT NULL DEFAULT 'v2',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_app_status` (`status`),
            KEY `idx_app_job` (`job_id`),
            KEY `idx_app_created` (`created_at`),
            KEY `idx_app_email` (`email`),
            KEY `idx_app_retain` (`retain_until`),
            CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
        )" . $suffix);

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `admin_id` INT UNSIGNED DEFAULT NULL,
            `action` VARCHAR(60) NOT NULL,
            `entity_type` VARCHAR(40) DEFAULT NULL,
            `entity_id` INT UNSIGNED DEFAULT NULL,
            `details` VARCHAR(1000) DEFAULT NULL,
            `ip_hash` CHAR(64) DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_audit_created` (`created_at`),
            KEY `idx_audit_entity` (`entity_type`, `entity_id`)
        )" . $suffix);

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `rate_limits` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `bucket` VARCHAR(40) NOT NULL,
            `key_hash` CHAR(64) NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_rate_lookup` (`bucket`, `key_hash`, `created_at`)
        )" . $suffix);

        HJ_AutoMigrate::ensureTable($db, "CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip_hash` CHAR(64) NOT NULL,
            `email` VARCHAR(190) DEFAULT NULL,
            `success` TINYINT(1) NOT NULL DEFAULT 0,
            `attempted_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_login_ip` (`ip_hash`, `attempted_at`)
        )" . $suffix);
    },
];
