<?php

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;

require dirname(__DIR__) . '/vendor/autoload.php';

function concurrencyDatabaseConfig(?string $database = null): array
{
    $host = getenv('TAXONOMY_MYSQL_HOST');
    $user = getenv('TAXONOMY_MYSQL_USER');
    if (! $host || ! $user) {
        throw new RuntimeException('Set TAXONOMY_MYSQL_HOST and TAXONOMY_MYSQL_USER for a disposable MySQL 8.4 server.');
    }

    return [
        'driver' => 'mysql', 'host' => $host,
        'port' => (int) (getenv('TAXONOMY_MYSQL_PORT') ?: 3306),
        'username' => $user, 'password' => getenv('TAXONOMY_MYSQL_PASSWORD') ?: '',
        'database' => $database, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
        'options' => [PDO::ATTR_TIMEOUT => 10],
    ];
}

function bootConcurrencyDatabase(string $database): Manager
{
    if (! preg_match('/^filament_taxonomies_f2_[a-f0-9]{12}$/', $database)) {
        throw new RuntimeException('The concurrency runner only uses its own randomly named disposable database.');
    }
    $container = new Container;
    Container::setInstance($container);
    $capsule = new Manager($container);
    $capsule->addConnection(concurrencyDatabaseConfig($database));
    $capsule->setEventDispatcher(new Dispatcher($container));
    $container->instance('db', $capsule->getDatabaseManager());
    $container->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
    Facade::setFacadeApplication($container);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $capsule->getConnection()->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $capsule->getConnection()->statement('SET SESSION innodb_lock_wait_timeout = 15');

    return $capsule;
}
