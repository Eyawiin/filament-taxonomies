<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelectionRule;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\FormFixture;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Decks\Pages\CreateDeck;
use Workbench\App\Filament\Resources\Decks\Pages\EditDeck;
use Workbench\App\Models\Deck;
use Workbench\Database\Factories\DeckFactory;

beforeEach(function (): void {
    foreach (FormFixture::create() as $key => $value) {
        $this->{$key} = $value;
    }
});

it('rejects forged foreign and malformed selections before an owner is created', function (string $kind): void {
    $value = match ($kind) {
        'foreign' => [$this->a1->id],
        'unknown' => [99999],
        'boolean' => [true],
        'duplicate' => [$this->grammar->id, (string) $this->grammar->id],
        'scalar' => $this->grammar->id,
        'associative' => ['id' => $this->grammar->id],
        'large' => ['9007199254740992'],
    };
    Livewire::test(CreateDeck::class)->fillForm(['name' => 'Forged', 'topic_ids' => $value])
        ->call('create')->assertHasFormErrors(['topic_ids']);
    expect(Deck::where('name', 'Forged')->exists())->toBeFalse();
})->with(['foreign', 'unknown', 'boolean', 'duplicate', 'scalar', 'associative', 'large']);

it('authorizes assignments and clearing independently of term management permissions', function (): void {
    $deck = DeckFactory::new()->create();
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'))
        ->canAssignUsing(fn (Taxonomy $taxonomy, ?Deck $record): bool => $record?->id === -1);
    expect($field->acceptsSelection([]))->toBeFalse()
        ->and($field->acceptsSelection([$this->grammar->id]))->toBeFalse()
        ->and(array_unique(array_column($field->getNodes(), 'disabled')))->toBe([true]);
});

it('displays restricted terms in their tree positions but rejects assignment without blocking expansion', function (): void {
    $field = TaxonomySelect::make('topics')->taxonomy('demo-topics')->multiple()->container(Schema::make())
        ->disableTermWhen(fn (TaxonomyTerm $term): bool => $term->slug === 'grammar');
    $nodes = collect($field->getNodes())->keyBy('id');
    expect($nodes[$this->grammar->id]['disabled'])->toBeTrue()
        ->and(count($nodes[$this->grammar->id]['ancestors']))->toBe(2)
        ->and($field->acceptsSelection([$this->grammar->id]))->toBeFalse()
        ->and($field->acceptsSelection([$this->algebra->id]))->toBeTrue();
});

it('rejects float identities before transport can erase their type', function (): void {
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->container(Schema::make());
    expect($field->acceptsSelection([(float) $this->grammar->id]))->toBeFalse();
});

it('validates whole-taxonomy denial even when clearing an empty selection', function (): void {
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->canAssignUsing(fn (): bool => false)
        ->container(Schema::make()->components([]));
    $validator = Validator::make(['topics' => []], [
        'topics' => [new TaxonomySelectionRule($field)],
    ]);
    expect($validator->fails())->toBeTrue()->and($validator->errors()->has('topics'))->toBeTrue();
});

it('rejects duplicate writable fields for the same owner and taxonomy before saving', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $first = TaxonomySelect::make('first')->taxonomy($this->topics)->multiple();
    $second = TaxonomySelect::make('second')->taxonomy($this->topics)->multiple();
    $schema = Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data')
        ->components([$first, $second]);
    $schema->getComponents();
    expect(fn () => $first->lockFormTaxonomies())->toThrow(LogicException::class, 'one TaxonomySelect')
        ->and($deck->fresh()->termsForTaxonomy($this->topics)->count())->toBe(2);
});

it('does not clear a formerly resolved taxonomy if its slug is reassigned before the lock callback', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $field = TaxonomySelect::make('topics')->taxonomy('demo-topics')->multiple()
        ->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->state([]);
    $taxonomy = $this->topics;
    $treeService = Mockery::mock(TaxonomyTreeService::class)->makePartial();
    $treeService->shouldReceive('withTaxonomyLock')->once()->andReturnUsing(function ($resolved, $callback) use ($taxonomy) {
        $taxonomy->update(['slug' => 'renamed-topics']);
        Taxonomy::create(['name' => 'Replacement', 'slug' => 'demo-topics']);

        return $callback($taxonomy->fresh());
    });
    app()->instance(TaxonomyTreeService::class, $treeService);
    expect(fn () => $field->saveRelationships())->toThrow(ValidationException::class)
        ->and($deck->fresh()->termsForTaxonomy($taxonomy)->count())->toBe(2);
});
