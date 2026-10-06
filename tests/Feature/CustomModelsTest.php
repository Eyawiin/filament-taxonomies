<?php

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyAssignmentService;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Eyawiin\FilamentTaxonomies\Tests\Support\CustomTaxonomy;
use Eyawiin\FilamentTaxonomies\Tests\Support\CustomTaxonomyTerm;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Workbench\App\Models\Deck;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    config([
        'filament-taxonomies.models.taxonomy' => CustomTaxonomy::class,
        'filament-taxonomies.models.term' => CustomTaxonomyTerm::class,
    ]);
});

it('uses the configured models for relations, managed writes and trees', function (): void {
    $taxonomy = CustomTaxonomy::create(['name' => 'Card sets', 'slug' => 'card-sets']);
    $service = app(TaxonomyTreeService::class);
    $pokemon = $service->createTerm($taxonomy, 'Pokémon', 'pokemon');
    $baseSet = $service->createTerm($taxonomy, 'Base Set', 'base-set', $pokemon);

    expect($pokemon)->toBeInstanceOf(CustomTaxonomyTerm::class)
        ->and($baseSet->parent)->toBeInstanceOf(CustomTaxonomyTerm::class)
        ->and($pokemon->children->first())->toBeInstanceOf(CustomTaxonomyTerm::class)
        ->and($baseSet->taxonomy)->toBeInstanceOf(CustomTaxonomy::class)
        ->and($taxonomy->terms()->first())->toBeInstanceOf(CustomTaxonomyTerm::class)
        ->and($service->getTree($taxonomy)[0]['term'])->toBeInstanceOf(CustomTaxonomyTerm::class);
});

it('returns configured models from owner assignments and taxonomy references', function (): void {
    $taxonomy = CustomTaxonomy::create(['name' => 'Card sets', 'slug' => 'card-sets']);
    $term = app(TaxonomyTreeService::class)->createTerm($taxonomy, 'Pokémon', 'pokemon');
    $deck = Deck::create(['name' => 'Starter deck']);
    $deck->attachTaxonomyTerms('card-sets', [$term->id]);

    expect($deck->taxonomyTerms->first())->toBeInstanceOf(CustomTaxonomyTerm::class)
        ->and(app(TaxonomyAssignmentService::class)->resolveTaxonomy('card-sets'))->toBeInstanceOf(CustomTaxonomy::class);
});

it('manages the configured taxonomy model in the resource', function (): void {
    $taxonomies = collect([
        CustomTaxonomy::create(['name' => 'Card sets', 'slug' => 'card-sets']),
        CustomTaxonomy::create(['name' => 'Conditions', 'slug' => 'conditions']),
    ]);

    expect(TaxonomyResource::getModel())->toBe(CustomTaxonomy::class);
    Livewire::test(ListTaxonomies::class)->assertCanSeeTableRecords($taxonomies);
});

it('rejects configured models that do not extend the package models', function (): void {
    config(['filament-taxonomies.models.term' => Deck::class]);

    expect(fn () => TaxonomyModels::term())->toThrow(InvalidArgumentException::class);
});
