<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;

it('allows valid parent relationships', function () {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'liloAndStitch' => $liloAndStitch,
    ] = TaxonomyTreeFixture::create();

    $tree = app(TaxonomyTreeService::class);

    expect($tree->canSetParent($lionKing, $disney))->toBeTrue();
    expect($tree->canSetParent($lionKing, $liloAndStitch))->toBeTrue();
    expect($tree->canSetParent($lionKing, null))->toBeTrue();
});

it('rejects assigning a term as its own parent', function () {
    ['lionKing' => $lionKing] = TaxonomyTreeFixture::create();

    $tree = app(TaxonomyTreeService::class);

    expect($tree->canSetParent($lionKing, $lionKing))->toBeFalse();
});

it('rejects assigning a descendant as the parent', function () {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
    ] = TaxonomyTreeFixture::create();

    $tree = app(TaxonomyTreeService::class);

    expect($tree->canSetParent($lionKing, $simba))->toBeFalse();
    expect($tree->canSetParent($disney, $lionKing))->toBeFalse();
});

it('rejects assigning a term from another taxonomy as the parent', function () {
    ['lionKing' => $lionKing] = TaxonomyTreeFixture::create();

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
    ] = TaxonomyTreeFixture::create();

    $tree = app(TaxonomyTreeService::class);

    $tree->setParent($lionKing, $liloAndStitch);

    expect($lionKing->fresh()->parent_id)
        ->toBe($liloAndStitch->id);
});

it('can move a term to the root', function () {
    [
        'lionKing' => $lionKing,
        'disney' => $disney,
    ] = TaxonomyTreeFixture::create();

    $tree = app(TaxonomyTreeService::class);

    $tree->setParent($lionKing, null);

    expect($lionKing->fresh()->parent_id)
        ->toBeNull();
});

it('rejects moving a term under itself', function () {
    ['lionKing' => $lionKing] = TaxonomyTreeFixture::create();

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
    ] = TaxonomyTreeFixture::create();

    $tree = app(TaxonomyTreeService::class);

    expect(fn () => $tree->setParent($lionKing, $simba))
        ->toThrow(InvalidTaxonomyParentException::class);

    expect($lionKing->fresh()->parent_id)
        ->toBe($disney->id);
});

it('rejects moving a term under a term from another taxonomy', function () {
    ['lionKing' => $lionKing] = TaxonomyTreeFixture::create();

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
    ] = TaxonomyTreeFixture::create();

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
