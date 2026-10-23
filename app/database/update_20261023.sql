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
