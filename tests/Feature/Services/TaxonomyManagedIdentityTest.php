<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;

it('rejects malformed reorder IDs without coercing them into existing siblings', function (string $kind): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent, 'destinationParent' => $destination, 'first' => $first, 'moving' => $moving, 'last' => $last] = OrderedTaxonomyTreeFixture::create();
    $invalid = match ($kind) {
        'suffix' => $last->id . 'invalid',
        'fraction' => $last->id + 0.5,
        'array' => [$parent->id],
    };
    // An array casts to 1 in PHP: exercise the actual root ID collision.
    $ids = $kind === 'array' ? [$invalid, $destination->id] : [$invalid, $moving->id, $first->id];
    $group = $kind === 'array' ? null : $parent;
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    expect(fn () => app(TaxonomyTreeService::class)->reorderSiblings($taxonomy, $group, $ids))
        ->toThrow(InvalidTaxonomyOrderException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['suffix', 'fraction', 'array']);

it('rejects changed destination identities for every managed entry point', function (string $operation, string $identity): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'sourceParent' => $source, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    if ($identity === 'key') {
        $parent->id = $source->id;
    } elseif ($identity === 'taxonomy') {
        $parent->taxonomy_id = $taxonomy->id + 1;
    } else {
        $parent->exists = false;
    }
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $service = app(TaxonomyTreeService::class);
    $call = fn () => match ($operation) {
        'create' => $service->createTerm($taxonomy, 'New', 'new', $parent),
        'reparent' => $service->setParent($moving, $parent),
        'move' => $service->moveTerm($moving, $parent, 0),
        'relative' => $service->moveRelativeTo($moving, $parent, TaxonomyTermDropPosition::Inside),
        'reorder' => $service->reorderSiblings($taxonomy, $parent, []),
    };
    expect($call)->toThrow(LogicException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['create', 'reparent', 'move', 'relative', 'reorder'])
    ->with(['key', 'taxonomy', 'unpersisted']);

it('accepts integer string reorder IDs and ignores unrelated destination metadata edits', function (): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent, 'first' => $first, 'moving' => $moving, 'last' => $last] = OrderedTaxonomyTreeFixture::create();
    $storedName = $parent->name;
    $parent->name = 'Unsubmitted';
    app(TaxonomyTreeService::class)->reorderSiblings($taxonomy, $parent, [(string) $last->id, (string) $moving->id, (string) $first->id]);
    expect($parent->children()->orderBy('position')->get()->modelKeys())->toBe([$last->id, $moving->id, $first->id])
        ->and($parent->fresh()->name)->toBe($storedName);
});
