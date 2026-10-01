<?php

use Eyawiin\FilamentTaxonomies\Authorization\TaxonomyTermAuthorization;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\ContextualTaxonomyTermPolicy;
use Eyawiin\FilamentTaxonomies\Tests\Support\ReadOnlyTaxonomyPolicy;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTermMutationPolicy;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Reader', 'email' => 'reader@example.test']));
});

it('retains Filament defaults when a term policy or its methods are missing', function (bool $missingMethods): void {
    if ($missingMethods) {
        Gate::policy(TaxonomyTerm::class, stdClass::class);
    }

    $taxonomy = Taxonomy::create(['name' => 'Public', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Topic', 'slug' => 'topic']);

    expect(TaxonomyTermAuthorization::create($taxonomy)->allowed())->toBeTrue()
        ->and(TaxonomyTermAuthorization::update($term)->allowed())->toBeTrue()
        ->and(TaxonomyTermAuthorization::delete($term)->allowed())->toBeTrue();
})->with(['no policy' => false, 'missing methods' => true]);

it('honors Gate before denials when no term policy handles the ability', function (string $ability, bool $missingMethods): void {
    if ($missingMethods) {
        Gate::policy(TaxonomyTerm::class, stdClass::class);
    }

    Gate::before(static fn (?Authenticatable $user, string $requested): ?bool => $requested === $ability ? false : null);
    $taxonomy = Taxonomy::create(['name' => 'Public', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Topic', 'slug' => 'topic']);

    $response = TaxonomyTermAuthorization::$ability($ability === 'create' ? $taxonomy : $term);

    expect($response->denied())->toBeTrue();
})->with(['create', 'update', 'delete'])
    ->with(['no policy' => false, 'missing methods' => true]);

it('preserves Gate before response messages and statuses', function (): void {
    Gate::before(static fn (): Response => Response::denyAsNotFound('Unavailable'));
    $taxonomy = Taxonomy::create(['name' => 'Public', 'slug' => 'public']);

    $response = TaxonomyTermAuthorization::create($taxonomy);

    expect($response->message())->toBe('Unavailable')
        ->and($response->status())->toBe(404);
});

it('reports missing term policies and methods in strict authorization mode', function (string $ability, bool $missingMethods): void {
    Filament::getCurrentPanel()->strictAuthorization();

    if ($missingMethods) {
        Gate::policy(TaxonomyTerm::class, stdClass::class);
    }

    $taxonomy = Taxonomy::create(['name' => 'Public', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Topic', 'slug' => 'topic']);

    expect(fn () => TaxonomyTermAuthorization::$ability($ability === 'create' ? $taxonomy : $term))
        ->toThrow(LogicException::class, $missingMethods ? "no [{$ability}()] method" : 'no policy');
})->with(['create', 'update', 'delete'])
    ->with(['no policy' => false, 'missing methods' => true]);

it('uses the active panel guard even when the default guard has a different user', function (): void {
    config()->set('auth.guards.reviewer', config('auth.guards.web'));
    $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Editor', 'email' => 'editor@example.test']), 'reviewer');
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Reader', 'email' => 'reader@example.test']), 'web');
    Filament::getCurrentPanel()->authGuard('reviewer');
    Gate::policy(TaxonomyTerm::class, TaxonomyTermMutationPolicy::class);
    Gate::before(static fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 2);

    $taxonomy = Taxonomy::create(['name' => 'Public', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Topic', 'slug' => 'topic']);

    expect(Gate::allows('update', $term))->toBeFalse()
        ->and(TaxonomyTermAuthorization::create($taxonomy)->allowed())->toBeTrue()
        ->and(TaxonomyTermAuthorization::update($term)->allowed())->toBeTrue()
        ->and(TaxonomyTermAuthorization::delete($term)->allowed())->toBeTrue();
});

it('passes the owning taxonomy to a contextual create policy on action execution', function (): void {
    Gate::policy(Taxonomy::class, ReadOnlyTaxonomyPolicy::class);
    Gate::policy(TaxonomyTerm::class, ContextualTaxonomyTermPolicy::class);
    $public = Taxonomy::create(['name' => 'Public', 'slug' => 'public']);
    $private = Taxonomy::create(['name' => 'Private', 'slug' => 'private']);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $public->id])
        ->callAction('createTerm', data: ['name' => 'Topic', 'slug' => 'topic', 'parent_id' => null])
        ->assertHasNoFormErrors();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $private->id])
        ->assertActionHidden('createTerm')
        ->call('mountAction', 'createTerm')
        ->assertSet('mountedActions', []);

    expect($public->terms()->count())->toBe(1)
        ->and($private->terms()->count())->toBe(0);
});
