<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
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
