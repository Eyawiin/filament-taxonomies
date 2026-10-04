<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyAssignmentService;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\FormFixture;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
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

it('creates an owner before saving single and multiple scoped relationships and reopens both', function (): void {
    $ids = $this->topics->terms()->whereIn('slug', ['languages', 'english', 'grammar', 'science', 'mathematics', 'algebra'])->orderBy('id')->pluck('id')->all();
    $submitted = array_map(fn (int $id): int | string => $id === $this->algebra->id ? (string) $id : $id, $ids);
    Livewire::test(CreateDeck::class)->fillForm([
        'name' => 'Created through fields', 'description' => 'Demo',
        'topic_ids' => $submitted, 'level_id' => (string) $this->a1->id,
    ])->call('create')->assertHasNoFormErrors();

    $deck = Deck::where('name', 'Created through fields')->firstOrFail();
    expect($deck->termsForTaxonomy($this->topics)->pluck('taxonomy_terms.id')->sort()->values()->all())
        ->toBe($ids);
    Livewire::test(EditDeck::class, ['record' => $deck->getKey()])->assertFormSet([
        'topic_ids' => $ids, 'level_id' => $this->a1->id,
    ]);
});

it('clears one taxonomy and preserves both the second field and unrelated assignments', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    $term = $other->terms()->create(['name' => 'Other term', 'slug' => 'other']);
    $deck->attachTaxonomyTerms($other, [$term->id]);
    Livewire::test(EditDeck::class, ['record' => $deck->id])->fillForm(['topic_ids' => []])->call('save')->assertHasNoFormErrors();
    expect($deck->fresh()->termsForTaxonomy($this->topics)->count())->toBe(0)
        ->and($deck->termsForTaxonomy($this->levels)->pluck('taxonomy_terms.id')->all())->toBe([$this->a1->id])
        ->and($deck->termsForTaxonomy($other)->pluck('taxonomy_terms.id')->all())->toBe([$term->id]);
    Livewire::test(EditDeck::class, ['record' => $deck->id])->fillForm(['level_id' => null])->call('save')->assertHasNoFormErrors();
    expect($deck->fresh()->taxonomyTerms->modelKeys())->toBe([$term->id]);
});

it('rolls back the owner and both relationship fields when a selected term disappears during creation', function (): void {
    $before = DB::table('taxonomy_term_assignments')->count();
    $a1 = $this->a1;
    Deck::created(static function () use ($a1): void {
        $a1->delete();
    });
    Livewire::test(CreateDeck::class)->fillForm([
        'name' => 'Rollback', 'topic_ids' => $this->topics->terms()->whereIn('slug', ['languages', 'english', 'grammar'])->pluck('id')->all(), 'level_id' => $a1->id,
    ])->call('create')->assertHasFormErrors(['level_id']);
    expect(Deck::where('name', 'Rollback')->exists())->toBeFalse()
        ->and(TaxonomyTerm::find($a1->id))->not->toBeNull()
        ->and(DB::table('taxonomy_term_assignments')->count())->toBe($before);
});

it('does not silently truncate multiple existing assignments when configured as a single field', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $field = TaxonomySelect::make('topic')->taxonomy('demo-topics')->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toBe([$this->grammar->id, $this->algebra->id])
        ->and($field->acceptsSelection($field->getState()))->toBeFalse()
        ->and($field->acceptsSelection(null))->toBeTrue();
});

it('preserves opaque unavailable assignment IDs during hydration and refuses destructive sync', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    TaxonomyTerm::addGlobalScope('hide-grammar', fn ($query) => $query->where('taxonomy_terms.slug', '!=', 'grammar'));
    $field = TaxonomySelect::make('topics')->taxonomy($this->topics)->multiple()->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toBe([$this->grammar->id, $this->algebra->id])
        ->and($field->acceptsSelection($field->getState()))->toBeFalse();
    Livewire::test(EditDeck::class, ['record' => $deck->id])->fillForm(['topic_ids' => []])->call('save')->assertHasFormErrors(['topic_ids']);
    expect(app(TaxonomyAssignmentService::class)->assignedTermIds($deck, $this->topics))->toBe([$this->grammar->id, $this->algebra->id]);
});
