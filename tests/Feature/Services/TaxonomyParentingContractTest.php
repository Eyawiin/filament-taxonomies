<?php

use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;

it('retains sibling order and saves pending metadata when the parent is unchanged', function (): void {
    [
        'taxonomy' => $taxonomy, 'sourceParent' => $sourceParent, 'moving' => $moving,
    ] = OrderedTaxonomyTreeFixture::create();
    $before = $taxonomy->terms()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray();
    $moving->name = 'Renamed middle';
    $moving->slug = 'renamed-middle';

    $saved = app(TaxonomyTreeService::class)->setParent($moving, $sourceParent);

    expect($saved)->toBeInstanceOf(TaxonomyTerm::class)
        ->and($saved->getKey())->toBe($moving->getKey())
        ->and($moving->fresh()->name)->toBe('Renamed middle')
        ->and($moving->fresh()->slug)->toBe('renamed-middle')
        ->and($taxonomy->terms()->orderBy('id')->get(['id', 'parent_id', 'position'])->toArray())->toBe($before);
});

it('saves pending metadata when changing parent and preserves the descendant subtree', function (): void {
    [
        'destinationParent' => $destinationParent, 'moving' => $moving, 'grandchild' => $grandchild,
    ] = OrderedTaxonomyTreeFixture::create();
    $moving->name = 'Renamed middle';
    $moving->slug = 'renamed-middle';

    $saved = app(TaxonomyTreeService::class)->setParent($moving, $destinationParent);

    expect($saved)->toBeInstanceOf(TaxonomyTerm::class)
        ->and($saved->getKey())->toBe($moving->getKey())
        ->and($moving->fresh()->name)->toBe('Renamed middle')
        ->and($moving->fresh()->slug)->toBe('renamed-middle')
        ->and($moving->fresh()->parent_id)->toBe($destinationParent->id)
        ->and($grandchild->fresh()->parent_id)->toBe($moving->id);
});
