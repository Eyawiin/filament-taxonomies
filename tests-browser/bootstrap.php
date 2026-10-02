<?php

use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesServiceProvider;
use Eyawiin\FilamentTaxonomies\Tests\Support\BrowserPanelProvider;
use Orchestra\Testbench\Foundation\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

class BrowserApplication extends Application
{
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set([
            'app.providers' => array_values(array_filter(
                $app['config']->get('app.providers'),
                fn (string $provider): bool => ! str_starts_with($provider, 'Workbench\\'),
            )),
            'app.key' => 'base64:' . base64_encode(str_repeat('b', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => dirname(__DIR__) . '/build/browser.sqlite',
            'cache.default' => 'array',
            'session.driver' => 'file',
            'app.debug' => true,
        ]);
    }
}

$app = BrowserApplication::create(
    options: ['extra' => ['providers' => [FilamentTaxonomiesServiceProvider::class, BrowserPanelProvider::class]]],
    resolvingCallback: function ($app): void {
        $app->booted(fn () => $app['view']->addNamespace('taxonomy-browser', __DIR__ . '/views'));
    },
);

if ($app['config']->get('database.connections.sqlite.database') !== dirname(__DIR__) . '/build/browser.sqlite') {
    throw new RuntimeException('Browser fixture refuses to use any database outside build/browser.sqlite.');
}

return $app;
