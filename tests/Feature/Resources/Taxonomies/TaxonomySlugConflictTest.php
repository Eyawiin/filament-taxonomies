<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('reports a real database slug conflict after validation and allows a corrected retry', function (bool $terms, bool $editing): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    if ($terms) {
        $taxonomy->terms()->create(['name' => 'Existing', 'slug' => 'existing']);
        $subject = $taxonomy->terms()->create(['name' => 'Subject', 'slug' => 'subject', 'position' => 1]);
        $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
            ->mountAction(TestAction::make($editing ? 'editTerm' : 'createTerm')->arguments(['term' => $subject->id]));
    } else {
        Taxonomy::create(['name' => 'Existing', 'slug' => 'existing']);
        $component = Livewire::test(
            $editing ? EditTaxonomy::class : CreateTaxonomy::class,
            $editing ? ['record' => $taxonomy->id] : []
        );
    }
    $component->fillForm(['name' => 'Changed', 'slug' => 'available', ...($terms ? ['parent_id' => null] : [])]);
    $model = $terms ? TaxonomyTerm::class : Taxonomy::class;
    $before = $model::orderBy('id')->get()->toArray();
    $dispatcher = $model::getEventDispatcher();
    $model::setEventDispatcher(clone $dispatcher);
    $attempted = false;
    $model::saving(function ($record) use (&$attempted): void {
        if ($record->name === 'Changed') {
            // Force an actual constraint conflict after the unique field rule passed.
            $attempted = true;
            $record->slug = 'existing';
        }
    });

    try {
        $component->call($terms ? 'callMountedAction' : ($editing ? 'save' : 'create'))
            ->assertHasFormErrors(['slug']);
    } finally {
        $model::setEventDispatcher($dispatcher);
    }
    expect($attempted)->toBeTrue()
        ->and($model::orderBy('id')->get()->toArray())->toBe($before);

    $component->fillForm(['name' => 'Changed', 'slug' => 'available', ...($terms ? ['parent_id' => null] : [])])
        ->call($terms ? 'callMountedAction' : ($editing ? 'save' : 'create'))
        ->assertHasNoFormErrors();
    expect($model::where('slug', 'available')->count())->toBe(1);
})->with(['taxonomy' => false, 'term' => true])->with(['create' => false, 'edit' => true]);

it('does not relabel an unrelated primary-key violation as a slug error', function (bool $terms): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $record = $terms ? $taxonomy->terms()->create(['name' => 'Subject', 'slug' => 'subject']) : $taxonomy;
    $model = $terms ? TaxonomyTerm::class : Taxonomy::class;
    $component = $terms
        ? Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
            ->mountAction('createTerm')
        : Livewire::test(CreateTaxonomy::class);
    $component->fillForm(['name' => 'Changed', 'slug' => 'available', ...($terms ? ['parent_id' => null] : [])]);
    $before = $model::orderBy('id')->get()->toArray();
    $dispatcher = $model::getEventDispatcher();
    $model::setEventDispatcher(clone $dispatcher);
    $model::saving(function () use ($record, $terms, $taxonomy): void {
        DB::table($record->getTable())->insert([
            'id' => $record->id, 'name' => 'Injected', 'slug' => 'other',
            ...($terms ? ['taxonomy_id' => $taxonomy->id] : []),
        ]);
    });

    try {
        $this->withoutExceptionHandling();
        expect(fn () => $component->call($terms ? 'callMountedAction' : 'create'))
            ->toThrow(UniqueConstraintViolationException::class);
    } finally {
        $model::setEventDispatcher($dispatcher);
    }

    expect($model::orderBy('id')->get()->toArray())->toBe($before);
})->with(['taxonomy' => false, 'term' => true]);

it('propagates unexpected persistence failures', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->mountAction('createTerm')
        ->fillForm(['name' => 'Changed', 'slug' => 'available', 'parent_id' => null]);
    $dispatcher = TaxonomyTerm::getEventDispatcher();
    TaxonomyTerm::setEventDispatcher(clone $dispatcher);
    TaxonomyTerm::saving(fn () => throw new RuntimeException('Unexpected persistence failure'));

    try {
        expect(fn () => $component->call('callMountedAction'))
            ->toThrow(RuntimeException::class, 'Unexpected persistence failure');
    } finally {
        TaxonomyTerm::setEventDispatcher($dispatcher);
    }

    expect($taxonomy->terms()->count())->toBe(0);
});
