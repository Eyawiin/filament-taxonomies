<?php

namespace Eyawiin\FilamentTaxonomies;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTaxonomiesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-taxonomies';

    public static string $viewNamespace = 'filament-taxonomies';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name(static::$name)
            ->hasViews(static::$viewNamespace)
            ->hasConfigFile()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName(),
        );

    }

    protected function getAssetPackageName(): ?string
    {
        return 'eyawiin/filament-taxonomies';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            Css::make('taxonomy-controls', __DIR__ . '/../resources/css/taxonomy-controls.css'),
            AlpineComponent::make(
                'taxonomy-parent-tree',
                __DIR__ . '/../resources/dist/components/taxonomy-parent-tree.js',
            ),
            AlpineComponent::make(
                'taxonomy-tree',
                __DIR__ . '/../resources/dist/components/taxonomy-tree.js',
            ),
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            '0001_create_taxonomies_table',
            '0002_create_taxonomy_terms_table',
            '0003_add_position_to_taxonomy_terms_table',
            '0004_create_taxonomy_term_assignments_table',
        ];
    }
}
