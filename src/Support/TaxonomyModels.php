<?php

namespace Eyawiin\FilamentTaxonomies\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Illuminate\Container\Container;
use InvalidArgumentException;

/** The taxonomy and term model classes configured in filament-taxonomies.models. */
final class TaxonomyModels
{
    /** @return class-string<Taxonomy> */
    public static function taxonomy(): string
    {
        return self::resolve('taxonomy', Taxonomy::class);
    }

    /** @return class-string<TaxonomyTerm> */
    public static function term(): string
    {
        return self::resolve('term', TaxonomyTerm::class);
    }

    /**
     * @template TModel of Taxonomy|TaxonomyTerm
     *
     * @param  class-string<TModel>  $base
     * @return class-string<TModel>
     */
    private static function resolve(string $key, string $base): string
    {
        $container = Container::getInstance();
        // Standalone Eloquent setups, such as the MySQL concurrency runner, have no config repository.
        $class = $container->bound('config')
            ? $container->make('config')->get("filament-taxonomies.models.{$key}", $base)
            : $base;

        if (! is_string($class) || ! is_a($class, $base, true)) {
            throw new InvalidArgumentException("The filament-taxonomies.models.{$key} model must extend {$base}.");
        }

        return $class;
    }
}
