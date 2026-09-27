<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxonomy extends CreateRecord
{
    protected static string $resource = TaxonomyResource::class;
}
