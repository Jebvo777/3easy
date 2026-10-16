<?php
declare(strict_types=1);

/** Additive compatibility for existing MySQL 5.7 installations. */
function easy3EnsureOptionalColumns(PDO $connection): void {
 if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
 $definitions = [
  'ai_generations' => [
   'request_json' => 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
   'job_json' => 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
   'lease_token' => 'CHAR(32) NULL',
   'lease_until' => 'DATETIME NULL',
   'call_count' => 'INT NOT NULL DEFAULT 0',
  ],
  'tasks' => [
   'planning_meta' => 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  ],
 ];
 foreach ($definitions as $table => $fields) {
  try {
   $columns = array_fill_keys($connection->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN), true);
  } catch (PDOException $error) {
   // The SQL installer creates service tables. Authentication can still work
   // before that installer has been applied.
   if ((int)($error->errorInfo[1] ?? 0) === 1146) continue;
   throw $error;
  }
  foreach ($fields as $name => $definition) {
   if (isset($columns[$name])) continue;
   try {
    $connection->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$name.'` '.$definition);
   } catch (PDOException $error) {
    // Two simultaneous first requests may both observe a missing column.
    if ((int)($error->errorInfo[1] ?? 0) !== 1060) throw $error;
   }
   $columns[$name] = true;
  }
 }
}
