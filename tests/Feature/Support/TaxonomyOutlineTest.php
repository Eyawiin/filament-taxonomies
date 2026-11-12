<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyOutline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

function outlineFixture(): array
{
    $taxonomy = Taxonomy::create(['name' => 'Themes', 'slug' => 'themes']);
    $make = fn (string $name, ?TaxonomyTerm $parent = null, int $position = 0): TaxonomyTerm => $taxonomy->terms()->create([
        'name' => $name, 'slug' => str($name)->slug()->toString(), 'parent_id' => $parent?->id, 'position' => $position,
    ]);
    $movies = $make('Movies', null, 0);
    $disney = $make('Disney', $movies, 1);
    $amelie = $make('Amélie', $movies, 0);
    $lionKing = $make('The Lion King', $disney, 0);
    $pride = $make('Pride Rock', $lionKing, 0);
    $simba = $make('Simba', $lionKing, 1);
    $nature = $make('Nature', null, 1);
    $rock = $make('Rock formations', $nature, 0);

    return compact('taxonomy', 'movies', 'disney', 'amelie', 'lionKing', 'pride', 'simba', 'nature', 'rock');
}

it('reads the visible tree in depth-first order with one query', function (): void {
    $tree = outlineFixture();
    DB::enableQueryLog();
    $outline = TaxonomyOutline::for($tree['taxonomy']);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($outline->count())->toBe(8)
        ->and($outline->ids())->toBe([
            $tree['movies']->id, $tree['amelie']->id, $tree['disney']->id, $tree['lionKing']->id,
            $tree['pride']->id, $tree['simba']->id, $tree['nature']->id, $tree['rock']->id,
        ])
        ->and($outline->children(null))->toBe([$tree['movies']->id, $tree['nature']->id])
        ->and($outline->children($tree['lionKing']->id))->toBe([$tree['pride']->id, $tree['simba']->id])
        ->and($outline->ancestors($tree['simba']->id))->toBe([$tree['movies']->id, $tree['disney']->id, $tree['lionKing']->id])
        ->and($outline->descendants($tree['disney']->id))->toBe([$tree['lionKing']->id, $tree['pride']->id, $tree['simba']->id])
        ->and($outline->parent($tree['amelie']->id))->toBe($tree['movies']->id)
        ->and($outline->hasChildren($tree['lionKing']->id))->toBeTrue()
        ->and($outline->hasChildren($tree['pride']->id))->toBeFalse()
        ->and($outline->name($tree['amelie']->id))->toBe('Amélie')
        ->and($outline->has(999))->toBeFalse();
});

it('leaves out hidden branches, cycles and identities the browser cannot represent', function (): void {
    $tree = outlineFixture();
    DB::table('taxonomy_terms')->where('id', $tree['nature']->id)->update(['parent_id' => $tree['rock']->id]);
    DB::table('taxonomy_terms')->insert([
        'id' => 9_007_199_254_740_993, 'taxonomy_id' => $tree['taxonomy']->id, 'parent_id' => null,
        'name' => 'Oversized', 'slug' => 'oversized', 'position' => 5,
    ]);
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('hide-disney', fn (Builder $query) => $query->where('taxonomy_terms.slug', '!=', 'disney'));

    try {
        expect(TaxonomyOutline::for($tree['taxonomy'])->ids())->toBe([$tree['movies']->id, $tree['amelie']->id]);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
});

it('searches case and accent insensitively, across ancestors, best matches first', function (): void {
    $tree = outlineFixture();
    $outline = TaxonomyOutline::for($tree['taxonomy']);

    expect($outline->search('AMELIE', 10))->toBe(['ids' => [$tree['amelie']->id], 'total' => 1])
        ->and($outline->search('rock', 10)['ids'])->toBe([$tree['rock']->id, $tree['pride']->id])
        ->and($outline->search('lion rock', 10)['ids'])->toBe([$tree['pride']->id])
        ->and($outline->search('disney simba', 10)['ids'])->toBe([$tree['simba']->id])
        ->and($outline->search('movies', 10)['ids'])->toBe([$tree['movies']->id])
        ->and($outline->search('e', 2))->toBe(['ids' => [$tree['movies']->id, $tree['amelie']->id], 'total' => 6])
        ->and($outline->search('   ', 10))->toBe(['ids' => [], 'total' => 0])
        ->and($outline->search('missing', 10))->toBe(['ids' => [], 'total' => 0]);
});
