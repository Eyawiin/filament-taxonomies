<?php

namespace Eyawiin\FilamentTaxonomies\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

/**
 * @internal The part of a term tree that the management page renders.
 *
 * @phpstan-type ManagedNode array{term: TaxonomyTerm, children: list<mixed>, remaining: int, hasChildren: bool, childCount: int, expanded: bool|null, previousId: int|null, nextId: int|null, match: bool|null}
 * @phpstan-type ManagedBranch array{nodes: list<ManagedNode>, remaining: int}
 */
final class ManagedTermTree
{
    /**
     * Every visible term. The browser manages which branches are expanded.
     *
     * @return ManagedBranch
     */
    public static function complete(Taxonomy $taxonomy, TaxonomyOutline $outline): array
    {
        $branches = [];
        foreach ([null, ...$outline->ids()] as $parent) {
            if ($parent === null || $outline->hasChildren($parent)) {
                $branches[$parent ?? 'root'] = ['ids' => $outline->children($parent), 'remaining' => 0];
            }
        }

        return self::assemble($taxonomy, $outline, $branches, null, null);
    }

    /**
     * Roots and the children of expanded branches whose parents are shown, a page at a time.
     *
     * @param  array<int, true>  $expanded
     * @param  array<int|string, int>  $limits  Rendered children per branch, keyed by term ID or 'root'.
     * @return ManagedBranch
     */
    public static function expanded(Taxonomy $taxonomy, TaxonomyOutline $outline, array $expanded, array $limits, int $pageSize): array
    {
        $branches = [];
        $queue = [null];
        while ($queue !== []) {
            $parent = array_shift($queue);
            $children = $outline->children($parent);
            $shown = array_slice($children, 0, max($pageSize, $limits[$parent ?? 'root'] ?? 0));
            $branches[$parent ?? 'root'] = ['ids' => $shown, 'remaining' => count($children) - count($shown)];
            foreach ($shown as $id) {
                if (isset($expanded[$id]) && $outline->hasChildren($id)) {
                    $queue[] = $id;
                }
            }
        }

        return self::assemble($taxonomy, $outline, $branches, $expanded, null);
    }

    /**
     * Matching terms below their ancestors, all expanded.
     *
     * @return array{nodes: list<ManagedNode>, remaining: int, total: int}
     */
    public static function search(Taxonomy $taxonomy, TaxonomyOutline $outline, string $query, int $limit): array
    {
        $result = $outline->search($query, $limit);
        $included = [];
        foreach ($result['ids'] as $id) {
            $included[$id] = true;
            foreach ($outline->ancestors($id) as $ancestor) {
                $included[$ancestor] = true;
            }
        }
        $branches = [];
        foreach ([null, ...array_keys($included)] as $parent) {
            $ids = array_values(array_filter($outline->children($parent), static fn (int $id): bool => isset($included[$id])));
            if ($ids !== []) {
                $branches[$parent ?? 'root'] = ['ids' => $ids, 'remaining' => 0];
            }
        }

        $expanded = array_fill_keys(array_keys($included), true);

        return [...self::assemble($taxonomy, $outline, $branches, $expanded, array_fill_keys($result['ids'], true)), 'total' => $result['total']];
    }

    /**
     * @param  array<int|string, array{ids: list<int>, remaining: int}>  $branches
     * @param  array<int, true>|null  $expanded  Null when the browser manages expansion.
     * @param  array<int, true>|null  $matches  Search matches; null outside a search.
     * @return ManagedBranch
     */
    private static function assemble(Taxonomy $taxonomy, TaxonomyOutline $outline, array $branches, ?array $expanded, ?array $matches): array
    {
        $ids = array_merge(...array_column($branches, 'ids'));
        $models = self::models($taxonomy, $ids, $outline->count());
        $positions = [];

        $build = static function (int | string $key) use (&$build, &$positions, $branches, $models, $outline, $expanded, $matches): array {
            $nodes = [];
            foreach ($branches[$key]['ids'] ?? [] as $id) {
                if (! isset($models[$id])) {
                    continue;
                }
                $parent = $outline->parent($id);
                $siblings = $outline->children($parent);
                $positions[$parent ?? 'root'] ??= array_flip($siblings);
                $index = $positions[$parent ?? 'root'][$id];
                $children = isset($branches[$id]) ? $build($id) : ['nodes' => [], 'remaining' => 0];
                $nodes[] = [
                    'term' => $models[$id],
                    'children' => $children['nodes'],
                    'remaining' => $children['remaining'],
                    'hasChildren' => $outline->hasChildren($id),
                    'childCount' => count($outline->children($id)),
                    'expanded' => $expanded === null ? null : isset($expanded[$id], $branches[$id]),
                    'previousId' => $siblings[$index - 1] ?? null,
                    'nextId' => $siblings[$index + 1] ?? null,
                    'match' => $matches === null ? null : isset($matches[$id]),
                ];
            }

            return ['nodes' => $nodes, 'remaining' => $branches[$key]['remaining'] ?? 0];
        };

        return $build('root');
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, TaxonomyTerm>
     */
    private static function models(Taxonomy $taxonomy, array $ids, int $total): array
    {
        if ($ids === []) {
            return [];
        }
        $query = $taxonomy->terms();
        $model = $query->getRelated();
        // Reading the whole taxonomy beats long key lists once most terms are shown anyway.
        if (count($ids) <= 500 || count($ids) * 2 < $total) {
            $models = [];
            foreach (array_chunk($ids, 500) as $chunk) {
                $chunkQuery = $taxonomy->terms();
                foreach ($chunkQuery->whereIn($model->getQualifiedKeyName(), $chunk)->get([$model->qualifyColumn('*')]) as $term) {
                    $models[(int) $term->getKey()] ??= $term;
                }
            }

            return $models;
        }
        $models = [];
        foreach ($query->get([$model->qualifyColumn('*')]) as $term) {
            $models[(int) $term->getKey()] ??= $term;
        }

        return $models;
    }
}
