<?php

namespace Eyawiin\FilamentTaxonomies\Services;

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Illuminate\Support\Facades\DB;

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

    public function canSetParent(
        TaxonomyTerm $term,
        ?TaxonomyTerm $parent,
    ): bool {
        if ($parent === null) {
            return true;
        }

        if ($term->is($parent)) {
            return false;
        }

        if ($term->taxonomy_id !== $parent->taxonomy_id) {
            return false;
        }

        return ! in_array(
            (int) $parent->getKey(),
            $this->getDescendantIds($term),
            true,
        );
    }

    public function setParent(
        TaxonomyTerm $term,
        ?TaxonomyTerm $parent,
    ): TaxonomyTerm {
        if (! $this->canSetParent($term, $parent)) {
            throw new InvalidTaxonomyParentException(
                'The selected term cannot be used as the parent of this term.',
            );
        }

        $currentParentId = $term->parent_id === null
            ? null
            : (int) $term->parent_id;

        $newParentId = $parent === null
            ? null
            : (int) $parent->getKey();

        if ($currentParentId !== $newParentId) {
            $term->parent_id = $newParentId;

            $term->position = $this->getNextPosition(
                (int) $term->taxonomy_id,
                $newParentId,
            );
        }

        $term->saveOrFail();

        return $term;
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
     * @param  list<int>  $termIds
     */
    public function reorderSiblings(
        Taxonomy $taxonomy,
        ?TaxonomyTerm $parent,
        array $termIds,
    ): void {
        if (
            $parent !== null
            && (int) $parent->taxonomy_id !== (int) $taxonomy->getKey()
        ) {
            throw new InvalidTaxonomyOrderException(
                'The parent must belong to the taxonomy being reordered.',
            );
        }

        if (count($termIds) !== count(array_unique($termIds))) {
            throw new InvalidTaxonomyOrderException(
                'The sibling order contains duplicate term IDs.',
            );
        }

        $parentId = $parent === null
            ? null
            : (int) $parent->getKey();

        DB::transaction(function () use (
            $taxonomy,
            $parentId,
            $termIds,
        ): void {
            $siblings = TaxonomyTerm::query()
                ->where('taxonomy_id', $taxonomy->getKey())
                ->where('parent_id', $parentId)
                ->get();

            $siblingIds = $siblings
                ->modelKeys();

            $expectedIds = array_map(
                static fn (mixed $id): int => (int) $id,
                $siblingIds,
            );

            $providedIds = array_map(
                static fn (mixed $id): int => (int) $id,
                $termIds,
            );

            $sortedExpectedIds = $expectedIds;
            $sortedProvidedIds = $providedIds;

            sort($sortedExpectedIds);
            sort($sortedProvidedIds);

            if ($sortedExpectedIds !== $sortedProvidedIds) {
                throw new InvalidTaxonomyOrderException(
                    'The provided terms must exactly match the sibling group.',
                );
            }

            foreach ($providedIds as $position => $termId) {
                TaxonomyTerm::query()
                    ->whereKey($termId)
                    ->update([
                        'position' => $position,
                    ]);
            }
        });
    }
}
