-- EASY3 / MySQL 5.7+: run the complete script in the existing database.
-- Existing data, tables and existing foreign keys are not removed or converted.
-- Missing optional columns of older tables are added by app/lib/schema.php.
USE `system404_easy3`;
SET NAMES utf8mb4;

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

