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
            'database.connections.sqlite.database' => database_path('database.sqlite'),
            'cache.default' => 'file',
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
