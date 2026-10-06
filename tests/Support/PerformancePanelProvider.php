<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Filament\Panel;
use Workbench\App\Providers\Filament\AdminPanelProvider;

class PerformancePanelProvider extends AdminPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->configurePanel($panel)->pages([PerformanceParentPage::class]);
    }
}
