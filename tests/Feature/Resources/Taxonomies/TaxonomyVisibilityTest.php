<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Tests\Support\SelectiveTaxonomyPolicy;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Reader', 'email' => 'reader@example.test']));
    Gate::policy(Taxonomy::class, SelectiveTaxonomyPolicy::class);
});

it('omits taxonomy labels and links when record viewing is denied', function (): void {
    $public = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);
    expect(Gate::allows('view', $public))->toBeTrue()
        ->and(Gate::denies('view', $private))->toBeTrue();

    $labels = array_map(
        static fn ($item): string => $item->getLabel(),
        TaxonomyResource::getNavigationItems(),
    );

    expect($labels)->toContain($public->name)
        ->not->toContain($private->name);
});

it('denies a manage-terms record when viewAny is allowed but view is denied', function (): void {
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);
    expect(Gate::allows('viewAny', Taxonomy::class))->toBeTrue()
        ->and(Gate::denies('view', $private))->toBeTrue();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $private->id])
        ->assertForbidden();
});

it('hides the manage link in the taxonomy table when record viewing is denied', function (): void {
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);

    Livewire::test(ListTaxonomies::class)
        ->assertActionHidden(TestAction::make('manageTerms')->table($private));
});

it('denies navigation and page access when listing taxonomies is forbidden', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    Gate::before(static fn ($user, string $ability): ?bool => $ability === 'viewAny' ? false : null);

    expect(TaxonomyResource::getNavigationItems())->toBe([]);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])->assertForbidden();
});

it('does not expose parent options for a taxonomy denied record viewing', function (): void {
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);
    $this->withoutExceptionHandling();

    expect(fn () => TaxonomyParentSelect::make($private))
        ->toThrow(HttpException::class);
});

it('rechecks owning taxonomy permissions on subsequent Livewire writes', function (string $ability, string $action): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Topic', 'slug' => 'topic']);
    $before = $taxonomy->terms()->get()->toArray();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);

    if ($action === 'createTerm') {
        $component->call('mountAction', 'createTerm')
            ->fillForm(['name' => 'New topic', 'slug' => 'new-topic', 'parent_id' => null]);
    }

    Gate::before(static fn ($user, string $requested): ?bool => $requested === $ability ? false : null);

    if ($action === 'createTerm') {
        $component->call('callMountedAction');
    } else {
        $component->call('moveTerm', $term->id, 0);
    }

    $component->assertForbidden();
    expect($taxonomy->terms()->get()->toArray())->toBe($before);
})->with(['viewAny', 'view'])
    ->with(['createTerm', 'moveTerm']);

it('rebuilds navigation permissions for the current user', function (): void {
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);

    expect(array_map(fn ($item) => $item->getLabel(), TaxonomyResource::getNavigationItems()))
        ->not->toContain($private->name);

    $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Editor', 'email' => 'editor@example.test']));
    Gate::before(static fn ($user, string $ability): ?bool => $ability === 'view' && $user->getAuthIdentifier() === 2 ? true : null);

    expect(array_map(fn ($item) => $item->getLabel(), TaxonomyResource::getNavigationItems()))
        ->toContain($private->name);
});
