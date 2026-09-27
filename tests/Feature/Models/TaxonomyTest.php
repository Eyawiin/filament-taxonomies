<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

it('can create a taxonomy with terms', function () {
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

    expect($taxonomy->fresh()->terms)->toHaveCount(2);
    expect($lionKing->taxonomy->is($taxonomy))->toBeTrue();
    expect($lionKing->parent->is($disney))->toBeTrue();
    expect($disney->children)->toHaveCount(1);
});

it('promotes children to root terms when a parent is deleted', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $parent = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $child = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $parent->id,
    ]);

    $parent->delete();

    expect(TaxonomyTerm::find($child->id))
        ->not->toBeNull()
        ->parent_id->toBeNull();
});

it('deletes all terms when a taxonomy is deleted', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $taxonomy->terms()->create([
        'name' => 'Countries',
        'slug' => 'countries',
    ]);

    $taxonomy->delete();

    expect(TaxonomyTerm::count())->toBe(0);
});
