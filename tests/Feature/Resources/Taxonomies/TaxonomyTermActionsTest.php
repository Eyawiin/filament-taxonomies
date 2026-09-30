<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
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

    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(ManageTaxonomyTerms::class, [
        'record' => $taxonomy->getKey(),
    ])->callAction(
        TestAction::make('deleteTerm')
            ->arguments([
                'term' => $italy->getKey(),
            ]),
    ))->toThrow(ModelNotFoundException::class);

    expect(TaxonomyTerm::find($italy->getKey()))
        ->not->toBeNull()
        ->and($italy->fresh()->name)
        ->toBe('Italy');
});
