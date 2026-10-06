<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyTreeOptions;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\FormFixture;
use Workbench\App\Models\Deck;
use Workbench\Database\Factories\LargeDemoTaxonomyFactory;
use Workbench\Database\Seeders\LargeDemoSeeder;

it('adds a repeatable deep and broad playground without changing existing data or assignments', function (): void {
    $fixture = FormFixture::create();
    $ordinaryDeck = Deck::where('demo_key', 'deck-0')->firstOrFail();
    $ordinaryDeck->update(['name' => 'My edited deck']);
    $ordinaryDeck->syncTaxonomyTerms($fixture['topics'], [$fixture['algebra']->id]);
    $this->seed(LargeDemoSeeder::class);

    $taxonomy = Taxonomy::where('slug', LargeDemoTaxonomyFactory::SLUG)->firstOrFail();
    $nodes = TaxonomyTreeOptions::flatten(app(TaxonomyTreeService::class)->getTree($taxonomy), fn (): string => '');
    expect($taxonomy->terms()->count())->toBe(LargeDemoTaxonomyFactory::TERM_COUNT)
        ->and(max(array_map(fn (array $node): int => count($node['ancestors']) + 1, $nodes)))->toBe(LargeDemoTaxonomyFactory::MAX_DEPTH)
        ->and($taxonomy->terms()->where('name', 'Overview')->count())->toBe(72)
        ->and(Deck::count())->toBe(7);

    $deepDeck = Deck::where('demo_key', 'large-tree-deep')->firstOrFail();
    $broadDeck = Deck::where('demo_key', 'large-tree-broad')->firstOrFail();
    $emptyDeck = Deck::where('demo_key', 'large-tree-empty')->firstOrFail();
    expect($deepDeck->termsForTaxonomy($taxonomy)->count())->toBe(25)
        ->and($broadDeck->termsForTaxonomy($taxonomy)->count())->toBe(90)
        ->and($emptyDeck->termsForTaxonomy($taxonomy)->count())->toBe(0);

    $leaf = $taxonomy->terms()->where('slug', 'deep-24')->firstOrFail();
    $leaf->update(['name' => 'My edited deepest term']);
    $deepDeck->syncTaxonomyTerms($taxonomy, [$leaf->id]);
    $this->seed(LargeDemoSeeder::class);

    expect($taxonomy->terms()->count())->toBe(534)->and(Deck::count())->toBe(7)
        ->and($leaf->fresh()->name)->toBe('My edited deepest term')
        ->and($deepDeck->fresh()->termsForTaxonomy($taxonomy)->pluck('taxonomy_terms.id')->all())->toBe([$leaf->id])
        ->and($ordinaryDeck->fresh()->name)->toBe('My edited deck')
        ->and($ordinaryDeck->termsForTaxonomy($fixture['topics'])->pluck('taxonomy_terms.id')->all())->toBe([$fixture['algebra']->id]);
});
