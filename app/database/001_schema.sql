-- EASY3 PHP demo; MySQL 8 / MariaDB 10.6+; тестовая отдельная схема.
CREATE DATABASE IF NOT EXISTS system404_easy3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE system404_easy3;
CREATE TABLE IF NOT EXISTS users (
 id CHAR(32) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS companies (
 id CHAR(32) PRIMARY KEY, name VARCHAR(120) NOT NULL,
 is_demo TINYINT NOT NULL DEFAULT 0, passport LONGTEXT NOT NULL,
 layout LONGTEXT NOT NULL, revision INT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS memberships (
 user_id CHAR(32) NOT NULL, company_id CHAR(32) NOT NULL,
 role VARCHAR(20) NOT NULL,
 PRIMARY KEY (user_id,company_id),
 FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS orders (
 id CHAR(32) PRIMARY KEY, company_id CHAR(32) NOT NULL,
 title VARCHAR(180) NOT NULL, product VARCHAR(180) NOT NULL,
 customer VARCHAR(180) NOT NULL DEFAULT '', mode VARCHAR(12) NOT NULL,
 quantity INT NOT NULL, deadline DATE NOT NULL, start_date DATE NOT NULL,
 price_cents BIGINT NULL, price_basis VARCHAR(12) NOT NULL DEFAULT 'unit',
 target_cents BIGINT NULL, material_owner VARCHAR(12) NOT NULL DEFAULT 'company',
 expenses LONGTEXT NOT NULL, route LONGTEXT NOT NULL, revision INT NOT NULL DEFAULT 1,
 status VARCHAR(20) NOT NULL DEFAULT 'draft', color VARCHAR(20) NOT NULL DEFAULT 'mint',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (company_id) REFERENCES companies(id), INDEX(company_id,status)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS plan_versions (
 id CHAR(32) PRIMARY KEY, company_id CHAR(32) NOT NULL,
 version_no INT NOT NULL, state VARCHAR(20) NOT NULL DEFAULT 'draft',
 snapshot LONGTEXT NOT NULL, input_hash CHAR(64) NOT NULL,
 approvals LONGTEXT NOT NULL, creator_id CHAR(32) NOT NULL,
 published_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(company_id,version_no), FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tasks (
 id CHAR(32) PRIMARY KEY, company_id CHAR(32) NOT NULL,
 order_id CHAR(32) NOT NULL, plan_id CHAR(32) NOT NULL, operation_id VARCHAR(60) NOT NULL,
 title VARCHAR(180) NOT NULL, resource_id VARCHAR(60) NOT NULL,
 worker_id VARCHAR(60) NOT NULL, quantity INT NOT NULL, released INT NOT NULL DEFAULT 0,
 transferred INT NOT NULL DEFAULT 0, accepted INT NOT NULL DEFAULT 0,
 rework INT NOT NULL DEFAULT 0, rework_ready INT NOT NULL DEFAULT 0, scrap INT NOT NULL DEFAULT 0,
 state VARCHAR(20) NOT NULL DEFAULT 'ready', is_final TINYINT NOT NULL DEFAULT 0,
 dependencies LONGTEXT NOT NULL, revision INT NOT NULL DEFAULT 1,
 planned_start DATETIME NOT NULL, planned_end DATETIME NOT NULL,
 FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (order_id) REFERENCES orders(id),
 FOREIGN KEY (plan_id) REFERENCES plan_versions(id), INDEX(company_id,order_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS production_events (
 id CHAR(32) PRIMARY KEY, company_id CHAR(32) NOT NULL,
 task_id CHAR(32) NOT NULL, actor_id CHAR(32) NOT NULL,
 kind VARCHAR(20) NOT NULL, quantity INT NULL, details LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (task_id) REFERENCES tasks(id),
 INDEX(company_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_log (
 id CHAR(32) PRIMARY KEY, company_id CHAR(32) NOT NULL, actor_id CHAR(32) NOT NULL,
 action VARCHAR(40) NOT NULL, details LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(company_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS idempotency (
 company_id CHAR(32) NOT NULL, actor_id CHAR(32) NOT NULL, command_key VARCHAR(100) NOT NULL,
 request_hash CHAR(64) NOT NULL, response LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(company_id,actor_id,command_key)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS auth_limits (
 bucket CHAR(64) PRIMARY KEY, failures INT NOT NULL DEFAULT 0,
 window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Обновление уже установленного EASY3: существующие таблицы и факты не меняются.

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

-- EASY3 7.2: independent preparation and its immutable revisions; existing data retained.
CREATE TABLE IF NOT EXISTS order_preparations (
 id CHAR(32) NOT NULL PRIMARY KEY,
 company_id CHAR(32) NOT NULL,
 order_id CHAR(32) NOT NULL,
 revision INT NOT NULL DEFAULT 1,
 state_json LONGTEXT NOT NULL,
 actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY preparation_order (company_id,order_id),
 FOREIGN KEY (company_id) REFERENCES companies(id),
 FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS preparation_revisions (
 id CHAR(32) NOT NULL PRIMARY KEY,
 preparation_id CHAR(32) NOT NULL,
 company_id CHAR(32) NOT NULL,
 revision INT NOT NULL,
 state_json LONGTEXT NOT NULL,
 actor_id CHAR(32) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY preparation_revision (preparation_id,revision),
 FOREIGN KEY (preparation_id) REFERENCES order_preparations(id),
 FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
