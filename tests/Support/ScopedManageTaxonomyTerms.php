<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;

class ScopedManageTaxonomyTerms extends ManageTaxonomyTerms
{
    protected static string $resource = ScopedTaxonomyResource::class;
}
