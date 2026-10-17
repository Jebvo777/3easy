-- EASY3 v6.1, 2026-10-17. Compatibility update for an EXISTING database.
-- v6.1 stores contours and confirmed scenario targets in existing JSON text columns.
-- No new columns, no rewriting of business data, no historical JSON conversion.
-- The additive v6 bootstrap below also supports installations still on v5.7.

-- EASY3 / MySQL 5.7+: run the complete script in the existing database.
-- Existing data, tables and existing foreign keys are not removed or converted.
-- Missing optional columns of older tables are added by app/lib/schema.php.
USE `system404_easy3`;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `economy_observations` (
 `id` CHAR(32) NOT NULL PRIMARY KEY,
 `company_id` CHAR(32) NOT NULL,
 `order_id` CHAR(32) NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `revision` INT NOT NULL,
 `accepted_quantity` INT NOT NULL,
 `recognized_cents` BIGINT NULL,
 `direct_cents` BIGINT NULL,
 `labor_cents` BIGINT NULL,
 `common_cents` BIGINT NULL,
 `source` TEXT NOT NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY `economy_order_revision` (`company_id`,`order_id`,`revision`),
 KEY `economy_actor` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_generations` (
 `id` CHAR(32) NOT NULL PRIMARY KEY,
 `company_id` CHAR(32) NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `order_id` CHAR(32) NOT NULL,
 `order_revision` INT NOT NULL,
 `request_key` VARCHAR(100) NOT NULL,
 `request_hash` CHAR(64) NOT NULL,
 `state` VARCHAR(20) NOT NULL DEFAULT 'pending',
 `prompt_version` VARCHAR(80) NOT NULL,
 `model` VARCHAR(80) NULL,
 `response_json` LONGTEXT NULL,
 `usage_json` LONGTEXT NULL,
 `latency_ms` INT NULL,
 `error_code` VARCHAR(40) NULL,
 `request_json` LONGTEXT NULL,
 `job_json` LONGTEXT NULL,
 `lease_token` CHAR(32) NULL,
 `lease_until` DATETIME NULL,
 `call_count` INT NOT NULL DEFAULT 0,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY `ai_command` (`company_id`,`actor_id`,`request_key`),
 KEY `ai_company_day` (`company_id`,`created_at`),
 KEY `ai_actor_order` (`actor_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_workspaces` (
 `id` CHAR(32) NOT NULL PRIMARY KEY,
 `company_id` CHAR(32) NOT NULL,
 `order_id` CHAR(32) NOT NULL,
 `revision` INT NOT NULL DEFAULT 1,
 `state_json` LONGTEXT NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY `workspace_order` (`company_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_workspace_revisions` (
 `id` CHAR(32) NOT NULL PRIMARY KEY,
 `workspace_id` CHAR(32) NOT NULL,
 `company_id` CHAR(32) NOT NULL,
 `revision` INT NOT NULL,
 `state_json` LONGTEXT NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY `workspace_revision` (`workspace_id`,`revision`),
 KEY `revision_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- MySQL 5.7+. The supplied current database already has all AI/PLAN columns.
-- Release only expired pending leases; keep all descriptions and generated parts.
-- A currently running request is not changed. Re-import is safe.
UPDATE `ai_generations`
SET `lease_token` = NULL,
    `lease_until` = NULL,
    `error_code` = 'interrupted'
WHERE `state` = 'pending'
  AND `lease_until` IS NOT NULL
  AND `lease_until` <= CURRENT_TIMESTAMP;

-- EASY3 v6 / MySQL 5.7+. Additive migration; no existing data removed.

CREATE TABLE IF NOT EXISTS `ai_provider_limits` (
 `bucket` CHAR(64) NOT NULL PRIMARY KEY,
 `next_allowed` BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assistant_jobs` (
 `id` CHAR(32) NOT NULL PRIMARY KEY,
 `company_id` CHAR(32) NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `kind` VARCHAR(30) NOT NULL,
 `request_key` VARCHAR(100) NOT NULL,
 `input_hash` CHAR(64) NOT NULL,
 `state` VARCHAR(20) NOT NULL DEFAULT 'pending',
 `input_json` LONGTEXT NOT NULL,
 `progress_json` LONGTEXT NOT NULL,
 `result_json` LONGTEXT NULL,
 `model` VARCHAR(100) NULL,
 `error_message` TEXT NULL,
 `retry_at` BIGINT NOT NULL DEFAULT 0,
 `calls` INT NOT NULL DEFAULT 0,
 `lease_token` CHAR(32) NULL,
 `lease_until` DATETIME NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY `assistant_command` (`company_id`,`actor_id`,`request_key`),
 KEY `assistant_actor` (`company_id`,`actor_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_imports` (
 `id` CHAR(32) NOT NULL PRIMARY KEY,
 `company_id` CHAR(32) NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `display_name` VARCHAR(240) NOT NULL,
 `extension` VARCHAR(12) NOT NULL,
 `source_hash` CHAR(64) NOT NULL,
 `raw_text` MEDIUMTEXT NOT NULL,
 `safe_text` MEDIUMTEXT NOT NULL,
 `metadata_json` LONGTEXT NOT NULL,
 `state` VARCHAR(20) NOT NULL DEFAULT 'review',
 `job_id` CHAR(32) NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY `document_source` (`company_id`,`actor_id`,`source_hash`),
 KEY `document_actor` (`company_id`,`actor_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_import_applications` (
 `import_id` CHAR(32) NOT NULL,
 `candidate_index` INT NOT NULL,
 `company_id` CHAR(32) NOT NULL,
 `order_id` CHAR(32) NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`import_id`,`candidate_index`),
 KEY `document_order` (`company_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `assistant_jobs` SET `state`='pending',`lease_token`=NULL,`lease_until`=NULL,
 `error_message`='Предыдущий запрос прерван. Продолжите сохранённое задание.'
WHERE `state`='working' AND `lease_until` IS NOT NULL AND `lease_until`<=CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS `simulation_cases` (
 `id` CHAR(32) NOT NULL PRIMARY KEY,
 `company_id` CHAR(32) NOT NULL,
 `actor_id` CHAR(32) NOT NULL,
 `job_id` CHAR(32) NOT NULL,
 `variant_index` INT NOT NULL DEFAULT -1,
 `title` VARCHAR(180) NOT NULL,
 `source_hash` CHAR(64) NOT NULL,
 `settings_json` LONGTEXT NOT NULL,
 `result_json` LONGTEXT NOT NULL,
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY `simulation_job_variant` (`company_id`,`job_id`,`variant_index`),
 KEY `simulation_owner` (`company_id`,`actor_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
