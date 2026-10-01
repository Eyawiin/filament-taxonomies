<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaxonomyTermForm
{
    /**
     * @param  class-string<TaxonomyResource>  $resource
     */
    public static function configure(
        Schema $schema,
        Taxonomy $taxonomy,
        ?TaxonomyTerm $term = null,
        string $resource = TaxonomyResource::class,
    ): Schema {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->required()
                ->maxLength(255),

            TaxonomyParentSelect::make($taxonomy, $term, $resource),
        ]);
    }
}
