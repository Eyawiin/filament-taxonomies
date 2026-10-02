<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;

class PerformanceParentPage extends Page
{
    protected string $view = 'taxonomy-performance::parent-page';

    #[Locked]
    public string $fixture = 'broad-100';

    public ?array $data = [];

    public function mount(): void
    {
        $this->fixture = (string) request()->query('fixture', 'broad-100');
        $this->form->fill(['parent_id' => null]);
    }

    public function form(Schema $schema): Schema
    {
        $taxonomy = Taxonomy::where('slug', $this->fixture)->firstOrFail();

        return $schema->statePath('data')->components([
            TaxonomyParentSelect::make($taxonomy, $taxonomy->terms()->orderBy('id')->firstOrFail()),
        ]);
    }
}
