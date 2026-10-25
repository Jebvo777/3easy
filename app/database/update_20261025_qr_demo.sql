-- EASY3 7.3.1 QR and demo update for an EXISTING MySQL 5.7 database.
-- Import this file, upload the complete overlay archive and reopen the demo.
-- The application updates demo companies once and keeps their previous configuration.
-- EASY3 7.3. Additive update for MySQL 5.7; run in the existing database.
-- No DROP, CHECK, JSON type, PREPARE, generated columns or existing-table conversion.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS person_accounts (
 company_id CHAR(32) NOT NULL,
 person_id VARCHAR(60) NOT NULL,
 user_id CHAR(32) NOT NULL,
 PRIMARY KEY (company_id,person_id),
 UNIQUE KEY person_account_user (company_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS order_variant_revisions (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 order_id CHAR(32) NOT NULL,
 revision INT NOT NULL,
 variants_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY order_variant_revision (company_id,order_id,revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS workplace_sessions (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 person_id VARCHAR(60) NOT NULL,
 user_id CHAR(32) NOT NULL,
 workplace_id VARCHAR(60) NOT NULL,
 workplace_name VARCHAR(100) NOT NULL,
 department_name VARCHAR(100) NOT NULL,
 timezone VARCHAR(60) NOT NULL,
 started_at DATETIME NOT NULL,
 finished_at DATETIME NULL,
 active_key CHAR(64) NULL,
 UNIQUE KEY one_active_workplace (active_key),
 KEY workplace_day (company_id,workplace_id,started_at),
 KEY employee_day (company_id,user_id,started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time demo environment revision. The application applies it when a demo
-- company is next opened. Existing production facts and published plans stay intact.
-- No routines, dynamic DDL, ALTER, PREPARE or collation conversion are required.
CREATE TABLE IF NOT EXISTS demo_environment_revisions (
 company_id CHAR(32) NOT NULL,
 migration_version INT NOT NULL,
 passport_before LONGTEXT NOT NULL,
 layout_before LONGTEXT NOT NULL,
 company_revision INT NOT NULL,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (company_id,migration_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
