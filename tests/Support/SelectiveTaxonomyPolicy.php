<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Contracts\Auth\Authenticatable;

class SelectiveTaxonomyPolicy extends ReadOnlyTaxonomyPolicy
{
    public function view(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        return $taxonomy->slug !== 'private';
    }
}
