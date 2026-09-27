<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas\TaxonomyForm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Tables\TaxonomiesTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TaxonomyResource extends Resource
{
    protected static ?string $model = Taxonomy::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Taxonomies';

    protected static ?string $slug = 'taxonomies';

    public static function form(Schema $schema): Schema
    {
        return TaxonomyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxonomiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxonomies::route('/'),
            'create' => CreateTaxonomy::route('/create'),
            'edit' => EditTaxonomy::route('/{record}/edit'),
        ];
    }
}
