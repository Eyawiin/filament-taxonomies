<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('reports invalid direct moves without changing any rows and clears the error on retry', function (bool $cycle): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'grandchild' => $child, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('moveTerm', $moving->id, $cycle ? 0 : -1, $cycle ? $child->id : $parent->id)
        ->assertHasErrors(['move']);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);

    $component->call('moveTerm', $moving->id, 0, $parent->id)->assertHasNoErrors(['move']);
    expect($moving->fresh()->parent_id)->toBe($parent->id)
        ->and($child->fresh()->parent_id)->toBe($moving->id);
})->with(['invalid position' => false, 'cycle' => true]);

it('reports a refused term deletion through a failure notification', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    Taxonomy::create(['name' => 'Other', 'slug' => 'other'])
        ->terms()->create(['name' => 'Foreign', 'slug' => 'foreign', 'parent_id' => $moving->id]);
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->callAction(TestAction::make('deleteTerm')->arguments(['term' => $moving->id]))
        ->assertNotified(Notification::make()->danger()->persistent()
            ->title('Unable to delete term')
            ->body('Deletion would affect a term belonging to another taxonomy.'));

    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});

it('clears movement feedback when retrying through the other movement endpoint', function (bool $dropFirst): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'grandchild' => $child, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);
    if ($dropFirst) {
        $component->call('dropTerm', $moving->id, $child->id, 'inside')->assertHasErrors(['drop']);
        $component->call('moveTerm', $moving->id, 0, $parent->id);
    } else {
        $component->call('moveTerm', $moving->id, -1, $parent->id)->assertHasErrors(['move']);
        $component->call('dropTerm', $moving->id, $parent->id, 'inside');
    }

    $component->assertHasNoErrors(['move', 'drop', 'placement']);
    expect($moving->fresh()->parent_id)->toBe($parent->id);
})->with(['drop then move' => true, 'move then drop' => false]);
