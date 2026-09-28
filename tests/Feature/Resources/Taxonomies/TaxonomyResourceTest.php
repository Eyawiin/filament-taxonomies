<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
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

it('cannot move taxonomy terms between parents through sibling reordering', function (): void {
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
    ]);

    expect(
        fn () => Livewire::test(ManageTaxonomyTerms::class, [
            'record' => $taxonomy->getKey(),
        ])->call(
            'moveTerm',
            $lionKing->id,
            0,
            $pixar->id,
        ),
    )->toThrow(InvalidTaxonomyOrderException::class);

    expect($lionKing->fresh()->parent_id)
        ->toBe($disney->id);
});
