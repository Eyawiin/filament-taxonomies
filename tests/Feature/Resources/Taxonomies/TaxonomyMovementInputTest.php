<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('rejects malformed movement record IDs without PHP coercion or partial writes', function (string $endpoint, int $argument, string $kind): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $arguments = $endpoint === 'moveTerm' ? [$moving->id, 0, $parent->id] : [$moving->id, $parent->id, 'inside'];
    $arguments[$argument] = match ($kind) {
        'fraction' => $arguments[$argument] + 0.5,
        'fraction string' => $arguments[$argument] . '.5',
        'boolean' => true,
        'array' => [$arguments[$argument]],
    };
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $this->withoutExceptionHandling();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);
    expect(fn () => $component->call($endpoint, ...$arguments))->toThrow(ModelNotFoundException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with([
    'move source' => ['moveTerm', 0],
    'move parent' => ['moveTerm', 2],
    'drop source' => ['dropTerm', 0],
    'drop target' => ['dropTerm', 1],
])->with(['fraction', 'fraction string', 'boolean', 'array']);

it('reports malformed movement positions as controlled errors', function (mixed $position): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('moveTerm', $moving->id, $position)
        ->assertHasErrors(['move']);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['fraction' => [0.5], 'fraction string' => ['0.5'], 'boolean' => [true], 'array' => [[0]], 'null' => [null]]);

it('retains string movement IDs and zero-based string positions', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('moveTerm', (string) $moving->id, '0', (string) $parent->id)
        ->assertHasNoErrors();
    expect($moving->fresh()->parent_id)->toBe($parent->id)->and($moving->fresh()->position)->toBe(0);
});

it('reports non-string drop placement without a server type error', function (mixed $placement): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('dropTerm', $moving->id, $parent->id, $placement)->assertHasErrors(['placement']);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['null' => [null], 'array' => [['inside']], 'boolean' => [true]]);

it('rejects rounded browser IDs instead of moving their stored neighbour', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $target] = OrderedTaxonomyTreeFixture::create();
    foreach ([9_007_199_254_740_992, 9_007_199_254_740_993] as $id) {
        DB::table('taxonomy_terms')->insert([
            'id' => $id, 'taxonomy_id' => $taxonomy->id, 'parent_id' => null,
            'name' => 'Imported ' . $id, 'slug' => 'imported-' . $id, 'position' => 9,
        ]);
    }
    DB::table('taxonomy_terms')->insert([
        'id' => 9999, 'taxonomy_id' => $taxonomy->id, 'parent_id' => 9_007_199_254_740_993,
        'name' => 'Child below oversized browser parent', 'slug' => 'below-oversized', 'position' => 0,
    ]);
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $field = TaxonomyParentSelect::make($taxonomy)
        ->container(Schema::make());
    expect(array_column($field->getTreeConfiguration()['nodes'], 'id'))
        ->not->toContain(9999, 9_007_199_254_740_992, 9_007_199_254_740_993);
    $this->withoutExceptionHandling();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);
    expect(collect($component->instance()->getTermTree())->pluck('term.id')->all())
        ->not->toContain(9_007_199_254_740_992, 9_007_199_254_740_993);
    expect(fn () => $component->call('dropTerm', (int) (float) 9_007_199_254_740_993, $target->id, 'before'))
        ->toThrow(ModelNotFoundException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});
