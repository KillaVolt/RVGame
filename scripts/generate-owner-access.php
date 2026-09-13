<?php
declare(strict_types=1);

$username = getenv('RVGAME_OWNER_USERNAME');
$password = getenv('RVGAME_OWNER_PASSWORD');
if (!is_string($username) || $username === '' || !is_string($password) || $password === '') {
    fwrite(STDERR, "Owner credentials missing\n");
    exit(1);
}

echo "<?php\ndeclare(strict_types=1);\nreturn " . var_export([
    'username' => $username,
    'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
], true) . ";\n";
