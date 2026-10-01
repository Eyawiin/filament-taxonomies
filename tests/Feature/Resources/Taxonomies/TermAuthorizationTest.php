<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\ReadOnlyTaxonomyPolicy;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTermMutationPolicy;
use Filament\Actions\Testing\TestAction;
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

it('rejects a direct movement request from a user denied term updates', function (string $method): void {
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

it('prevents denied term actions from mutating the database', function (string $action, string $ability): void {
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

it('rechecks term permission when submitting an already mounted action', function (string $action, string $ability): void {
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

it('permits term mutations independently of denied taxonomy CRUD', function (string $action): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    $policy = new TaxonomyTermMutationPolicy;
    $policy->allowMutations = true;
    app()->instance(TaxonomyTermMutationPolicy::class, $policy);
    expect(Gate::denies('delete', $taxonomy))->toBeTrue();

    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('mountAction', $action, ['term' => $moving->id]);

    if ($action !== 'deleteTerm') {
        $component->fillForm(['name' => 'Changed', 'slug' => 'changed', 'parent_id' => null]);
    }

    $component->call('callMountedAction')->assertHasNoFormErrors();

    if ($action === 'createTerm') {
        expect($taxonomy->terms()->where('slug', 'changed')->exists())->toBeTrue();
    } elseif ($action === 'editTerm') {
        expect($moving->fresh()->name)->toBe('Changed');
    } else {
        expect($moving->fresh())->toBeNull();
    }
})->with(['createTerm', 'editTerm', 'deleteTerm']);

it('permits positional and relative movement when term update is allowed', function (string $method): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $policy = new TaxonomyTermMutationPolicy;
    $policy->allowMutations = true;
    app()->instance(TaxonomyTermMutationPolicy::class, $policy);

    $arguments = $method === 'moveTerm'
        ? [$moving->id, 1, $parent->id]
        : [$moving->id, $parent->id, 'inside'];

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call($method, ...$arguments)->assertHasNoErrors();

    expect($moving->fresh()->parent_id)->toBe($parent->id);
})->with(['moveTerm', 'dropTerm']);

it('hides denied actions and disables every movement control while retaining the tree', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertSee($moving->name)
        ->assertActionHidden('createTerm')
        ->assertActionHidden(TestAction::make('editTerm')->arguments(['term' => $moving->id]))
        ->assertActionHidden(TestAction::make('deleteTerm')->arguments(['term' => $moving->id]));

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>' . $component->html());
    $xpath = new DOMXPath($document);
    $terms = $xpath->query('//*[@data-taxonomy-term]');

    expect($terms->length)->toBe($taxonomy->terms()->count())
        ->and($xpath->query('//*[@data-taxonomy-drag-handle]')->length)->toBe(0);

    foreach ($terms as $term) {
        expect($term->getAttribute('data-can-move'))->toBe('false');
        $arrows = $xpath->query('./div[@data-taxonomy-row]//button[starts-with(@aria-label, "Move ")]', $term);
        expect($arrows->length)->toBe(2);

        foreach ($arrows as $arrow) {
            expect($arrow->getAttribute('aria-disabled'))->toBe('true')
                ->and($arrow->hasAttribute('x-on:click'))->toBeFalse();
        }
    }
});

it('allows a visible read-only destination when only the source can be updated', function (string $method): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    Gate::before(static function ($user, string $ability, array $arguments) use ($moving): ?bool {
        if ($ability !== 'update' || ! ($arguments[0] ?? null) instanceof TaxonomyTerm) {
            return null;
        }

        return $arguments[0]->is($moving);
    });
    expect(Gate::allows('update', $moving))->toBeTrue()
        ->and(Gate::denies('update', $parent))->toBeTrue();

    $arguments = $method === 'moveTerm'
        ? [$moving->id, 1, $parent->id]
        : [$moving->id, $parent->id, 'inside'];

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call($method, ...$arguments)->assertHasNoErrors();

    expect($moving->fresh()->parent_id)->toBe($parent->id);
})->with(['moveTerm', 'dropTerm']);

it('does not borrow taxonomy deletion permission for denied term deletion', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    Gate::before(static fn ($user, string $ability, array $arguments): ?bool => $ability === 'delete'
        && ($arguments[0] ?? null) instanceof Taxonomy ? true : null);
    expect(Gate::allows('delete', $taxonomy))->toBeTrue()
        ->and(Gate::denies('delete', $moving))->toBeTrue();
    $before = $taxonomy->terms()->orderBy('id')->get()->toArray();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertActionHidden(TestAction::make('deleteTerm')->arguments(['term' => $moving->id]))
        ->call('mountAction', 'deleteTerm', ['term' => $moving->id])
        ->assertSet('mountedActions', []);

    expect($taxonomy->terms()->orderBy('id')->get()->toArray())->toBe($before);
});
