<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('can reorder taxonomy terms within the same parent', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->id,
        'position' => 0,
    ]);

    $liloAndStitch = $taxonomy->terms()->create([
        'name' => 'Lilo & Stitch',
        'slug' => 'lilo-stitch',
        'parent_id' => $disney->id,
        'position' => 1,
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->call(
            'moveTerm',
            $liloAndStitch->id,
            0,
            $disney->id,
        );

    expect($liloAndStitch->fresh()->position)->toBe(0)
        ->and($lionKing->fresh()->position)->toBe(1);
});

it('can move a taxonomy term between parents through drag ordering', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->id,
        'position' => 0,
    ]);

    $toyStory = $taxonomy->terms()->create([
        'name' => 'Toy Story',
        'slug' => 'toy-story',
        'parent_id' => $pixar->id,
        'position' => 0,
    ]);

    $cars = $taxonomy->terms()->create([
        'name' => 'Cars',
        'slug' => 'cars',
        'parent_id' => $pixar->id,
        'position' => 1,
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->call(
            'moveTerm',
            $lionKing->id,
            1,
            $pixar->id,
        )
        ->assertDispatched(
            'taxonomy-tree-expand-term',
            termId: $pixar->id,
        );

    expect($lionKing->fresh())
        ->parent_id->toBe($pixar->id)
        ->position->toBe(1);

    expect($toyStory->fresh()->position)
        ->toBe(0)
        ->and($cars->fresh()->position)
        ->toBe(2);
});

it('can move a taxonomy term to the root through drag ordering', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
        'position' => 0,
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
        'position' => 1,
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->id,
        'position' => 0,
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->call(
            'moveTerm',
            $lionKing->id,
            1,
            null,
        )
        ->assertNotDispatched('taxonomy-tree-expand-term');

    expect($lionKing->fresh())
        ->parent_id->toBeNull()
        ->position->toBe(1);

    expect($disney->fresh()->position)->toBe(0)
        ->and($pixar->fresh()->position)->toBe(2);
});

it('can move a taxonomy term into an empty parent through drag ordering', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $pixar = $taxonomy->terms()->create([
        'name' => 'Pixar',
        'slug' => 'pixar',
    ]);

    $lionKing = $taxonomy->terms()->create([
        'name' => 'Lion King',
        'slug' => 'lion-king',
        'parent_id' => $disney->id,
        'position' => 0,
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->call(
            'moveTerm',
            $lionKing->id,
            0,
            $pixar->id,
        )
        ->assertDispatched(
            'taxonomy-tree-expand-term',
            termId: $pixar->id,
        );

    expect($lionKing->fresh())
        ->parent_id->toBe($pixar->id)
        ->position->toBe(0);
});

it('can drop a term before or after a sibling through Livewire', function (
    string $placement,
    array $expectedNames,
): void {
    $taxonomy = Taxonomy::create(['name' => 'Theme', 'slug' => 'theme']);
    $source = $taxonomy->terms()->create(['name' => 'Disney', 'slug' => 'disney', 'position' => 0]);
    $target = $taxonomy->terms()->create(['name' => 'Pixar', 'slug' => 'pixar', 'position' => 1]);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('dropTerm', $source->id, $target->id, $placement)
        ->assertHasNoErrors()
        ->assertNotDispatched('taxonomy-tree-expand-term')
        ->assertSeeInOrder($expectedNames);

    $terms = $taxonomy->terms()->whereNull('parent_id')->orderBy('position')->get();

    expect($terms->pluck('name')->all())->toBe($expectedNames)
        ->and($terms->pluck('position')->all())->toBe([0, 1]);
})->with([
    'before' => ['before', ['Disney', 'Pixar']],
    'after' => ['after', ['Pixar', 'Disney']],
]);

it('drops a term inside a parent and expands that parent through Livewire', function (bool $alreadyChild): void {
    $taxonomy = Taxonomy::create(['name' => 'Theme', 'slug' => 'theme']);
    $target = $taxonomy->terms()->create(['name' => 'Disney', 'slug' => 'disney']);
    $source = $taxonomy->terms()->create([
        'name' => 'Lion King', 'slug' => 'lion-king',
        'parent_id' => $alreadyChild ? $target->id : null,
        'position' => 0,
    ]);
    $sibling = $taxonomy->terms()->create([
        'name' => 'Frozen', 'slug' => 'frozen', 'parent_id' => $target->id, 'position' => 1,
    ]);
    $child = $taxonomy->terms()->create([
        'name' => 'Simba', 'slug' => 'simba', 'parent_id' => $source->id,
    ]);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('dropTerm', $source->id, $target->id, 'inside')
        ->assertHasNoErrors()
        ->assertDispatched('taxonomy-tree-expand-term', termId: $target->id)
        ->assertSeeInOrder(['Disney', 'Frozen', 'Lion King', 'Simba']);

    expect($source->fresh()->parent_id)->toBe($target->id)
        ->and($source->fresh()->position)->toBe(1)
        ->and($sibling->fresh()->position)->toBe(0)
        ->and($child->fresh()->parent_id)->toBe($source->id);
})->with(['new parent' => false, 'existing parent' => true]);

it('drops a nested term back to root through Livewire', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Theme', 'slug' => 'theme']);
    $target = $taxonomy->terms()->create(['name' => 'Disney', 'slug' => 'disney']);
    $source = $taxonomy->terms()->create([
        'name' => 'Lion King', 'slug' => 'lion-king', 'parent_id' => $target->id,
    ]);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('dropTerm', $source->id, $target->id, 'after')
        ->assertHasNoErrors()
        ->assertNotDispatched('taxonomy-tree-expand-term');

    expect($source->fresh()->parent_id)->toBeNull()
        ->and($source->fresh()->position)->toBe(1);
});

