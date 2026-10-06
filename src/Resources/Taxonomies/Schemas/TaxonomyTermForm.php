<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

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
                ->label(__('filament-taxonomies::taxonomies.fields.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->label(__('filament-taxonomies::taxonomies.fields.slug'))
                ->unique(
                    table: TaxonomyModels::term(),
                    ignorable: $term,
                    ignoreRecord: false,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('taxonomy_id', $taxonomy->getKey()),
                )
                ->required()
                ->maxLength(255),

            TaxonomyParentSelect::make($taxonomy, $term, $resource),
        ]);
    }
}
