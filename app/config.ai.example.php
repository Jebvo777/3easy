<?php
// Copy to config.ai.local.php or set EASY3_AI_KEY on the server.
return [
 'enabled'=>true,'base_url'=>getenv('EASY3_AI_BASE_URL') ?: 'https://api.artemox.com/v1',
 'key'=>getenv('EASY3_AI_KEY') ?: '',
 'model'=>'gpt-5-nano','fallback_models'=>['gpt-5.4-mini','gpt-4o-mini'],
 'temperature'=>0.3,'total_timeout'=>50,'attempt_timeout'=>30,'max_tokens'=>12000,
 'daily_limit'=>100,'global_daily_limit'=>100,'min_interval'=>10,'ca_file'=>null,
];
