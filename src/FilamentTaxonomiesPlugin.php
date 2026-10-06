<?php

namespace Eyawiin\FilamentTaxonomies;

use BackedEnum;
use Closure;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use InvalidArgumentException;
use UnitEnum;

class FilamentTaxonomiesPlugin implements Plugin
{
    use EvaluatesClosures;

    /** @var class-string<TaxonomyResource> */
    protected string $resource = TaxonomyResource::class;

    protected string | Closure | null $navigationLabel = null;

    protected string | BackedEnum | Closure | null $navigationIcon = null;

    protected string | UnitEnum | Closure | null $navigationGroup = null;

    protected bool $hasNavigationGroup = false;

    protected int | Closure | null $navigationSort = null;

    protected bool | Closure $hasTaxonomyNavigation = true;

    protected string | UnitEnum | Closure | null $taxonomyNavigationGroup = null;

    protected bool $hasTaxonomyNavigationGroup = false;

    public function getId(): string
    {
        return 'filament-taxonomies';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            $this->getResource(),
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Register a subclass, for example to scope the taxonomy query.
     *
     * @param  class-string  $resource  Must extend TaxonomyResource; checked at runtime.
     */
    public function resource(string $resource): static
    {
        if (! is_a($resource, TaxonomyResource::class, true)) {
            throw new InvalidArgumentException('The taxonomy resource must extend ' . TaxonomyResource::class . '.');
        }

        $this->resource = $resource;

        return $this;
    }

    /** @return class-string<TaxonomyResource> */
    public function getResource(): string
    {
        return $this->resource;
    }

    public function navigationLabel(string | Closure | null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->evaluate($this->navigationLabel);
    }

    public function navigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string | BackedEnum | null
    {
        return $this->evaluate($this->navigationIcon);
    }

    /** Group of the taxonomy list entry; null places it outside any group, which is the default. */
    public function navigationGroup(string | UnitEnum | Closure | null $group): static
    {
        $this->navigationGroup = $group;
        $this->hasNavigationGroup = true;

        return $this;
    }

    public function hasNavigationGroup(): bool
    {
        return $this->hasNavigationGroup;
    }

    public function getNavigationGroup(): string | UnitEnum | null
    {
        return $this->evaluate($this->navigationGroup);
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->evaluate($this->navigationSort);
    }

    /** List every visible taxonomy with its term count in the navigation. Costs one query per page. */
    public function taxonomyNavigation(bool | Closure $condition = true): static
    {
        $this->hasTaxonomyNavigation = $condition;

        return $this;
    }

    public function hasTaxonomyNavigation(): bool
    {
        return (bool) $this->evaluate($this->hasTaxonomyNavigation);
    }

    /** Group of the per-taxonomy entries; defaults to the translated "Taxonomies". */
    public function taxonomyNavigationGroup(string | UnitEnum | Closure | null $group): static
    {
        $this->taxonomyNavigationGroup = $group;
        $this->hasTaxonomyNavigationGroup = true;

        return $this;
    }

    public function getTaxonomyNavigationGroup(): string | UnitEnum | null
    {
        if (! $this->hasTaxonomyNavigationGroup) {
            return __('filament-taxonomies::taxonomies.navigation.taxonomies_group');
        }

        return $this->evaluate($this->taxonomyNavigationGroup);
    }
}
