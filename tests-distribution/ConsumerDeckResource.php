<?php

namespace App\Filament;

use App\Models\ConsumerDeck;
use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsumerDeckResource extends Resource
{
    protected static ?string $model = ConsumerDeck::class;

    protected static ?string $slug = 'decks';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TaxonomySelect::make('topics')->taxonomy('consumer-field-topics')->multiple(),
            TaxonomySelect::make('level')->taxonomy('consumer-field-levels'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListConsumerDecks::route('/'), 'create' => CreateConsumerDeck::route('/create'), 'edit' => EditConsumerDeck::route('/{record}/edit')];
    }
}

class CreateConsumerDeck extends CreateRecord
{
    protected static string $resource = ConsumerDeckResource::class;

    protected ?bool $hasDatabaseTransactions = true;
}

class EditConsumerDeck extends EditRecord
{
    protected static string $resource = ConsumerDeckResource::class;

    protected ?bool $hasDatabaseTransactions = true;
}

class ListConsumerDecks extends ListRecords
{
    protected static string $resource = ConsumerDeckResource::class;
}
