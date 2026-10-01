<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('reports a duplicate taxonomy slug as a field error without saving', function (bool $editing): void {
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

it('reports a duplicate term slug within its taxonomy without saving', function (bool $editing): void {
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

it('allows unchanged slugs on taxonomy and term edits', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $term = $taxonomy->terms()->create(['name' => 'Subject', 'slug' => 'subject', 'position' => 7]);

    Livewire::test(EditTaxonomy::class, ['record' => $taxonomy->id])
        ->fillForm(['name' => 'Renamed topics', 'slug' => 'topics'])
        ->call('save')->assertHasNoFormErrors();
    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->callAction(
            TestAction::make('editTerm')->arguments(['term' => $term->id]),
            data: ['name' => 'Renamed subject', 'slug' => 'subject', 'parent_id' => null]
        )
        ->assertHasNoFormErrors();

    expect($taxonomy->fresh()->name)->toBe('Renamed topics')
        ->and($term->fresh()->name)->toBe('Renamed subject')
        ->and($term->fresh()->position)->toBe(7);
});

it('allows a term slug used in a different taxonomy', function (bool $editing): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    Taxonomy::create(['name' => 'Other', 'slug' => 'other'])
        ->terms()->create(['name' => 'Other shared', 'slug' => 'Shared slug!']);
    $subject = $taxonomy->terms()->create(['name' => 'Subject', 'slug' => 'subject']);
    $action = TestAction::make($editing ? 'editTerm' : 'createTerm')->arguments(['term' => $subject->id]);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->callAction($action, data: ['name' => 'Shared', 'slug' => 'Shared slug!', 'parent_id' => null])
        ->assertHasNoFormErrors();

    expect($taxonomy->terms()->where('slug', 'Shared slug!')->count())->toBe(1);
})->with(['create' => false, 'edit' => true]);

it('checks uniqueness against hidden records as required by the database constraint', function (bool $term): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $model = $term ? TaxonomyTerm::class : Taxonomy::class;
    if ($term) {
        $taxonomy->terms()->create(['name' => 'Hidden', 'slug' => 'hidden']);
    } else {
        Taxonomy::create(['name' => 'Hidden', 'slug' => 'hidden']);
    }
    $model::addGlobalScope('hidden-slug', fn (Builder $query) => $query->where('slug', '!=', 'hidden'));

    try {
        if ($term) {
            Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
                ->callAction('createTerm', data: ['name' => 'Conflict', 'slug' => 'hidden', 'parent_id' => null])
                ->assertHasFormErrors(['slug' => 'unique']);
        } else {
            Livewire::test(CreateTaxonomy::class)->fillForm(['name' => 'Conflict', 'slug' => 'hidden'])
                ->call('create')->assertHasFormErrors(['slug' => 'unique']);
        }
        expect($model::withoutGlobalScopes()->where('slug', 'hidden')->count())->toBe(1);
    } finally {
        Model::clearBootedModels();
    }
})->with(['taxonomy' => false, 'term' => true]);
