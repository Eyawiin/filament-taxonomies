<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

class OrderedTaxonomyTreeFixture
{
    /** @return array{taxonomy: Taxonomy, sourceParent: TaxonomyTerm, destinationParent: TaxonomyTerm, first: TaxonomyTerm, moving: TaxonomyTerm, last: TaxonomyTerm, destinationChild: TaxonomyTerm, grandchild: TaxonomyTerm} */
    public static function create(): array
    {
        $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
        $sourceParent = $taxonomy->terms()->create([
            'name' => 'Source', 'slug' => 'source', 'position' => 0,
        ]);
        $destinationParent = $taxonomy->terms()->create([
            'name' => 'Destination', 'slug' => 'destination', 'position' => 1,
        ]);
        $first = $sourceParent->children()->create([
            'taxonomy_id' => $taxonomy->id, 'name' => 'Zulu', 'slug' => 'zulu', 'position' => 0,
        ]);
        $moving = $sourceParent->children()->create([
            'taxonomy_id' => $taxonomy->id, 'name' => 'Middle', 'slug' => 'middle', 'position' => 1,
        ]);
        $last = $sourceParent->children()->create([
            'taxonomy_id' => $taxonomy->id, 'name' => 'Alpha', 'slug' => 'alpha', 'position' => 2,
        ]);
        $destinationChild = $destinationParent->children()->create([
            'taxonomy_id' => $taxonomy->id, 'name' => 'Existing', 'slug' => 'existing', 'position' => 0,
        ]);
        $grandchild = $moving->children()->create([
            'taxonomy_id' => $taxonomy->id, 'name' => 'Grandchild', 'slug' => 'grandchild', 'position' => 0,
        ]);

        return compact(
            'taxonomy',
            'sourceParent',
            'destinationParent',
            'first',
            'moving',
            'last',
            'destinationChild',
            'grandchild',
        );
    }
}
