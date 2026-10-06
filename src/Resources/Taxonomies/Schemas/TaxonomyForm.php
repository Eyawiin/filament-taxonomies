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
                    ->required()
                    ->maxLength(255)
                    ->helperText('A readable name, such as Topics or Difficulty'),

                TextInput::make('slug')
                    ->unique(table: Taxonomy::class, ignoreRecord: true)
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
