<?php
// Скопировать в config.local.php; этот файл не содержит секретов.
return [
    'host' => 'localhost', 'port' => 3306,
    'database' => 'system404_easy3', 'username' => 'system404_easy3',
    'password' => getenv('EASY3_DB_PASSWORD') ?: '',
    'base_url' => 'https://m21.system404-design.ru',
    'timezone' => 'Europe/Moscow', 'demo_enabled' => true,
    'session_minutes' => 240,
];
