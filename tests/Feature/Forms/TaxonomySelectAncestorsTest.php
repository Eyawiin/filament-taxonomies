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
    $this->disney = $this->themes->terms()->where('slug', 'disney')->firstOrFail();
    $this->popCulture = $this->themes->terms()->where('slug', 'pop-culture')->firstOrFail();
    $this->chain = [$this->popCulture->id, $this->disney->id, $this->liloAndStitch->id];
});

it('requires the complete permitted ancestor chain only when enabled in multiple mode', function (): void {
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->container(Schema::make());
    expect($field->shouldSelectAncestors())->toBeFalse()
        ->and($field->acceptsSelection([$this->liloAndStitch->id]))->toBeTrue();
    $field->selectAncestors();
    expect($field->acceptsSelection([$this->liloAndStitch->id]))->toBeFalse()
        ->and($field->acceptsSelection($this->chain))->toBeTrue()
        ->and($field->acceptsSelection([]))->toBeTrue();
    $field->multiple(false);
    expect($field->shouldSelectAncestors())->toBeFalse()
        ->and($field->acceptsSelection($this->liloAndStitch->id))->toBeTrue();
});

it('disables descendants of a denied ancestor without preventing independent selection', function (): void {
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->selectAncestors()
        ->disableTermWhen(fn (TaxonomyTerm $term): bool => $term->id === $this->disney->id)->container(Schema::make());
    $nodes = collect($field->getNodes())->keyBy('id');
    expect($nodes[$this->liloAndStitch->id]['disabled'])->toBeTrue()
        ->and($nodes[$this->liloAndStitch->id]['reason'])->toBe(__('filament-taxonomies::assignment-tree.ancestor_denied'))
        ->and($field->acceptsSelection($this->chain))->toBeFalse();
    $field->selectAncestors(false);
    expect($field->acceptsSelection([$this->liloAndStitch->id]))->toBeTrue();
});

it('expands hydrated assignments in form state and persists them only on save', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $deck->syncTaxonomyTerms($this->themes, [$this->liloAndStitch->id]);
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->selectAncestors()
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toEqualCanonicalizing($this->chain)
        ->and($deck->fresh()->termsForTaxonomy($this->themes)->get()->modelKeys())->toBe([$this->liloAndStitch->id]);
    $field->saveRelationships();
    expect($deck->fresh()->termsForTaxonomy($this->themes)->get()->modelKeys())->toEqualCanonicalizing($this->chain)
        ->and($deck->termsForTaxonomy($this->brands)->get()->modelKeys())->toBe([$this->riderBackRed->id]);
});

it('keeps read-only hydrated selections intact and cannot expose hidden ancestor branches', function (): void {
    $deck = DeckFactory::new()->create();
    $deck->syncTaxonomyTerms($this->themes, [$this->liloAndStitch->id]);
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->selectAncestors()->readOnly()
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toBe([$this->liloAndStitch->id]);
    TaxonomyTerm::addGlobalScope('hide-disney', fn ($query) => $query->where('taxonomy_terms.slug', '!=', 'disney'));
    $field->readOnly(false);
    expect($field->acceptsSelection($this->chain))->toBeFalse()
        ->and(array_column($field->getNodes(), 'id'))->not->toContain($this->liloAndStitch->id, $this->disney->id);
});

it('uses field configuration and rejects child-only submissions before creating an owner', function (): void {
    Livewire::test(CreateDeck::class)->fillForm(['name' => 'Ancestor demo', 'theme_ids' => $this->chain])
        ->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateDeck::class)->fillForm(['name' => 'Invalid ancestor demo', 'theme_ids' => [$this->liloAndStitch->id]])
        ->call('create')->assertHasFormErrors(['theme_ids']);
});

it('revalidates hierarchy and ancestor permission under the save lock', function (string $change): void {
    $deck = DeckFactory::new()->create();
    $denied = false;
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->selectAncestors()
        ->disableTermWhen(function (TaxonomyTerm $term) use (&$denied): bool {
            return $denied && $term->id === $this->disney->id;
        })
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->state($this->chain);
    expect($field->acceptsSelection($field->getState()))->toBeTrue();
    $service = Mockery::mock(TaxonomyTreeService::class)->makePartial();
    $service->shouldReceive('withTaxonomyLock')->once()->andReturnUsing(function ($taxonomy, $callback) use ($change, &$denied) {
        if ($change === 'permission') {
            $denied = true;
        } else {
            $this->liloAndStitch->update(['parent_id' => $this->wolves->id]);
        }

        return $callback($taxonomy->fresh());
    });
    app()->instance(TaxonomyTreeService::class, $service);
    expect(fn () => $field->saveRelationships())->toThrow(ValidationException::class)
        ->and($deck->fresh()->termsForTaxonomy($this->themes)->count())->toBe(0);
})->with(['permission', 'hierarchy']);
