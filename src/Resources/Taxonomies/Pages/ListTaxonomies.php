<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxonomies extends ListRecords
{
    protected static string $resource = TaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
