<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Closure;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

/** @internal Shared projection of a browser-safe taxonomy tree. */
class TaxonomyTreeOptions
{
    /**
     * @param  list<array{term: TaxonomyTerm, children: list<mixed>}>  $tree
     * @param  Closure(TaxonomyTerm, list<int>): string  $reason
     * @param  list<int>  $ancestors
     * @return list<array{id: int, name: string, ancestors: list<int>, hasChildren: bool, disabled: bool, reason: string}>
     */
    public static function flatten(array $tree, Closure $reason, array $ancestors = []): array
    {
        $nodes = [];
        foreach ($tree as $branch) {
            $term = $branch['term'];
            $id = (int) $term->getKey();
            $message = $reason($term, $ancestors);
            $nodes[] = ['id' => $id, 'name' => $term->name, 'ancestors' => $ancestors,
                'hasChildren' => $branch['children'] !== [], 'disabled' => $message !== '', 'reason' => $message];
            array_push($nodes, ...self::flatten($branch['children'], $reason, [...$ancestors, $id]));
        }

        return $nodes;
    }
}
