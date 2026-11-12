<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\CollectionOwner;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\FormFixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\RepeaterPage;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Decks\Pages\EditDeck;
use Workbench\Database\Factories\DeckFactory;

beforeEach(function (): void {
    foreach (FormFixture::create() as $key => $value) {
        $this->{$key} = $value;
    }
});

it('skips saving forged state in a disabled or read-only field', function (string $mode): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'))->{$mode}();
    $field->state([]);
    $field->saveRelationships();
    expect($deck->fresh()->termsForTaxonomy($this->themes)->count())->toBe(2);
})->with(['disabled', 'readOnly']);

it('persists relationship repeater assignments on each existing and new row owner', function (): void {
    FormFixture::createCollectionSchema();
    $owner = CollectionOwner::create();
    $deck = $owner->decks()->create(['name' => 'Existing row']);
    $deck->syncTaxonomyTerms($this->themes, [$this->liloAndStitch->id]);
    $test = Livewire::test(RepeaterPage::class, ['ownerId' => $owner->id]);
    $rows = $test->get('data.decks');
    $key = array_key_first($rows);
    expect($rows[$key]['theme_ids'])->toBe([$this->liloAndStitch->id]);
    $rows[$key]['theme_ids'] = [$this->wolves->id];
    $rows['new-row'] = ['name' => 'New row', 'theme_ids' => [$this->liloAndStitch->id]];
    $test->set('data.decks', $rows)->call('save')->assertHasNoFormErrors();
    $new = $owner->decks()->where('name', 'New row')->firstOrFail();
    expect($deck->fresh()->termsForTaxonomy($this->themes)->pluck('taxonomy_terms.id')->all())->toBe([$this->wolves->id])
        ->and($new->termsForTaxonomy($this->themes)->pluck('taxonomy_terms.id')->all())->toBe([$this->liloAndStitch->id])
        ->and($owner->fresh()->taxonomyTerms->count())->toBe(0);
});

it('keeps state-only JSON repeater selections in their rows without hydrating or saving root assignments', function (): void {
    FormFixture::createCollectionSchema();
    $owner = CollectionOwner::create(['rows' => [
        ['name' => 'First JSON', 'theme_ids' => [$this->liloAndStitch->id]],
        ['name' => 'Second JSON', 'theme_ids' => []],
    ]]);
    $owner->syncTaxonomyTerms($this->themes, [$this->wolves->id]);
    $test = Livewire::test(RepeaterPage::class, ['ownerId' => $owner->id, 'jsonMode' => true]);
    $rows = $test->get('data.rows');
    $keys = array_keys($rows);
    expect($rows[$keys[0]]['theme_ids'])->toBe([$this->liloAndStitch->id]);
    $rows[$keys[1]]['theme_ids'] = [$this->liloAndStitch->id];
    $test->set('data.rows', $rows)->call('save')->assertHasNoFormErrors();
    expect($owner->fresh()->rows[1]['theme_ids'])->toBe([$this->liloAndStitch->id])
        ->and($owner->fresh()->termsForTaxonomy($this->themes)->pluck('taxonomy_terms.id')->all())->toBe([$this->wolves->id]);
});

it('skips saving hidden field state', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->hidden()
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->state([]);
    $field->saveRelationships();
    expect($deck->fresh()->termsForTaxonomy($this->themes)->count())->toBe(2);
});

it('rejects automatic JSON row assignments even when a custom form validates without rendering first', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $component = Livewire::test(EditDeck::class, ['record' => $deck->id])->instance();
    $schema = Schema::make($component)->model($deck)->statePath('data')->components([
        Repeater::make('rows')->schema([
            TaxonomySelect::make('theme_ids')->taxonomy($this->themes)->multiple(),
        ]),
    ]);
    $schema->fill(['rows' => [['theme_ids' => []]]]);
    expect(fn () => $schema->getState())->toThrow(LogicException::class, 'JSON repeater')
        ->and($deck->fresh()->termsForTaxonomy($this->themes)->count())->toBe(2);
});
