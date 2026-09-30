<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;

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
    ] = TaxonomyTreeFixture::create();

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
