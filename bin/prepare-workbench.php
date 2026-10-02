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
