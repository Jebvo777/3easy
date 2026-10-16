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
  'CREATE TABLE IF NOT EXISTS economy_observations (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 company_id ',
  @easy3_company_type,
  ' NOT NULL,
 order_id ',
  @easy3_order_type,
  ' NOT NULL,
 actor_id ',
  @easy3_actor_type,
  ' NOT NULL,
 revision INT NOT NULL,
 accepted_quantity INT NOT NULL,
 recognized_cents BIGINT NULL,
 direct_cents BIGINT NULL,
 labor_cents BIGINT NULL,
 common_cents BIGINT NULL,
 source TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY economy_order_revision (company_id,order_id,revision),
 FOREIGN KEY (company_id) REFERENCES companies(id),
 FOREIGN KEY (order_id) REFERENCES orders(id),
 FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

