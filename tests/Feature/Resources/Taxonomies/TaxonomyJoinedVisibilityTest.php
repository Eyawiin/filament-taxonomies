<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Model::clearBootedModels();
});

it('preserves taxonomy record identity and labels with a joined visibility scope', function (): void {
    $visible = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $hidden = Taxonomy::create(['name' => 'Private topics', 'slug' => 'private']);
    $term = $visible->terms()->create(['name' => 'Visible term', 'slug' => 'visible']);
    Taxonomy::addGlobalScope('joined-visibility', function (Builder $query): void {
        $query->crossJoin(DB::raw("(select 777 as id, 'Permission label' as name, 'permission' as slug) as permissions"))
            ->where('taxonomies.slug', 'public');
    });
    $items = TaxonomyResource::getNavigationItems();
    expect(array_map(fn ($item) => $item->getLabel(), $items))->toBe(['Taxonomies', $visible->name])
        ->and($items[1]->getBadge())->toBe('1')
        ->and($items[1]->getUrl())->toContain('/taxonomies/' . $visible->id . '/manage-terms');

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $visible->id])
        ->assertSee($term->name)
        ->call('moveTerm', $term->id, 0)
        ->assertHasNoErrors();
    expect($term->fresh()->taxonomy_id)->toBe($visible->id)
        ->and(Taxonomy::query()->whereKey($hidden->id)->exists())->toBeFalse()
        ->and($hidden->fresh()->slug)->toBe('private')
        ->and($visible->resolveRouteBindingQuery(TaxonomyResource::getEloquentQuery(), 'public', 'slug')->first()->id)->toBe($visible->id);

    Livewire::test(EditTaxonomy::class, ['record' => $visible->id])
        ->fillForm(['name' => 'Renamed taxonomy', 'slug' => 'public'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($visible->fresh()->name)->toBe('Renamed taxonomy');
    Livewire::test(ListTaxonomies::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($visible));
    expect($visible->fresh())->toBeNull()
        ->and($term->fresh())->toBeNull()
        ->and($hidden->fresh()->name)->toBe('Private topics');
});

it('preserves term identity in joined parent options and form execution', function (string $action): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $root = $taxonomy->terms()->create(['name' => 'Root', 'slug' => 'root']);
    $child = $taxonomy->terms()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);
    TaxonomyTerm::addGlobalScope('joined-visibility', fn (Builder $query) => $query->join('taxonomies as visible_taxonomies', 'visible_taxonomies.id', '=', 'taxonomy_terms.taxonomy_id'));

    $options = TaxonomyParentSelect::make($taxonomy, $child)->getNodes();
    expect(array_column($options, 'name', 'id'))->toBe([$root->id => 'Root', $child->id => 'Child'])
        ->and(array_column($options, 'disabled', 'id'))->toBe([$root->id => false, $child->id => true]);
    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('mountAction', $action, ['term' => $child->id])
        ->fillForm(['name' => 'Updated child', 'slug' => 'updated-child', 'parent_id' => $root->id])
        ->call('callMountedAction')
        ->assertHasNoFormErrors();
    $updated = TaxonomyTerm::withoutGlobalScopes()->where('slug', 'updated-child')->firstOrFail();
    expect($root->fresh()->name)->toBe('Root')
        ->and($updated->parent_id)->toBe($root->id)
        ->and($updated->taxonomy_id)->toBe($taxonomy->id)
        ->and($updated->id === $child->id)->toBe($action === 'editTerm');
})->with(['createTerm', 'editTerm']);

it('counts distinct visible terms when visibility joins duplicate rows', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Public topics', 'slug' => 'public']);
    $visible = $taxonomy->terms()->create(['name' => 'Visible term', 'slug' => 'visible']);
    $hidden = $taxonomy->terms()->create(['name' => 'Hidden term', 'slug' => 'hidden']);
    TaxonomyTerm::addGlobalScope('joined-visibility', function (Builder $query) use ($hidden): void {
        $query->crossJoin(DB::raw('(select 1 as copy union all select 2 as copy) as permissions'))
            ->whereKeyNot($hidden->id);
    });

    expect(TaxonomyResource::getNavigationItems()[1]->getBadge())->toBe('1');
    $component = Livewire::test(ListTaxonomies::class)->instance();
    $record = $component->getTableRecords()->first();
    expect($record->terms_count)->toBe(1)
        ->and(app(TaxonomyTreeService::class)->getTree($taxonomy))->toHaveCount(1);
});

it('preserves model-configured aggregates when guarding resource record columns', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $taxonomy->terms()->create(['name' => 'One', 'slug' => 'one']);
    $taxonomy->terms()->create(['name' => 'Two', 'slug' => 'two']);

    $record = AggregateTaxonomyResource::getEloquentQuery()->firstOrFail();
    expect($record->id)->toBe($taxonomy->id)
        ->and($record->name)->toBe('Topics')
        ->and($record->terms_count)->toBe(2);
});

class AggregateTaxonomy extends Taxonomy
{
    protected $table = 'taxonomies';

    protected $withCount = ['terms'];

    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class, 'taxonomy_id');
    }
}

class AggregateTaxonomyResource extends TaxonomyResource
{
    protected static ?string $model = AggregateTaxonomy::class;
}

it('paginates each visible taxonomy once when permission joins duplicate records', function (): void {
    $first = Taxonomy::create(['name' => 'First', 'slug' => 'first']);
    $second = Taxonomy::create(['name' => 'Second', 'slug' => 'second']);
    $hidden = Taxonomy::create(['name' => 'Hidden', 'slug' => 'hidden']);
    Taxonomy::addGlobalScope('duplicate-permissions', function (Builder $query) use ($hidden): void {
        $query->crossJoin(DB::raw("(select 777 as id, 'Permission' as name, 'permission' as slug, 1 as copy union all select 778, 'Permission', 'permission', 2) as permissions"))
            ->whereKeyNot($hidden->id);
    });
    $component = Livewire::test(ListTaxonomies::class);
    $records = $component->instance()->getTableRecords();
    expect($records->total())->toBe(2)
        ->and($records->count())->toBe(2)
        ->and($records->getCollection()->modelKeys())->toEqualCanonicalizing([$first->id, $second->id]);
    $component->searchTable('First')
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
    expect($component->instance()->getTableRecords()->total())->toBe(1);
    foreach (['name', 'slug'] as $column) {
        $component->searchTable('')->sortTable($column, 'desc');
        expect($component->instance()->getTableRecords()->getCollection()->modelKeys())
            ->toBe([$second->id, $first->id]);
    }
});
