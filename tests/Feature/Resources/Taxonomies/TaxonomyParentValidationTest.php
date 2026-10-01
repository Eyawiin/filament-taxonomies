<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('accepts explicit root states and integer or string parent IDs', function (string $input): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $value = match ($input) {
        'null' => null,
        'empty' => '',
        'integer' => $parent->id,
        'string' => (string) $parent->id,
    };

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->callAction(
            TestAction::make('editTerm')->arguments(['term' => $moving->id]),
            data: ['name' => 'Changed', 'slug' => $moving->slug, 'parent_id' => $value]
        )
        ->assertHasNoFormErrors();

    expect($moving->fresh()->parent_id)->toBe(in_array($input, ['null', 'empty'], true) ? null : $parent->id)
        ->and($moving->fresh()->name)->toBe('Changed');
})->with(['null', 'empty', 'integer', 'string']);

it('rejects malformed parent IDs instead of casting them to a term or root', function (mixed $value): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->callAction(
            TestAction::make('editTerm')->arguments(['term' => $moving->id]),
            data: ['name' => 'Changed', 'slug' => $moving->slug, 'parent_id' => $value]
        )
        ->assertHasFormErrors(['parent_id']);

    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with([
    'boolean true' => [true],
    'boolean false' => [false],
    'array' => [[1]],
    'empty array' => [[]],
    'fractional number' => [1.5],
    'zero integer' => [0],
    'zero string' => ['0'],
    'negative' => [-1],
    'non-numeric' => ['1junk'],
    'fraction' => ['1.5'],
    'overflow' => ['999999999999999999999999'],
]);

it('reports a parent removed after opening the form without saving edits', function (string $action): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationChild' => $parent] = OrderedTaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->mountAction(TestAction::make($action)->arguments(['term' => $moving->id]))
        ->fillForm(['name' => 'Changed', 'slug' => 'changed', 'parent_id' => $parent->id]);
    $parent->delete();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();

    $component->call('callMountedAction')->assertHasFormErrors(['parent_id']);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['createTerm', 'editTerm']);

it('rechecks parent availability and cycles after form validation under the taxonomy lock', function (string $change, string $action): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationChild' => $parent] = OrderedTaxonomyTreeFixture::create();
    $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->mountAction(TestAction::make($action)->arguments(['term' => $moving->id]))
        ->fillForm(['name' => 'Changed', 'slug' => 'changed', 'parent_id' => $parent->id]);
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $connection->setEventDispatcher(clone $dispatcher);
    $baseLevel = $connection->transactionLevel();
    $changed = false;
    $connection->getEventDispatcher()->listen(QueryExecuted::class, function (QueryExecuted $event) use ($baseLevel, $change, $moving, $parent, $other, &$changed): void {
        if ($changed || $event->connection->transactionLevel() <= $baseLevel || ! str_starts_with($event->sql, 'select * from "taxonomies"')) {
            return;
        }
        $changed = true;
        match ($change) {
            'removed' => DB::table('taxonomy_terms')->where('id', $parent->id)->delete(),
            'foreign' => DB::table('taxonomy_terms')->where('id', $parent->id)->update(['taxonomy_id' => $other->id]),
            'cycle' => DB::table('taxonomy_terms')->where('id', $parent->id)->update(['parent_id' => $moving->id]),
        };
    });

    try {
        $component->call('callMountedAction')->assertHasFormErrors(['parent_id']);
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }

    expect($changed)->toBeTrue()
        ->and(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with([
    'edit removed parent' => ['removed', 'editTerm'],
    'edit foreign parent' => ['foreign', 'editTerm'],
    'edit cycle' => ['cycle', 'editTerm'],
    'create removed parent' => ['removed', 'createTerm'],
    'create foreign parent' => ['foreign', 'createTerm'],
]);
