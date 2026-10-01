<?php

use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('appends a reparented subtree and normalizes both sibling groups', function (string $entryPoint): void {
    [
        'taxonomy' => $taxonomy, 'sourceParent' => $sourceParent,
        'destinationParent' => $destinationParent, 'first' => $first,
        'moving' => $moving, 'last' => $last,
        'destinationChild' => $destinationChild, 'grandchild' => $grandchild,
    ] = OrderedTaxonomyTreeFixture::create();

    if ($entryPoint === 'service') {
        app(TaxonomyTreeService::class)->setParent($moving, $destinationParent);
    } else {
        Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
            ->callAction(
                TestAction::make('editTerm')->arguments(['term' => $moving->id]),
                data: ['name' => $moving->name, 'slug' => $moving->slug, 'parent_id' => $destinationParent->id],
            )
            ->assertHasNoFormErrors();
    }

    $source = $sourceParent->children()->orderBy('position')->get();
    $destination = $destinationParent->children()->orderBy('position')->get();

    expect($source->modelKeys())->toBe([$first->id, $last->id])
        ->and($destination->modelKeys())->toBe([$destinationChild->id, $moving->id])
        ->and($destination->pluck('position')->all())->toBe([0, 1])
        ->and($grandchild->fresh()->parent_id)->toBe($moving->id)
        ->and($source->pluck('position')->all())->toBe([0, 1]);
})->with(['service', 'edit action']);

it('appends promoted children after surviving roots in their previous order', function (bool $nested): void {
    [
        'taxonomy' => $taxonomy, 'sourceParent' => $sourceParent,
        'destinationParent' => $destinationParent, 'first' => $first,
        'moving' => $moving, 'last' => $last,
        'destinationChild' => $destinationChild, 'grandchild' => $grandchild,
    ] = OrderedTaxonomyTreeFixture::create();

    $otherRoot = $taxonomy->terms()->create([
        'name' => 'Another root', 'slug' => 'another-root', 'position' => 2,
    ]);

    if ($nested) {
        $otherRoot->update(['position' => 1]);
        $destinationParent->update(['position' => 0]);
        $sourceParent->update(['parent_id' => $destinationParent->id, 'position' => 0]);
        $destinationChild->update(['position' => 1]);
    }

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->callAction(TestAction::make('deleteTerm')->arguments(['term' => $sourceParent->id]));

    $roots = $taxonomy->terms()->whereNull('parent_id')
        ->orderBy('position')->orderBy('name')->orderBy('id')->get();

    expect(TaxonomyTerm::find($sourceParent->id))->toBeNull()
        ->and($first->fresh()->parent_id)->toBeNull()
        ->and($moving->fresh()->parent_id)->toBeNull()
        ->and($last->fresh()->parent_id)->toBeNull()
        ->and($grandchild->fresh()->parent_id)->toBe($moving->id)
        ->and($roots->modelKeys())->toBe([$destinationParent->id, $otherRoot->id, $first->id, $moving->id, $last->id])
        ->and($roots->pluck('position')->all())->toBe([0, 1, 2, 3, 4]);

    expect($destinationChild->fresh()->parent_id)->toBe($destinationParent->id)
        ->and($destinationChild->fresh()->position)->toBe(0);
})->with(['root parent' => false, 'nested parent' => true]);
