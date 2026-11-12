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
    $ids = $this->themes->terms()->whereIn('slug', ['pop-culture', 'disney', 'lilo-and-stitch', 'nature', 'animals', 'wolves'])->orderBy('id')->pluck('id')->all();
    $submitted = array_map(fn (int $id): int | string => $id === $this->wolves->id ? (string) $id : $id, $ids);
    Livewire::test(CreateDeck::class)->fillForm([
        'name' => 'Created through fields', 'description' => 'Demo',
        'theme_ids' => $submitted, 'brand_id' => (string) $this->riderBackRed->id,
    ])->call('create')->assertHasNoFormErrors();

    $deck = Deck::where('name', 'Created through fields')->firstOrFail();
    expect($deck->termsForTaxonomy($this->themes)->pluck('taxonomy_terms.id')->sort()->values()->all())
        ->toBe($ids);
    Livewire::test(EditDeck::class, ['record' => $deck->getKey()])->assertFormSet([
        'theme_ids' => $ids, 'brand_id' => $this->riderBackRed->id,
    ]);
});

it('clears one taxonomy and preserves both the second field and unrelated assignments', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    $term = $other->terms()->create(['name' => 'Other term', 'slug' => 'other']);
    $deck->attachTaxonomyTerms($other, [$term->id]);
    Livewire::test(EditDeck::class, ['record' => $deck->id])->fillForm(['theme_ids' => []])->call('save')->assertHasNoFormErrors();
    expect($deck->fresh()->termsForTaxonomy($this->themes)->count())->toBe(0)
        ->and($deck->termsForTaxonomy($this->brands)->pluck('taxonomy_terms.id')->all())->toBe([$this->riderBackRed->id])
        ->and($deck->termsForTaxonomy($other)->pluck('taxonomy_terms.id')->all())->toBe([$term->id]);
    Livewire::test(EditDeck::class, ['record' => $deck->id])->fillForm(['brand_id' => null])->call('save')->assertHasNoFormErrors();
    expect($deck->fresh()->taxonomyTerms->modelKeys())->toBe([$term->id]);
});

it('rolls back the owner and both relationship fields when a selected term disappears during creation', function (): void {
    $before = DB::table('taxonomy_term_assignments')->count();
    $a1 = $this->riderBackRed;
    Deck::created(static function () use ($a1): void {
        $a1->delete();
    });
    Livewire::test(CreateDeck::class)->fillForm([
        'name' => 'Rollback', 'theme_ids' => $this->themes->terms()->whereIn('slug', ['pop-culture', 'disney', 'lilo-and-stitch'])->pluck('id')->all(), 'brand_id' => $a1->id,
    ])->call('create')->assertHasFormErrors(['brand_id']);
    expect(Deck::where('name', 'Rollback')->exists())->toBeFalse()
        ->and(TaxonomyTerm::find($a1->id))->not->toBeNull()
        ->and(DB::table('taxonomy_term_assignments')->count())->toBe($before);
});

it('does not silently truncate multiple existing assignments when configured as a single field', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    $field = TaxonomySelect::make('topic')->taxonomy('themes')->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toBe([$this->liloAndStitch->id, $this->wolves->id])
        ->and($field->acceptsSelection($field->getState()))->toBeFalse()
        ->and($field->acceptsSelection(null))->toBeTrue();
});

it('preserves opaque unavailable assignment IDs during hydration and refuses destructive sync', function (): void {
    $deck = DeckFactory::new()->withDemoTerms()->create();
    TaxonomyTerm::addGlobalScope('hide-lilo-and-stitch', fn ($query) => $query->where('taxonomy_terms.slug', '!=', 'lilo-and-stitch'));
    $field = TaxonomySelect::make('topics')->taxonomy($this->themes)->multiple()->container(Schema::make(Livewire::test(EditDeck::class, ['record' => $deck->id])->instance())->model($deck)->statePath('data'));
    $field->loadStateFromRelationships();
    expect($field->getState())->toBe([$this->liloAndStitch->id, $this->wolves->id])
        ->and($field->acceptsSelection($field->getState()))->toBeFalse();
    Livewire::test(EditDeck::class, ['record' => $deck->id])->fillForm(['theme_ids' => []])->call('save')->assertHasFormErrors(['theme_ids']);
    expect(app(TaxonomyAssignmentService::class)->assignedTermIds($deck, $this->themes))->toBe([$this->liloAndStitch->id, $this->wolves->id]);
});
