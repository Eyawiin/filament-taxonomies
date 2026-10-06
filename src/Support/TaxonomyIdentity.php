<?php

namespace Eyawiin\FilamentTaxonomies\Support;

use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;

/** @internal Integer boundaries for storage and browser controls. */
final class TaxonomyIdentity
{
    public const MAX_BROWSER_ID = 9_007_199_254_740_991;

    public static function normalize(mixed $value, int $maximum = PHP_INT_MAX): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);

        return $id === false ? null : $id;
    }

    /**
     * @param  list<array{term: TaxonomyTerm, children: list<mixed>}>  $tree
     * @return list<array{term: TaxonomyTerm, children: list<mixed>}>
     */
    public static function browserTree(array $tree): array
    {
        $result = [];
        foreach ($tree as $node) {
            if (self::normalize($node['term']->getRawOriginal($node['term']->getKeyName()), self::MAX_BROWSER_ID) === null) {
                continue;
            }
            $node['children'] = self::browserTree($node['children']);
            $result[] = $node;
        }

        return $result;
    }
}
