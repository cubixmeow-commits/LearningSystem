<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}

$php = __DIR__ . $path;
if (!str_ends_with($php, '.php')) {
    $php .= '.php';
}
if (is_file($php)) {
    require $php;
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
