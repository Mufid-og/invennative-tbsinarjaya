<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/middleware.php';

date_default_timezone_set('Asia/Jakarta');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

if (str_starts_with($uri, '/assets/') || str_starts_with($uri, '/favicon')) {
    return false;
}

$routes = [
    'GET /'                               => ['action' => 'middleware',    'auth' => 'guest'],
    'GET /login'                          => ['page' => 'login',           'auth' => 'guest'],
    'POST /login'                         => ['action' => 'auth',          'auth' => 'guest'],
    'GET /logout'                         => ['action' => 'auth',          'auth' => 'auth', 'do' => 'logout'],
    'GET /dashboard'                      => ['page' => 'dashboard',       'auth' => 'auth'],
    'GET /produk'                         => ['page' => 'produk',          'auth' => 'auth'],
    'POST /produk'                        => ['action' => 'produk',        'auth' => 'auth'],
    'GET /mutasi-stok'                    => ['page' => 'mutasi-stok',     'auth' => 'auth'],
    'GET /mutasi-stok/input'              => ['page' => 'mutasi-stok-input', 'auth' => 'auth'],
    'POST /mutasi-stok'                   => ['action' => 'mutasi',        'auth' => 'auth'],
    'POST /mutasi-stok/input'             => ['action' => 'mutasi',        'auth' => 'auth'],
    'GET /verifikasi-stok'                => ['page' => 'verifikasi-stok', 'auth' => 'auth'],
    'GET /verifikasi-stok/input'          => ['page' => 'verifikasi-stok-input', 'auth' => 'auth'],
    'POST /verifikasi-stok'               => ['action' => 'verifikasi',    'auth' => 'auth'],
    'POST /verifikasi-stok/input'         => ['action' => 'verifikasi',    'auth' => 'auth'],
    'GET /manajemen-user'                 => ['page' => 'manajemen-user',  'auth' => 'auth'],
    'POST /manajemen-user'                => ['action' => 'user',          'auth' => 'auth'],
    'GET /log-aktivitas'                  => ['page' => 'log-aktivitas',   'auth' => 'auth'],
    'GET /cetak-mutasi'                   => ['page' => 'cetak-mutasi',    'auth' => 'auth'],
    'GET /cetak-log'                      => ['page' => 'cetak-log',       'auth' => 'auth'],
    'GET /setting'                        => ['page' => 'setting',         'auth' => 'auth'],
    'POST /setting'                       => ['action' => 'setting',       'auth' => 'auth'],
    'GET /api/produk-cascade'             => ['api' => 'produk-cascade', 'auth' => 'auth'],
];

$routeKey = $method . ' ' . $uri;

if (!isset($routes[$routeKey])) {
    http_response_code(404);
    echo 'Halaman tidak ditemukan.';
    exit;
}

$route = $routes[$routeKey];

// var_dump($route);
// return;

if (($route['auth'] ?? '') === 'auth') {
    middleware_auth();
} elseif (($route['auth'] ?? '') === 'guest') {
    middleware_guest();
}

if (isset($route['do'])) {
    $_GET['do'] = $route['do'];
}

if (isset($route['action'])) {
    $actionFile = __DIR__ . '/actions/' . $route['action'] . '.php';
    if (!is_file($actionFile)) {
        http_response_code(500);
        echo 'Action not found.';
        exit;
    }
    require $actionFile;
    exit;
}

if (isset($route['api'])) {
    $apiFile = __DIR__ . '/api/' . $route['api'] . '.php';
    if (!is_file($apiFile)) {
        http_response_code(404);
        echo 'API not found.';
        exit;
    }
    require $apiFile;
    exit;
}

$pageFile = __DIR__ . '/pages/' . $route['page'] . '.php';
if (!is_file($pageFile)) {
    http_response_code(404);
    echo 'Page not found.';
    exit;
}

require $pageFile;
