<?php

use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesServiceProvider;
use Illuminate\Support\ServiceProvider;

it('registers the advertised config publish tag with a real source file', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        FilamentTaxonomiesServiceProvider::class,
        'filament-taxonomies-config',
    );

    expect($paths)->toHaveCount(1);

    foreach ($paths as $source => $destination) {
        expect(is_file($source))->toBeTrue()
            ->and($destination)->toBe(config_path('filament-taxonomies.php'));
    }
});

it('merges package configuration under the documented config key', function (): void {
    expect(config('filament-taxonomies'))->toBeArray();
});
