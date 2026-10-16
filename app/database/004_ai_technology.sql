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
  'CREATE TABLE IF NOT EXISTS ai_generations (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 company_id ',
  @easy3_company_type,
  ' NOT NULL,
 actor_id ',
  @easy3_actor_type,
  ' NOT NULL,
 order_id ',
  @easy3_order_type,
  ' NOT NULL,
 order_revision INT NOT NULL,
 request_key VARCHAR(100) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 state VARCHAR(20) NOT NULL DEFAULT ''pending'',
 prompt_version VARCHAR(80) NOT NULL,
 model VARCHAR(80) NULL,
 response_json LONGTEXT NULL,
 usage_json LONGTEXT NULL,
 latency_ms INT NULL,
 error_code VARCHAR(40) NULL,
 request_json LONGTEXT NULL,
 job_json LONGTEXT NULL,
 lease_token CHAR(32) NULL,
 lease_until DATETIME NULL,
 call_count INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY ai_command (company_id,actor_id,request_key),
 KEY ai_company_day (company_id,created_at),
 KEY ai_actor_order (actor_id,order_id),
 CONSTRAINT ai_company_fk FOREIGN KEY (company_id) REFERENCES companies(id),
 CONSTRAINT ai_actor_fk FOREIGN KEY (actor_id) REFERENCES users(id),
 CONSTRAINT ai_order_fk FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
PREPARE easy3_update FROM @easy3_ddl;
EXECUTE easy3_update;
DEALLOCATE PREPARE easy3_update;

