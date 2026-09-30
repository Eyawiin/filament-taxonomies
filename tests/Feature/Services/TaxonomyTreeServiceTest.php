<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyDropException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
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

it('moves a term to the end of its new sibling group', function (): void {
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

    $taxonomy->terms()->create([
        'name' => 'Toy Story',
        'slug' => 'toy-story',
        'parent_id' => $pixar->getKey(),
        'position' => 0,
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->getKey(),
        'position' => 0,
    ]);

    app(TaxonomyTreeService::class)
        ->setParent($lionKing, $pixar);

    $lionKing->refresh();

    expect($lionKing->parent_id)
        ->toBe($pixar->getKey())
        ->and($lionKing->position)
        ->toBe(1);
});

it('preserves position when the parent does not change', function (): void {
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
        'parent_id' => $disney->getKey(),
        'position' => 5,
    ]);

    app(TaxonomyTreeService::class)
        ->setParent($lionKing, $disney);

    expect($lionKing->fresh()->position)
        ->toBe(5);
});

it('reorders sibling terms', function (): void {
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

    $countries = $taxonomy->terms()->create([
        'name' => 'Countries',
        'slug' => 'countries',
        'position' => 2,
    ]);

    app(TaxonomyTreeService::class)->reorderSiblings(
        $taxonomy,
        null,
        [
            $countries->id,
            $disney->id,
            $pixar->id,
        ],
    );

    expect($countries->fresh()->position)->toBe(0)
        ->and($disney->fresh()->position)->toBe(1)
        ->and($pixar->fresh()->position)->toBe(2);
});

it('reorders only the specified sibling group', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->id,
        'position' => 0,
    ]);

    $lilo = $taxonomy->terms()->create([
        'name' => 'Lilo & Stitch',
        'slug' => 'lilo-stitch',
        'parent_id' => $disney->id,
        'position' => 1,
    ]);

    app(TaxonomyTreeService::class)->reorderSiblings(
        $taxonomy,
        $disney,
        [
            $lilo->id,
            $lionKing->id,
        ],
    );

    expect($disney->fresh()->position)->toBe(0)
        ->and($lilo->fresh()->position)->toBe(0)
        ->and($lionKing->fresh()->position)->toBe(1);
});

it('rejects incomplete sibling orders', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
    ]);

    expect(
        fn () => app(TaxonomyTreeService::class)->reorderSiblings(
            $taxonomy,
            null,
            [$disney->id],
        ),
    )->toThrow(InvalidTaxonomyOrderException::class);

    expect($disney->fresh()->position)->toBe(0)
        ->and($pixar->fresh()->position)->toBe(0);
});

it('rejects duplicate term IDs when reordering siblings', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
    ]);

    expect(
        fn () => app(TaxonomyTreeService::class)->reorderSiblings(
            $taxonomy,
            null,
            [
                $disney->id,
                $disney->id,
            ],
        ),
    )->toThrow(InvalidTaxonomyOrderException::class);

    expect($pixar->fresh())->not->toBeNull();
});

it('moves a term within its sibling group', function (): void {
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

    $countries = $taxonomy->terms()->create([
        'name' => 'Countries',
        'slug' => 'countries',
        'position' => 2,
    ]);

    app(TaxonomyTreeService::class)->moveTerm(
        $countries,
        null,
        0,
    );

    expect($countries->fresh()->position)->toBe(0)
        ->and($disney->fresh()->position)->toBe(1)
        ->and($pixar->fresh()->position)->toBe(2);
});

it('moves a term to a specific position under another parent', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->id,
        'position' => 0,
    ]);

    $frozen = $taxonomy->terms()->create([
        'name' => 'Frozen',
        'slug' => 'frozen',
        'parent_id' => $disney->id,
        'position' => 1,
    ]);

    $toyStory = $taxonomy->terms()->create([
        'name' => 'Toy Story',
        'slug' => 'toy-story',
        'parent_id' => $pixar->id,
        'position' => 0,
    ]);

    $cars = $taxonomy->terms()->create([
        'name' => 'Cars',
        'slug' => 'cars',
        'parent_id' => $pixar->id,
        'position' => 1,
    ]);

    app(TaxonomyTreeService::class)->moveTerm(
        $lionKing,
        $pixar,
        1,
    );

    expect($lionKing->fresh())
        ->parent_id->toBe($pixar->id)
        ->position->toBe(1);

    expect($toyStory->fresh()->position)->toBe(0)
        ->and($cars->fresh()->position)->toBe(2);

    expect($frozen->fresh()->position)->toBe(0);
});

