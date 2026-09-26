<?php

namespace Eyawiin\FilamentTaxonomies\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Eyawiin\FilamentTaxonomies\FilamentTaxonomies
 */
class FilamentTaxonomies extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Eyawiin\FilamentTaxonomies\FilamentTaxonomies::class;
    }
}
