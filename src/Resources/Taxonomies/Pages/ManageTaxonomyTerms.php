<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageTaxonomyTerms extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TaxonomyResource::class;

    protected string $view = 'filament-taxonomies::resources.taxonomies.pages.manage-taxonomy-terms';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getRecord(): Taxonomy
    {
        /** @var Taxonomy $record */
        $record = $this->record;

        return $record;
    }

    public function getHeading(): string
    {
        return "Manage Terms: {$this->getRecord()->name}";
    }
}
