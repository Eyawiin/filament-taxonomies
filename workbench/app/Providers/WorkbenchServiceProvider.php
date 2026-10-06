<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $config = $this->app->make(Repository::class);

        // Recent Testbench releases do this themselves; early 9.x releases keep the skeleton user model,
        // which Filament rejects outside the local environment.
        $config->set('auth.providers.users.model', User::class);

        if ($this->app->runningUnitTests()) {
            return;
        }

        $this->app->useStoragePath(dirname(__DIR__, 2) . '/storage');

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
