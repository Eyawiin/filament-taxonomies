<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Filament\Panel;
use Workbench\App\Providers\Filament\AdminPanelProvider;

class PerformancePanelProvider extends AdminPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)->pages([PerformanceParentPage::class]);
    }
}
