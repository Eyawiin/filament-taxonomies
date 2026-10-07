<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyOutline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

function outlineFixture(): array
{
    $taxonomy = Taxonomy::create(['name' => 'Catalog', 'slug' => 'catalog']);
    $make = fn (string $name, ?TaxonomyTerm $parent = null, int $position = 0): TaxonomyTerm => $taxonomy->terms()->create([
        'name' => $name, 'slug' => str($name)->slug()->toString(), 'parent_id' => $parent?->id, 'position' => $position,
    ]);
    $games = $make('Games', null, 0);
    $pokemon = $make('Pokémon', $games, 1);
    $magic = $make('Magic', $games, 0);
    $violet = $make('Scarlet & Violet', $pokemon, 0);
    $paldea = $make('Paldea Evolved', $violet, 0);
    $fates = $make('Paldean Fates', $violet, 1);
    $places = $make('Places', null, 1);
    $region = $make('Paldea', $places, 0);

    return compact('taxonomy', 'games', 'pokemon', 'magic', 'violet', 'paldea', 'fates', 'places', 'region');
}

it('reads the visible tree in depth-first order with one query', function (): void {
    $tree = outlineFixture();
    DB::enableQueryLog();
    $outline = TaxonomyOutline::for($tree['taxonomy']);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($outline->count())->toBe(8)
        ->and($outline->ids())->toBe([
            $tree['games']->id, $tree['magic']->id, $tree['pokemon']->id, $tree['violet']->id,
            $tree['paldea']->id, $tree['fates']->id, $tree['places']->id, $tree['region']->id,
        ])
        ->and($outline->children(null))->toBe([$tree['games']->id, $tree['places']->id])
        ->and($outline->children($tree['violet']->id))->toBe([$tree['paldea']->id, $tree['fates']->id])
        ->and($outline->ancestors($tree['fates']->id))->toBe([$tree['games']->id, $tree['pokemon']->id, $tree['violet']->id])
        ->and($outline->descendants($tree['pokemon']->id))->toBe([$tree['violet']->id, $tree['paldea']->id, $tree['fates']->id])
        ->and($outline->parent($tree['magic']->id))->toBe($tree['games']->id)
        ->and($outline->hasChildren($tree['violet']->id))->toBeTrue()
        ->and($outline->hasChildren($tree['paldea']->id))->toBeFalse()
        ->and($outline->name($tree['pokemon']->id))->toBe('Pokémon')
        ->and($outline->has(999))->toBeFalse();
});

it('leaves out hidden branches, cycles and identities the browser cannot represent', function (): void {
    $tree = outlineFixture();
    DB::table('taxonomy_terms')->where('id', $tree['places']->id)->update(['parent_id' => $tree['region']->id]);
    DB::table('taxonomy_terms')->insert([
        'id' => 9_007_199_254_740_993, 'taxonomy_id' => $tree['taxonomy']->id, 'parent_id' => null,
        'name' => 'Oversized', 'slug' => 'oversized', 'position' => 5,
    ]);
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('hide-pokemon', fn (Builder $query) => $query->where('taxonomy_terms.slug', '!=', 'pokemon'));

    try {
        expect(TaxonomyOutline::for($tree['taxonomy'])->ids())->toBe([$tree['games']->id, $tree['magic']->id]);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
});

it('searches case and accent insensitively, across ancestors, best matches first', function (): void {
    $tree = outlineFixture();
    $outline = TaxonomyOutline::for($tree['taxonomy']);

    expect($outline->search('POKEMON', 10))->toBe(['ids' => [$tree['pokemon']->id], 'total' => 1])
        ->and($outline->search('paldea', 10)['ids'])->toBe([$tree['region']->id, $tree['paldea']->id, $tree['fates']->id])
        ->and($outline->search('pokemon paldea', 10)['ids'])->toBe([$tree['paldea']->id, $tree['fates']->id])
        ->and($outline->search('violet fates', 10)['ids'])->toBe([$tree['fates']->id])
        ->and($outline->search('games', 10)['ids'])->toBe([$tree['games']->id])
        ->and($outline->search('a', 2))->toBe(['ids' => [$tree['games']->id, $tree['magic']->id], 'total' => 7])
        ->and($outline->search('   ', 10))->toBe(['ids' => [], 'total' => 0])
        ->and($outline->search('missing', 10))->toBe(['ids' => [], 'total' => 0]);
});
