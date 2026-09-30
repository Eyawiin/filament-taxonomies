<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyDropException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;

it('does not allow dropping a term relative to itself', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $term = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
        $term,
        $term,
        TaxonomyTermDropPosition::Inside,
    ))->toThrow(InvalidTaxonomyDropException::class);
});

it('does not allow dropping relative to a term from another taxonomy', function (): void {
    $theme = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $country = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $term = $theme->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $target = $country->terms()->create([
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
        $term,
        $target,
        TaxonomyTermDropPosition::After,
    ))->toThrow(InvalidTaxonomyDropException::class);
});

it('rejects moving a term inside one of its descendants', function (): void {
    [
        'disney' => $disney,
        'simba' => $simba,
    ] = TaxonomyTreeFixture::create();

    expect(
        fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
            $disney,
            $simba,
            TaxonomyTermDropPosition::Inside,
        ),
    )->toThrow(InvalidTaxonomyParentException::class);

    expect($disney->fresh()->parent_id)->toBeNull();
});

it('rejects relative moves whose derived parent is self or a descendant without changing the tree', function (
    string $targetKey,
    TaxonomyTermDropPosition $placement,
): void {
    $tree = TaxonomyTreeFixture::create();
    $before = $tree['taxonomy']->terms()->orderBy('id')->get()->toArray();

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
        $tree['disney'],
        $tree[$targetKey],
        $placement,
    ))->toThrow(InvalidTaxonomyParentException::class);

    expect($tree['taxonomy']->terms()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['direct child' => 'lionKing', 'deep descendant' => 'simba'])
    ->with([TaxonomyTermDropPosition::Before, TaxonomyTermDropPosition::After]);

it('rejects every self or foreign-taxonomy relative drop without changing terms', function (
    bool $foreign,
    TaxonomyTermDropPosition $placement,
): void {
    ['taxonomy' => $taxonomy, 'disney' => $term] = TaxonomyTreeFixture::create();
    $target = $term;

    if ($foreign) {
        $other = Taxonomy::create(['name' => 'Country', 'slug' => 'country']);
        $target = $other->terms()->create(['name' => 'Italy', 'slug' => 'italy']);
    }

    $before = TaxonomyTerm::query()->orderBy('id')->get()->toArray();

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo($term, $target, $placement))
        ->toThrow(InvalidTaxonomyDropException::class);

    expect(TaxonomyTerm::query()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['self' => false, 'foreign taxonomy' => true])
    ->with(TaxonomyTermDropPosition::cases());
