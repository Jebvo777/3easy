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

