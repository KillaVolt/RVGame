<?php
declare(strict_types=1);

function fail(string $message): never { fwrite(STDERR, "FAIL $message\n"); exit(1); }

$secrets = [];
foreach (file('E:/.secrets.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (!preg_match('/^\s*([A-Z0-9_]+)=(.*)$/', $line, $match)) continue;
    $value = trim($match[2]);
    if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
    $secrets[$match[1]] = $value;
}

$ftp = ftp_ssl_connect($secrets['STARCORE_FTP_HOST'], (int) $secrets['STARCORE_FTP_PORT'], 30) ?: fail('FTPS connect failed');
ftp_login($ftp, $secrets['STARCORE_FTP_USERNAME'], $secrets['STARCORE_FTP_PASSWORD']) || fail('FTPS login failed');
ftp_pasv($ftp, true) || fail('passive mode failed');
ftp_chdir($ftp, rtrim(str_replace('\\', '/', $secrets['STARCORE_FTP_ROOT']), '/')) || fail('remote root unavailable');

$input = fopen('php://temp', 'w+b') ?: fail('temp stream failed');
ftp_fget($ftp, $input, '.htaccess', FTP_BINARY) || fail('cannot read root rules');
rewind($input);
$rules = stream_get_contents($input) ?: fail('root rules are empty');
$exemption = 'RewriteRule ^RVGame(?:/|$) - [L,NC]';
$mainBlock = "RewriteRule ^RVGame/?$ RVGame/index.php [L,NC]\nRewriteRule ^RVGame/dist/?$ RVGame/dist/index.html [L,NC]\n" . $exemption;
$block = "RewriteRule ^RVGame/OT/?$ RVGame/OT/index.php [L,NC]\nRewriteRule ^RVGame/OT/dist/?$ RVGame/OT/dist/index.html [L,NC]\n" . $mainBlock;
if (!str_contains($rules, 'RewriteRule ^RVGame/OT/?$ RVGame/OT/index.php [L,NC]')) {
    $anchor = 'RewriteRule ^api(?:/|$) - [F,L]';
    if (str_contains($rules, $mainBlock)) {
        $rules = str_replace($mainBlock, $block, $rules, $count);
        $count === 1 || fail('RVGame main route block was not unique');
    } elseif (str_contains($rules, $exemption)) {
        $rules = str_replace($exemption, $block, $rules, $count);
        $count === 1 || fail('RVGame rule was not unique');
    } else {
        str_contains($rules, $anchor) || fail('expected root rule anchor missing');
        $rules = str_replace($anchor, $block . "\n\n" . $anchor, $rules, $count);
        $count === 1 || fail('root rule anchor was not unique');
    }
}

$output = fopen('php://temp', 'w+b') ?: fail('output stream failed');
fwrite($output, $rules);
rewind($output);
ftp_fput($ftp, '.htaccess.rvgame-uploading', $output, FTP_BINARY) || fail('root rule upload failed');
@ftp_delete($ftp, '.htaccess.rvgame-before');
ftp_rename($ftp, '.htaccess', '.htaccess.rvgame-before') || fail('root rule backup failed');
if (!ftp_rename($ftp, '.htaccess.rvgame-uploading', '.htaccess')) {
    ftp_rename($ftp, '.htaccess.rvgame-before', '.htaccess');
    fail('root rule activation failed; original restored');
}
@ftp_delete($ftp, '.htaccess.rvgame-before');
@ftp_close($ftp);
echo "OK /RVGame route exemption active\n";
