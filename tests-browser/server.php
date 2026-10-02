<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$public = dirname(__DIR__) . '/vendor/orchestra/testbench-core/laravel/public';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($public . $path)) {
    return false;
}
$app = require __DIR__ . '/bootstrap.php';
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
