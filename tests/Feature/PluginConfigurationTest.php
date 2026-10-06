<?php

use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesPlugin;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Tests\Support\ScopedTaxonomyResource;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->plugin = FilamentTaxonomiesPlugin::get();
});

it('applies navigation settings to the list entry and the per-taxonomy entries', function (): void {
    Taxonomy::create(['name' => 'Animals', 'slug' => 'animals']);
    $this->plugin
        ->navigationLabel(fn (): string => 'Vocabularies')
        ->navigationIcon('heroicon-o-book-open')
        ->navigationGroup('Content')
        ->navigationSort(7)
        ->taxonomyNavigationGroup('Vocabulary trees');

    $items = TaxonomyResource::getNavigationItems();

    expect($items)->toHaveCount(2)
        ->and($items[0]->getLabel())->toBe('Vocabularies')
        ->and($items[0]->getIcon())->toBe('heroicon-o-book-open')
        ->and($items[0]->getGroup())->toBe('Content')
        ->and($items[0]->getSort())->toBe(7)
        ->and($items[1]->getLabel())->toBe('Animals')
        ->and($items[1]->getGroup())->toBe('Vocabulary trees');
});

it('can place the per-taxonomy entries outside any group', function (): void {
    Taxonomy::create(['name' => 'Animals', 'slug' => 'animals']);
    $this->plugin->taxonomyNavigationGroup(null);

    expect(TaxonomyResource::getNavigationItems()[1]->getGroup())->toBeNull();
});

it('registers one standard entry without querying taxonomies when taxonomy navigation is off', function (): void {
    Taxonomy::create(['name' => 'Animals', 'slug' => 'animals']);
    $this->plugin->taxonomyNavigation(false)->navigationGroup('Content');

    DB::enableQueryLog();
    $items = TaxonomyResource::getNavigationItems();

    expect($items)->toHaveCount(1)
        ->and($items[0]->getLabel())->toBe('Taxonomies')
        ->and($items[0]->getGroup())->toBe('Content')
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('registers a custom resource subclass and rejects other classes', function (): void {
    $panel = Panel::make()->id('custom-taxonomies');
    FilamentTaxonomiesPlugin::make()->resource(ScopedTaxonomyResource::class)->register($panel);

    expect($panel->getResources())->toContain(ScopedTaxonomyResource::class)
        ->not->toContain(TaxonomyResource::class)
        ->and(fn () => FilamentTaxonomiesPlugin::make()->resource(Taxonomy::class))
        ->toThrow(InvalidArgumentException::class);
});

it('leaves the panel navigation groups to the application', function (): void {
    $panel = Panel::make()->id('navigation-groups');
    $plugin = FilamentTaxonomiesPlugin::make();
    $plugin->register($panel);
    $plugin->boot($panel);

    expect($panel->getNavigationGroups())->toBe([]);
});
