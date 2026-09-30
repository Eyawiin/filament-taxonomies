<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;

it('moves a term before another sibling', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $first = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $second = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    $third = $taxonomy->terms()->create([
        'name' => 'Countries',
        'slug' => 'countries',
        'position' => 2,
    ]);

    app(TaxonomyTreeService::class)->moveRelativeTo(
        $third,
        $first,
        TaxonomyTermDropPosition::Before,
    );

    expect(
        TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomy->getKey())
            ->whereNull('parent_id')
            ->orderBy('position')
            ->pluck('id')
            ->all(),
    )->toBe([
        $third->getKey(),
        $first->getKey(),
        $second->getKey(),
    ]);
});

it('moves a term after another sibling', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $first = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $second = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    $third = $taxonomy->terms()->create([
        'name' => 'Countries',
        'slug' => 'countries',
        'position' => 2,
    ]);

    app(TaxonomyTreeService::class)->moveRelativeTo(
        $first,
        $second,
        TaxonomyTermDropPosition::After,
    );

    expect(
        TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomy->getKey())
            ->whereNull('parent_id')
            ->orderBy('position')
            ->pluck('id')
            ->all(),
    )->toBe([
        $second->getKey(),
        $first->getKey(),
        $third->getKey(),
    ]);
});

it('moves a term inside another term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $parent = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $term = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    app(TaxonomyTreeService::class)->moveRelativeTo(
        $term,
        $parent,
        TaxonomyTermDropPosition::Inside,
    );

    $term->refresh();

    expect($term->parent_id)
        ->toBe($parent->getKey())
        ->and($term->position)
        ->toBe(0);
});

it('appends a term when moved inside a parent with children', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $parent = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $parent->getKey(),
        'position' => 0,
    ]);

    $term = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    app(TaxonomyTreeService::class)->moveRelativeTo(
        $term,
        $parent,
        TaxonomyTermDropPosition::Inside,
    );

    $term->refresh();

    expect($term->parent_id)
        ->toBe($parent->getKey())
        ->and($term->position)
        ->toBe(1);
});

it('moves a term before a target under another parent', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->getKey(),
        'position' => 0,
    ]);

    $frozen = $taxonomy->terms()->create([
        'name' => 'Frozen',
        'slug' => 'frozen',
        'parent_id' => $disney->getKey(),
        'position' => 1,
    ]);

    $toyStory = $taxonomy->terms()->create([
        'name' => 'Toy Story',
        'slug' => 'toy-story',
        'parent_id' => $pixar->getKey(),
        'position' => 0,
    ]);

    $cars = $taxonomy->terms()->create([
        'name' => 'Cars',
        'slug' => 'cars',
        'parent_id' => $pixar->getKey(),
        'position' => 1,
    ]);

    app(TaxonomyTreeService::class)->moveRelativeTo(
        $lionKing,
        $cars,
        TaxonomyTermDropPosition::Before,
    );

    expect($lionKing->fresh())
        ->parent_id->toBe($pixar->getKey())
        ->position->toBe(1);

    expect($toyStory->fresh()->position)->toBe(0)
        ->and($cars->fresh()->position)->toBe(2)
        ->and($frozen->fresh()->position)->toBe(0);
});

it('moves a nested term to root when dropped before a root term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->getKey(),
        'position' => 0,
    ]);

    app(TaxonomyTreeService::class)->moveRelativeTo(
        $lionKing,
        $pixar,
        TaxonomyTermDropPosition::Before,
    );

    expect($lionKing->fresh())
        ->parent_id->toBeNull()
        ->position->toBe(1);

    expect($pixar->fresh()->position)->toBe(2);
});

it('handles relative sibling moves in both directions without index drift', function (
    int $source,
    int $target,
    TaxonomyTermDropPosition $placement,
    array $expected,
): void {
    $taxonomy = Taxonomy::create(['name' => 'Theme', 'slug' => 'theme']);
    $terms = [];

    foreach (['A', 'B', 'C', 'D'] as $position => $name) {
        $terms[] = $taxonomy->terms()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'position' => $position * 10,
        ]);
    }

    app(TaxonomyTreeService::class)->moveRelativeTo($terms[$source], $terms[$target], $placement);

    $siblings = $taxonomy->terms()->whereNull('parent_id')->orderBy('position')->get();

    expect($siblings->modelKeys())->toBe(array_map(fn (int $index): int => $terms[$index]->id, $expected))
        ->and($siblings->pluck('position')->all())->toBe([0, 1, 2, 3]);
})->with([
    'forward before' => [0, 2, TaxonomyTermDropPosition::Before, [1, 0, 2, 3]],
    'backward before' => [3, 1, TaxonomyTermDropPosition::Before, [0, 3, 1, 2]],
    'forward after' => [0, 2, TaxonomyTermDropPosition::After, [1, 2, 0, 3]],
    'backward after' => [3, 1, TaxonomyTermDropPosition::After, [0, 1, 3, 2]],
    'already before' => [1, 2, TaxonomyTermDropPosition::Before, [0, 1, 2, 3]],
    'already after' => [2, 1, TaxonomyTermDropPosition::After, [0, 1, 2, 3]],
]);

