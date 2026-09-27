<?php

namespace Eyawiin\FilamentTaxonomies;

use Eyawiin\FilamentTaxonomies\Pages\Taxonomies;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentTaxonomiesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-taxonomies';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            Taxonomies::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
