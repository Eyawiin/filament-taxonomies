<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentField;
use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('shows parent options in tree order with nested branches and excludes other taxonomies', function () {
    $tree = TaxonomyTreeFixture::create();
    $tree['lionKing']->update(['position' => 0]);
    $tree['liloAndStitch']->update(['position' => 1]);
    Taxonomy::create(['name' => 'Other', 'slug' => 'other'])
        ->terms()->create(['name' => 'Foreign', 'slug' => 'foreign']);

    $select = TaxonomyParentSelect::make($tree['taxonomy']);

    expect(array_column($select->getNodes(), 'name', 'id'))->toBe([
        $tree['disney']->id => 'Disney',
        $tree['lionKing']->id => 'Lion King',
        $tree['simba']->id => 'Simba',
        $tree['liloAndStitch']->id => 'Lilo & Stitch',
    ]);

    foreach ($select->getNodes() as $node) {
        expect($node['disabled'])->toBeFalse();
    }
});

it('keeps the current term and descendants visible but disabled', function () {
    $tree = TaxonomyTreeFixture::create();
    $select = TaxonomyParentSelect::make($tree['taxonomy'], $tree['lionKing']);
    $options = array_column($select->getNodes(), 'name', 'id');

    $nodes = collect($select->getNodes())->keyBy('id');
    expect($nodes[$tree['lionKing']->id]['reason'])->toBe('Current term')
        ->and($nodes[$tree['simba']->id]['reason'])->toBe('Would create a cycle')
        ->and($nodes[$tree['simba']->id]['ancestors'])->toBe([$tree['disney']->id, $tree['lionKing']->id]);

    foreach ($options as $id => $label) {
        expect($nodes[$id]['disabled'])
            ->toBe(in_array($id, [$tree['lionKing']->id, $tree['simba']->id], true));
    }
});

it('rejects a submitted disabled parent without changing the term', function (string $parent) {
    $tree = TaxonomyTreeFixture::create();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])
        ->callAction(
            TestAction::make('editTerm')->arguments(['term' => $tree['lionKing']->id]),
            data: [
                'name' => 'Lion King',
                'slug' => 'lion-king',
                'parent_id' => $tree[$parent]->id,
            ],
        )
        ->assertHasFormErrors(['parent_id']);

    expect($tree['lionKing']->fresh()->parent_id)->toBe($tree['disney']->id);
})->with(['self' => 'lionKing', 'descendant' => 'simba']);

it('uses an explicit parent Field contract and translates field messages', function (): void {
    $tree = TaxonomyTreeFixture::create();
    app('translator')->addLines([
        'parent-tree.parent' => 'Elternbegriff',
        'parent-tree.root' => 'Kein Elternbegriff',
        'parent-tree.current' => 'Aktueller Begriff',
    ], 'de', 'filament-taxonomies');
    app()->setLocale('de');

    $field = TaxonomyParentSelect::make($tree['taxonomy'], $tree['lionKing'])->readOnly()->container(Schema::make());
    expect($field)->toBeInstanceOf(TaxonomyParentField::class)
        ->not->toBeInstanceOf(Select::class)
        ->and($field->getLabel())->toBe('Elternbegriff')
        ->and($field->getTreeConfiguration()['labels']['root'])->toBe('Kein Elternbegriff')
        ->and($field->getTreeConfiguration()['readOnly'])->toBeTrue()
        ->and(collect($field->getNodes())->firstWhere('id', $tree['lionKing']->id)['reason'])->toBe('Aktueller Begriff');
});
