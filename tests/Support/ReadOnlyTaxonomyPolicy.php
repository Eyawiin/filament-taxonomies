<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Contracts\Auth\Authenticatable;

class ReadOnlyTaxonomyPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        return false;
    }
}
