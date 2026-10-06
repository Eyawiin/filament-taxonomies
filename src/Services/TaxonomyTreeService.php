<?php

namespace Eyawiin\FilamentTaxonomies\Services;

use Closure;
use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyDropException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class TaxonomyTreeService
{
    /**
     * @return list<int>
     */
    public function getDescendantIds(TaxonomyTerm $term): array
    {
        $this->assertIdentity($term);
        $descendantIds = [];
        $parentIds = [(int) $term->getKey()];
        $visitedIds = $parentIds;

        while (true) {
            $query = TaxonomyModels::term()::query();
            $children = $query
                ->where($query->qualifyColumn('taxonomy_id'), $term->taxonomy_id)
                ->whereIn($query->qualifyColumn('parent_id'), $parentIds)
                ->toBase()->pluck($query->getModel()->getQualifiedKeyName())
                ->map(static fn (mixed $id): ?int => TaxonomyIdentity::normalize($id))
                ->filter()
                ->values()
                ->all();

            if ($children === []) {
                break;
            }

            $children = array_values(array_unique(array_diff($children, $visitedIds)));

            if ($children === []) {
                break;
            }

            $descendantIds = [
                ...$descendantIds,
                ...$children,
            ];

            $visitedIds = [
                ...$visitedIds,
                ...$children,
            ];

            $parentIds = $children;
        }

        return $descendantIds;
    }

    /**
     * For read filters: each requested term with, optionally, every term below it, using one
     * query. Missing, hidden and foreign terms are left out, as are terms only reachable
     * through a hidden ancestor, matching the displayed tree.
     *
     * @param  list<int>  $termIds
     * @return array<int, list<int>> Keyed by requested term ID; each list starts with that term.
     */
    public function getVisibleSubtreeIds(Taxonomy $taxonomy, array $termIds, bool $includeDescendants = true): array
    {
        $this->assertIdentity($taxonomy);
        $query = TaxonomyModels::term()::query();
        $parents = $query->where($query->qualifyColumn('taxonomy_id'), $taxonomy->getKey())
            ->toBase()->pluck($query->qualifyColumn('parent_id'), $query->getModel()->getQualifiedKeyName())->all();

        $visible = [];
        $children = [];
        foreach ($parents as $id => $parent) {
            $id = TaxonomyIdentity::normalize($id);
            if ($id === null) {
                continue;
            }
            $visible[$id] = true;
            $parentId = $parent === null ? null : TaxonomyIdentity::normalize($parent);
            if ($parentId !== null) {
                $children[$parentId][] = $id;
            }
        }

        $subtrees = [];
        foreach ($termIds as $termId) {
            if (! isset($visible[$termId])) {
                continue;
            }
            $subtree = [$termId];
            $seen = [$termId => true];
            // Breadth-first; $seen also stops at cycles in imported data.
            for ($index = 0; $includeDescendants && $index < count($subtree); $index++) {
                foreach ($children[$subtree[$index]] ?? [] as $child) {
                    if (! isset($seen[$child])) {
                        $seen[$child] = true;
                        $subtree[] = $child;
                    }
                }
            }
            $subtrees[$termId] = $subtree;
        }

        return $subtrees;
    }

    /**
     * @return list<array{term: TaxonomyTerm, children: list<mixed>}>
     */
    public function getTree(Taxonomy $taxonomy): array
    {
        $this->assertIdentity($taxonomy);
        $query = $taxonomy->terms();
        $terms = $query
            ->orderBy($query->qualifyColumn('position'))
            ->orderBy($query->qualifyColumn('name'))
            ->orderBy($query->qualifyColumn('id'))
            ->get([$query->getModel()->qualifyColumn('*')])
            ->filter(static fn (TaxonomyTerm $term): bool => TaxonomyIdentity::normalize($term->getRawOriginal($term->getKeyName())) !== null)
            ->unique('id');

        /** @var array<int|string, list<TaxonomyTerm>> $childrenByParent */
        $childrenByParent = [];

        foreach ($terms as $term) {
            $rawParent = $term->getRawOriginal('parent_id');
            $parentId = $rawParent === null ? 'root' : TaxonomyIdentity::normalize($rawParent);
            if ($parentId === null) {
                continue;
            }

            $childrenByParent[$parentId][] = $term;
        }

        return $this->buildTree($childrenByParent, 'root');
    }

    /**
     * Read-only import diagnostics, including scoped-out structural rows.
     * Callers must authorize access to the entire taxonomy before exposing results.
     * A reason describes the affected ancestor chain, including descendants of a defect.
     *
     * @return list<array{term_id: int|string, reason: 'cycle'|'missing_or_foreign_parent'|'invalid_identity'}>
     */
    public function diagnoseTree(Taxonomy $taxonomy): array
    {
        $this->assertConnection($taxonomy);
        $this->assertIdentity($taxonomy);
        $parents = TaxonomyModels::term()::withoutGlobalScopes()
            ->where('taxonomy_id', $taxonomy->getKey())
            ->orderBy('id')
            ->toBase()->pluck('parent_id', 'id')->all();
        $resolved = [];

        foreach ($parents as $id => $parent) {
            $path = [];
            $current = $id;
            $reason = null;
            while ($current !== null) {
                if (TaxonomyIdentity::normalize($current) === null) {
                    $path[$current] = true;
                    $reason = 'invalid_identity';

                    break;
                }
                if (array_key_exists($current, $resolved)) {
                    $reason = $resolved[$current];

                    break;
                }
                if (isset($path[$current])) {
                    $reason = 'cycle';

                    break;
                }
                if (! array_key_exists($current, $parents)) {
                    $reason = 'missing_or_foreign_parent';

                    break;
                }
                $path[$current] = true;
                $current = $parents[$current];
            }
            foreach ($path as $visited => $unused) {
                $resolved[$visited] = $reason;
            }
        }

        $issues = [];
        foreach ($parents as $id => $parent) {
            if ($resolved[$id] !== null) {
                $issues[] = ['term_id' => $id, 'reason' => $resolved[$id]];
            }
        }

        return $issues;
    }

    /**
     * @param  array<int|string, list<TaxonomyTerm>>  $childrenByParent
     * @return list<array{term: TaxonomyTerm, children: list<mixed>}>
     */
    private function buildTree(array $childrenByParent, int | string $parentId): array
    {
        $tree = [];

        foreach ($childrenByParent[$parentId] ?? [] as $term) {
            $tree[] = [
                'term' => $term,
                'children' => $this->buildTree(
                    $childrenByParent,
                    (int) $term->getKey(),
                ),
            ];
        }

        return $tree;
    }

    public function getNextPosition(
        int $taxonomyId,
        ?int $parentId,
    ): int {
        $query = TaxonomyModels::term()::query();
        $maxPosition = $query
            ->where($query->qualifyColumn('taxonomy_id'), $taxonomyId)
            ->where($query->qualifyColumn('parent_id'), $parentId)
            ->max($query->qualifyColumn('position'));

        if ($maxPosition === null) {
            return 0;
        }

        return ((int) $maxPosition) + 1;
    }

    /**
     * Advisory only: writes always repeat validation under the taxonomy lock.
     */
    public function canSetParent(TaxonomyTerm $term, ?TaxonomyTerm $parent): bool
    {
        try {
            $this->assertConnection($term);
            $taxonomyId = (int) $term->getRawOriginal('taxonomy_id');
            $source = $this->resolveTerm($term, $taxonomyId, false);
            $destination = $parent === null ? null : $this->resolveTerm($parent, $taxonomyId, false);
            $this->validateParent($source, $destination, $this->structure($taxonomyId, false));

            return true;
        } catch (InvalidTaxonomyParentException | ModelNotFoundException) {
            return false;
        }
    }

    /**
     * Integration boundary for fresh resource visibility and policy checks.
     * The callback runs on the default connection while the taxonomy is locked.
     */
    public function withTaxonomyLock(Taxonomy $taxonomy, Closure $operation): mixed
    {
        return $this->mutate($taxonomy, $operation);
    }

    public function createTerm(Taxonomy $taxonomy, string $name, string $slug, ?TaxonomyTerm $parent = null): TaxonomyTerm
    {
        return $this->mutate($taxonomy, function (Taxonomy $fresh) use ($name, $slug, $parent): TaxonomyTerm {
            $taxonomyId = (int) $fresh->getKey();
            $destination = $parent === null ? null : $this->resolveTerm($parent, $taxonomyId);
            $terms = $this->structure($taxonomyId);
            if ($destination !== null) {
                $this->ancestors($destination, $terms);
            }
            $parentId = $destination === null ? null : (int) $destination->getKey();
            $siblings = $this->siblingIds($terms, $parentId);
            $termClass = TaxonomyModels::term();
            $term = new $termClass([
                'taxonomy_id' => $taxonomyId, 'parent_id' => $parentId,
                'name' => $name, 'slug' => $slug, 'position' => count($siblings),
            ]);
            $this->save($term);
            $this->persistSiblingPositions($taxonomyId, $parentId, [...$siblings, (int) $term->getKey()]);

            return $this->reload($term);
        });
    }

    public function setParent(TaxonomyTerm $term, ?TaxonomyTerm $parent): TaxonomyTerm
    {
        $metadata = $this->metadata($term);
        $result = $this->mutate($term, function (Taxonomy $taxonomy) use ($term, $parent, $metadata): TaxonomyTerm {
            $taxonomyId = (int) $taxonomy->getKey();
            $source = $this->resolveTerm($term, $taxonomyId);
            $destination = $parent === null ? null : $this->resolveTerm($parent, $taxonomyId);
            $terms = $this->structure($taxonomyId);
            $this->validateParent($source, $destination, $terms);
            $parentId = $destination === null ? null : (int) $destination->getKey();

            // Metadata-only edits preserve the current stored position, including legacy gaps.
            if ($this->parentId($source) === $parentId) {
                $source->fill($metadata);
                $this->save($source);

                return $this->reload($source);
            }

            return $this->moveLocked($source, $destination, $terms, count($this->siblingIds($terms, $parentId)), $metadata);
        });

        return $this->synchronize($term, $result);
    }

    public function moveTerm(TaxonomyTerm $term, ?TaxonomyTerm $parent, int $position): TaxonomyTerm
    {
        $metadata = $this->metadata($term);
        $result = $this->mutate($term, function (Taxonomy $taxonomy) use ($term, $parent, $position, $metadata): TaxonomyTerm {
            $taxonomyId = (int) $taxonomy->getKey();

            return $this->moveLocked(
                $this->resolveTerm($term, $taxonomyId),
                $parent === null ? null : $this->resolveTerm($parent, $taxonomyId),
                $this->structure($taxonomyId),
                $position,
                $metadata,
            );
        });

        return $this->synchronize($term, $result);
    }

    public function moveRelativeTo(TaxonomyTerm $term, TaxonomyTerm $target, TaxonomyTermDropPosition $position): TaxonomyTerm
    {
        $metadata = $this->metadata($term);
        $result = $this->mutate($term, function (Taxonomy $taxonomy) use ($term, $target, $position, $metadata): TaxonomyTerm {
            $taxonomyId = (int) $taxonomy->getKey();
            $this->assertIdentity($target);
            if ((int) $target->getRawOriginal('taxonomy_id') !== $taxonomyId || $term->getKey() === $target->getKey()) {
                throw new InvalidTaxonomyDropException('A drop target must be a different term in the same taxonomy.');
            }
            $source = $this->resolveTerm($term, $taxonomyId);
            $freshTarget = $this->resolveTerm($target, $taxonomyId);
            $terms = $this->structure($taxonomyId);
            $this->ancestors($freshTarget, $terms);

            if ($position === TaxonomyTermDropPosition::Inside) {
                $parent = $freshTarget;
                $index = count($this->siblingIds($terms, (int) $parent->getKey(), (int) $source->getKey()));
            } else {
                $parentId = $this->parentId($freshTarget);
                $parentQuery = TaxonomyModels::term()::query();
                $parent = $parentId === null ? null : $parentQuery
                    ->where($parentQuery->qualifyColumn('taxonomy_id'), $taxonomyId)
                    ->lockForUpdate()->findOrFail($parentId, [$parentQuery->getModel()->qualifyColumn('*')]);
                $siblings = $this->siblingIds($terms, $parentId, (int) $source->getKey());
                $index = array_search((int) $freshTarget->getKey(), $siblings, true);
                if ($index === false) {
                    throw new InvalidTaxonomyDropException('The drop target is no longer in its sibling group.');
                }
                if ($position === TaxonomyTermDropPosition::After) {
                    $index++;
                }
            }

            return $this->moveLocked($source, $parent, $terms, $index, $metadata);
        });

        return $this->synchronize($term, $result);
    }

    /** @param array<array-key, mixed> $termIds Positive integer IDs or integer strings, validated before writing. */
    public function reorderSiblings(Taxonomy $taxonomy, ?TaxonomyTerm $parent, array $termIds): void
    {
        $this->mutate($taxonomy, function (Taxonomy $fresh) use ($parent, $termIds): void {
            $taxonomyId = (int) $fresh->getKey();
            if ($parent !== null && (int) $parent->getRawOriginal('taxonomy_id') !== $taxonomyId) {
                throw new InvalidTaxonomyOrderException('The parent must belong to the taxonomy being reordered.');
            }
            $destination = $parent === null ? null : $this->resolveTerm($parent, $taxonomyId);
            $terms = $this->structure($taxonomyId);
            if ($destination !== null) {
                $this->ancestors($destination, $terms);
            }
            $parentId = $destination === null ? null : (int) $destination->getKey();
            $expected = $this->siblingIds($terms, $parentId);
            $provided = [];
            foreach ($termIds as $id) {
                $normalized = is_int($id) || is_string($id)
                    ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : false;
                if ($normalized === false) {
                    throw new InvalidTaxonomyOrderException('Sibling IDs must be positive integers.');
                }
                $provided[] = $normalized;
            }
            $visibleQuery = TaxonomyModels::term()::query();
            $visibleQuery->where($visibleQuery->qualifyColumn('taxonomy_id'), $taxonomyId)
                ->where($visibleQuery->qualifyColumn('parent_id'), $parentId)->lockForUpdate();
            $visible = array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                $visibleQuery->pluck($visibleQuery->getModel()->getQualifiedKeyName())->all(),
            )));
            $sorted = $provided;
            sort($expected);
            sort($sorted);
            sort($visible);
            if ($expected !== $sorted || count($provided) !== count(array_unique($provided)) || $visible !== $expected) {
                throw new InvalidTaxonomyOrderException('The order must contain every visible sibling exactly once, with no hidden siblings.');
            }
            foreach ($provided as $id) {
                $this->ancestors($terms[$id], $terms);
            }
            $this->persistSiblingPositions($taxonomyId, $parentId, $provided);
        });
    }

    public function deleteTerm(TaxonomyTerm $term): bool
    {
        $deleted = $this->mutate($term, function (Taxonomy $taxonomy) use ($term): bool {
            $taxonomyId = (int) $taxonomy->getKey();
            $source = $this->resolveTerm($term, $taxonomyId);
            $terms = $this->structure($taxonomyId);
            $this->ancestors($source, $terms);
            $this->assertNoForeignChildren($taxonomyId, [(int) $source->getKey()]);
            $oldParentId = $this->parentId($source);
            $roots = $this->siblingIds($terms, null, (int) $source->getKey());
            $children = $this->siblingIds($terms, (int) $source->getKey());

            // The FK promotes direct children; normalize only after deletion is accepted.
            if (! $source->delete()) {
                return false;
            }
            if ($oldParentId !== null) {
                $this->persistSiblingPositions($taxonomyId, $oldParentId, $this->siblingIds($terms, $oldParentId, (int) $source->getKey()));
            }
            $this->persistSiblingPositions($taxonomyId, null, [...$roots, ...$children]);

            return true;
        });
        if ($deleted) {
            $term->exists = false;
        }

        return $deleted;
    }

    public function deleteTaxonomy(Taxonomy $taxonomy): bool
    {
        $deleted = $this->mutate($taxonomy, function (Taxonomy $fresh): bool {
            $terms = $this->structure((int) $fresh->getKey());
            $this->assertNoForeignChildren((int) $fresh->getKey(), array_keys($terms));

            return (bool) $fresh->delete();
        });
        if ($deleted) {
            $taxonomy->exists = false;
        }

        return $deleted;
    }

    private function mutate(Taxonomy | TaxonomyTerm $owner, Closure $operation): mixed
    {
        $this->assertConnection($owner);
        $this->assertIdentity($owner);
        $taxonomyId = $owner instanceof Taxonomy ? (int) $owner->getKey() : (int) $owner->getRawOriginal('taxonomy_id');

        // One attempt: replaying callbacks could replay consumer observers or external effects.
        return DB::connection()->transaction(function () use ($taxonomyId, $operation): mixed {
            $query = TaxonomyModels::taxonomy()::query();
            $taxonomy = $query->lockForUpdate()->findOrFail($taxonomyId, [$query->getModel()->qualifyColumn('*')]);

            return $operation($taxonomy);
        }, 1);
    }

    private function assertIdentity(Taxonomy | TaxonomyTerm $model): void
    {
        $invalidTaxonomy = $model instanceof TaxonomyTerm
            && (TaxonomyIdentity::normalize($model->getRawOriginal('taxonomy_id')) === null || $model->isDirty('taxonomy_id'));
        if (! $model->exists || TaxonomyIdentity::normalize($model->getRawOriginal($model->getKeyName())) === null || $model->isDirty($model->getKeyName()) || $invalidTaxonomy) {
            throw new LogicException('Managed writes require a persisted model with positive, unchanged identity and taxonomy.');
        }
    }

    private function assertConnection(Model $model): void
    {
        $default = DB::getDefaultConnection();
        $taxonomyClass = TaxonomyModels::taxonomy();
        $termClass = TaxonomyModels::term();
        foreach ([$model, new $taxonomyClass, new $termClass] as $candidate) {
            if ($candidate->getConnection()->getName() !== $default) {
                throw new LogicException('Managed taxonomy writes require taxonomy and term models on the default database connection.');
            }
        }
    }

    private function resolveTerm(TaxonomyTerm $term, int $taxonomyId, bool $lock = true): TaxonomyTerm
    {
        $this->assertConnection($term);
        $this->assertIdentity($term);
        if ((int) $term->getRawOriginal('taxonomy_id') !== $taxonomyId) {
            throw new InvalidTaxonomyParentException('The term must belong to the owning taxonomy.');
        }

        $query = TaxonomyModels::term()::query();
        $query->where($query->qualifyColumn('taxonomy_id'), $taxonomyId);
        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var TaxonomyTerm $resolved A scalar key yields one model. */
        $resolved = $query->findOrFail($term->getKey(), [$query->getModel()->qualifyColumn('*')]);

        return $resolved;
    }

    /** @return array<int|string, TaxonomyTerm> */
    private function structure(int $taxonomyId, bool $lock = true): array
    {
        // Structural integrity must include scoped-out ancestors and siblings.
        // These rows are never returned as UI options or selectable input records.
        $query = TaxonomyModels::term()::withoutGlobalScopes()->where('taxonomy_id', $taxonomyId)->orderBy('position')->orderBy('name')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy(static fn (TaxonomyTerm $term): int | string => $term->getRawOriginal($term->getKeyName()))->all();
    }

    /** @param array<int|string, TaxonomyTerm> $terms
     * @return list<int>
     */
    private function ancestors(TaxonomyTerm $term, array $terms): array
    {
        $visited = [];
        $current = $term;
        while (true) {
            $id = TaxonomyIdentity::normalize($current->getRawOriginal($current->getKeyName()));
            if ($id === null) {
                throw new InvalidTaxonomyParentException('The affected hierarchy contains an unsupported term identity.');
            }
            if (in_array($id, $visited, true)) {
                throw new InvalidTaxonomyParentException('The affected hierarchy contains a cycle.');
            }
            $visited[] = $id;
            $parentId = $this->parentId($current);
            if ($parentId === null) {
                return $visited;
            }
            if (! isset($terms[$parentId])) {
                throw new InvalidTaxonomyParentException('The affected hierarchy contains a missing or foreign parent.');
            }
            $current = $terms[$parentId];
        }
    }

    /** @param array<int|string, TaxonomyTerm> $terms */
    private function validateParent(TaxonomyTerm $source, ?TaxonomyTerm $parent, array $terms): void
    {
        $this->ancestors($source, $terms);
        if ($parent !== null && in_array((int) $source->getKey(), $this->ancestors($parent, $terms), true)) {
            throw new InvalidTaxonomyParentException('The selected term cannot be used as the parent of this term.');
        }
    }

    /** @param array<int|string, TaxonomyTerm> $terms
     * @param  array<string, mixed>  $metadata
     */
    private function moveLocked(TaxonomyTerm $source, ?TaxonomyTerm $parent, array $terms, int $position, array $metadata): TaxonomyTerm
    {
        $this->validateParent($source, $parent, $terms);
        $taxonomyId = (int) $source->taxonomy_id;
        $termId = (int) $source->getKey();
        $oldParentId = $this->parentId($source);
        $newParentId = $parent === null ? null : (int) $parent->getKey();
        $destination = $this->siblingIds($terms, $newParentId, $termId);
        if ($position < 0 || $position > count($destination)) {
            throw new InvalidTaxonomyOrderException('The term position is outside the destination sibling group.');
        }
        $source->fill($metadata);
        $source->parent_id = $newParentId;
        $source->position = $position;
        $this->save($source);
        if ($oldParentId !== $newParentId) {
            $this->persistSiblingPositions($taxonomyId, $oldParentId, $this->siblingIds($terms, $oldParentId, $termId));
        }
        array_splice($destination, $position, 0, [$termId]);
        $this->persistSiblingPositions($taxonomyId, $newParentId, $destination);

        return $this->reload($source);
    }

    private function parentId(TaxonomyTerm $term): ?int
    {
        $raw = $term->getRawOriginal('parent_id');
        if ($raw === null) {
            return null;
        }
        $id = TaxonomyIdentity::normalize($raw);
        if ($id === null) {
            throw new InvalidTaxonomyParentException('The affected hierarchy contains an unsupported parent identity.');
        }

        return $id;
    }

    /** @param array<int|string, TaxonomyTerm> $terms
     * @return list<int>
     */
    private function siblingIds(array $terms, ?int $parentId, ?int $exclude = null): array
    {
        $siblings = [];
        foreach ($terms as $term) {
            $rawParent = $term->getRawOriginal('parent_id');
            $storedParent = $rawParent === null ? null : TaxonomyIdentity::normalize($rawParent);
            if (($rawParent !== null && $storedParent === null) || $storedParent !== $parentId) {
                continue;
            }
            $id = TaxonomyIdentity::normalize($term->getRawOriginal($term->getKeyName()));
            if ($id === null) {
                throw new InvalidTaxonomyParentException('The affected sibling group contains an unsupported identity.');
            }
            if ($id !== $exclude) {
                $siblings[] = $id;
            }
        }

        return $siblings;
    }

    /** @param list<int> $termIds */
    private function persistSiblingPositions(int $taxonomyId, ?int $parentId, array $termIds): void
    {
        foreach ($termIds as $position => $termId) {
            // Bulk maintenance deliberately does not fire sibling model save events.
            TaxonomyModels::term()::withoutGlobalScopes()->where('taxonomy_id', $taxonomyId)
                ->where('parent_id', $parentId)->whereKey($termId)->update(['position' => $position]);
        }
    }

    /** @param list<int|string> $termIds */
    private function assertNoForeignChildren(int $taxonomyId, array $termIds): void
    {
        if ($termIds !== [] && TaxonomyModels::term()::withoutGlobalScopes()->where('taxonomy_id', '!=', $taxonomyId)
            ->whereIn('parent_id', $termIds)->lockForUpdate()->first() !== null) {
            throw new InvalidTaxonomyParentException('Deletion would affect a term belonging to another taxonomy.');
        }
    }

    /** @return array<string, mixed> */
    private function metadata(TaxonomyTerm $term): array
    {
        return array_intersect_key($term->getDirty(), array_flip(['name', 'slug']));
    }

    private function save(TaxonomyTerm $term): void
    {
        $creating = ! $term->exists;
        if (! $term->save()) {
            throw new RuntimeException('The taxonomy term write was cancelled.');
        }
        // insertGetId() can clamp unsigned overflow before Eloquent stores the key.
        if ($creating && (TaxonomyIdentity::normalize($term->getKey()) === null || $term->getKey() >= PHP_INT_MAX)) {
            throw new RuntimeException('The generated taxonomy term identity exceeds the supported integer range.');
        }
    }

    private function reload(TaxonomyTerm $term): TaxonomyTerm
    {
        // refresh() uses a snapshot read, which can be stale in an outer RR transaction.
        $query = TaxonomyModels::term()::query();
        /** @var TaxonomyTerm $fresh A scalar key yields one model. */
        $fresh = $query->where($query->qualifyColumn('taxonomy_id'), $term->taxonomy_id)
            ->lockForUpdate()->findOrFail($term->getKey(), [$query->getModel()->qualifyColumn('*')]);

        return $fresh;
    }

    private function synchronize(TaxonomyTerm $original, TaxonomyTerm $fresh): TaxonomyTerm
    {
        $original->setRawAttributes($fresh->getAttributes(), true);
        $original->setRelations([]);

        return $original;
    }
}
