<?php

namespace Workbench\App\Providers\Filament;

use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesPlugin;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Workbench\App\Filament\Resources\Decks\DeckResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([DeckResource::class])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->pages([
                Dashboard::class,
            ])
            ->plugin(
                FilamentTaxonomiesPlugin::make(),
            );
    }
}
