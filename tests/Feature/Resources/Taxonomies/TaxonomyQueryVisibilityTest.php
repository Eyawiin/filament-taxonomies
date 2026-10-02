<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Tests\Support\ScopedManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\ScopedTaxonomyResource;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

// Named visibility scopes in these tests must not persist into other applications.
afterEach(function (): void {
    Model::clearBootedModels();
});

it('uses resource and model visibility scopes for navigation, page resolution, and parent options', function (bool $resourceScope): void {
    $public = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $private = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);
    $private->terms()->create(['name' => 'Secret topic', 'slug' => 'secret-topic']);
    $resource = $resourceScope ? ScopedTaxonomyResource::class : TaxonomyResource::class;
    $page = $resourceScope ? ScopedManageTaxonomyTerms::class : ManageTaxonomyTerms::class;

    if (! $resourceScope) {
        Taxonomy::addGlobalScope('visible', fn (Builder $query) => $query->where('slug', '!=', 'private'));
    }

    expect(array_map(fn ($item) => $item->getLabel(), $resource::getNavigationItems()))
        ->toContain($public->name)->not->toContain($private->name);

    Livewire::test($page, ['record' => $public->id])
        ->assertSuccessful()->assertDontSee('Secret topic');

    $this->withoutExceptionHandling();
    expect(fn () => Livewire::test($page, ['record' => $private->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => TaxonomyParentSelect::make($private, resource: $resource))->toThrow(ModelNotFoundException::class);
})->with(['model scope' => false, 'resource scope' => true]);

it('reapplies resource visibility when hydrating an already mounted action', function (string $action): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Topic', 'slug' => 'topic']);
    $before = $taxonomy->terms()->get()->toArray();

    $component = Livewire::test(ScopedManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('mountAction', $action, ['term' => $term->id]);

    if ($action !== 'deleteTerm') {
        $component->fillForm(['name' => 'Changed', 'slug' => 'changed', 'parent_id' => null]);
    }

    $taxonomy->update(['slug' => 'private']);
    $this->withoutExceptionHandling();

    try {
        $component->call('callMountedAction')->assertNotFound();
    } catch (ModelNotFoundException) {
        // Older Livewire versions may propagate the framework's missing-record exception.
    }
    expect($taxonomy->terms()->get()->toArray())->toBe($before);
})->with(['createTerm', 'editTerm', 'deleteTerm']);

it('honors term global scopes in tree labels, navigation counts, and parent options', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $visible = $taxonomy->terms()->create(['name' => 'Visible topic', 'slug' => 'visible']);
    $hidden = $taxonomy->terms()->create(['name' => 'Hidden topic', 'slug' => 'hidden']);
    TaxonomyTerm::addGlobalScope('visible', fn (Builder $query) => $query->where('slug', '!=', 'hidden'));

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertSee($visible->name)->assertDontSee($hidden->name);

    expect(array_column(TaxonomyParentSelect::make($taxonomy)->getNodes(), 'name', 'id'))->toBe([$visible->id => $visible->name])
        ->and(TaxonomyResource::getNavigationItems()[1]->getBadge())->toBe('1');
});

it('rejects hidden terms used as movement sources or destinations without changing any rows', function (string $method, bool $hiddenSource): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $visible = $taxonomy->terms()->create(['name' => 'Visible topic', 'slug' => 'visible']);
    $hidden = $taxonomy->terms()->create(['name' => 'Hidden topic', 'slug' => 'hidden']);
    TaxonomyTerm::addGlobalScope('visible', fn (Builder $query) => $query->where('slug', '!=', 'hidden'));
    $before = TaxonomyTerm::withoutGlobalScopes()->orderBy('id')->get()->toArray();
    $sourceId = $hiddenSource ? $hidden->id : $visible->id;
    $targetId = $hiddenSource ? $visible->id : $hidden->id;
    $arguments = $method === 'moveTerm' ? [$sourceId, 0, $targetId] : [$sourceId, $targetId, 'inside'];
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])->call($method, ...$arguments))
        ->toThrow(ModelNotFoundException::class);

    expect(TaxonomyTerm::withoutGlobalScopes()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['moveTerm', 'dropTerm'])
    ->with(['hidden source' => true, 'hidden destination' => false]);

