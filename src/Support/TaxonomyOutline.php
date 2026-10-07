<?php

namespace Eyawiin\FilamentTaxonomies\Support;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Normalizer;

/**
 * @internal The visible, rooted terms of one taxonomy in tree order, without models.
 *
 * One query reads IDs, parents and names. Like the displayed tree, it omits terms behind
 * hidden ancestors, cycles and identities the browser cannot represent exactly.
 */
final class TaxonomyOutline
{
    /** @var array<int, ?int> Term ID => parent ID, in depth-first tree order. */
    private array $parents = [];

    /** @var array<int|string, list<int>> Parent ID or 'root' => ordered child IDs. */
    private array $children = [];

    /** @var array<int, string> */
    private array $names = [];

    /** @var array<int, int> */
    private array $order = [];

    /** @var array<int, string> */
    private array $folded = [];

    public static function for(Taxonomy $taxonomy): self
    {
        $query = $taxonomy->terms();
        $model = $query->getRelated();
        $key = $model->getKeyName();
        $rows = $query
            ->orderBy($model->qualifyColumn('position'))
            ->orderBy($model->qualifyColumn('name'))
            ->orderBy($model->getQualifiedKeyName())
            ->toBase()
            ->get([$model->getQualifiedKeyName(), $model->qualifyColumn('parent_id'), $model->qualifyColumn('name')]);

        $outline = new self;
        $parents = [];
        $children = [];
        foreach ($rows as $row) {
            $id = TaxonomyIdentity::normalize($row->{$key}, TaxonomyIdentity::MAX_BROWSER_ID);
            // Joined scopes can repeat a row; the first occurrence keeps the sort order.
            if ($id === null || array_key_exists($id, $parents)) {
                continue;
            }
            $parent = $row->parent_id === null ? null : TaxonomyIdentity::normalize($row->parent_id);
            if ($row->parent_id !== null && $parent === null) {
                continue;
            }
            $parents[$id] = $parent;
            $children[$parent ?? 'root'][] = $id;
            $outline->names[$id] = (string) $row->name;
        }

        // Walk from the roots, so orphans, cycles and hidden branches never become reachable.
        $stack = array_reverse($children['root'] ?? []);
        while ($stack !== []) {
            $id = array_pop($stack);
            $outline->order[$id] = count($outline->parents);
            $outline->parents[$id] = $parents[$id];
            $outline->children[$parents[$id] ?? 'root'][] = $id;
            array_push($stack, ...array_reverse($children[$id] ?? []));
        }
        $outline->names = array_intersect_key($outline->names, $outline->parents);

        return $outline;
    }

    public function count(): int
    {
        return count($this->parents);
    }

    public function has(int $id): bool
    {
        return array_key_exists($id, $this->parents);
    }

    /** @return list<int> Every visible term ID in depth-first tree order. */
    public function ids(): array
    {
        return array_keys($this->parents);
    }

    public function parent(int $id): ?int
    {
        return $this->parents[$id] ?? null;
    }

    public function name(int $id): string
    {
        return $this->names[$id] ?? '';
    }

    /** @return list<int> */
    public function children(?int $id): array
    {
        return $this->children[$id ?? 'root'] ?? [];
    }

    public function hasChildren(int $id): bool
    {
        return isset($this->children[$id]);
    }

    /** @return list<int> From the root down to the direct parent. */
    public function ancestors(int $id): array
    {
        $ancestors = [];
        for ($parent = $this->parent($id); $parent !== null; $parent = $this->parent($parent)) {
            $ancestors[] = $parent;
        }

        return array_reverse($ancestors);
    }

    /** @return list<int> In tree order. */
    public function descendants(int $id): array
    {
        $descendants = [];
        $stack = array_reverse($this->children($id));
        while ($stack !== []) {
            $child = array_pop($stack);
            $descendants[] = $child;
            array_push($stack, ...array_reverse($this->children($child)));
        }

        return $descendants;
    }

    /**
     * Every word must occur in the term's name or an ancestor's name, and at least one in
     * its own name. Case and accents are ignored. Names containing more of the words come
     * first, then exact, prefix and word-start matches, then tree order.
     *
     * @return array{ids: list<int>, total: int}
     */
    public function search(string $query, int $limit): array
    {
        $words = preg_split('/\s+/u', self::fold(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return ['ids' => [], 'total' => 0];
        }
        $phrase = implode(' ', $words);
        $matches = [];
        foreach ($this->parents as $id => $unused) {
            $name = $this->folded($id);
            $missing = array_values(array_filter($words, static fn (string $word): bool => ! str_contains($name, $word)));
            if (count($missing) === count($words)) {
                continue;
            }
            if ($missing !== []) {
                $path = implode(' ', array_map($this->folded(...), $this->ancestors($id)));
                foreach ($missing as $word) {
                    if (! str_contains($path, $word)) {
                        continue 2;
                    }
                }
            }
            $rank = match (true) {
                $name === $phrase => 0,
                str_starts_with($name, $phrase) => 1,
                preg_match('/(?<![\p{L}\p{N}])' . preg_quote($words[0], '/') . '/u', $name) === 1 => 2,
                default => 3,
            };
            $matches[] = [count($missing), $rank, $this->order[$id], $id];
        }
        sort($matches);

        return [
            'ids' => array_column(array_slice($matches, 0, max(0, $limit)), 3),
            'total' => count($matches),
        ];
    }

    /** Lowercase without accents, so "pokemon" finds "Pokémon". */
    public static function fold(string $value): string
    {
        $value = mb_strtolower($value);
        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);

        return $decomposed === false ? $value : (preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $value);
    }

    private function folded(int $id): string
    {
        return $this->folded[$id] ??= self::fold($this->name($id));
    }
}
