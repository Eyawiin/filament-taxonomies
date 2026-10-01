<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Resources\Pages\EditRecord;

class EditTaxonomy extends EditRecord
{
    protected static string $resource = TaxonomyResource::class;

    public function hydrate(): void
    {
        // Restore the resource query scope before Filament authorizes subsequent requests.
        $this->record = $this->resolveRecord($this->getRecord()->getRouteKey());

        parent::hydrate();
    }
}
