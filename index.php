<?php
declare(strict_types=1);

$built = __DIR__ . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'index.html';
if (!is_file($built)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "RVGame has not been built. Run npm install and npm run build in this folder.";
    exit;
}

header('Location: dist/index.html');
