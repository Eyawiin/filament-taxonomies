<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\ReadOnlyTaxonomyPolicy;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTermMutationPolicy;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportEvents\SupportEvents;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('rechecks term permission after the taxonomy lock is acquired', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    $this->actingAs(new GenericUser(['id' => 1]));
    Gate::policy(Taxonomy::class, ReadOnlyTaxonomyPolicy::class);
    Gate::policy(TaxonomyTerm::class, TaxonomyTermMutationPolicy::class);
    $policy = new TaxonomyTermMutationPolicy;
    $policy->allowMutations = true;
    app()->instance(TaxonomyTermMutationPolicy::class, $policy);
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $baseLevel = $connection->transactionLevel();
    $locked = false;
    $connection->setEventDispatcher(clone $dispatcher);
    $connection->getEventDispatcher()->listen(QueryExecuted::class, function (QueryExecuted $event) use ($policy, $baseLevel, &$locked): void {
        if ($event->connection->transactionLevel() > $baseLevel && str_starts_with($event->sql, 'select * from "taxonomies"')) {
            $locked = true;
            $policy->allowMutations = false;
        }
    });

    try {
        $component->call('moveTerm', $moving->id, 1, $destination->id)->assertForbidden();
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }
    expect($locked)->toBeTrue()->and(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});

it('defers expansion until a consumer transaction commits', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])->instance();
    $connection = DB::connection();
    $connection->beginTransaction();

    try {
        $component->dropTerm($moving->id, $destination->id, 'inside');
        expect((new SupportEvents)->getServerDispatchedEvents($component))->toBe([]);
        $connection->commit();
    } finally {
        if ($connection->transactionLevel() > 1) {
            $connection->rollBack();
        }
    }
    expect((new SupportEvents)->getServerDispatchedEvents($component))->toContain([
        'name' => 'taxonomy-tree-expand-term', 'params' => ['termId' => $destination->id],
    ]);
});

it('discards expansion and data changes on an outer rollback', function (): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'destinationParent' => $destination] = OrderedTaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])->instance();
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $connection = DB::connection();
    $connection->beginTransaction();

    try {
        $component->dropTerm($moving->id, $destination->id, 'inside');
    } finally {
        $connection->rollBack();
    }
    expect((new SupportEvents)->getServerDispatchedEvents($component))->toBe([])
        ->and(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
});

it('routes taxonomy deletion through managed cascade validation', function (): void {
    ['taxonomy' => $taxonomy, 'sourceParent' => $parent] = OrderedTaxonomyTreeFixture::create();
    $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    $foreign = $other->terms()->create(['name' => 'Foreign', 'slug' => 'foreign', 'parent_id' => $parent->id]);
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    Livewire::test(ListTaxonomies::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($taxonomy))
        ->assertNotified(Notification::make()->danger()
            ->persistent()->title('The taxonomy could not be deleted')
            ->body('Deletion would affect a term belonging to another taxonomy.'));
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
    expect($taxonomy->fresh())->not->toBeNull()->and($foreign->fresh()->parent_id)->toBe($parent->id);
});

it('preserves the native delete action record state after managed deletion', function (): void {
    ['taxonomy' => $taxonomy] = OrderedTaxonomyTreeFixture::create();
    $component = Livewire::test(ListTaxonomies::class)->instance();
    $action = $component->getTable()->getAction('delete')->record($taxonomy);
    $action->call();
    expect(Taxonomy::find($taxonomy->id))->toBeNull()
        ->and($action->getRecord()->exists)->toBeFalse();
});
