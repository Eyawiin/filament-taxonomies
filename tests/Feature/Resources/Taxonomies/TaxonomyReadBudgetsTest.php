<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('renders row actions without a term lookup per action', function (): void {
    // One read for the tree's structure and one for the rendered terms, regardless of size.
    $taxonomy = Taxonomy::create(['name' => 'Broad', 'slug' => 'broad']);
    foreach (range(1, 25) as $index) {
        $taxonomy->terms()->create(['name' => 'Term ' . $index, 'slug' => 'term-' . $index]);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])->assertSuccessful();
        $reads = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with($query['query'], 'select ') && str_contains($query['query'], 'from "taxonomy_terms"'));
        expect($reads)->toHaveCount(2);
    } finally {
        DB::disableQueryLog();
    }
});

it('builds disabled parent choices with a constant read budget on a deep tree', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Deep', 'slug' => 'deep']);
    $root = $taxonomy->terms()->create(['name' => 'Root', 'slug' => 'root']);
    $parent = $root;
    foreach (range(1, 32) as $index) {
        $parent = $taxonomy->terms()->create(['name' => 'Term ' . $index, 'slug' => 'term-' . $index, 'parent_id' => $parent->id]);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $nodes = TaxonomyParentSelect::make($taxonomy, $root)->getNodes();
        expect(DB::getQueryLog())->toHaveCount(3)
            ->and($nodes)->toHaveCount(33)
            ->and(array_column($nodes, 'disabled'))->not->toContain(false);
    } finally {
        DB::disableQueryLog();
    }
});
