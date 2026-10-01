<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('F3 reports a duplicate taxonomy slug as a field error without saving', function (bool $editing): void {
    $existing = Taxonomy::create(['name' => 'Existing', 'slug' => 'existing']);

    if ($editing) {
        $subject = Taxonomy::create(['name' => 'Subject', 'slug' => 'subject']);
        $component = Livewire::test(EditTaxonomy::class, ['record' => $subject->id]);
    } else {
        $component = Livewire::test(CreateTaxonomy::class);
    }

    $component->fillForm(['name' => 'Changed', 'slug' => $existing->slug])
        ->call($editing ? 'save' : 'create')
        ->assertHasFormErrors(['slug' => 'unique']);

    expect(Taxonomy::count())->toBe($editing ? 2 : 1)
        ->and($existing->fresh()->name)->toBe('Existing');

    if ($editing) {
        expect($subject->fresh()->name)->toBe('Subject')
            ->and($subject->fresh()->slug)->toBe('subject');
    }
})->with(['create' => false, 'edit' => true]);

it('F3 reports a duplicate term slug within its taxonomy without saving', function (bool $editing): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $existing = $taxonomy->terms()->create(['name' => 'Existing', 'slug' => 'existing']);
    $subject = $taxonomy->terms()->create(['name' => 'Subject', 'slug' => 'subject', 'position' => 1]);
    $before = TaxonomyTerm::query()->orderBy('id')->get()->toArray();

    $action = TestAction::make($editing ? 'editTerm' : 'createTerm');
    if ($editing) {
        $action->arguments(['term' => $subject->id]);
    }

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->callAction($action, data: ['name' => 'Changed', 'slug' => $existing->slug, 'parent_id' => null])
        ->assertHasFormErrors(['slug' => 'unique']);

    expect(TaxonomyTerm::query()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['create' => false, 'edit' => true]);
