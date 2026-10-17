<?php
declare(strict_types=1);
require_once __DIR__.'/../ai/workflows.php';
function assistantAction(string $action,array $input):array{owner();return workflowAction($action,$input);}
