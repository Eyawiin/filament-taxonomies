<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Contracts\Auth\Authenticatable;

class ContextualTaxonomyTermPolicy
{
    public function create(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        return $taxonomy->slug === 'public';
    }
}
