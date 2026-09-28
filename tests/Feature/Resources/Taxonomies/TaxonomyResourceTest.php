<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('can render the taxonomy list page', function () {
    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful();
});

it('has a create taxonomy action', function () {
    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful()
        ->assertActionExists('create');
});

it('can create a taxonomy', function () {
    Livewire::test(CreateTaxonomy::class)
        ->fillForm([
            'name' => 'Theme',
            'slug' => 'theme',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Taxonomy::query())
        ->where('name', 'Theme')
        ->where('slug', 'theme')
        ->exists()
        ->toBeTrue();
});

it('can edit a taxonomy', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(EditTaxonomy::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->fillForm([
            'name' => 'Themes',
            'slug' => 'themes',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($taxonomy->fresh())
        ->name->toBe('Themes')
        ->slug->toBe('themes');
});

it('can delete a taxonomy', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful()
        ->assertActionExists(
            TestAction::make(DeleteAction::class)->table($taxonomy),
        )
        ->callAction(
            TestAction::make(DeleteAction::class)->table($taxonomy),
        );

    expect(Taxonomy::find($taxonomy->getKey()))
        ->toBeNull();
});

it('has a manage terms action', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful()
        ->assertActionExists(
            TestAction::make('manageTerms')->table($taxonomy),
        );
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
        ->assertHasNoFormErrors();

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
        ->assertHasNoFormErrors();

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
