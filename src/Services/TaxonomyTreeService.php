<?php

namespace Eyawiin\FilamentTaxonomies\Services;

use Closure;
use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyDropException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
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
        $descendantIds = [];
        $parentIds = [(int) $term->getKey()];
        $visitedIds = [];

        while (true) {
            $children = TaxonomyTerm::query()
                ->where('taxonomy_id', $term->taxonomy_id)
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            if (empty($children)) {
                break;
            }

            $children = array_values(array_diff($children, $visitedIds));

            if (empty($children)) {
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

    public function getTree(Taxonomy $taxonomy): array
    {
        $terms = $taxonomy->terms()
            ->orderBy('position')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        /** @var array<int, list<TaxonomyTerm>> $childrenByParent */
        $childrenByParent = [];

        foreach ($terms as $term) {
            $parentId = $term->parent_id ?? 0;

            $childrenByParent[$parentId][] = $term;
        }

        return $this->buildTree($childrenByParent, 0);
    }

    /**
     * @param  array<int, list<TaxonomyTerm>>  $childrenByParent
     * @return list<array{term: TaxonomyTerm, children: list<mixed>}>
     */
    private function buildTree(array $childrenByParent, int $parentId): array
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
        $maxPosition = TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('parent_id', $parentId)
            ->max('position');

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
        } catch (InvalidTaxonomyParentException | ModelNotFoundException $exception) {
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
            $term = new TaxonomyTerm([
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
                $parent = $parentId === null ? null : TaxonomyTerm::query()
                    ->where('taxonomy_id', $taxonomyId)->lockForUpdate()->findOrFail($parentId);
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

    /** @param array<array-key, int> $termIds */
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
            $provided = array_values(array_map(static fn (mixed $id): int => (int) $id, $termIds));
            $visibleQuery = TaxonomyTerm::query()->where('taxonomy_id', $taxonomyId)
                ->where('parent_id', $parentId)->lockForUpdate();
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
        if (! $owner->exists || $owner->isDirty($owner->getKeyName()) || ($owner instanceof TaxonomyTerm && $owner->isDirty('taxonomy_id'))) {
            throw new LogicException('Managed writes require a persisted model with an unchanged identity and taxonomy.');
        }
        $taxonomyId = $owner instanceof Taxonomy ? (int) $owner->getKey() : (int) $owner->getRawOriginal('taxonomy_id');

        // One attempt: replaying callbacks could replay consumer observers or external effects.
        return DB::connection()->transaction(function () use ($taxonomyId, $operation): mixed {
            $taxonomy = Taxonomy::query()->lockForUpdate()->findOrFail($taxonomyId);

            return $operation($taxonomy);
        }, 1);
    }

    private function assertConnection(Model $model): void
    {
        $default = DB::getDefaultConnection();
        foreach ([$model, new Taxonomy, new TaxonomyTerm] as $candidate) {
            if ($candidate->getConnection()->getName() !== $default) {
                throw new LogicException('Managed taxonomy writes require taxonomy and term models on the default database connection.');
            }
        }
    }

    private function resolveTerm(TaxonomyTerm $term, int $taxonomyId, bool $lock = true): TaxonomyTerm
    {
        $this->assertConnection($term);
        if ((int) $term->getRawOriginal('taxonomy_id') !== $taxonomyId) {
            throw new InvalidTaxonomyParentException('The term must belong to the owning taxonomy.');
        }

        $query = TaxonomyTerm::query()->where('taxonomy_id', $taxonomyId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($term->getKey());
    }

    /** @return array<int, TaxonomyTerm> */
    private function structure(int $taxonomyId, bool $lock = true): array
    {
        // Structural integrity must include scoped-out ancestors and siblings.
        // These rows are never returned as UI options or selectable input records.
        $query = TaxonomyTerm::withoutGlobalScopes()->where('taxonomy_id', $taxonomyId)->orderBy('position')->orderBy('name')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy('id')->all();
    }

    /** @param array<int, TaxonomyTerm> $terms
     * @return list<int>
     */
    private function ancestors(TaxonomyTerm $term, array $terms): array
    {
        $visited = [];
        $current = $term;
        while (true) {
            $id = (int) $current->getKey();
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

    /** @param array<int, TaxonomyTerm> $terms */
    private function validateParent(TaxonomyTerm $source, ?TaxonomyTerm $parent, array $terms): void
    {
        $this->ancestors($source, $terms);
        if ($parent !== null && in_array((int) $source->getKey(), $this->ancestors($parent, $terms), true)) {
            throw new InvalidTaxonomyParentException('The selected term cannot be used as the parent of this term.');
        }
    }

    /** @param array<int, TaxonomyTerm> $terms
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
        return $term->parent_id === null ? null : (int) $term->parent_id;
    }

    /** @param array<int, TaxonomyTerm> $terms
     * @return list<int>
     */
    private function siblingIds(array $terms, ?int $parentId, ?int $exclude = null): array
    {
        $siblings = array_filter($terms, fn (TaxonomyTerm $term): bool => $this->parentId($term) === $parentId && (int) $term->getKey() !== $exclude);

        return array_values(array_map(static fn (TaxonomyTerm $term): int => (int) $term->getKey(), $siblings));
    }

    /** @param list<int> $termIds */
    private function persistSiblingPositions(int $taxonomyId, ?int $parentId, array $termIds): void
    {
        foreach ($termIds as $position => $termId) {
            // Bulk maintenance deliberately does not fire sibling model save events.
            TaxonomyTerm::withoutGlobalScopes()->where('taxonomy_id', $taxonomyId)
                ->where('parent_id', $parentId)->whereKey($termId)->update(['position' => $position]);
        }
    }

    /** @param list<int> $termIds */
    private function assertNoForeignChildren(int $taxonomyId, array $termIds): void
    {
        if ($termIds !== [] && TaxonomyTerm::withoutGlobalScopes()->where('taxonomy_id', '!=', $taxonomyId)
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
        if (! $term->save()) {
            throw new RuntimeException('The taxonomy term write was cancelled.');
        }
    }

    private function reload(TaxonomyTerm $term): TaxonomyTerm
    {
        // refresh() uses a snapshot read, which can be stale in an outer RR transaction.
        return TaxonomyTerm::query()->where('taxonomy_id', $term->taxonomy_id)
            ->lockForUpdate()->findOrFail($term->getKey());
    }

    private function synchronize(TaxonomyTerm $original, TaxonomyTerm $fresh): TaxonomyTerm
    {
        $original->setRawAttributes($fresh->getAttributes(), true);
        $original->setRelations([]);

        return $original;
    }
}
