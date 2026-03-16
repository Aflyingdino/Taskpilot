<?php
/*
 * Local development router for php -S.
 * Routes /api/* requests to api/index.php.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../' . ltrim($path, '/');

if ($path !== '/' && is_file($file)) {
    return false;
}

if (str_starts_with($path, '/api')) {
    require __DIR__ . '/index.php';
    return true;
}

return false;
