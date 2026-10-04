<?php

$root = dirname(__DIR__);
$storage = $root . '/workbench/storage';

// Remove only the legacy link itself; never follow it or delete its target.
if (is_link($storage)) {
    if (! unlink($storage)) {
        throw new RuntimeException('Cannot remove the legacy workbench storage link.');
    }
}

if (is_file($storage)) {
    throw new RuntimeException('workbench/storage is a file; move it aside before preparing the workbench.');
}

foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    $path = $storage . '/' . $directory;
    if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
        throw new RuntimeException("Cannot create workbench directory: {$path}");
    }
}

// Keep development data outside the disposable Testbench skeleton and storage purge.
$databaseDirectory = $root . '/workbench/database';
if (! is_dir($databaseDirectory) && ! mkdir($databaseDirectory, 0755, true) && ! is_dir($databaseDirectory)) {
    throw new RuntimeException('Cannot create the workbench database directory.');
}
$database = $databaseDirectory . '/database.sqlite';
if (! file_exists($database)) {
    $legacy = $root . '/vendor/orchestra/testbench-core/laravel/database/database.sqlite';
    if (is_file($legacy)) {
        // A SQLite snapshot includes committed WAL data; copying only the main file can lose it.
        $connection = new PDO('sqlite:' . $legacy, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $snapshot = $database . '.snapshot-' . bin2hex(random_bytes(6));

        try {
            $connection->prepare('VACUUM INTO ?')->execute([$snapshot]);
            if (! rename($snapshot, $database)) {
                throw new RuntimeException('Cannot install the workbench database snapshot.');
            }
        } finally {
            if (is_file($snapshot)) {
                unlink($snapshot);
            }
        }
    } else {
        $handle = fopen($database, 'x');
        if ($handle === false) {
            throw new RuntimeException('Cannot create the workbench SQLite database.');
        }
        fclose($handle);
    }
}
if (! is_file($database) || ! is_writable($database)) {
    throw new RuntimeException('The workbench SQLite database must be a writable file.');
}
