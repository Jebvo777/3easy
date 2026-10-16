-- MySQL 5.7+. The supplied current database already has all AI/PLAN columns.
-- Release only expired pending leases; keep all descriptions and generated parts.
-- A currently running request is not changed. Re-import is safe.
UPDATE `ai_generations`
SET `lease_token` = NULL,
    `lease_until` = NULL,
    `error_code` = 'interrupted'
WHERE `state` = 'pending'
  AND `lease_until` IS NOT NULL
  AND `lease_until` <= CURRENT_TIMESTAMP;
