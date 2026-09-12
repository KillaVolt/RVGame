<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/Database.php';

$_SERVER['SCRIPT_NAME'] = '/RVGame/OT/api/index.php';
assert(Database::channel() === 'ot');

$_SERVER['SCRIPT_NAME'] = '/RVGame/api/index.php';
assert(Database::channel() === 'main');

echo "PASS channel: owner-test and main routes remain distinct\n";
