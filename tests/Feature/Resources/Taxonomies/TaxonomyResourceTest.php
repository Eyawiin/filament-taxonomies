<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('can render the taxonomy list page', function () {
    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful();
});

it('has a create taxonomy action', function () {
    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful()
        ->assertActionExists('create');
});

it('can create a taxonomy', function () {
    Livewire::test(CreateTaxonomy::class)
        ->fillForm([
            'name' => 'Theme',
            'slug' => 'theme',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Taxonomy::query())
        ->where('name', 'Theme')
        ->where('slug', 'theme')
        ->exists()
        ->toBeTrue();
});

it('can edit a taxonomy', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(EditTaxonomy::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->fillForm([
            'name' => 'Themes',
            'slug' => 'themes',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($taxonomy->fresh())
        ->name->toBe('Themes')
        ->slug->toBe('themes');
});

it('can delete a taxonomy', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful()
        ->assertActionExists(
            TestAction::make(DeleteAction::class)->table($taxonomy),
        )
        ->callAction(
            TestAction::make(DeleteAction::class)->table($taxonomy),
        );

    expect(Taxonomy::find($taxonomy->getKey()))
        ->toBeNull();
});

it('has a manage terms action', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ListTaxonomies::class)
        ->assertSuccessful()
        ->assertActionExists(
            TestAction::make('manageTerms')->table($taxonomy),
        );
});

it('offers taxonomy edit and delete actions on the manage terms page', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->assertSuccessful()
        ->assertActionVisible('editTaxonomy')
        ->assertActionHasUrl(
            'editTaxonomy',
            TaxonomyResource::getUrl('edit', ['record' => $taxonomy]),
        )
        ->assertActionVisible('deleteTaxonomy')
        ->assertActionVisible('createTerm');
});

it('deletes the taxonomy and its terms from the manage terms page', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $term = $taxonomy->terms()->create([
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction('deleteTaxonomy')
        ->assertRedirect(TaxonomyResource::getUrl('index'));

    expect(Taxonomy::find($taxonomy->getKey()))->toBeNull()
        ->and(TaxonomyTerm::find($term->getKey()))->toBeNull();
});

it('can create a taxonomy term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $parent = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => null,
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->assertSuccessful()
        ->assertActionExists('createTerm')
        ->callAction('createTerm', data: [
            'name' => 'Lilo & Stitch',
            'slug' => 'lilo-and-stitch',
            'parent_id' => $parent->getKey(),
        ])
        ->assertHasNoFormErrors()
        ->assertDispatched(
            'taxonomy-tree-expand-term',
            termId: $parent->getKey(),
        );

    $term = TaxonomyTerm::query()
        ->where('slug', 'lilo-and-stitch')
        ->first();

    expect($term)
        ->not->toBeNull()
        ->taxonomy_id->toBe($taxonomy->getKey())
        ->parent_id->toBe($parent->getKey())
        ->name->toBe('Lilo & Stitch')
        ->slug->toBe('lilo-and-stitch');
});

it('can create a root taxonomy term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction('createTerm', data: [
            'name' => 'Disney',
            'slug' => 'disney',
            'parent_id' => null,
        ])
        ->assertHasNoFormErrors()
        ->assertNotDispatched('taxonomy-tree-expand-term');

    expect(TaxonomyTerm::query()->where('slug', 'disney')->first())
        ->not->toBeNull()
        ->taxonomy_id->toBe($taxonomy->getKey())
        ->parent_id->toBeNull();
});

it('cannot create a taxonomy term with a parent from another taxonomy', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $otherTaxonomy = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $foreignParent = TaxonomyTerm::create([
        'taxonomy_id' => $otherTaxonomy->getKey(),
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction('createTerm', data: [
            'name' => 'Venice',
            'slug' => 'venice',
            'parent_id' => $foreignParent->getKey(),
        ])
        ->assertHasFormErrors(['parent_id']);

    expect(
        TaxonomyTerm::query()->where('slug', 'venice')->exists(),
    )->toBeFalse();
});

it('renders the taxonomy term tree', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $lionKing = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lion King',
        'slug' => 'lion-king',
    ]);

    TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $lionKing->getKey(),
        'name' => 'Simba',
        'slug' => 'simba',
    ]);

    TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lilo & Stitch',
        'slug' => 'lilo-stitch',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Disney',
            'Lilo & Stitch',
            'Lion King',
            'Simba',
        ]);
});

it('has an edit term action', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $term = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->assertActionExists(
            TestAction::make('editTerm')
                ->arguments([
                    'term' => $term->getKey(),
                ]),
        );
});

it('prefills the edit term form', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $lionKing = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lion King',
        'slug' => 'lion-king',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->mountAction(
            TestAction::make('editTerm')
                ->arguments([
                    'term' => $lionKing->getKey(),
                ]),
        )
        ->assertSchemaStateSet([
            'name' => 'Lion King',
            'slug' => 'lion-king',
            'parent_id' => $disney->getKey(),
        ]);
});

