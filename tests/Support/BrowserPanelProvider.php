<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\Deck;
use Workbench\App\Providers\Filament\AdminPanelProvider;

class BrowserPanelProvider extends AdminPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->configurePanel($panel)->pages([BrowserParentPage::class, BrowserAssignmentPage::class]);
    }

    public function boot(): void
    {
        Route::get('/__assignment-state', function (): array {
            return Deck::orderBy('id')->get()->map(fn ($deck): array => [
                'id' => $deck->id, 'name' => $deck->name, 'demo_key' => $deck->demo_key,
                'topics' => $deck->termsForTaxonomy('demo-topics')->pluck('taxonomy_terms.name')->all(),
                'levels' => $deck->termsForTaxonomy('demo-levels')->pluck('taxonomy_terms.name')->all(),
                'large_tree' => $deck->termsForTaxonomy('demo-large-tree')->pluck('taxonomy_terms.id')->all(),
            ])->all();
        });
        Route::get('/__taxonomy-terms/{slug}', function (string $slug): array {
            $taxonomy = Taxonomy::where('slug', $slug)->firstOrFail();

            return ['id' => $taxonomy->id, 'terms' => $taxonomy->terms()->orderBy('parent_id')->orderBy('position')
                ->get(['id', 'name', 'slug', 'parent_id', 'position'])->all()];
        });
    }
}
