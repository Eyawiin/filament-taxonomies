<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;

function createThemeTree(): array
{
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->id,
    ]);

    $simba = $taxonomy->terms()->create([
        'name' => 'Simba',
        'slug' => 'simba',
        'parent_id' => $lionKing->id,
    ]);

    $liloAndStitch = $taxonomy->terms()->create([
        'name' => 'Lilo & Stitch',
        'slug' => 'lilo-stitch',
        'parent_id' => $disney->id,
    ]);

    return compact(
        'taxonomy',
        'disney',
        'lionKing',
        'simba',
        'liloAndStitch',
    );
}

it('returns all descendants of a term', function () {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
        'liloAndStitch' => $liloAndStitch,
    ] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    expect($tree->getDescendantIds($disney))
        ->toEqualCanonicalizing([
            $lionKing->id,
            $simba->id,
            $liloAndStitch->id,
        ]);

    expect($tree->getDescendantIds($lionKing))
        ->toEqualCanonicalizing([
            $simba->id,
        ]);

    expect($tree->getDescendantIds($simba))
        ->toBe([]);
});

it('allows valid parent relationships', function () {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'liloAndStitch' => $liloAndStitch,
    ] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    expect($tree->canSetParent($lionKing, $disney))->toBeTrue();
    expect($tree->canSetParent($lionKing, $liloAndStitch))->toBeTrue();
    expect($tree->canSetParent($lionKing, null))->toBeTrue();
});

it('rejects assigning a term as its own parent', function () {
    ['lionKing' => $lionKing] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    expect($tree->canSetParent($lionKing, $lionKing))->toBeFalse();
});

it('rejects assigning a descendant as the parent', function () {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
    ] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    expect($tree->canSetParent($lionKing, $simba))->toBeFalse();
    expect($tree->canSetParent($disney, $lionKing))->toBeFalse();
});

it('rejects assigning a term from another taxonomy as the parent', function () {
    ['lionKing' => $lionKing] = createThemeTree();

    $otherTaxonomy = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $italy = $otherTaxonomy->terms()->create([
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    $tree = app(TaxonomyTreeService::class);

    expect($tree->canSetParent($lionKing, $italy))->toBeFalse();
});
