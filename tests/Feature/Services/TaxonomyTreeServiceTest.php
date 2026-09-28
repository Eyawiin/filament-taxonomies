<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
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

it('moves a term to a valid parent', function () {
    [
        'lionKing' => $lionKing,
        'liloAndStitch' => $liloAndStitch,
    ] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    $tree->setParent($lionKing, $liloAndStitch);

    expect($lionKing->fresh()->parent_id)
        ->toBe($liloAndStitch->id);
});

it('can move a term to the root', function () {
    [
        'lionKing' => $lionKing,
        'disney' => $disney,
    ] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    $tree->setParent($lionKing, null);

    expect($lionKing->fresh()->parent_id)
        ->toBeNull();
});

it('rejects moving a term under itself', function () {
    ['lionKing' => $lionKing] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    expect(fn () => $tree->setParent($lionKing, $lionKing))
        ->toThrow(InvalidTaxonomyParentException::class);

    expect($lionKing->fresh()->parent_id)
        ->not->toBe($lionKing->id);
});

it('rejects moving a term under one of its descendants', function () {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
    ] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    expect(fn () => $tree->setParent($lionKing, $simba))
        ->toThrow(InvalidTaxonomyParentException::class);

    expect($lionKing->fresh()->parent_id)
        ->toBe($disney->id);
});

it('rejects moving a term under a term from another taxonomy', function () {
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

    expect(fn () => $tree->setParent($lionKing, $italy))
        ->toThrow(InvalidTaxonomyParentException::class);

    expect($lionKing->fresh()->parent_id)
        ->not->toBe($italy->id);
});

it('preserves a term subtree when moving the term', function () {
    [
        'lionKing' => $lionKing,
        'simba' => $simba,
        'liloAndStitch' => $liloAndStitch,
    ] = createThemeTree();

    $tree = app(TaxonomyTreeService::class);

    $tree->setParent($lionKing, $liloAndStitch);

    expect($lionKing->fresh()->parent_id)
        ->toBe($liloAndStitch->id);

    expect($simba->fresh()->parent_id)
        ->toBe($lionKing->id);

    expect($tree->getDescendantIds($liloAndStitch))
        ->toEqualCanonicalizing([
            $lionKing->id,
            $simba->id,
        ]);
});

it('builds a taxonomy tree', function (): void {
    ['taxonomy' => $taxonomy] = createThemeTree();

    $tree = app(TaxonomyTreeService::class)->getTree($taxonomy);

    expect($tree)->toHaveCount(1);

    $disney = $tree[0];

    expect($disney['term']->name)->toBe('Disney')
        ->and($disney['children'])->toHaveCount(2);

    $lionKing = collect($disney['children'])
        ->first(fn (array $node): bool => $node['term']->name === 'Lion King');

    expect($lionKing)->not->toBeNull()
        ->and($lionKing['children'])->toHaveCount(1)
        ->and($lionKing['children'][0]['term']->name)->toBe('Simba');
});
