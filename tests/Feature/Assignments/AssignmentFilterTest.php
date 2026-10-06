<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyAssignmentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Deck;

beforeEach(function (): void {
    $service = app(TaxonomyTreeService::class);
    $this->categories = Taxonomy::create(['name' => 'Categories', 'slug' => 'categories']);
    $this->cards = $service->createTerm($this->categories, 'Trading cards', 'trading-cards');
    $this->pokemon = $service->createTerm($this->categories, 'Pokémon', 'pokemon', $this->cards);
    $this->baseSet = $service->createTerm($this->categories, 'Base Set', 'base-set', $this->pokemon);
    $this->coins = $service->createTerm($this->categories, 'Coins', 'coins');
    $this->conditions = Taxonomy::create(['name' => 'Conditions', 'slug' => 'conditions']);
    $this->mint = $service->createTerm($this->conditions, 'Mint', 'mint');

    Deck::create(['name' => 'Charizard'])->attachTaxonomyTerms($this->categories, [$this->baseSet->id]);
    Deck::where('name', 'Charizard')->firstOrFail()->attachTaxonomyTerms($this->conditions, [$this->mint->id]);
    Deck::create(['name' => 'Booster'])->attachTaxonomyTerms($this->categories, [$this->pokemon->id]);
    Deck::create(['name' => 'Denarius'])->attachTaxonomyTerms($this->categories, [$this->coins->id]);
    Deck::create(['name' => 'Unsorted']);
});

it('matches exactly the given terms by default', function (): void {
    expect(Deck::whereHasTaxonomyTerms('categories', [$this->pokemon->id])->pluck('name')->all())
        ->toBe(['Booster']);
});

it('also matches owners assigned below a term when descendants are included', function (): void {
    $names = Deck::whereHasTaxonomyTerms('categories', $this->cards, includeDescendants: true)
        ->orderBy('name')->pluck('name')->all();

    expect($names)->toBe(['Booster', 'Charizard']);
});

it('requires every term or a term in each subtree for all-of filters', function (): void {
    $both = Deck::whereHasAllTaxonomyTerms('categories', collect([$this->cards, $this->pokemon]), includeDescendants: true)
        ->orderBy('name')->pluck('name')->all();
    $none = Deck::whereHasAllTaxonomyTerms('categories', [$this->pokemon->id, $this->coins->id], includeDescendants: true)
        ->pluck('name')->all();
    $acrossTaxonomies = Deck::whereHasTaxonomyTerms('categories', [$this->cards->id], includeDescendants: true)
        ->whereHasTaxonomyTerms('conditions', [$this->mint->id])->pluck('name')->all();

    expect($both)->toBe(['Booster', 'Charizard'])
        ->and($none)->toBe([])
        ->and($acrossTaxonomies)->toBe(['Charizard']);
});

it('matches nothing for unknown, foreign or hidden terms and hidden branches', function (): void {
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    $pokemonId = $this->pokemon->id;

    expect(Deck::whereHasTaxonomyTerms('categories', [999_999])->exists())->toBeFalse()
        ->and(Deck::whereHasTaxonomyTerms('conditions', [$this->pokemon->id])->exists())->toBeFalse();

    try {
        TaxonomyTerm::addGlobalScope('hide-pokemon', fn (Builder $query) => $query->whereKeyNot($pokemonId));

        // Base Set stays visible, but only through its hidden parent, as in the displayed tree.
        expect(Deck::whereHasTaxonomyTerms('categories', [$this->cards->id], includeDescendants: true)->exists())->toBeFalse()
            ->and(Deck::whereHasTaxonomyTerms('categories', [$this->baseSet->id])->pluck('name')->all())->toBe(['Charizard']);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
});

it('rejects malformed filter terms', function (mixed $terms): void {
    expect(fn () => Deck::whereHasTaxonomyTerms('categories', $terms)->get())
        ->toThrow(InvalidTaxonomyAssignmentException::class);
})->with([
    'non-numeric string' => ['pokemon'],
    'zero' => [[0]],
    'fraction' => [[1.5]],
    'unsaved model' => [fn () => new TaxonomyTerm],
]);

it('resolves any depth of descendants with one query', function (): void {
    $service = app(TaxonomyTreeService::class);
    $parent = $this->baseSet;
    foreach (range(1, 10) as $level) {
        $parent = $service->createTerm($this->categories, "Level {$level}", "level-{$level}", $parent);
    }
    Deck::create(['name' => 'Deep'])->attachTaxonomyTerms($this->categories, [$parent->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $names = Deck::whereHasTaxonomyTerms('categories', [$this->cards->id], includeDescendants: true)->pluck('name')->all();

    // One query each: resolving the taxonomy slug, loading the visible tree and filtering owners.
    expect($names)->toContain('Deep')
        ->and(DB::getQueryLog())->toHaveCount(3);
});
