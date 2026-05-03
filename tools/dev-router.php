<?php

declare(strict_types=1);

$projectDir = dirname(__DIR__);
$publicDir = $projectDir.'/public_html';
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if (is_string($path)) {
    $file = realpath($publicDir.$path);

    if (false !== $file && str_starts_with($file, $publicDir.DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}

require $publicDir.'/index.php';
