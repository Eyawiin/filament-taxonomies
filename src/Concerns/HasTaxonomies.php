<?php

namespace Eyawiin\FilamentTaxonomies\Concerns;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyAssignmentService;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @mixin Model */
trait HasTaxonomies
{
    public static function bootHasTaxonomies(): void
    {
        static::deleted(function (Model $owner): void {
            if (in_array(SoftDeletes::class, class_uses_recursive($owner), true) && method_exists($owner, 'isForceDeleting') && ! $owner->isForceDeleting()) {
                return;
            }
            app(TaxonomyAssignmentService::class)->forgetOwner($owner);
        });
    }

    /** @return MorphToMany<TaxonomyTerm, $this> */
    public function taxonomyTerms(): MorphToMany
    {
        app(TaxonomyAssignmentService::class)->assertConnection($this);

        return $this->morphToMany(TaxonomyModels::term(), 'assignable', 'taxonomy_term_assignments', 'assignable_id', 'taxonomy_term_id')->distinct('taxonomy_terms.id');
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @return MorphToMany<TaxonomyTerm, $this>
     */
    public function termsForTaxonomy(mixed $taxonomy): MorphToMany
    {
        $resolved = app(TaxonomyAssignmentService::class)->resolveTaxonomy($taxonomy);
        $relation = $this->taxonomyTerms();

        return $relation->where($relation->getRelated()->qualifyColumn('taxonomy_id'), $resolved->getKey());
    }

    /**
     * Owners assigned to at least one of the terms. With $includeDescendants, a term also
     * matches owners assigned to any visible term below it.
     *
     * @param  Builder<static>  $query
     * @param  Taxonomy|int|string  $taxonomy
     * @param  TaxonomyTerm|int|string|iterable<TaxonomyTerm|int|string>  $terms
     */
    public function scopeWhereHasTaxonomyTerms(Builder $query, mixed $taxonomy, mixed $terms, bool $includeDescendants = false): void
    {
        $ids = app(TaxonomyAssignmentService::class)->filterTermIds($taxonomy, $terms, $includeDescendants);

        $query->whereHas('taxonomyTerms', static fn (Builder $assigned): Builder => $assigned->whereIn($assigned->qualifyColumn('id'), $ids));
    }

    /**
     * Owners assigned to every term or, with $includeDescendants, to a visible term in each
     * term's subtree.
     *
     * @param  Builder<static>  $query
     * @param  Taxonomy|int|string  $taxonomy
     * @param  TaxonomyTerm|int|string|iterable<TaxonomyTerm|int|string>  $terms
     */
    public function scopeWhereHasAllTaxonomyTerms(Builder $query, mixed $taxonomy, mixed $terms, bool $includeDescendants = false): void
    {
        foreach (app(TaxonomyAssignmentService::class)->filterTermIdGroups($taxonomy, $terms, $includeDescendants) as $ids) {
            $query->whereHas('taxonomyTerms', static fn (Builder $assigned): Builder => $assigned->whereIn($assigned->qualifyColumn('id'), $ids));
        }
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @param  iterable<int|string>  $termIds
     */
    public function attachTaxonomyTerms(mixed $taxonomy, iterable $termIds): void
    {
        app(TaxonomyAssignmentService::class)->attach($this, $taxonomy, $termIds);
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @param  iterable<int|string>  $termIds
     */
    public function detachTaxonomyTerms(mixed $taxonomy, iterable $termIds): void
    {
        app(TaxonomyAssignmentService::class)->detach($this, $taxonomy, $termIds);
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @param  iterable<int|string>  $termIds
     */
    public function syncTaxonomyTerms(mixed $taxonomy, iterable $termIds): void
    {
        app(TaxonomyAssignmentService::class)->sync($this, $taxonomy, $termIds);
    }
}
