-- =====================================================================
-- Hubjob Platform — database schema + starting data
--
-- HOW TO USE (phpMyAdmin):
--   1. Create an empty database (utf8mb4_unicode_ci) and a user with ALL PRIVILEGES on it.
--   2. Select that database on the left, open the Import tab, choose this file, click Import.
--   3. Put the database name, user and password in config/config.php.
--   4. Open https://YOUR-SITE/admin/setup.php to create the first admin login.
--
-- Creates 9 tables, the default site settings, the 8 job categories and the 30 job listings.
-- Safe to import more than once: tables are only created if missing and rows are only added
-- if missing (nothing you edited in the admin is overwritten).
--
-- Generated from database/auto-migrations/. Later schema changes are applied automatically
-- when an admin opens the admin area, so you never need to re-import this file.
-- Requires MySQL 5.7+ or MariaDB 10.3+.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Table `admins`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('owner','recruiter') NOT NULL DEFAULT 'recruiter',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `settings`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` mediumtext DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `categories`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `icon` varchar(60) NOT NULL DEFAULT 'work',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `previous_slugs` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_category_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `jobs`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ref_code` varchar(30) NOT NULL,
  `title` varchar(160) NOT NULL,
  `company_name` varchar(160) DEFAULT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `employment_types` set('full_time','part_time','contract','temporary') NOT NULL DEFAULT '',
  `work_arrangement` enum('remote','hybrid','onsite','unspecified') NOT NULL DEFAULT 'unspecified',
  `applicant_region` enum('worldwide','uk_europe','uk_only','unspecified') NOT NULL DEFAULT 'unspecified',
  `location_text` varchar(160) DEFAULT NULL,
  `schedule` enum('fixed','flexible','unspecified') NOT NULL DEFAULT 'unspecified',
  `schedule_note` varchar(160) DEFAULT NULL,
  `pay_min` decimal(12,2) DEFAULT NULL,
  `pay_max` decimal(12,2) DEFAULT NULL,
  `pay_currency` char(3) NOT NULL DEFAULT 'USD',
  `pay_period` enum('hour','month','year') NOT NULL DEFAULT 'hour',
  `skills` varchar(200) DEFAULT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `badge` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `employer_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','draft','closed') NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `posted_at` datetime DEFAULT NULL,
  `source_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_job_ref` (`ref_code`),
  KEY `idx_job_status_category` (`status`,`category_id`),
  KEY `idx_job_featured` (`is_featured`),
  KEY `fk_jobs_category` (`category_id`),
  CONSTRAINT `fk_jobs_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `applications`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` int(10) unsigned DEFAULT NULL,
  `role_title` varchar(160) DEFAULT NULL,
  `fullname` varchar(160) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `is_adult` tinyint(1) DEFAULT NULL,
  `residence_country` varchar(100) DEFAULT NULL,
  `residence_country_code` char(2) DEFAULT NULL,
  `right_to_work` enum('yes','no','not_sure') DEFAULT NULL,
  `work_type` varchar(30) DEFAULT NULL,
  `cv_stored_name` varchar(80) DEFAULT NULL,
  `cv_original_name` varchar(255) DEFAULT NULL,
  `cv_mime` varchar(100) DEFAULT NULL,
  `cv_size` int(10) unsigned DEFAULT NULL,
  `consent_at` datetime DEFAULT NULL,
  `status` enum('new','reviewed','shortlisted','rejected','hired') NOT NULL DEFAULT 'new',
  `admin_notes` text DEFAULT NULL,
  `keep_flag` tinyint(1) NOT NULL DEFAULT 0,
  `retain_until` datetime DEFAULT NULL,
  `anonymised_at` datetime DEFAULT NULL,
  `notify_status` enum('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `confirm_status` enum('pending','sent','failed','skipped','disabled') NOT NULL DEFAULT 'pending',
  `last_mail_error` varchar(500) DEFAULT NULL,
  `utm_source` varchar(255) DEFAULT NULL,
  `utm_medium` varchar(255) DEFAULT NULL,
  `utm_campaign` varchar(255) DEFAULT NULL,
  `utm_term` varchar(255) DEFAULT NULL,
  `utm_content` varchar(255) DEFAULT NULL,
  `utm_landing` varchar(255) DEFAULT NULL,
  `source_page` varchar(255) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `form_version` varchar(20) NOT NULL DEFAULT 'v2',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_app_status` (`status`),
  KEY `idx_app_job` (`job_id`),
  KEY `idx_app_created` (`created_at`),
  KEY `idx_app_email` (`email`),
  KEY `idx_app_retain` (`retain_until`),
  CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `audit_log`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `details` varchar(1000) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `rate_limits`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bucket` varchar(40) NOT NULL,
  `key_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate_lookup` (`bucket`,`key_hash`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `login_attempts`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_hash` char(64) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_ip` (`ip_hash`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table `auto_migrations`
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auto_migrations` (
  `id` varchar(191) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `applied_by` int(10) unsigned DEFAULT NULL,
  `applied_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Default site settings (edit them later in Admin -> Settings)
-- =====================================================================
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('address_city', 'Bournemouth', UTC_TIMESTAMP()),
('address_country', 'UK', UTC_TIMESTAMP()),
('address_line1', '42 Windham Road', UTC_TIMESTAMP()),
('address_postcode', 'BH1 2AW', UTC_TIMESTAMP()),
('address_region', 'Dorset', UTC_TIMESTAMP()),
('attach_cv_to_notification', '0', UTC_TIMESTAMP()),
('captcha_provider', 'none', UTC_TIMESTAMP()),
('captcha_secret_enc', '', UTC_TIMESTAMP()),
('captcha_site_key', '', UTC_TIMESTAMP()),
('confirmation_body', 'Hi {name},\n\nThank you for applying{role_phrase} through {site_name}. Our team reviews every application and will be in touch within {response_time} if there is a suitable match.\n\nIf you have any questions, reply to this email or contact us at {contact_email}.\n\nKind regards,\nThe {site_name} team', UTC_TIMESTAMP()),
('confirmation_enabled', '1', UTC_TIMESTAMP()),
('confirmation_subject', 'We received your application — {site_name}', UTC_TIMESTAMP()),
('contact_email', 'info@hubjobplatform.com', UTC_TIMESTAMP()),
('contact_phone', '+44 1202 958648', UTC_TIMESTAMP()),
('copyright_name', 'Hubjob Platform', UTC_TIMESTAMP()),
('last_purge_at', '', UTC_TIMESTAMP()),
('legacy_form_until', DATE_FORMAT(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY), '%Y-%m-%d %H:%i:%s'), UTC_TIMESTAMP()),
('logo_path', 'static/images/logo22.png', UTC_TIMESTAMP()),
('mail_from_email', 'noreply@hubjobplatform.com', UTC_TIMESTAMP()),
('mail_from_name', 'Hubjob Platform', UTC_TIMESTAMP()),
('map_url', 'https://www.google.com/maps/search/?api=1&query=42+Windham+Road+Bournemouth+BH1+2AW', UTC_TIMESTAMP()),
('min_fill_seconds', '3', UTC_TIMESTAMP()),
('notification_email', '', UTC_TIMESTAMP()),
('office_hours', 'Mon – Fri: 8:30 AM – 6:00 PM GMT', UTC_TIMESTAMP()),
('rate_limit_day', '20', UTC_TIMESTAMP()),
('rate_limit_hour', '5', UTC_TIMESTAMP()),
('response_time', '24 business hours', UTC_TIMESTAMP()),
('response_time_short', '24h', UTC_TIMESTAMP()),
('retention_months', '12', UTC_TIMESTAMP()),
('site_name', 'Hubjob Platform', UTC_TIMESTAMP()),
('smtp_encryption', 'tls', UTC_TIMESTAMP()),
('smtp_host', '', UTC_TIMESTAMP()),
('smtp_password_enc', '', UTC_TIMESTAMP()),
('smtp_port', '587', UTC_TIMESTAMP()),
('smtp_username', '', UTC_TIMESTAMP()),
('snapshot_version', '20261009012628', UTC_TIMESTAMP()),
('whatsapp_number', '', UTC_TIMESTAMP());

-- =====================================================================
-- The 8 job categories
-- =====================================================================
INSERT IGNORE INTO `categories` (`slug`, `name`, `description`, `icon`, `sort_order`, `is_visible`, `created_at`) VALUES
('tech', 'Tech & Development', 'Build and test the websites, apps and tools that modern businesses run on.', 'terminal', 10, 1, UTC_TIMESTAMP()),
('support', 'Customer & Technical Support', 'Help customers and clients solve problems, from first contact to technical troubleshooting.', 'support_agent', 20, 1, UTC_TIMESTAMP()),
('operations', 'Admin & Operations', 'Keep businesses running smoothly with remote administration, data and coordination roles.', 'inventory_2', 30, 1, UTC_TIMESTAMP()),
('marketing', 'Marketing & Content', 'Help brands grow through social media, writing, email and search.', 'campaign', 40, 1, UTC_TIMESTAMP()),
('media', 'Video & Motion', 'Edit, animate and produce video content for brands and creators.', 'movie', 50, 1, UTC_TIMESTAMP()),
('sales', 'Sales & Business Development', 'Work with global brands to win new clients, build partnerships and grow revenue.', 'trending_up', 60, 1, UTC_TIMESTAMP()),
('design', 'Design & Creative', 'Shape the visual identity of global brands through graphic, product and brand design.', 'palette', 70, 1, UTC_TIMESTAMP()),
('education', 'Education & Training', 'Empower learners worldwide through online tutoring, coaching and course production.', 'school', 80, 1, UTC_TIMESTAMP());

-- =====================================================================
-- The 30 original job listings (category looked up by its URL name)
-- =====================================================================
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'TECH-01', 'Frontend Developer', NULL, '', 'remote', 'worldwide', 'Remote Worldwide', 'unspecified', NULL, '35.00', '90.00', 'USD', 'hour', 'Full-Stack / UI', 'laptop_mac', 'Posted Today', NULL, 0, 'active', 1, 10, 'TECH-01 | Posted Today | Frontend Developer | $35–$90/hr | Full-Stack / UI | Remote Worldwide | Original category: Tech & Development', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'tech';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'TECH-02', 'Backend Developer', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '40.00', '85.00', 'USD', 'hour', 'Node / Python / Cloud', 'cloud', NULL, NULL, 0, 'active', 0, 20, 'TECH-02 | Verified Partner | Backend Developer | $40–$85/hr | Node / Python / Cloud | Remote | Original category: Tech & Development', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'tech';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'TECH-03', 'Full-Stack Developer', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '45.00', '90.00', 'USD', 'hour', 'React / TypeScript / Go', 'code', NULL, NULL, 0, 'active', 0, 30, 'TECH-03 | Full-Stack Developer | $45–$90/hr | React / TypeScript / Go | Remote | Original category: Tech & Development', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'tech';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'TECH-04', 'Web Tester', NULL, '', 'remote', 'uk_europe', 'Remote Europe / UK', 'unspecified', NULL, '35.00', '50.00', 'USD', 'hour', 'QA / Automated Testing', 'bug_report', NULL, NULL, 0, 'active', 0, 40, 'TECH-04 | Web Tester | $35–$50/hr | QA / Automated Testing | Remote Europe / UK | Original category: Tech & Development', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'tech';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'TECH-05', 'Technical Support Specialist', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '35.00', '55.00', 'USD', 'hour', 'Tier 2 / Client Systems', 'lan', NULL, NULL, 0, 'active', 0, 50, 'TECH-05 | Technical Support Specialist | $35–$55/hr | Tier 2 / Client Systems | Remote | Original category: Tech & Development', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'support';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'OPS-01', 'Data Entry Clerk', NULL, 'full_time,part_time', 'unspecified', 'unspecified', NULL, 'unspecified', 'Full-Time / Part-Time', '25.00', '35.00', 'USD', 'hour', 'Operations & Admin', 'description', 'High Volume', NULL, 0, 'active', 0, 60, 'OPS-01 | High Volume | Data Entry Clerk | $25–$35/hr | Operations & Admin | Full-Time / Part-Time | Original category: Operations & Support', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'operations';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'OPS-02', 'Executive Assistant', NULL, '', 'remote', 'uk_europe', 'Remote UK / Europe', 'unspecified', NULL, '30.00', '50.00', 'USD', 'hour', 'Executive Liaison', 'badge', NULL, NULL, 0, 'active', 0, 70, 'OPS-02 | Executive Assistant | $30–$50/hr | Executive Liaison | Remote UK / Europe | Original category: Operations & Support', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'operations';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'OPS-03', 'Operations Coordinator', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '32.00', '52.00', 'USD', 'hour', 'Workflow & Logistics', 'account_tree', NULL, NULL, 0, 'active', 1, 80, 'OPS-03 | Operations Coordinator | $32–$52/hr | Workflow & Logistics | Remote | Original category: Operations & Support', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'operations';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'OPS-04', 'Customer Support Agent', NULL, '', 'unspecified', 'unspecified', NULL, 'flexible', 'Flexible Shift', '25.00', '40.00', 'USD', 'hour', 'Multilingual Support', 'translate', NULL, NULL, 0, 'active', 0, 90, 'OPS-04 | Customer Support Agent | $25–$40/hr | Multilingual Support | Flexible Shift | Original category: Operations & Support', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'support';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'OPS-05', 'Virtual Assistant', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '25.00', '45.00', 'USD', 'hour', 'Digital Coordination', 'assistant', NULL, NULL, 0, 'active', 0, 100, 'OPS-05 | Virtual Assistant | $25–$45/hr | Digital Coordination | Remote | Original category: Operations & Support', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'operations';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'MKT-01', 'Social Media Manager', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '30.00', '55.00', 'USD', 'hour', 'Brand & Growth', 'share', NULL, NULL, 0, 'active', 0, 110, 'MKT-01 | Social Media Manager | $30–$55/hr | Brand & Growth | Remote | Original category: Marketing & Content', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'marketing';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'MKT-02', 'Content Writer', NULL, '', 'remote', 'worldwide', 'Remote Worldwide', 'unspecified', NULL, '32.00', '60.00', 'USD', 'hour', 'Editorial & Copy', 'edit_note', NULL, NULL, 0, 'active', 1, 120, 'MKT-02 | Content Writer | $32–$60/hr | Editorial & Copy | Remote Worldwide | Original category: Marketing & Content', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'marketing';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'MKT-03', 'Email Marketing Assistant', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '30.00', '48.00', 'USD', 'hour', 'Automation & Lifecycle', 'mail', NULL, NULL, 0, 'active', 0, 130, 'MKT-03 | Email Marketing Assistant | $30–$48/hr | Automation & Lifecycle | Remote | Original category: Marketing & Content', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'marketing';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'MKT-04', 'SEO Specialist', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '35.00', '65.00', 'USD', 'hour', 'Technical SEO & Traffic', 'troubleshoot', NULL, NULL, 0, 'active', 0, 140, 'MKT-04 | SEO Specialist | $35–$65/hr | Technical SEO & Traffic | Remote | Original category: Marketing & Content', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'marketing';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'MKT-05', 'Video Editor', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '35.00', '60.00', 'USD', 'hour', 'Motion & Post-Production', 'movie', NULL, NULL, 0, 'active', 0, 150, 'MKT-05 | Video Editor | $35–$60/hr | Motion & Post-Production | Remote | Original category: Marketing & Content', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'media';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'SLS-01', 'Sales Representative', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '30.00', '65.00', 'USD', 'hour', 'B2B Client Acquisition', 'handshake', NULL, NULL, 0, 'active', 1, 160, 'SLS-01 | Sales Representative | $30–$65/hr | B2B Client Acquisition | Remote | Original category: Sales & Growth', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'sales';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'SLS-02', 'Lead Generator', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '30.00', '50.00', 'USD', 'hour', 'Outbound Prospecting', 'contact_mail', NULL, NULL, 0, 'active', 0, 170, 'SLS-02 | Lead Generator | $30–$50/hr | Outbound Prospecting | Remote | Original category: Sales & Growth', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'sales';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'SLS-03', 'Business Development Rep', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '35.00', '70.00', 'USD', 'hour', 'Enterprise Corridors', 'corporate_fare', NULL, NULL, 0, 'active', 0, 180, 'SLS-03 | Business Development Rep | $35–$70/hr | Enterprise Corridors | Remote | Original category: Sales & Growth', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'sales';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'SLS-04', 'Affiliate Manager', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '35.00', '68.00', 'USD', 'hour', 'Partnership Network', 'hub', NULL, NULL, 0, 'active', 0, 190, 'SLS-04 | Affiliate Manager | $35–$68/hr | Partnership Network | Remote | Original category: Sales & Growth', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'sales';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'SLS-05', 'Account Executive', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '40.00', '75.00', 'USD', 'hour', 'Client Management', 'supervised_user_circle', NULL, NULL, 0, 'active', 0, 200, 'SLS-05 | Account Executive | $40–$75/hr | Client Management | Remote | Original category: Sales & Growth', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'sales';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'DES-01', 'Graphic Designer', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '30.00', '55.00', 'USD', 'hour', 'Visual Identity', 'brush', NULL, NULL, 0, 'active', 0, 210, 'DES-01 | Graphic Designer | $30–$55/hr | Visual Identity | Remote | Original category: Design & Creative', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'design';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'DES-02', 'UI/UX Designer', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '38.00', '70.00', 'USD', 'hour', 'Product & Web Interface', 'devices', NULL, NULL, 0, 'active', 1, 220, 'DES-02 | UI/UX Designer | $38–$70/hr | Product & Web Interface | Remote | Original category: Design & Creative', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'design';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'DES-03', 'Presentation Designer', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '32.00', '55.00', 'USD', 'hour', 'Corporate Pitch Decks', 'slideshow', NULL, NULL, 0, 'active', 0, 230, 'DES-03 | Presentation Designer | $32–$55/hr | Corporate Pitch Decks | Remote | Original category: Design & Creative', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'design';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'DES-04', 'Brand Designer', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '35.00', '65.00', 'USD', 'hour', 'Typography & Systems', 'draw', NULL, NULL, 0, 'active', 0, 240, 'DES-04 | Brand Designer | $35–$65/hr | Typography & Systems | Remote | Original category: Design & Creative', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'design';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'DES-05', 'Motion Graphics Artist', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '38.00', '70.00', 'USD', 'hour', '2D/3D Animation', 'animation', NULL, NULL, 0, 'active', 0, 250, 'DES-05 | Motion Graphics Artist | $38–$70/hr | 2D/3D Animation | Remote | Original category: Design & Creative', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'media';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'EDU-01', 'Online Tutor', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '18.00', '35.00', 'USD', 'hour', 'Academic & Test Prep', 'menu_book', NULL, NULL, 0, 'active', 1, 260, 'EDU-01 | Online Tutor | $18–$35/hr | Academic & Test Prep | Remote | Original category: Education & Training', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'education';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'EDU-02', 'Course Content Assistant', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '18.00', '30.00', 'USD', 'hour', 'Curriculum Production', 'auto_stories', NULL, NULL, 0, 'active', 0, 270, 'EDU-02 | Course Content Assistant | $18–$30/hr | Curriculum Production | Remote | Original category: Education & Training', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'education';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'EDU-03', 'eLearning Coordinator', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '22.00', '35.00', 'USD', 'hour', 'LMS & Digital Learning', 'cast_for_education', NULL, NULL, 0, 'active', 0, 280, 'EDU-03 | eLearning Coordinator | $22–$35/hr | LMS & Digital Learning | Remote | Original category: Education & Training', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'education';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'EDU-04', 'Language Coach', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '20.00', '35.00', 'USD', 'hour', 'ESL & Business English', 'record_voice_over', NULL, NULL, 0, 'active', 0, 290, 'EDU-04 | Language Coach | $20–$35/hr | ESL & Business English | Remote | Original category: Education & Training', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'education';
INSERT IGNORE INTO `jobs` (`category_id`, `ref_code`, `title`, `company_name`, `employment_types`, `work_arrangement`, `applicant_region`, `location_text`, `schedule`, `schedule_note`, `pay_min`, `pay_max`, `pay_currency`, `pay_period`, `skills`, `icon`, `badge`, `description`, `employer_verified`, `status`, `is_featured`, `sort_order`, `source_text`, `posted_at`, `created_at`)
SELECT c.id, 'EDU-05', 'Academic Proofreader', NULL, '', 'remote', 'unspecified', 'Remote', 'unspecified', NULL, '15.00', '28.00', 'USD', 'hour', 'Editorial Review', 'spellcheck', NULL, NULL, 0, 'active', 0, 300, 'EDU-05 | Academic Proofreader | $15–$28/hr | Editorial Review | Remote | Original category: Education & Training', UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `categories` c WHERE c.slug = 'education';

-- =====================================================================
-- Mark the built-in database updates as done, so the admin area
-- doesn't try to apply them again after this import
-- =====================================================================
INSERT IGNORE INTO `auto_migrations` (`id`, `description`, `status`, `applied_at`, `updated_at`) VALUES
('2026_10_09_000001_core_schema', 'Create core tables (admins, settings, categories, jobs, applications, audit and rate limits)', 'success', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('2026_10_09_000002_seed_settings', 'Seed default site settings (contact details, email, spam and retention defaults)', 'success', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('2026_10_09_000003_seed_categories', 'Seed the 8 job categories', 'success', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('2026_10_09_000004_seed_jobs', 'Seed the 30 original job listings', 'success', UTC_TIMESTAMP(), UTC_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;
