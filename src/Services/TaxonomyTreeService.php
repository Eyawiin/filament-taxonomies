<?php

namespace Eyawiin\FilamentTaxonomies\Services;

use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

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
}
