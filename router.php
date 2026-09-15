<?php
// Router untuk PHP built-in server (php -S localhost:8080 router.php).
// .htaccess hanya bekerja di Apache; script ini meniru fallback ke index.php.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== '/') {
    $file = __DIR__ . '/' . ltrim($path, '/');
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';