<?php

namespace Eyawiin\FilamentTaxonomies\Authorization;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

use function Filament\get_authorization_response;

class TaxonomyTermAuthorization
{
    public static function create(Taxonomy $taxonomy): Response
    {
        $gate = Gate::forUser(Filament::auth()->user());
        // Laravel falls back to a policy registered for a parent class, so package-model policies still apply.
        $termClass = TaxonomyModels::term();
        $policy = $gate->getPolicyFor($termClass);

        if ($policy && method_exists($policy, 'create')) {
            return $gate->inspect('create', [$termClass, $taxonomy]);
        }

        return get_authorization_response('create', $termClass);
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
