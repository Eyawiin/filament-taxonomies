<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$public = dirname(__DIR__) . '/vendor/orchestra/testbench-core/laravel/public';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($public . $path)) {
    return false;
}
$start = hrtime(true);
$app = require __DIR__ . '/bootstrap.php';
$queries = 0;
DB::listen(function () use (&$queries): void {
    $queries++;
});
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request = Request::capture());
$response->headers->set('X-Performance-Queries', (string) $queries);
$response->headers->set('X-Performance-Server-Ms', (string) ((hrtime(true) - $start) / 1e6));
$response->headers->set('X-Performance-Html-Bytes', (string) strlen($response->getContent()));
$response->send();
$kernel->terminate($request, $response);
