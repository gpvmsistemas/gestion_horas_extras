<?php
// Ejecución recomendada diaria por cron/Task Scheduler.
chdir(__DIR__ . '/../public');
require_once '../app/bootstrap.php';

$result = (new VacationLedgerService())->expireConventionalCredits(null, 0);
echo $result['message'] . PHP_EOL;
exit($result['ok'] ? 0 : 1);
