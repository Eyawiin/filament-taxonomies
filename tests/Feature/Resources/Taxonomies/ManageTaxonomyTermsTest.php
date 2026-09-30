<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('offers taxonomy edit and delete actions on the manage terms page', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->assertSuccessful()
        ->assertActionVisible('editTaxonomy')
        ->assertActionHasUrl(
            'editTaxonomy',
            TaxonomyResource::getUrl('edit', ['record' => $taxonomy]),
        )
        ->assertActionVisible('deleteTaxonomy')
        ->assertActionVisible('createTerm');
});

it('deletes the taxonomy and its terms from the manage terms page', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $term = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction('deleteTaxonomy')
        ->assertRedirect(TaxonomyResource::getUrl('index'));

    expect(Taxonomy::find($taxonomy->getKey()))->toBeNull()
        ->and(TaxonomyTerm::find($term->getKey()))->toBeNull();
});

it('can create a taxonomy term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $parent = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => null,
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->assertSuccessful()
        ->assertActionExists('createTerm')
        ->callAction('createTerm', data: [
            'name' => 'Lilo & Stitch',
            'slug' => 'lilo-and-stitch',
            'parent_id' => $parent->getKey(),
        ])
        ->assertHasNoFormErrors()
        ->assertDispatched(
            'taxonomy-tree-expand-term',
            termId: $parent->getKey(),
        );

    $term = TaxonomyTerm::query()
        ->where('slug', 'lilo-and-stitch')
        ->first();

    expect($term)
        ->not->toBeNull()
        ->taxonomy_id->toBe($taxonomy->getKey())
        ->parent_id->toBe($parent->getKey())
        ->name->toBe('Lilo & Stitch')
        ->slug->toBe('lilo-and-stitch');
});

it('can create a root taxonomy term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction('createTerm', data: [
            'name' => 'Disney',
            'slug' => 'disney',
            'parent_id' => null,
        ])
        ->assertHasNoFormErrors()
        ->assertNotDispatched('taxonomy-tree-expand-term');

    expect(TaxonomyTerm::query()->where('slug', 'disney')->first())
        ->not->toBeNull()
        ->taxonomy_id->toBe($taxonomy->getKey())
        ->parent_id->toBeNull();
});

it('cannot create a taxonomy term with a parent from another taxonomy', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $otherTaxonomy = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $foreignParent = TaxonomyTerm::create([
        'taxonomy_id' => $otherTaxonomy->getKey(),
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction('createTerm', data: [
            'name' => 'Venice',
            'slug' => 'venice',
            'parent_id' => $foreignParent->getKey(),
        ])
        ->assertHasFormErrors(['parent_id']);

    expect(
        TaxonomyTerm::query()->where('slug', 'venice')->exists(),
    )->toBeFalse();
});

it('renders the taxonomy term tree', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $lionKing = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lion King',
        'slug' => 'lion-king',
    ]);

    TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $lionKing->getKey(),
        'name' => 'Simba',
        'slug' => 'simba',
    ]);

    TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lilo & Stitch',
        'slug' => 'lilo-stitch',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Disney',
            'Lilo & Stitch',
            'Lion King',
            'Simba',
        ]);
});
