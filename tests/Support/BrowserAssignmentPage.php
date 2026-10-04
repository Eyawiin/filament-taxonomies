<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Workbench\App\Models\Deck;

class BrowserAssignmentPage extends Page
{
    protected string $view = 'taxonomy-browser::assignment-page';

    public ?array $data = [];

    public bool $disabled = false;

    public bool $readOnly = false;

    public function mount(): void
    {
        $grammar = TaxonomyTerm::where('slug', 'grammar')->firstOrFail();
        $this->form->fill(['topics' => [$grammar->id], 'rows' => [['topic_ids' => []], ['topic_ids' => []]]]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TaxonomySelect::make('topics')->taxonomy('demo-topics')->multiple()->live()
                ->disabled(fn (): bool => $this->disabled)->readOnly(fn (): bool => $this->readOnly)
                ->disableTermWhen(fn (TaxonomyTerm $term): bool => $term->slug === 'algebra'),
            Repeater::make('rows')->schema([
                TaxonomySelect::make('topic_ids')->taxonomy('demo-topics')->multiple()->saved(false)->dehydrated(),
            ])->reorderable(false),
        ]);
    }

    public function save(): void
    {
        $this->form->getState();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('assign')->label('Edit deck assignments')
            ->record(fn () => Deck::where('demo_key', 'deck-0')->firstOrFail())
            ->schema([TaxonomySelect::make('topic_ids')->taxonomy('demo-topics')->multiple()])
            ->modalSubmitActionLabel('Save assignments')
            ->databaseTransaction()
            ->action(fn () => null)];
    }

    public function removeSelection(): void
    {
        TaxonomyTerm::where('slug', 'grammar')->update(['name' => 'Updated grammar']);
    }
}
