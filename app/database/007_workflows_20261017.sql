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
