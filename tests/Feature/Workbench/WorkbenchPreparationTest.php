<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->fixtureRoot = dirname(__DIR__, 3) . '/build/workbench-prepare-' . bin2hex(random_bytes(6));
    mkdir($this->fixtureRoot . '/bin', 0755, true);
    copy(dirname(__DIR__, 3) . '/bin/prepare-workbench.php', $this->fixtureRoot . '/bin/prepare-workbench.php');
});

afterEach(function (): void {
    $expected = dirname(__DIR__, 3) . '/build/workbench-prepare-';
    if (str_starts_with($this->fixtureRoot, $expected) && is_dir($this->fixtureRoot)) {
        (new Filesystem)->deleteDirectory($this->fixtureRoot);
    }
});

it('creates a durable database and preserves its records across preparation', function (): void {
    (new Process([PHP_BINARY, $this->fixtureRoot . '/bin/prepare-workbench.php']))->mustRun();
    $database = $this->fixtureRoot . '/workbench/database/database.sqlite';
    expect(is_file($database) && is_writable($database))->toBeTrue();
    $connection = new PDO('sqlite:' . $database);
    $connection->exec("CREATE TABLE sentinel (value TEXT); INSERT INTO sentinel VALUES ('preserve me')");
    (new Process([PHP_BINARY, $this->fixtureRoot . '/bin/prepare-workbench.php']))->mustRun();
    expect($connection->query('SELECT value FROM sentinel')->fetchColumn())->toBe('preserve me');
});

it('preserves committed legacy WAL data before Testbench cleanup and never replaces existing data', function (): void {
    $legacyDirectory = $this->fixtureRoot . '/vendor/orchestra/testbench-core/laravel/database';
    mkdir($legacyDirectory, 0755, true);
    $legacy = $legacyDirectory . '/database.sqlite';
    $connection = new PDO('sqlite:' . $legacy);
    $connection->exec('PRAGMA journal_mode=WAL; PRAGMA wal_autocheckpoint=0');
    $connection->exec("CREATE TABLE sentinel (value TEXT); INSERT INTO sentinel VALUES ('committed WAL data')");
    expect(is_file($legacy . '-wal'))->toBeTrue();
    (new Process([PHP_BINARY, $this->fixtureRoot . '/bin/prepare-workbench.php']))->mustRun();
    $durable = new PDO('sqlite:' . $this->fixtureRoot . '/workbench/database/database.sqlite');
    expect($durable->query('SELECT value FROM sentinel')->fetchColumn())->toBe('committed WAL data')
        ->and($connection->query('SELECT value FROM sentinel')->fetchColumn())->toBe('committed WAL data');
    $connection->exec("UPDATE sentinel SET value = 'changed legacy'");
    (new Process([PHP_BINARY, $this->fixtureRoot . '/bin/prepare-workbench.php']))->mustRun();
    expect($durable->query('SELECT value FROM sentinel')->fetchColumn())->toBe('committed WAL data');
    $connection = null;
    $durable = null;
});

it('fails without replacing a corrupt legacy database or retaining an incomplete snapshot', function (): void {
    $directory = $this->fixtureRoot . '/vendor/orchestra/testbench-core/laravel/database';
    mkdir($directory, 0755, true);
    file_put_contents($directory . '/database.sqlite', 'not a SQLite database');
    $process = new Process([PHP_BINARY, $this->fixtureRoot . '/bin/prepare-workbench.php']);
    $process->run();
    expect($process->isSuccessful())->toBeFalse()
        ->and(file_get_contents($directory . '/database.sqlite'))->toBe('not a SQLite database')
        ->and(file_exists($this->fixtureRoot . '/workbench/database/database.sqlite'))->toBeFalse()
        ->and(glob($this->fixtureRoot . '/workbench/database/*.snapshot-*'))->toBe([]);
});

it('rejects an existing directory where the durable database file belongs', function (): void {
    mkdir($this->fixtureRoot . '/workbench/database/database.sqlite', 0755, true);
    $process = new Process([PHP_BINARY, $this->fixtureRoot . '/bin/prepare-workbench.php']);
    $process->run();
    expect($process->isSuccessful())->toBeFalse()
        ->and(is_dir($this->fixtureRoot . '/workbench/database/database.sqlite'))->toBeTrue();
});
