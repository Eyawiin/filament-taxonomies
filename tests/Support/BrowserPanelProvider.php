<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\Deck;
use Workbench\App\Providers\Filament\AdminPanelProvider;

class BrowserPanelProvider extends AdminPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)->pages([BrowserParentPage::class, BrowserAssignmentPage::class]);
    }

    public function boot(): void
    {
        Route::get('/__assignment-state', function (): array {
            return Deck::orderBy('id')->get()->map(fn ($deck): array => [
                'id' => $deck->id, 'name' => $deck->name,
                'topics' => $deck->termsForTaxonomy('demo-topics')->pluck('taxonomy_terms.name')->all(),
                'levels' => $deck->termsForTaxonomy('demo-levels')->pluck('taxonomy_terms.name')->all(),
            ])->all();
        });
    }
}
