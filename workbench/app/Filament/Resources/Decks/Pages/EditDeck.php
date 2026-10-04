<?php

namespace Workbench\App\Filament\Resources\Decks\Pages;

use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\Decks\DeckResource;

class EditDeck extends EditRecord
{
    protected static string $resource = DeckResource::class;

    protected ?bool $hasDatabaseTransactions = true;
}
