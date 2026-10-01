<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('creates at the end and normalizes an existing sibling group', function (): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent, 'first' => $first, 'moving' => $moving, 'last' => $last] = OrderedTaxonomyTreeFixture::create();
    $last->update(['position' => 8]);
    $created = app(TaxonomyTreeService::class)->createTerm($taxonomy, 'New', 'new', $parent);
    $siblings = $parent->children()->orderBy('position')->get();
    expect($siblings->modelKeys())->toBe([$first->id, $moving->id, $last->id, $created->id])
        ->and($siblings->pluck('position')->all())->toBe([0, 1, 2, 3]);
});

it('uses the current parent and preserves pending metadata on a stale source', function (): void {
    ['moving' => $moving, 'destinationParent' => $destination, 'sourceParent' => $source] = OrderedTaxonomyTreeFixture::create();
    $stale = clone $moving;
    app(TaxonomyTreeService::class)->setParent($moving, $destination);
    $stale->name = 'Edited';
    $stale->slug = 'edited';
    $returned = app(TaxonomyTreeService::class)->setParent($stale, $source);
    expect($returned)->toBe($stale)
        ->and($returned->name)->toBe('Edited')
        ->and($returned->slug)->toBe('edited')
        ->and($source->children()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2])
        ->and($destination->children()->orderBy('position')->pluck('position')->all())->toBe([0]);
});

it('derives a relative destination from the freshly loaded target', function (): void {
    ['moving' => $moving, 'destinationChild' => $target, 'first' => $first, 'last' => $last, 'sourceParent' => $source] = OrderedTaxonomyTreeFixture::create();
    $stale = clone $target;
    app(TaxonomyTreeService::class)->setParent($target, $source);
    app(TaxonomyTreeService::class)->moveRelativeTo($moving, $stale, TaxonomyTermDropPosition::Before);
    expect($moving->fresh()->parent_id)->toBe($source->id)
        ->and($source->children()->orderBy('position')->pluck('id')->all())
        ->toBe([$first->id, $last->id, $moving->id, $target->id]);
});

it('appends an existing child on an inside drop without losing its subtree', function (): void {
    ['sourceParent' => $parent, 'moving' => $moving, 'grandchild' => $grandchild, 'first' => $first, 'last' => $last] = OrderedTaxonomyTreeFixture::create();
    app(TaxonomyTreeService::class)->moveRelativeTo($moving, $parent, TaxonomyTermDropPosition::Inside);
    expect($parent->children()->orderBy('position')->pluck('id')->all())->toBe([$first->id, $last->id, $moving->id])
        ->and($parent->children()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2])
        ->and($grandchild->fresh()->parent_id)->toBe($moving->id);
});

it('rejects a deleted input without partial changes', function (string $deleted): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    match ($deleted) {
        'source' => TaxonomyTerm::whereKey($moving->id)->delete(),
        'parent', 'target' => TaxonomyTerm::whereKey($destination->id)->delete(),
        'taxonomy' => Taxonomy::whereKey($taxonomy->id)->delete(),
    };
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $service = app(TaxonomyTreeService::class);
    expect(fn () => $deleted === 'target'
        ? $service->moveRelativeTo($moving, $destination, TaxonomyTermDropPosition::Inside)
        : $service->setParent($moving, $destination))->toThrow(ModelNotFoundException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['source', 'parent', 'target', 'taxonomy']);

it('rejects a source whose stored taxonomy has changed', function (): void {
    ['moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    TaxonomyTerm::whereKey($moving->id)->update(['taxonomy_id' => $other->id, 'parent_id' => null]);
    expect(fn () => app(TaxonomyTreeService::class)->setParent($moving, null))->toThrow(ModelNotFoundException::class);
    expect($moving->fresh()->taxonomy_id)->toBe($other->id);
});

it('deletes a taxonomy through the same managed boundary and preserves other taxonomies', function (): void {
    ['taxonomy' => $taxonomy] = OrderedTaxonomyTreeFixture::create();
    $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    $term = app(TaxonomyTreeService::class)->createTerm($other, 'Other term', 'other');
    expect(app(TaxonomyTreeService::class)->deleteTaxonomy($taxonomy))->toBeTrue()
        ->and($taxonomy->exists)->toBeFalse()
        ->and(TaxonomyTerm::where('taxonomy_id', $taxonomy->id)->count())->toBe(0)
        ->and($term->fresh())->not->toBeNull();
});

it('rejects alternate connections before writing', function (): void {
    ['moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    config(['database.connections.other' => config('database.connections.' . config('database.default'))]);
    $moving->setConnection('other');
    expect(fn () => app(TaxonomyTreeService::class)->setParent($moving, null))->toThrow(LogicException::class);
});

it('ignores pending structural attributes while applying explicit movement', function (): void {
    ['moving' => $moving, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    $moving->parent_id = null;
    $moving->position = 99;
    app(TaxonomyTreeService::class)->setParent($moving, $destination);
    expect($moving->parent_id)->toBe($destination->id)->and($moving->position)->toBe(1);
});

it('normalizes keyed reorder input to zero-based sibling positions', function (array $keys): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent, 'first' => $first, 'moving' => $moving, 'last' => $last] = OrderedTaxonomyTreeFixture::create();
    $orderedIds = [$last->id, $moving->id, $first->id];
    app(TaxonomyTreeService::class)->reorderSiblings($taxonomy, $parent, array_combine($keys, $orderedIds));
    $siblings = $parent->children()->orderBy('position')->get();
    expect($siblings->modelKeys())->toBe($orderedIds)
        ->and($siblings->pluck('position')->all())->toBe([0, 1, 2]);
})->with([
    'retained numeric keys' => [[1, 4, 9]],
    'named keys' => [['last', 'middle', 'first']],
]);
