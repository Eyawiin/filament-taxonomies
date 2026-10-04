<?php

namespace Workbench\App\Filament\Resources\Decks\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Decks\DeckResource;

class CreateDeck extends CreateRecord
{
    protected static string $resource = DeckResource::class;

    protected ?bool $hasDatabaseTransactions = true;
}
