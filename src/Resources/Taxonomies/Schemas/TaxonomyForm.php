<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaxonomyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament-taxonomies::taxonomies.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->helperText(__('filament-taxonomies::taxonomies.fields.name_helper')),

                TextInput::make('slug')
                    ->label(__('filament-taxonomies::taxonomies.fields.slug'))
                    ->unique(table: Taxonomy::class, ignoreRecord: true)
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
