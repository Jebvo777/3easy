-- EASY3: existing database update, MySQL 5.7+ / MariaDB.
-- Repeatable; no DROP, no CHECK, no table/column charset conversion.
USE `system404_easy3`;
SET NAMES utf8mb4;

-- Match the exact type, charset and collation of existing referenced IDs.
SELECT CONCAT(COLUMN_TYPE,
  IF(CHARACTER_SET_NAME IS NULL, '',
    CONCAT(' CHARACTER SET ', CHARACTER_SET_NAME, ' COLLATE ', COLLATION_NAME)))
INTO @easy3_company_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='companies' AND COLUMN_NAME='id';

SELECT CONCAT(COLUMN_TYPE,
  IF(CHARACTER_SET_NAME IS NULL, '',
    CONCAT(' CHARACTER SET ', CHARACTER_SET_NAME, ' COLLATE ', COLLATION_NAME)))
INTO @easy3_actor_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id';

SELECT CONCAT(COLUMN_TYPE,
  IF(CHARACTER_SET_NAME IS NULL, '',
    CONCAT(' CHARACTER SET ', CHARACTER_SET_NAME, ' COLLATE ', COLLATION_NAME)))
INTO @easy3_order_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='id';

SET @easy3_ddl = CONCAT(
  'CREATE TABLE IF NOT EXISTS plan_workspaces (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 company_id ',
  @easy3_company_type,
  ' NOT NULL,
 order_id ',
  @easy3_order_type,
  ' NOT NULL,
 revision INT NOT NULL DEFAULT 1,
 state_json LONGTEXT NOT NULL,
 actor_id ',
  @easy3_actor_type,
  ' NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY workspace_order (company_id,order_id),
 FOREIGN KEY (company_id) REFERENCES companies(id),
 FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

SELECT CONCAT(COLUMN_TYPE,
  IF(CHARACTER_SET_NAME IS NULL, '',
    CONCAT(' CHARACTER SET ', CHARACTER_SET_NAME, ' COLLATE ', COLLATION_NAME)))
INTO @easy3_workspace_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plan_workspaces' AND COLUMN_NAME='id';

SET @easy3_ddl = CONCAT(
  'CREATE TABLE IF NOT EXISTS plan_workspace_revisions (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 workspace_id ',
  @easy3_workspace_type,
  ' NOT NULL,
 company_id ',
  @easy3_company_type,
  ' NOT NULL,
 revision INT NOT NULL,
 state_json LONGTEXT NOT NULL,
 actor_id ',
  @easy3_actor_type,
  ' NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY workspace_revision (workspace_id,revision),
 FOREIGN KEY (workspace_id) REFERENCES plan_workspaces(id),
 FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

-- Add only missing columns after an earlier or partially completed update.
SET @easy3_ddl = (
 SELECT IF(COUNT(*)=0,
   'ALTER TABLE `ai_generations` ADD COLUMN `request_json` LONGTEXT NULL', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='request_json'
);
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (
 SELECT IF(COUNT(*)=0,
   'ALTER TABLE `ai_generations` ADD COLUMN `job_json` LONGTEXT NULL', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='job_json'
);
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (
 SELECT IF(COUNT(*)=0,
   'ALTER TABLE `ai_generations` ADD COLUMN `lease_token` CHAR(32) NULL', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='lease_token'
);
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (
 SELECT IF(COUNT(*)=0,
   'ALTER TABLE `ai_generations` ADD COLUMN `lease_until` DATETIME NULL', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='lease_until'
);
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (
 SELECT IF(COUNT(*)=0,
   'ALTER TABLE `ai_generations` ADD COLUMN `call_count` INT NOT NULL DEFAULT 0', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='call_count'
);
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (
 SELECT IF(COUNT(*)=0,
   'ALTER TABLE `tasks` ADD COLUMN `planning_meta` LONGTEXT NULL', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='planning_meta'
);
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

