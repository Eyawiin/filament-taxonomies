<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('rolls back every affected row when persistence fails partway through maintenance', function (string $operation): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent, 'moving' => $moving, 'destinationParent' => $destination, 'first' => $first, 'last' => $last] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $connection->setEventDispatcher(clone $dispatcher);
    $updates = 0;
    $connection->getEventDispatcher()->listen(QueryExecuted::class, function (QueryExecuted $event) use (&$updates): void {
        if (str_starts_with(strtolower($event->sql), 'update "taxonomy_terms"') && ++$updates === 2) {
            throw new RuntimeException('Injected persistence failure');
        }
    });
    $service = app(TaxonomyTreeService::class);
    $moving->name = 'Pending edit';

    try {
        expect(fn () => match ($operation) {
            'create' => $service->createTerm($taxonomy, 'New', 'new', $parent),
            'reparent' => $service->setParent($moving, $destination),
            'reorder' => $service->reorderSiblings($taxonomy, $parent, [$last->id, $moving->id, $first->id]),
            'delete' => $service->deleteTerm($parent),
        })->toThrow(RuntimeException::class, 'Injected persistence failure');
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }

    expect($updates)->toBe(2)
        ->and(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before)
        ->and($moving->name)->toBe('Pending edit')
        ->and($moving->isDirty('name'))->toBeTrue();
})->with(['create', 'reparent', 'reorder', 'delete']);

it('honors a cancelled term save without changing structural rows', function (): void {
    ['moving' => $moving, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $dispatcher = TaxonomyTerm::getEventDispatcher();
    TaxonomyTerm::setEventDispatcher(clone $dispatcher);

    try {
        TaxonomyTerm::saving(fn (): bool => false);
        expect(fn () => app(TaxonomyTreeService::class)->setParent($moving, $destination))
            ->toThrow(RuntimeException::class, 'cancelled');
    } finally {
        TaxonomyTerm::setEventDispatcher($dispatcher);
    }
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});

it('honors a cancelled term delete without promoting or reordering children', function (): void {
    ['sourceParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $dispatcher = TaxonomyTerm::getEventDispatcher();
    TaxonomyTerm::setEventDispatcher(clone $dispatcher);

    try {
        TaxonomyTerm::deleting(fn (): bool => false);
        expect(app(TaxonomyTreeService::class)->deleteTerm($parent))->toBeFalse()
            ->and($parent->exists)->toBeTrue();
    } finally {
        TaxonomyTerm::setEventDispatcher($dispatcher);
    }
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});

it('uses model events for the explicit term and bulk updates for sibling maintenance', function (): void {
    ['moving' => $moving, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    $dispatcher = TaxonomyTerm::getEventDispatcher();
    TaxonomyTerm::setEventDispatcher(clone $dispatcher);
    $saved = [];

    try {
        TaxonomyTerm::saved(function (TaxonomyTerm $term) use (&$saved): void {
            $saved[] = $term->id;
        });
        app(TaxonomyTreeService::class)->setParent($moving, $destination);
    } finally {
        TaxonomyTerm::setEventDispatcher($dispatcher);
    }
    expect($saved)->toBe([$moving->id]);
});

it('restores cascaded terms when a taxonomy deleted observer fails', function (): void {
    ['taxonomy' => $taxonomy] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $dispatcher = Taxonomy::getEventDispatcher();
    Taxonomy::setEventDispatcher(clone $dispatcher);

    try {
        Taxonomy::deleted(fn () => throw new RuntimeException('Injected cascade failure'));
        expect(fn () => app(TaxonomyTreeService::class)->deleteTaxonomy($taxonomy))
            ->toThrow(RuntimeException::class, 'Injected cascade failure');
    } finally {
        Taxonomy::setEventDispatcher($dispatcher);
    }
    expect($taxonomy->exists)->toBeTrue()
        ->and($taxonomy->fresh())->not->toBeNull()
        ->and(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});

it('honors a cancelled taxonomy deletion without cascading terms', function (): void {
    ['taxonomy' => $taxonomy] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $dispatcher = Taxonomy::getEventDispatcher();
    Taxonomy::setEventDispatcher(clone $dispatcher);

    try {
        Taxonomy::deleting(fn (): bool => false);
        expect(app(TaxonomyTreeService::class)->deleteTaxonomy($taxonomy))->toBeFalse();
    } finally {
        Taxonomy::setEventDispatcher($dispatcher);
    }
    expect($taxonomy->exists)->toBeTrue()->and($taxonomy->fresh())->not->toBeNull()
        ->and(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});
