<?php

$root = dirname(__DIR__);
$consumer = trim(file_get_contents($root . '/build/consumer-path.txt'));
$resolved = realpath($consumer);
// realpath() returns native separators, so compare with a resolved prefix on every OS.
$fixtures = realpath($root . '/build') . DIRECTORY_SEPARATOR . 'distribution-';
if (! $resolved || ! str_starts_with($resolved, $fixtures)) {
    throw new RuntimeException('Consumer server refuses any application outside its generated distribution fixture.');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($consumer . '/public' . $path)) {
    return false;
}
require $consumer . '/public/index.php';
