<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

class TaxonomyTreeFixture
{
    /** @return array{taxonomy: Taxonomy, disney: TaxonomyTerm, lionKing: TaxonomyTerm, simba: TaxonomyTerm, liloAndStitch: TaxonomyTerm} */
    public static function create(): array
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
}