it('rejects invalid drop placement and clears the error on a valid retry', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Theme', 'slug' => 'theme']);
    $source = $taxonomy->terms()->create(['name' => 'Disney', 'slug' => 'disney', 'position' => 0]);
    $target = $taxonomy->terms()->create(['name' => 'Pixar', 'slug' => 'pixar', 'position' => 1]);
    $before = $taxonomy->terms()->orderBy('id')->get()->toArray();

    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('dropTerm', $source->id, $target->id, 'invalid')
        ->assertHasErrors(['placement'])
        ->assertNotDispatched('taxonomy-tree-expand-term');

    expect($taxonomy->terms()->orderBy('id')->get()->toArray())->toBe($before);

    $component->call('dropTerm', $source->id, $target->id, 'after')->assertHasNoErrors();

    expect($source->fresh()->position)->toBe(1);
});

it('rejects self and cyclic drops through Livewire without changing the tree', function (string $targetKey, string $placement): void {
    $taxonomy = Taxonomy::create(['name' => 'Theme', 'slug' => 'theme']);
    $source = $taxonomy->terms()->create(['name' => 'Disney', 'slug' => 'disney']);
    $child = $taxonomy->terms()->create([
        'name' => 'Lion King', 'slug' => 'lion-king', 'parent_id' => $source->id,
    ]);
    $grandchild = $taxonomy->terms()->create([
        'name' => 'Simba', 'slug' => 'simba', 'parent_id' => $child->id,
    ]);
    $target = match ($targetKey) {
        'self' => $source,
        'child' => $child,
        'grandchild' => $grandchild,
    };
    $before = $taxonomy->terms()->orderBy('id')->get()->toArray();

    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call('dropTerm', $source->id, $target->id, $placement)
        ->assertHasErrors(['drop'])
        ->assertNotDispatched('taxonomy-tree-expand-term');

    expect($taxonomy->terms()->orderBy('id')->get()->toArray())->toBe($before);

    $component->call('dropTerm', $child->id, $source->id, 'after')->assertHasNoErrors();

    expect($child->fresh()->parent_id)->toBeNull();
})->with([
    'self before' => ['self', 'before'],
    'self inside' => ['self', 'inside'],
    'self after' => ['self', 'after'],
    'inside descendant' => ['grandchild', 'inside'],
    'before own child' => ['child', 'before'],
    'after descendant' => ['grandchild', 'after'],
]);

it('cannot use foreign or missing terms in a Livewire drop', function (string $invalidArgument, bool $missing): void {
    $taxonomy = Taxonomy::create(['name' => 'Theme', 'slug' => 'theme']);
    $local = $taxonomy->terms()->create(['name' => 'Disney', 'slug' => 'disney']);
    $other = Taxonomy::create(['name' => 'Country', 'slug' => 'country']);
    $foreign = $other->terms()->create(['name' => 'Italy', 'slug' => 'italy']);
    $invalidId = $foreign->id;

    if ($missing) {
        $foreign->delete();
    }

    $before = TaxonomyTerm::query()->orderBy('id')->get()->toArray();

    // Livewire versions differ in whether model-not-found exceptions become 404 responses.
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call(
            'dropTerm',
            $invalidArgument === 'source' ? $invalidId : $local->id,
            $invalidArgument === 'target' ? $invalidId : $local->id,
            'inside',
        ))->toThrow(ModelNotFoundException::class);

    expect(TaxonomyTerm::query()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['source', 'target'])
    ->with(['foreign' => false, 'missing' => true]);
