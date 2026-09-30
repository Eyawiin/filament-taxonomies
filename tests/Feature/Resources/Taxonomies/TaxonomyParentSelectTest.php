<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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

    expect($select->getOptions())->toBe([
        $tree['disney']->id => 'Disney',
        $tree['lionKing']->id => 'Lion King',
        $tree['simba']->id => 'Simba',
        $tree['liloAndStitch']->id => 'Lilo & Stitch',
    ]);

    foreach ($select->getOptions() as $id => $label) {
        expect($select->isOptionDisabled($id, $label))->toBeFalse();
    }
});

it('keeps the current term and descendants visible but disabled', function () {
    $tree = TaxonomyTreeFixture::create();
    $select = TaxonomyParentSelect::make($tree['taxonomy'], $tree['lionKing']);
    $options = $select->getOptions();

    $nodes = collect($select->getViewData()['nodes'])->keyBy('id');
    expect($nodes[$tree['lionKing']->id]['reason'])->toBe('Current term')
        ->and($nodes[$tree['simba']->id]['reason'])->toBe('Would create a cycle')
        ->and($nodes[$tree['simba']->id]['ancestors'])->toBe([$tree['disney']->id, $tree['lionKing']->id]);

    foreach ($options as $id => $label) {
        expect($select->isOptionDisabled((string) $id, $label))
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