it('rejects forged hidden or foreign action records without changing any rows', function (string $action, bool $foreign): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $owner = $foreign ? Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']) : $taxonomy;
    $hidden = $owner->terms()->create(['name' => 'Hidden topic', 'slug' => 'hidden']);

    if (! $foreign) {
        TaxonomyTerm::addGlobalScope('visible', fn (Builder $query) => $query->where('slug', '!=', 'hidden'));
    }

    $before = TaxonomyTerm::withoutGlobalScopes()->get()->toArray();
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('mountAction', $action, ['term' => $hidden->id]))->toThrow(ModelNotFoundException::class);

    expect(TaxonomyTerm::withoutGlobalScopes()->get()->toArray())->toBe($before);
})->with(['editTerm', 'deleteTerm'])
    ->with(['hidden' => false, 'foreign' => true]);

it('rejects hidden parents submitted to create and edit forms', function (string $action): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Visible topic', 'slug' => 'visible']);
    $hidden = $taxonomy->terms()->create(['name' => 'Hidden topic', 'slug' => 'hidden']);
    TaxonomyTerm::addGlobalScope('visible', fn (Builder $query) => $query->where('slug', '!=', 'hidden'));
    $before = TaxonomyTerm::withoutGlobalScopes()->get()->toArray();

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('mountAction', $action, ['term' => $term->id])
        ->fillForm(['name' => 'Changed', 'slug' => 'changed', 'parent_id' => $hidden->id])
        ->call('callMountedAction')->assertHasFormErrors(['parent_id']);

    expect(TaxonomyTerm::withoutGlobalScopes()->get()->toArray())->toBe($before);
})->with(['createTerm', 'editTerm']);

it('rechecks term scope when submitting an already mounted action', function (string $action): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $term = $taxonomy->terms()->create(['name' => 'Visible topic', 'slug' => 'visible']);
    TaxonomyTerm::addGlobalScope('visible', fn (Builder $query) => $query->where('slug', '!=', 'hidden'));

    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('mountAction', $action, ['term' => $term->id]);

    if ($action === 'editTerm') {
        $component->fillForm(['name' => 'Changed', 'slug' => 'changed', 'parent_id' => null]);
    }

    $term->update(['slug' => 'hidden']);
    $before = TaxonomyTerm::withoutGlobalScopes()->get()->toArray();
    $this->withoutExceptionHandling();

    try {
        $component->call('callMountedAction')->assertNotFound();
    } catch (ModelNotFoundException) {
        // Older Livewire versions may propagate the framework's missing-record exception.
    }

    expect(TaxonomyTerm::withoutGlobalScopes()->get()->toArray())->toBe($before);
})->with(['editTerm', 'deleteTerm']);

it('reapplies taxonomy visibility before saving an already mounted edit page', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $component = Livewire::test(EditTaxonomy::class, ['record' => $taxonomy->id])
        ->fillForm(['name' => 'Changed', 'slug' => 'changed']);

    $taxonomy->update(['slug' => 'private']);
    Taxonomy::addGlobalScope('visible', fn (Builder $query) => $query->where('slug', '!=', 'private'));
    $before = Taxonomy::withoutGlobalScopes()->findOrFail($taxonomy->id)->toArray();
    $this->withoutExceptionHandling();

    try {
        $component->call('save')->assertNotFound();
    } catch (ModelNotFoundException) {
        // Older Livewire versions may propagate the framework's missing-record exception.
    }

    expect(Taxonomy::withoutGlobalScopes()->findOrFail($taxonomy->id)->toArray())->toBe($before);
});
