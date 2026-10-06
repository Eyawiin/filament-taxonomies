<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $this->app->useStoragePath(dirname(__DIR__, 2) . '/storage');

        $config = $this->app->make(Repository::class);

        $config->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => dirname(__DIR__, 2) . '/database/database.sqlite',
            'cache.default' => 'file',
            // Composer's skeleton purge deletes Testbench's .env, whose absence means no app key and
            // non-persistent array sessions; Herd serves requests without rebuilding it. Pin both so
            // the workbench behaves the same with or without that file.
            'app.key' => 'base64:' . base64_encode(str_repeat('w', 32)),
            'app.debug' => true,
            'session.driver' => 'file',
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
