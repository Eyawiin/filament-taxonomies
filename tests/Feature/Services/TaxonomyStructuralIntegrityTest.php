<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

it('detects a cycle through a scoped-out intermediate ancestor', function (): void {
    ['sourceParent' => $parent, 'moving' => $bridge, 'grandchild' => $descendant] = OrderedTaxonomyTreeFixture::create();
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('hidden-bridge', fn (Builder $query) => $query->whereKeyNot($bridge->id));

    try {
        $service = app(TaxonomyTreeService::class);
        expect($service->canSetParent($parent, $descendant))->toBeFalse();
        expect(fn () => $service->setParent($parent, $descendant))->toThrow(InvalidTaxonomyParentException::class);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
    expect($parent->fresh()->parent_id)->toBeNull();
});

it('maintains complete sibling positions without making hidden inputs selectable', function (): void {
    ['moving' => $moving, 'first' => $hidden, 'last' => $last, 'sourceParent' => $parent, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('hidden-sibling', fn (Builder $query) => $query->whereKeyNot($hidden->id));

    try {
        $service = app(TaxonomyTreeService::class);
        expect(fn () => $service->reorderSiblings($parent->taxonomy, $parent, [$last->id, $moving->id, $hidden->id]))
            ->toThrow(InvalidTaxonomyOrderException::class);
        $service->setParent($moving, $destination);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
    expect($parent->children()->orderBy('position')->pluck('id')->all())->toBe([$hidden->id, $last->id])
        ->and($parent->children()->orderBy('position')->pluck('position')->all())->toBe([0, 1]);
});

it('rejects movement involving a malformed ancestor chain without repairing it', function (string $malformation): void {
    ['moving' => $moving, 'sourceParent' => $parent, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    if ($malformation === 'cycle') {
        $parent->update(['parent_id' => $moving->id]);
    } else {
        $foreign = Taxonomy::create(['name' => 'Foreign', 'slug' => 'foreign'])->terms()->create(['name' => 'Foreign', 'slug' => 'foreign']);
        $parent->update(['parent_id' => $foreign->id]);
    }
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    expect(fn () => app(TaxonomyTreeService::class)->setParent($moving, $destination))->toThrow(InvalidTaxonomyParentException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['cycle', 'foreign parent']);

it('rejects deletion that would promote children from another taxonomy', function (string $operation): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    $other->terms()->create(['name' => 'Invalid foreign child', 'slug' => 'invalid', 'parent_id' => $parent->id]);
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $service = app(TaxonomyTreeService::class);
    expect(fn () => $operation === 'term' ? $service->deleteTerm($parent) : $service->deleteTaxonomy($taxonomy))
        ->toThrow(InvalidTaxonomyParentException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before)
        ->and($taxonomy->fresh())->not->toBeNull();
})->with(['term', 'taxonomy']);

it('checks exact reorder visibility when a join duplicates sibling rows', function (bool $hideSibling): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent, 'first' => $hidden, 'moving' => $moving, 'last' => $last] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('duplicated-visibility', function (Builder $query) use ($hidden, $moving, $hideSibling): void {
        $query->crossJoin(DB::raw('(select 1 as copy union all select 2 as copy) as copies'))
            ->when($hideSibling, fn (Builder $query) => $query->whereKeyNot($hidden->id))
            ->where(fn (Builder $query) => $query->where('copies.copy', 1)->orWhere($query->getModel()->getQualifiedKeyName(), $moving->id));
    });

    try {
        expect($parent->children()->count())->toBe($hideSibling ? 3 : 4);
        $operation = fn () => app(TaxonomyTreeService::class)->reorderSiblings($taxonomy, $parent, [$last->id, $moving->id, $hidden->id]);
        if ($hideSibling) {
            expect($operation)->toThrow(InvalidTaxonomyOrderException::class);
        } else {
            $operation();
        }
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
    if ($hideSibling) {
        expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
    } else {
        $siblings = $parent->children()->orderBy('position')->get();
        expect($siblings->modelKeys())->toBe([$last->id, $moving->id, $hidden->id])
            ->and($siblings->pluck('position')->all())->toBe([0, 1, 2]);
    }
})->with(['hidden sibling' => true, 'all siblings visible' => false]);
