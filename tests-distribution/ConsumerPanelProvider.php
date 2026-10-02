<?php

namespace App\Providers;

use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesPlugin;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ConsumerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, SubstituteBindings::class])
            ->plugin(FilamentTaxonomiesPlugin::make());
    }
}
