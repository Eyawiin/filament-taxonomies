<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support;

use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Illuminate\Contracts\Auth\Authenticatable;

class TaxonomyTermMutationPolicy
{
    public bool $allowMutations = false;

    public function create(Authenticatable $user): bool
    {
        return $this->allowMutations;
    }

    public function update(Authenticatable $user, TaxonomyTerm $term): bool
    {
        return $this->allowMutations;
    }

    public function delete(Authenticatable $user, TaxonomyTerm $term): bool
    {
        return $this->allowMutations;
    }
}
