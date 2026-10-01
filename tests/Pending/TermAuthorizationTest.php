<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\ReadOnlyTaxonomyPolicy;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTermMutationPolicy;
use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Reader', 'email' => 'reader@example.test']));
    Gate::policy(Taxonomy::class, ReadOnlyTaxonomyPolicy::class);
    Gate::policy(TaxonomyTerm::class, TaxonomyTermMutationPolicy::class);
});

it('F1 rejects a direct movement request from a user denied term updates', function (string $method): void {
    [
        'taxonomy' => $taxonomy, 'moving' => $moving,
        'destinationParent' => $destinationParent,
    ] = OrderedTaxonomyTreeFixture::create();
    expect(Gate::denies('update', $moving))->toBeTrue();
    $before = $taxonomy->terms()->orderBy('id')->get()->toArray();

    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertSuccessful();

    if ($method === 'moveTerm') {
        $component->call('moveTerm', $moving->id, 1, $destinationParent->id);
    } else {
        $component->call('dropTerm', $moving->id, $destinationParent->id, 'inside');
    }

    expect($taxonomy->terms()->orderBy('id')->get()->toArray())->toBe($before);
    $component->assertForbidden();
})->with(['moveTerm', 'dropTerm']);

it('F1 prevents denied term actions from mutating the database', function (string $action, string $ability): void {
    [
        'taxonomy' => $taxonomy, 'moving' => $moving,
    ] = OrderedTaxonomyTreeFixture::create();
    expect(Gate::denies($ability, $ability === 'create' ? TaxonomyTerm::class : $moving))->toBeTrue();
    $before = $taxonomy->terms()->orderBy('id')->get()->toArray();

    // Call Livewire directly: Filament's callAction test helper requires a visible action.
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertSuccessful()
        ->call('mountAction', $action, ['term' => $moving->id]);

    if ($component->instance()->getMountedAction() !== null) {
        if ($action !== 'deleteTerm') {
            $component->fillForm([
                'name' => 'Changed', 'slug' => 'changed', 'parent_id' => null,
            ]);
        }

        $component->call('callMountedAction');
    }

    expect($taxonomy->terms()->orderBy('id')->get()->toArray())->toBe($before);
})->with([
    'create' => ['createTerm', 'create'],
    'edit' => ['editTerm', 'update'],
    'delete' => ['deleteTerm', 'delete'],
]);

it('F1 rechecks term permission when submitting an already mounted action', function (string $action, string $ability): void {
    [
        'taxonomy' => $taxonomy, 'moving' => $moving,
    ] = OrderedTaxonomyTreeFixture::create();
    $policy = new TaxonomyTermMutationPolicy;
    $policy->allowMutations = true;
    app()->instance(TaxonomyTermMutationPolicy::class, $policy);
    $subject = $ability === 'create' ? TaxonomyTerm::class : $moving;
    expect(Gate::allows($ability, $subject))->toBeTrue();
    $before = $taxonomy->terms()->orderBy('id')->get()->toArray();

    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertSuccessful()
        ->call('mountAction', $action, ['term' => $moving->id]);

    expect($component->instance()->getMountedAction()?->getName())->toBe($action);

    if ($action !== 'deleteTerm') {
        $component->fillForm([
            'name' => 'Changed', 'slug' => 'changed', 'parent_id' => null,
        ]);
    }

    $policy->allowMutations = false;
    expect(Gate::denies($ability, $subject))->toBeTrue();
    $component->call('callMountedAction');

    expect($taxonomy->terms()->orderBy('id')->get()->toArray())->toBe($before);
})->with([
    'create' => ['createTerm', 'create'],
    'edit' => ['editTerm', 'update'],
    'delete' => ['deleteTerm', 'delete'],
]);
