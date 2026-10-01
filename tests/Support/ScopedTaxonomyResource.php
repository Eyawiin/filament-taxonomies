<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Illuminate\Database\Eloquent\Builder;

class ScopedTaxonomyResource extends TaxonomyResource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('slug', '!=', 'private');
    }

    public static function getPages(): array
    {
        return [
            ...parent::getPages(),
            'manageTerms' => ScopedManageTaxonomyTerms::route('/{record}/manage-terms'),
        ];
    }
}
