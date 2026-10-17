-- EASY3 v6 / MySQL 5.7+. Additive migration; no existing data removed.

CREATE TABLE IF NOT EXISTS `ai_provider_limits` (
 `bucket` CHAR(64) NOT NULL PRIMARY KEY,
 `next_allowed` BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
