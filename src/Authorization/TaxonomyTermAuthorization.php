<?php

namespace Eyawiin\FilamentTaxonomies\Authorization;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

use function Filament\get_authorization_response;

class TaxonomyTermAuthorization
{
    public static function create(Taxonomy $taxonomy): Response
    {
        $gate = Gate::forUser(Filament::auth()->user());
        $policy = $gate->getPolicyFor(TaxonomyTerm::class);

        if ($policy && method_exists($policy, 'create')) {
            return $gate->inspect('create', [TaxonomyTerm::class, $taxonomy]);
        }

        return get_authorization_response('create', TaxonomyTerm::class);
    }

    public static function update(TaxonomyTerm $term): Response
    {
        return get_authorization_response('update', $term);
    }

    public static function delete(TaxonomyTerm $term): Response
    {
        return get_authorization_response('delete', $term);
    }
}
