<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    // Load the package lines first: addLines() would otherwise mark the whole group as loaded.
    __('filament-taxonomies::taxonomies.resource.model_label');
    app('translator')->addLines([
        'taxonomies.resource.model_label' => 'Taxonomie',
        'taxonomies.resource.plural_model_label' => 'Taxonomien',
        'taxonomies.navigation.terms_count' => 'Begriffe in :name',
        'taxonomies.manage_terms.heading' => 'Begriffe verwalten: :name',
        'taxonomies.manage_terms.expand_all' => 'Alle ausklappen',
    ], 'en', 'filament-taxonomies');
});

it('labels the resource and its navigation from the package translations', function (): void {
    Taxonomy::create(['name' => 'Tiere', 'slug' => 'tiere']);

    $items = TaxonomyResource::getNavigationItems();

    expect(TaxonomyResource::getModelLabel())->toBe('Taxonomie')
        ->and(TaxonomyResource::getNavigationLabel())->toBe('Taxonomien')
        ->and($items[0]->getLabel())->toBe('Taxonomien')
        ->and($items[1]->getBadgeTooltip('0'))->toBe('Begriffe in Tiere');
});

it('renders the term management page from the package translations', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Tiere', 'slug' => 'tiere']);
    TaxonomyTerm::create(['taxonomy_id' => $taxonomy->id, 'name' => 'Vögel', 'slug' => 'voegel']);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertSee('Begriffe verwalten: Tiere')
        ->assertSee('Alle ausklappen');
});

it('passes domain errors through JSON translations', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    // addLines() splits keys on dots, so the English sentence needs a real JSON file.
    $path = sys_get_temp_dir() . '/filament-taxonomies-json-' . bin2hex(random_bytes(6));
    mkdir($path);
    file_put_contents($path . '/de.json', json_encode([
        'A drop target must be a different term in the same taxonomy.' => 'Das Ziel muss ein anderer Begriff sein.',
    ], JSON_THROW_ON_ERROR));
    app('translator')->addJsonPath($path);
    app()->setLocale('de');

    try {
        $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
            ->call('dropTerm', $moving->id, $moving->id, 'inside');
    } finally {
        unlink($path . '/de.json');
        rmdir($path);
    }

    expect($component->errors()->get('drop'))->toBe(['Das Ziel muss ein anderer Begriff sein.']);
});
