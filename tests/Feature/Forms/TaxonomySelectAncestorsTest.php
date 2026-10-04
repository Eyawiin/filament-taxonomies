<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\FormFixture;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Decks\Pages\CreateDeck;
use Workbench\App\Filament\Resources\Decks\Pages\EditDeck;
use Workbench\Database\Factories\DeckFactory;

beforeEach(function (): void {
    foreach (FormFixture::create() as $key => $value) {
        $this->{$key} = $value;
    }
    $this->english = $this->topics->terms()->where('slug', 'english')->firstOrFail();
    $this->languages = $this->topics->terms()->where('slug', 'languages')->firstOrFail();
    $this->chain = [$this->languages->id, $this->english->id, $this->grammar->id];
});

it('requires the complete permitted ancestor chain only when enabled in multiple mode', function (): void {
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->container(Schema::make());
    expect($field->shouldSelectAncestors())->toBeFalse()
        ->and($field->acceptsSelection([$this->grammar->id]))->toBeTrue();
    $field->selectAncestors();
    expect($field->acceptsSelection([$this->grammar->id]))->toBeFalse()
        ->and($field->acceptsSelection($this->chain))->toBeTrue()
        ->and($field->acceptsSelection([]))->toBeTrue();
    $field->multiple(false);
    expect($field->shouldSelectAncestors())->toBeFalse()
        ->and($field->acceptsSelection($this->grammar->id))->toBeTrue();
});

it('disables descendants of a denied ancestor without preventing independent selection', function (): void {
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->selectAncestors()
        ->disableTermWhen(fn (TaxonomyTerm $term): bool => $term->id === $this->english->id)->container(Schema::make());
    $nodes = collect($field->getNodes())->keyBy('id');
    expect($nodes[$this->grammar->id]['disabled'])->toBeTrue()
        ->and($nodes[$this->grammar->id]['reason'])->toBe(__('filament-taxonomies::assignment-tree.ancestor_denied'))
        ->and($field->acceptsSelection($this->chain))->toBeFalse();
    $field->selectAncestors(false);
    expect($field->acceptsSelection([$this->grammar->id]))->toBeTrue();
});

it('expands hydrated assignments in form state and persists them only on save', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $deck->syncTaxonomyTerms($this->topics, [$this->grammar->id]);
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->selectAncestors()
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toEqualCanonicalizing($this->chain)
        ->and($deck->fresh()->termsForTaxonomy($this->topics)->get()->modelKeys())->toBe([$this->grammar->id]);
    $field->saveRelationships();
    expect($deck->fresh()->termsForTaxonomy($this->topics)->get()->modelKeys())->toEqualCanonicalizing($this->chain)
        ->and($deck->termsForTaxonomy($this->levels)->get()->modelKeys())->toBe([$this->a1->id]);
});

it('keeps read-only hydrated selections intact and cannot expose hidden ancestor branches', function (): void {
    $deck = DeckFactory::new()->create();
    $deck->syncTaxonomyTerms($this->topics, [$this->grammar->id]);
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->selectAncestors()->readOnly()
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toBe([$this->grammar->id]);
    TaxonomyTerm::addGlobalScope('hide-english', fn ($query) => $query->where('taxonomy_terms.slug', '!=', 'english'));
    $field->readOnly(false);
    expect($field->acceptsSelection($this->chain))->toBeFalse()
        ->and(array_column($field->getNodes(), 'id'))->not->toContain($this->grammar->id, $this->english->id);
});

it('uses field configuration and rejects child-only submissions before creating an owner', function (): void {
    Livewire::test(CreateDeck::class)->fillForm(['name' => 'Ancestor demo', 'topic_ids' => $this->chain])
        ->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateDeck::class)->fillForm(['name' => 'Invalid ancestor demo', 'topic_ids' => [$this->grammar->id]])
        ->call('create')->assertHasFormErrors(['topic_ids']);
});

it('revalidates hierarchy and ancestor permission under the save lock', function (string $change): void {
    $deck = DeckFactory::new()->create();
    $denied = false;
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->selectAncestors()
        ->disableTermWhen(function (TaxonomyTerm $term) use (&$denied): bool {
            return $denied && $term->id === $this->english->id;
        })
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->state($this->chain);
    expect($field->acceptsSelection($field->getState()))->toBeTrue();
    $service = Mockery::mock(TaxonomyTreeService::class)->makePartial();
    $service->shouldReceive('withTaxonomyLock')->once()->andReturnUsing(function ($taxonomy, $callback) use ($change, &$denied) {
        if ($change === 'permission') {
            $denied = true;
        } else {
            $this->grammar->update(['parent_id' => $this->algebra->id]);
        }

        return $callback($taxonomy->fresh());
    });
    app()->instance(TaxonomyTreeService::class, $service);
    expect(fn () => $field->saveRelationships())->toThrow(ValidationException::class)
        ->and($deck->fresh()->termsForTaxonomy($this->topics)->count())->toBe(0);
})->with(['permission', 'hierarchy']);