it('rejects moving a term outside the destination sibling positions', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    expect(
        fn () => app(TaxonomyTreeService::class)->moveTerm(
            $disney,
            null,
            5,
        ),
    )->toThrow(InvalidTaxonomyOrderException::class);

    expect($disney->fresh()->position)
        ->toBe(0);
});

it('rejects moving a term under one of its descendants with moveTerm', function (): void {
    [
        'disney' => $disney,
        'simba' => $simba,
    ] = createThemeTree();

    expect(
        fn () => app(TaxonomyTreeService::class)->moveTerm(
            $disney,
            $simba,
            0,
        ),
    )->toThrow(InvalidTaxonomyParentException::class);

    expect($disney->fresh()->parent_id)
        ->toBeNull();
});

it('moves a term into an empty parent', function (): void {
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

    app(TaxonomyTreeService::class)->moveTerm(
        $pixar,
        $disney,
        0,
    );

    expect($pixar->fresh())
        ->parent_id->toBe($disney->id)
        ->position->toBe(0);
});

it('rejects negative destination positions', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    expect(
        fn () => app(TaxonomyTreeService::class)->moveTerm(
            $disney,
            null,
            -1,
        ),
    )->toThrow(InvalidTaxonomyOrderException::class);
});

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

it('does not allow dropping a term relative to itself', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $term = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
        $term,
        $term,
        TaxonomyTermDropPosition::Inside,
    ))->toThrow(InvalidTaxonomyDropException::class);
});

it('does not allow dropping relative to a term from another taxonomy', function (): void {
    $theme = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $country = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $term = $theme->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $target = $country->terms()->create([
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
        $term,
        $target,
        TaxonomyTermDropPosition::After,
    ))->toThrow(InvalidTaxonomyDropException::class);
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

it('rejects moving a term inside one of its descendants', function (): void {
    [
        'disney' => $disney,
        'simba' => $simba,
    ] = createThemeTree();

    expect(
        fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
            $disney,
            $simba,
            TaxonomyTermDropPosition::Inside,
        ),
    )->toThrow(InvalidTaxonomyParentException::class);

    expect($disney->fresh()->parent_id)->toBeNull();
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
    ] = createThemeTree();

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
    ] = createThemeTree();

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
    ] = createThemeTree();

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

it('rejects relative moves whose derived parent is self or a descendant without changing the tree', function (
    string $targetKey,
    TaxonomyTermDropPosition $placement,
): void {
    $tree = createThemeTree();
    $before = $tree['taxonomy']->terms()->orderBy('id')->get()->toArray();

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo(
        $tree['disney'],
        $tree[$targetKey],
        $placement,
    ))->toThrow(InvalidTaxonomyParentException::class);

    expect($tree['taxonomy']->terms()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['direct child' => 'lionKing', 'deep descendant' => 'simba'])
    ->with([TaxonomyTermDropPosition::Before, TaxonomyTermDropPosition::After]);

it('rejects every self or foreign-taxonomy relative drop without changing terms', function (
    bool $foreign,
    TaxonomyTermDropPosition $placement,
): void {
    ['taxonomy' => $taxonomy, 'disney' => $term] = createThemeTree();
    $target = $term;

    if ($foreign) {
        $other = Taxonomy::create(['name' => 'Country', 'slug' => 'country']);
        $target = $other->terms()->create(['name' => 'Italy', 'slug' => 'italy']);
    }

    $before = TaxonomyTerm::query()->orderBy('id')->get()->toArray();

    expect(fn () => app(TaxonomyTreeService::class)->moveRelativeTo($term, $target, $placement))
        ->toThrow(InvalidTaxonomyDropException::class);

    expect(TaxonomyTerm::query()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['self' => false, 'foreign taxonomy' => true])
    ->with(TaxonomyTermDropPosition::cases());
