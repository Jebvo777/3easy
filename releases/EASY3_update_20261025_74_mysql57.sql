-- EASY3 7.4. Additive migration, MySQL 5.7. Run in the existing database.
-- Existing rows, settings, published plans and production facts are not modified.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS cutting_assets (
 id VARCHAR(60) NOT NULL,
 company_id CHAR(32) NOT NULL,
 kind VARCHAR(20) NOT NULL,
 revision INT NOT NULL DEFAULT 1,
 state_json LONGTEXT NOT NULL,
 actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (company_id,id),
 KEY cutting_asset_kind (company_id,kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cutting_plans (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 order_id CHAR(32) NOT NULL,
 revision INT NOT NULL DEFAULT 1,
 status VARCHAR(24) NOT NULL DEFAULT 'draft',
 state_json LONGTEXT NOT NULL,
 calculation_json LONGTEXT NOT NULL,
 actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY cutting_order (company_id,order_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS manufacturing_revisions (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 entity_kind VARCHAR(24) NOT NULL,
 entity_id VARCHAR(60) NOT NULL,
 revision INT NOT NULL,
 state_json LONGTEXT NOT NULL,
 actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY manufacturing_revision (company_id,entity_kind,entity_id,revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cutting_events (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 plan_id CHAR(32) NOT NULL,
 lay_id VARCHAR(60) NOT NULL,
 kind VARCHAR(24) NOT NULL,
 state_json LONGTEXT NOT NULL,
 actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY cutting_event_plan (company_id,plan_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cutting_roll_reservations (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 plan_id CHAR(32) NOT NULL,
 lay_id VARCHAR(60) NOT NULL,
 roll_id VARCHAR(60) NOT NULL,
 length_mm BIGINT NOT NULL,
 state VARCHAR(20) NOT NULL DEFAULT 'reserved',
 UNIQUE KEY cutting_roll_lay (company_id,plan_id,lay_id,roll_id),
 KEY cutting_roll_state (company_id,roll_id,state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS preparation_movements (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 order_id CHAR(32) NOT NULL,
 tranche_id VARCHAR(96) NOT NULL,
 batch_key VARCHAR(200) NOT NULL,
 kind VARCHAR(20) NOT NULL,
 quantity INT NOT NULL,
 state_json LONGTEXT NOT NULL,
 actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY preparation_lot (company_id,order_id,tranche_id),
 KEY preparation_batch (company_id,order_id,batch_key,kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
