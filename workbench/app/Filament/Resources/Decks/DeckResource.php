<?php

namespace Workbench\App\Filament\Resources\Decks;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Decks\Pages\CreateDeck;
use Workbench\App\Filament\Resources\Decks\Pages\EditDeck;
use Workbench\App\Filament\Resources\Decks\Pages\ListDecks;
use Workbench\App\Models\Deck;
use Workbench\Database\Factories\LargeDemoTaxonomyFactory;

class DeckResource extends Resource
{
    protected static ?string $model = Deck::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $components = [
            TextInput::make('name')->required()->maxLength(255),
            Textarea::make('description'),
            TaxonomySelect::make('topic_ids')->label('Topics')->taxonomy('demo-topics')->multiple()
                ->selectAncestors(),
            TaxonomySelect::make('level_id')->label('Level')->taxonomy('demo-levels'),
        ];
        // Omit the optional relationship field entirely until its taxonomy exists.
        // Hidden fields can still hydrate their relationship state.
        if (Taxonomy::where('slug', LargeDemoTaxonomyFactory::SLUG)->exists()) {
            $components[] = TaxonomySelect::make('large_tree_ids')->label('Large tree playground')
                ->taxonomy(LargeDemoTaxonomyFactory::SLUG)->multiple()->selectAncestors()
                ->helperText('534 terms across six roots, including a branch with 25 levels. Search “Overview” to compare identical names.');
        }

        return $schema->components($components);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('updated_at')->dateTime()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListDecks::route('/'), 'create' => CreateDeck::route('/create'), 'edit' => EditDeck::route('/{record}/edit')];
    }
}
