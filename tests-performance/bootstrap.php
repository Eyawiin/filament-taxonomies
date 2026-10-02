<?php

use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesServiceProvider;
use Eyawiin\FilamentTaxonomies\Tests\Support\PerformancePanelProvider;
use Orchestra\Testbench\Foundation\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

class PerformanceApplication extends Application
{
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);
        $app['config']->set([
            'app.providers' => array_values(array_filter(
                $app['config']->get('app.providers'),
                fn (string $provider): bool => ! str_starts_with($provider, 'Workbench\\'),
            )),
            'app.key' => 'base64:' . base64_encode(str_repeat('p', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => dirname(__DIR__) . '/build/performance.sqlite',
            'cache.default' => 'array',
            'session.driver' => 'file',
            'app.debug' => true,
        ]);
    }
}

$app = PerformanceApplication::create(
    options: ['extra' => ['providers' => [FilamentTaxonomiesServiceProvider::class, PerformancePanelProvider::class]]],
    resolvingCallback: fn ($app) => $app->booted(fn () => $app['view']->addNamespace('taxonomy-performance', __DIR__ . '/views')),
);
if ($app['config']->get('database.connections.sqlite.database') !== dirname(__DIR__) . '/build/performance.sqlite') {
    throw new RuntimeException('Performance fixture refuses any database outside build/performance.sqlite.');
}

return $app;
