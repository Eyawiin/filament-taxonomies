<?php

namespace Eyawiin\FilamentTaxonomies\Services;

use Closure;
use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyAssignmentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TaxonomyAssignmentService
{
    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @param  iterable<int|string>  $termIds
     */
    public function attach(Model $owner, mixed $taxonomy, iterable $termIds): void
    {
        $this->mutate($owner, $taxonomy, $termIds, function (Builder $assignments, array $selected, array $current, Taxonomy $fresh, string $type, string $key): void {
            $this->insert($selected, $current, $type, $key);
        });
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @param  iterable<int|string>  $termIds
     */
    public function detach(Model $owner, mixed $taxonomy, iterable $termIds): void
    {
        $this->mutate($owner, $taxonomy, $termIds, function (Builder $assignments, array $selected): void {
            $assignments->whereIn('taxonomy_term_id', $selected)->delete();
        });
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @param  iterable<int|string>  $termIds
     */
    public function sync(Model $owner, mixed $taxonomy, iterable $termIds): void
    {
        $this->mutate($owner, $taxonomy, $termIds, function (Builder $assignments, array $selected, array $current, Taxonomy $fresh, string $type, string $key): void {
            $inTaxonomy = TaxonomyModels::term()::withoutGlobalScopes()->where('taxonomy_id', $fresh->getKey())
                ->whereIn('id', $current)->lockForUpdate()->pluck('id')->all();
            // A scoped consumer cannot silently erase assignments it cannot see.
            $this->validateTerms($fresh, $inTaxonomy);
            $remove = array_diff($inTaxonomy, $selected);
            $assignments->whereIn('taxonomy_term_id', $remove)->delete();
            $this->insert($selected, $current, $type, $key);
        });
    }

    /**
     * IDs only, including scoped-out assigned terms, so a form never silently truncates state.
     * Authorize the owner and taxonomy before exposing these opaque IDs.
     *
     * @return list<int>
     */
    public function assignedTermIds(Model $owner, mixed $taxonomy): array
    {
        $this->assertConnection($owner);
        if (! in_array(HasTaxonomies::class, class_uses_recursive($owner), true) || ! $owner->exists || $owner->isDirty($owner->getKeyName())) {
            throw new InvalidTaxonomyAssignmentException('The owner must use HasTaxonomies and have an unchanged persisted identity.');
        }
        [$type, $key] = $this->identity($owner);
        $owner->newQuery()->whereKey($key)->firstOrFail([$owner->qualifyColumn('*')]);
        $resolved = $this->resolveTaxonomy($taxonomy);

        return $this->normalizeTerms($this->assignments($type, $key)
            ->join('taxonomy_terms', 'taxonomy_terms.id', '=', 'taxonomy_term_assignments.taxonomy_term_id')
            ->where('taxonomy_terms.taxonomy_id', $resolved->getKey())->orderBy('taxonomy_terms.id')
            ->pluck('taxonomy_term_assignments.taxonomy_term_id')->all());
    }

    public function assertConnection(Model $owner): void
    {
        $default = DB::connection()->getName();
        $taxonomyClass = TaxonomyModels::taxonomy();
        $termClass = TaxonomyModels::term();
        foreach ([$owner, new $taxonomyClass, new $termClass] as $model) {
            if ($model->getConnection()->getName() !== $default) {
                throw new InvalidTaxonomyAssignmentException('Assignments require the default database connection.');
            }
        }
    }

    /** Integer references are IDs; string references are exact slugs, including numeric slugs. */
    public function resolveTaxonomy(mixed $taxonomy): Taxonomy
    {
        $query = TaxonomyModels::taxonomy()::query();
        if ($taxonomy instanceof Taxonomy) {
            $this->assertConnection($taxonomy);
            $id = TaxonomyIdentity::normalize($taxonomy->getRawOriginal($taxonomy->getKeyName()));
            if (! $taxonomy->exists || $taxonomy->isDirty($taxonomy->getKeyName()) || $id === null) {
                throw new InvalidTaxonomyAssignmentException('A taxonomy must have an unchanged persisted identity.');
            }

            return $query->whereKey($id)->firstOrFail([$query->qualifyColumn('*')]);
        }
        if (is_int($taxonomy)) {
            if (TaxonomyIdentity::normalize($taxonomy) === null) {
                throw new InvalidTaxonomyAssignmentException('A taxonomy ID must be a positive native integer.');
            }

            return $query->whereKey($taxonomy)->firstOrFail([$query->qualifyColumn('*')]);
        }
        if (! is_string($taxonomy) || $taxonomy === '' || mb_strlen($taxonomy) > 255) {
            throw new InvalidTaxonomyAssignmentException('A taxonomy slug must be nonempty and at most 255 characters.');
        }
        $resolved = $query->where($query->qualifyColumn('slug'), $taxonomy)->firstOrFail([$query->qualifyColumn('*')]);
        if ($resolved->slug !== $taxonomy) {
            throw new InvalidTaxonomyAssignmentException('A taxonomy slug must match exactly.');
        }

        return $resolved;
    }

    /** Explicit cleanup for eventless deletions; use in the same transaction as the owner deletion. */
    public function forgetOwner(Model $owner): void
    {
        $this->assertConnection($owner);
        [$type, $key] = $this->identity($owner);
        $this->assignments($type, $key)->delete();
        $owner->unsetRelation('taxonomyTerms');
    }

    /**
     * Term IDs for an owner filter: the requested terms and, optionally, every visible term
     * below them. Missing, hidden and foreign terms match nothing.
     *
     * @param  Taxonomy|int|string  $taxonomy
     * @param  TaxonomyTerm|int|string|iterable<TaxonomyTerm|int|string>  $terms
     * @return list<int>
     */
    public function filterTermIds(mixed $taxonomy, mixed $terms, bool $includeDescendants = false): array
    {
        return array_values(array_unique(array_merge(...$this->filterTermIdGroups($taxonomy, $terms, $includeDescendants))));
    }

    /**
     * Like filterTermIds(), with one group per requested term for "all of these terms" filters.
     *
     * @param  Taxonomy|int|string  $taxonomy
     * @param  TaxonomyTerm|int|string|iterable<TaxonomyTerm|int|string>  $terms
     * @return list<list<int>>
     */
    public function filterTermIdGroups(mixed $taxonomy, mixed $terms, bool $includeDescendants = false): array
    {
        $requested = $this->normalizeFilterTerms($terms);
        $subtrees = app(TaxonomyTreeService::class)
            ->getVisibleSubtreeIds($this->resolveTaxonomy($taxonomy), $requested, $includeDescendants);

        return array_map(static fn (int $id): array => $subtrees[$id] ?? [], $requested);
    }

    /**
     * @param  Taxonomy|int|string  $taxonomy
     * @param  iterable<int|string>  $termIds
     */
    private function mutate(Model $owner, mixed $taxonomy, iterable $termIds, Closure $operation): void
    {
        $this->assertConnection($owner);
        if (! in_array(HasTaxonomies::class, class_uses_recursive($owner), true) || ! $owner->exists || $owner->isDirty($owner->getKeyName())) {
            throw new InvalidTaxonomyAssignmentException('The owner must use HasTaxonomies and have an unchanged persisted identity.');
        }
        [$type, $key] = $this->identity($owner);
        $selected = $this->normalizeTerms($termIds);
        $resolved = $this->resolveTaxonomy($taxonomy);
        app(TaxonomyTreeService::class)->withTaxonomyLock($resolved, function (Taxonomy $fresh) use ($owner, $taxonomy, $selected, $operation, $type, $key): void {
            if (is_string($taxonomy) && $fresh->slug !== $taxonomy) {
                throw new InvalidTaxonomyAssignmentException('The taxonomy slug changed before assignment.');
            }
            $stored = $owner->newQuery()->whereKey($key)->lockForUpdate()->firstOrFail([$owner->qualifyColumn('*')]);
            if ($this->identity($stored) !== [$type, $key]) {
                throw new InvalidTaxonomyAssignmentException('The stored owner identity does not match.');
            }
            $this->validateTerms($fresh, $selected);
            $assignments = $this->assignments($type, $key);
            $current = $this->normalizeTerms((clone $assignments)->lockForUpdate()->pluck('taxonomy_term_id')->all());
            $operation($assignments, $selected, $current, $fresh, $type, $key);
        });
        $owner->unsetRelation('taxonomyTerms');
    }

    /** @return array{string, string} */
    private function identity(Model $owner): array
    {
        $raw = $owner->getRawOriginal($owner->getKeyName());
        $integer = $owner->getKeyType() === 'int' && TaxonomyIdentity::normalize($raw) !== null;
        $string = $owner->getKeyType() === 'string' && is_string($raw) &&
            (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD', $raw) ||
             preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/iD', $raw));
        $type = $owner->getMorphClass();
        if ((! $integer && ! $string) || strlen($type) > 191 || ! preg_match('/^[a-z0-9_\\\\.\\-]+$/iD', $type)) {
            throw new InvalidTaxonomyAssignmentException('Owners require positive integer, UUID or ULID keys and an ASCII morph type of at most 191 bytes.');
        }

        return [$type, (string) $raw];
    }

    /**
     * Filters accept models as well as IDs and ignore repeated terms.
     *
     * @return list<int>
     */
    private function normalizeFilterTerms(mixed $terms): array
    {
        $values = is_iterable($terms) ? $terms : [$terms];
        $ids = [];
        foreach ($values as $value) {
            $id = TaxonomyIdentity::normalize($value instanceof TaxonomyTerm ? $value->getRawOriginal($value->getKeyName()) : $value);
            if ($id === null) {
                throw new InvalidTaxonomyAssignmentException('Filter terms must be persisted term models or positive integer IDs.');
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    /**
     * @param  iterable<int|string>  $values
     * @return list<int>
     */
    private function normalizeTerms(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = TaxonomyIdentity::normalize($value);
            if ($id === null || isset($ids[$id])) {
                throw new InvalidTaxonomyAssignmentException('Term IDs must be distinct positive native integers.');
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    /** @param array<int, int|string> $ids */
    private function validateTerms(Taxonomy $taxonomy, array $ids): void
    {
        $query = TaxonomyModels::term()::query();
        $visible = $query->where($query->qualifyColumn('taxonomy_id'), $taxonomy->getKey())
            ->whereIn($query->qualifyColumn('id'), $ids)->lockForUpdate()->get([$query->qualifyColumn('id')])
            ->pluck('id')->unique()->all();
        if (count($visible) !== count($ids)) {
            throw new InvalidTaxonomyAssignmentException('Every term must still be visible and belong to the selected taxonomy.');
        }
    }

    private function assignments(string $type, string $key): Builder
    {
        return DB::table('taxonomy_term_assignments')->where('assignable_type', $type)->where('assignable_id', $key);
    }

    /**
     * @param  list<int>  $selected
     * @param  array<int, int|string>  $current
     */
    private function insert(array $selected, array $current, string $type, string $key): void
    {
        $rows = array_map(fn (int $id): array => ['taxonomy_term_id' => $id, 'assignable_type' => $type, 'assignable_id' => $key], array_values(array_diff($selected, $current)));
        if ($rows !== []) {
            DB::table('taxonomy_term_assignments')->insert($rows);
        }
    }
}
