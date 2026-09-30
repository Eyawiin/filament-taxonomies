<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;

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
