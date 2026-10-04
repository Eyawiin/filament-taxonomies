<?php

namespace Eyawiin\FilamentTaxonomies\Concerns;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyAssignmentService;
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

        return $this->morphToMany(TaxonomyTerm::class, 'assignable', 'taxonomy_term_assignments', 'assignable_id', 'taxonomy_term_id')->distinct('taxonomy_terms.id');
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @return MorphToMany<TaxonomyTerm, $this>
     */
    public function termsForTaxonomy(mixed $taxonomy): MorphToMany
    {
        $resolved = app(TaxonomyAssignmentService::class)->resolveTaxonomy($taxonomy);

        return $this->taxonomyTerms()->where('taxonomy_terms.taxonomy_id', $resolved->getKey());
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
