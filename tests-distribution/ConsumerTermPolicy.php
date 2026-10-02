<?php

namespace App\Policies;

use App\Models\User;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

class ConsumerTermPolicy
{
    public function create(?User $user, Taxonomy $taxonomy): bool
    {
        return true;
    }

    public function update(?User $user, TaxonomyTerm $term): bool
    {
        return $term->slug !== 'locked';
    }

    public function delete(?User $user, TaxonomyTerm $term): bool
    {
        return $term->slug !== 'locked';
    }
}
