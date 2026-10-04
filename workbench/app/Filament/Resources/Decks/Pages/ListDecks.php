<?php

namespace Workbench\App\Filament\Resources\Decks\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\Decks\DeckResource;

class ListDecks extends ListRecords
{
    protected static string $resource = DeckResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
