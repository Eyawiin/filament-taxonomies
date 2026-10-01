<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Tests\Support\SelectiveTaxonomyPolicy;
use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Reader', 'email' => 'reader@example.test']));
    Gate::policy(Taxonomy::class, SelectiveTaxonomyPolicy::class);
});

it('F1 omits taxonomy labels and links when record viewing is denied', function (): void {
    $public = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);
    expect(Gate::allows('view', $public))->toBeTrue()
        ->and(Gate::denies('view', $private))->toBeTrue();

    $labels = array_map(
        static fn ($item): string => $item->getLabel(),
        TaxonomyResource::getNavigationItems(),
    );

    expect($labels)->toContain($public->name)
        ->not->toContain($private->name);
});

it('F1 denies a manage-terms record when viewAny is allowed but view is denied', function (): void {
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);
    expect(Gate::allows('viewAny', Taxonomy::class))->toBeTrue()
        ->and(Gate::denies('view', $private))->toBeTrue();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $private->id])
        ->assertForbidden();
});
