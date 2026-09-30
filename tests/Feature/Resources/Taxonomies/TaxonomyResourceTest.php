<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('lists taxonomy links separately with term-count badges and direct manage URLs', function (): void {
    $firstTaxonomy = Taxonomy::create([
        'name' => 'Animals',
        'slug' => 'animals',
    ]);

    TaxonomyTerm::create([
        'taxonomy_id' => $firstTaxonomy->getKey(),
        'name' => 'Mammals',
        'slug' => 'mammals',
    ]);

    TaxonomyTerm::create([
        'taxonomy_id' => $firstTaxonomy->getKey(),
        'name' => 'Birds',
        'slug' => 'birds',
    ]);

    $secondTaxonomy = Taxonomy::create([
        'name' => 'Places',
        'slug' => 'places',
    ]);

    $items = TaxonomyResource::getNavigationItems();

    expect($items)->toHaveCount(3)
        ->and($items[0]->getLabel())->toBe('Taxonomies')
        ->and($items[0]->getGroup())->toBeNull()
        ->and($items[0]->getIcon())->toBe('heroicon-o-rectangle-stack')
        ->and($items[1]->getLabel())->toBe('Animals')
        ->and($items[1]->getGroup())->toBe('Taxonomies')
        ->and($items[1]->getBadge())->toBe('2')
        ->and($items[1]->getBadgeTooltip('2'))->toBe('Number of terms in Animals')
        ->and($items[1]->getUrl())->toEndWith("/taxonomies/{$firstTaxonomy->getKey()}/manage-terms")
        ->and($items[2]->getLabel())->toBe('Places')
        ->and($items[2]->getBadge())->toBe('0')
        ->and($items[2]->getUrl())->toEndWith("/taxonomies/{$secondTaxonomy->getKey()}/manage-terms");
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
