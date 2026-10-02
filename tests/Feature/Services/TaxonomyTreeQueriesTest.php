<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

it('returns all descendants of a term', function () {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
        'liloAndStitch' => $liloAndStitch,
    ] = TaxonomyTreeFixture::create();

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

it('builds a taxonomy tree', function (): void {
    ['taxonomy' => $taxonomy] = TaxonomyTreeFixture::create();

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

it('orders sibling terms by position', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 1,
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 0,
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->getKey(),
        'position' => 1,
    ]);

    $liloAndStitch = $taxonomy->terms()->create([
        'name' => 'Lilo & Stitch',
        'slug' => 'lilo-stitch',
        'parent_id' => $disney->getKey(),
        'position' => 0,
    ]);

    $tree = app(TaxonomyTreeService::class)->getTree($taxonomy);

    expect($tree)
        ->toHaveCount(2)
        ->and($tree[0]['term']->is($pixar))->toBeTrue()
        ->and($tree[1]['term']->is($disney))->toBeTrue();

    expect($tree[1]['children'])
        ->toHaveCount(2)
        ->and($tree[1]['children'][0]['term']->is($liloAndStitch))->toBeTrue()
        ->and($tree[1]['children'][1]['term']->is($lionKing))->toBeTrue();
});

it('returns the next sibling position', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->getKey(),
        'position' => 0,
    ]);

    $tree = app(TaxonomyTreeService::class);

    expect(
        $tree->getNextPosition(
            (int) $taxonomy->getKey(),
            null,
        ),
    )->toBe(2);

    expect(
        $tree->getNextPosition(
            (int) $taxonomy->getKey(),
            (int) $disney->getKey(),
        ),
    )->toBe(1);
});

it('terminates cyclic descendant queries without including the source itself', function (bool $selfCycle): void {
    ['taxonomy' => $taxonomy, 'disney' => $source, 'lionKing' => $child, 'simba' => $grandchild, 'liloAndStitch' => $other] = TaxonomyTreeFixture::create();
    $source->update(['parent_id' => $selfCycle ? $source->id : $grandchild->id]);
    $before = $taxonomy->terms()->orderBy('id')->get()->toArray();
    expect(app(TaxonomyTreeService::class)->getDescendantIds($source))
        ->toEqualCanonicalizing([$child->id, $grandchild->id, $other->id]);
    expect($taxonomy->terms()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['self cycle' => true, 'multi-node cycle' => false]);

it('returns each descendant once when a visibility join duplicates rows', function (): void {
    ['taxonomy' => $taxonomy, 'disney' => $source, 'lionKing' => $child, 'simba' => $grandchild, 'liloAndStitch' => $other] = TaxonomyTreeFixture::create();
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('copies', fn (Builder $query) => $query->crossJoin(DB::raw('(select 1 as copy union all select 2 as copy) as copies')));

    try {
        expect(app(TaxonomyTreeService::class)->getDescendantIds($source))
            ->toEqualCanonicalizing([$child->id, $grandchild->id, $other->id]);
        $tree = app(TaxonomyTreeService::class)->getTree($taxonomy);
        expect($tree)->toHaveCount(1)
            ->and($tree[0]['children'])->toHaveCount(2)
            ->and(collect($tree[0]['children'])->first(fn (array $node): bool => $node['term']->id === $child->id)['children'])->toHaveCount(1);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
});

it('uses term columns when visibility joins another table with matching column names', function (string $operation): void {
    ['taxonomy' => $taxonomy, 'disney' => $source, 'lionKing' => $child, 'simba' => $grandchild, 'liloAndStitch' => $hidden] = TaxonomyTreeFixture::create();
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('taxonomy-visibility', function (Builder $query) use ($hidden): void {
        $query->join('taxonomies', 'taxonomies.id', '=', 'taxonomy_terms.taxonomy_id')
            ->where('taxonomies.slug', 'theme')
            ->whereKeyNot($hidden->id);
    });

    try {
        $service = app(TaxonomyTreeService::class);
        if ($operation === 'descendants') {
            expect($service->getDescendantIds($source))->toEqualCanonicalizing([$child->id, $grandchild->id]);

            return;
        }
        if ($operation === 'next position') {
            expect($service->getNextPosition($taxonomy->id, $source->id))->toBe(1);

            return;
        }
        $tree = $service->getTree($taxonomy);
        expect($tree)->toHaveCount(1)
            ->and($tree[0]['term']->id)->toBe($source->id)
            ->and($tree[0]['term']->name)->toBe($source->name)
            ->and($tree[0]['children'][0]['term']->id)->toBe($child->id)
            ->and($tree[0]['children'][0]['children'][0]['term']->id)->toBe($grandchild->id);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
})->with(['descendants', 'tree', 'next position']);

it('keeps joined term identities intact during managed movement', function (): void {
    ['taxonomy' => $taxonomy, 'disney' => $root, 'lionKing' => $source, 'simba' => $grandchild, 'liloAndStitch' => $target] = TaxonomyTreeFixture::create();
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('taxonomy-visibility', fn (Builder $query) => $query->join('taxonomies', 'taxonomies.id', '=', 'taxonomy_terms.taxonomy_id'));

    try {
        $source->name = 'Updated child';
        app(TaxonomyTreeService::class)->moveRelativeTo($source, $target, TaxonomyTermDropPosition::After);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
    expect($root->fresh()->name)->toBe('Disney')
        ->and($source->fresh()->name)->toBe('Updated child')
        ->and($root->children()->orderBy('position')->pluck('id')->all())->toBe([$target->id, $source->id])
        ->and($grandchild->fresh()->parent_id)->toBe($source->id);
});
