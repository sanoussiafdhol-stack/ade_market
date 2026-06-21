<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $vars = parse_ini_file($envFile);
    if ($vars) {
        foreach ($vars as $key => $value) {
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}