it('moves a subtree between deep branches and normalizes both sibling groups', function (
    TaxonomyTermDropPosition $placement,
    array $expectedNames,
): void {
    [
        'taxonomy' => $taxonomy,
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
        'liloAndStitch' => $lilo,
    ] = TaxonomyTreeFixture::create();

    $lilo->update(['position' => 8]);
    $destination = $taxonomy->terms()->create([
        'name' => 'Destination', 'slug' => 'destination', 'parent_id' => $lilo->id,
    ]);
    $first = $taxonomy->terms()->create([
        'name' => 'First', 'slug' => 'first', 'parent_id' => $destination->id, 'position' => 5,
    ]);
    $target = $taxonomy->terms()->create([
        'name' => 'Target', 'slug' => 'target', 'parent_id' => $destination->id, 'position' => 9,
    ]);

    app(TaxonomyTreeService::class)->moveRelativeTo($lionKing, $target, $placement);

    $siblings = $destination->children()->orderBy('position')->get();

    expect($lionKing->fresh()->parent_id)->toBe($destination->id)
        ->and($simba->fresh()->parent_id)->toBe($lionKing->id)
        ->and($siblings->pluck('name')->all())->toBe($expectedNames)
        ->and($siblings->pluck('position')->all())->toBe([0, 1, 2])
        ->and($disney->children()->pluck('id')->all())->toBe([$lilo->id])
        ->and($lilo->fresh()->position)->toBe(0)
        ->and($first->fresh()->parent_id)->toBe($destination->id);
})->with([
    'before' => [TaxonomyTermDropPosition::Before, ['First', 'Lion King', 'Target']],
    'after' => [TaxonomyTermDropPosition::After, ['First', 'Target', 'Lion King']],
]);

it('moves a nested subtree to root relative to a root term', function (
    TaxonomyTermDropPosition $placement,
    array $expectedNames,
): void {
    [
        'taxonomy' => $taxonomy,
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
        'liloAndStitch' => $lilo,
    ] = TaxonomyTreeFixture::create();

    app(TaxonomyTreeService::class)->moveRelativeTo($lionKing, $disney, $placement);

    $roots = $taxonomy->terms()->whereNull('parent_id')->orderBy('position')->get();

    expect($roots->pluck('name')->all())->toBe($expectedNames)
        ->and($roots->pluck('position')->all())->toBe([0, 1])
        ->and($lionKing->fresh()->parent_id)->toBeNull()
        ->and($simba->fresh()->parent_id)->toBe($lionKing->id)
        ->and($lilo->fresh()->position)->toBe(0);
})->with([
    'before' => [TaxonomyTermDropPosition::Before, ['Lion King', 'Disney']],
    'after' => [TaxonomyTermDropPosition::After, ['Disney', 'Lion King']],
]);

it('appends an existing child inside its current parent without counting itself', function (): void {
    [
        'disney' => $disney,
        'lionKing' => $lionKing,
        'simba' => $simba,
        'liloAndStitch' => $lilo,
    ] = TaxonomyTreeFixture::create();

    $lionKing->update(['position' => 0]);
    $lilo->update(['position' => 4]);

    $service = app(TaxonomyTreeService::class);
    $service->moveRelativeTo($lionKing, $disney, TaxonomyTermDropPosition::Inside);
    $service->moveRelativeTo($lionKing, $disney, TaxonomyTermDropPosition::Inside);

    expect($disney->children()->orderBy('position')->pluck('id')->all())->toBe([$lilo->id, $lionKing->id])
        ->and($lilo->fresh()->position)->toBe(0)
        ->and($lionKing->fresh()->position)->toBe(1)
        ->and($simba->fresh()->parent_id)->toBe($lionKing->id);
});
