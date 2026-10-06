<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/** @property-read Schema $form */
class RepeaterPage extends Page
{
    protected string $view = 'assignment-test::form';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public CollectionOwner $owner;

    public bool $jsonMode = false;

    public function mount(int $ownerId, bool $jsonMode = false): void
    {
        $this->owner = CollectionOwner::findOrFail($ownerId);
        $this->jsonMode = $jsonMode;
        $this->form->fill($jsonMode ? ['rows' => $this->owner->rows] : []);
    }

    public function form(Schema $schema): Schema
    {
        $field = TaxonomySelect::make('topic_ids')->taxonomy('demo-topics')->multiple();
        $repeater = Repeater::make($this->jsonMode ? 'rows' : 'decks')->schema([
            TextInput::make('name')->required(),
            $this->jsonMode ? $field->saved(false)->dehydrated() : $field,
        ]);
        if (! $this->jsonMode) {
            $repeater->relationship('decks');
        }

        return $schema->model($this->owner)->statePath('data')->components([$repeater]);
    }

    public function save(): void
    {
        DB::transaction(function (): void {
            $data = $this->form->getState();
            if ($this->jsonMode) {
                $this->owner->update(['rows' => $data['rows']]);
            }
        });
    }
}
