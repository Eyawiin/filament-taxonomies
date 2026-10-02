<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Filament\Forms\Components\Repeater;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class BrowserParentPage extends Page
{
    protected string $view = 'taxonomy-browser::parent-page';

    public ?array $data = [];

    public bool $disabled = false;

    public bool $readOnly = false;

    public function mount(): void
    {
        TaxonomyTerm::findOrFail(4)->update(['name' => 'France']);
        $this->form->fill(['first' => 2, 'second' => null, 'rows' => [['parent_id' => 1], ['parent_id' => null]]]);
    }

    public function form(Schema $schema): Schema
    {
        $taxonomy = Taxonomy::findOrFail(1);
        $term = TaxonomyTerm::findOrFail(2);

        return $schema->statePath('data')->components([
            TaxonomyParentSelect::make($taxonomy, $term)->name('first')->statePath('first')->label('First parent')
                ->disabled(fn (): bool => $this->disabled)->readOnly(fn (): bool => $this->readOnly)->live(),
            TaxonomyParentSelect::make($taxonomy)->name('second')->statePath('second')->label('Second parent')->live(onBlur: true),
            Repeater::make('rows')->schema([TaxonomyParentSelect::make($taxonomy)])->reorderable(false),
        ]);
    }

    public function setParent(): void
    {
        $this->data['first'] = 4;
    }

    public function save(): void
    {
        $this->form->getState();
    }

    public function rename(): void
    {
        TaxonomyTerm::findOrFail(4)->update(['name' => 'Updated parent']);
    }
}