it('can edit a taxonomy term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $term = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction(
            TestAction::make('editTerm')
                ->arguments([
                    'term' => $term->getKey(),
                ]),
            data: [
                'name' => 'Disney Characters',
                'slug' => 'disney-characters',
                'parent_id' => null,
            ],
        )
        ->assertHasNoFormErrors();

    expect($term->fresh())
        ->name->toBe('Disney Characters')
        ->slug->toBe('disney-characters')
        ->parent_id->toBeNull();
});

it('can move a taxonomy term to another parent', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $pixar = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Pixar',
        'slug' => 'pixar',
    ]);

    $lionKing = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lion King',
        'slug' => 'lion-king',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction(
            TestAction::make('editTerm')
                ->arguments([
                    'term' => $lionKing->getKey(),
                ]),
            data: [
                'name' => 'Lion King',
                'slug' => 'lion-king',
                'parent_id' => $pixar->getKey(),
            ],
        )
        ->assertHasNoFormErrors();

    expect($lionKing->fresh()->parent_id)
        ->toBe($pixar->getKey());
});

it('can move a taxonomy term to the root', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $lionKing = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lion King',
        'slug' => 'lion-king',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction(
            TestAction::make('editTerm')
                ->arguments([
                    'term' => $lionKing->getKey(),
                ]),
            data: [
                'name' => 'Lion King',
                'slug' => 'lion-king',
                'parent_id' => null,
            ],
        )
        ->assertHasNoFormErrors();

    expect($lionKing->fresh()->parent_id)
        ->toBeNull();
});

it('cannot edit a term from another taxonomy', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $otherTaxonomy = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $italy = TaxonomyTerm::create([
        'taxonomy_id' => $otherTaxonomy->getKey(),
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    expect(
        fn () => Livewire::test(ManageTaxonomyTerms::class, [
            'record' => $taxonomy->getKey(),
        ])->callAction(
            TestAction::make('editTerm')
                ->arguments([
                    'term' => $italy->getKey(),
                ]),
            data: [
                'name' => 'Modified Italy',
                'slug' => 'modified-italy',
                'parent_id' => null,
            ],
        ),
    )->toThrow(ModelNotFoundException::class);

    expect($italy->fresh())
        ->name->toBe('Italy')
        ->slug->toBe('italy');
});

it('cannot move a term under a parent from another taxonomy', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $otherTaxonomy = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $italy = TaxonomyTerm::create([
        'taxonomy_id' => $otherTaxonomy->getKey(),
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction(
            TestAction::make('editTerm')
                ->arguments([
                    'term' => $disney->getKey(),
                ]),
            data: [
                'name' => 'Disney',
                'slug' => 'disney',
                'parent_id' => $italy->getKey(),
            ],
        )
        ->assertHasFormErrors(['parent_id']);

    expect($disney->fresh()->parent_id)
        ->toBeNull();
});

it('can delete a taxonomy term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $term = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction(
            TestAction::make('deleteTerm')
                ->arguments([
                    'term' => $term->getKey(),
                ]),
        );

    expect(TaxonomyTerm::find($term->getKey()))
        ->toBeNull();
});

it('promotes children to root when deleting their parent term', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $disney = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Disney',
        'slug' => 'disney',
    ]);

    $lionKing = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $disney->getKey(),
        'name' => 'Lion King',
        'slug' => 'lion-king',
    ]);

    $simba = TaxonomyTerm::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'parent_id' => $lionKing->getKey(),
        'name' => 'Simba',
        'slug' => 'simba',
    ]);

    Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])
        ->callAction(
            TestAction::make('deleteTerm')
                ->arguments([
                    'term' => $lionKing->getKey(),
                ]),
        );

    expect(TaxonomyTerm::find($lionKing->getKey()))
        ->toBeNull()
        ->and($simba->fresh()->parent_id)
        ->toBeNull();
});

it('cannot delete a term from another taxonomy', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Theme',
        'slug' => 'theme',
    ]);

    $otherTaxonomy = Taxonomy::create([
        'name' => 'Country',
        'slug' => 'country',
    ]);

    $italy = TaxonomyTerm::create([
        'taxonomy_id' => $otherTaxonomy->getKey(),
        'name' => 'Italy',
        'slug' => 'italy',
    ]);

    try {
        Livewire::test(ManageTaxonomyTerms::class, [
            'record' => $taxonomy->getKey(),
        ])
            ->callAction(
                TestAction::make('deleteTerm')
                    ->arguments([
                        'term' => $italy->getKey(),
                    ]),
            );
    } catch (ModelNotFoundException $exception) {
        expect($exception->getModel())
            ->toBe(TaxonomyTerm::class);
    }

    expect(TaxonomyTerm::find($italy->getKey()))
        ->not->toBeNull()
        ->and($italy->fresh()->name)
        ->toBe('Italy');
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

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->call(
            'dropTerm',
            $invalidArgument === 'source' ? $invalidId : $local->id,
            $invalidArgument === 'target' ? $invalidId : $local->id,
            'inside',
        )
        ->assertNotFound();

    expect(TaxonomyTerm::query()->orderBy('id')->get()->toArray())->toBe($before);
})->with(['source', 'target'])
    ->with(['foreign' => false, 'missing' => true]);
