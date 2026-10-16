-- EASY3: update an existing database. Repeatable. Do not import install.sql into a working database.

-- Обновление уже установленного EASY3: существующие таблицы и факты не меняются.
USE system404_easy3;
CREATE TABLE IF NOT EXISTS economy_observations (
 id CHAR(32) PRIMARY KEY, company_id CHAR(32) NOT NULL,
 order_id CHAR(32) NOT NULL, actor_id CHAR(32) NOT NULL,
 revision INT NOT NULL, accepted_quantity INT NOT NULL, recognized_cents BIGINT NULL,
 direct_cents BIGINT NULL, labor_cents BIGINT NULL, common_cents BIGINT NULL,
 source TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(company_id,order_id,revision),
 FOREIGN KEY (company_id) REFERENCES companies(id),
 FOREIGN KEY (order_id) REFERENCES orders(id),
 FOREIGN KEY (actor_id) REFERENCES users(id),
 CHECK(accepted_quantity>0), CHECK(direct_cents IS NULL OR direct_cents>=0),
 CHECK(labor_cents IS NULL OR labor_cents>=0), CHECK(common_cents IS NULL OR common_cents>=0)
) ENGINE=InnoDB;


-- EASY3 v4: validated proposals are separate from orders and published plans.
USE system404_easy3;
CREATE TABLE IF NOT EXISTS ai_generations (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 actor_id CHAR(32) NOT NULL,
 order_id CHAR(32) NOT NULL,
 order_revision INT NOT NULL,
 request_key VARCHAR(100) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 state VARCHAR(20) NOT NULL DEFAULT 'pending',
 prompt_version VARCHAR(80) NOT NULL,
 model VARCHAR(80) NULL,
 response_json LONGTEXT NULL,
 usage_json LONGTEXT NULL,
 latency_ms INT NULL,
 error_code VARCHAR(40) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY ai_command (company_id,actor_id,request_key),
 KEY ai_company_day (company_id,created_at),
 KEY ai_actor_order (actor_id,order_id),
 CONSTRAINT ai_company_fk FOREIGN KEY (company_id) REFERENCES companies(id),
 CONSTRAINT ai_actor_fk FOREIGN KEY (actor_id) REFERENCES users(id),
 CONSTRAINT ai_order_fk FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- EASY3 16.10.2026. Additive, repeatable update for MySQL 5.7+/8 and MariaDB.
USE system404_easy3;
CREATE TABLE IF NOT EXISTS plan_workspaces (
 id CHAR(32) NOT NULL PRIMARY KEY, company_id CHAR(32) NOT NULL, order_id CHAR(32) NOT NULL,
 revision INT NOT NULL DEFAULT 1, state_json LONGTEXT NOT NULL, actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY workspace_order (company_id,order_id),
 FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS plan_workspace_revisions (
 id CHAR(32) NOT NULL PRIMARY KEY, workspace_id CHAR(32) NOT NULL, company_id CHAR(32) NOT NULL,
 revision INT NOT NULL, state_json LONGTEXT NOT NULL, actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY workspace_revision (workspace_id,revision),
 FOREIGN KEY (workspace_id) REFERENCES plan_workspaces(id), FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @easy3_ddl = (SELECT IF(COUNT(*)=0, 'ALTER TABLE ai_generations ADD COLUMN request_json LONGTEXT NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='request_json');
PREPARE easy3_update FROM @easy3_ddl; EXECUTE easy3_update; DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (SELECT IF(COUNT(*)=0, 'ALTER TABLE ai_generations ADD COLUMN job_json LONGTEXT NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='job_json');
PREPARE easy3_update FROM @easy3_ddl; EXECUTE easy3_update; DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (SELECT IF(COUNT(*)=0, 'ALTER TABLE ai_generations ADD COLUMN lease_token CHAR(32) NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='lease_token');
PREPARE easy3_update FROM @easy3_ddl; EXECUTE easy3_update; DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (SELECT IF(COUNT(*)=0, 'ALTER TABLE ai_generations ADD COLUMN lease_until DATETIME NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='lease_until');
PREPARE easy3_update FROM @easy3_ddl; EXECUTE easy3_update; DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (SELECT IF(COUNT(*)=0, 'ALTER TABLE ai_generations ADD COLUMN call_count INT NOT NULL DEFAULT 0', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_generations' AND COLUMN_NAME='call_count');
PREPARE easy3_update FROM @easy3_ddl; EXECUTE easy3_update; DEALLOCATE PREPARE easy3_update;

SET @easy3_ddl = (SELECT IF(COUNT(*)=0, 'ALTER TABLE tasks ADD COLUMN planning_meta LONGTEXT NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='planning_meta');
PREPARE easy3_update FROM @easy3_ddl; EXECUTE easy3_update; DEALLOCATE PREPARE easy3_update;
