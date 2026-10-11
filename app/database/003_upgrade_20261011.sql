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
