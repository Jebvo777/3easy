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
