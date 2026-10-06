<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

return [
    /*
     * Models used throughout the package, for example to add attributes or
     * relationships. Custom models must extend these classes and keep their
     * tables. Panel settings belong on FilamentTaxonomiesPlugin instead.
     */
    'models' => [
        'taxonomy' => Taxonomy::class,
        'term' => TaxonomyTerm::class,
    ],
];
