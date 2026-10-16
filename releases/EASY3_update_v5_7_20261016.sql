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
