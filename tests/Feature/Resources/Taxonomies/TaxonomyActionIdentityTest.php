<?php

use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('rejects malformed source IDs instead of casting them to a valid action record', function (string $action, string $input): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();
    $id = match ($input) {
        'suffix' => $moving->id . 'junk',
        'fraction' => $moving->id . '.5',
        'array' => [$moving->id],
        'boolean' => true,
        'empty' => '',
        'zero' => '0',
        'overflow' => '999999999999999999999999',
    };
    $before = TaxonomyTerm::orderBy('id')->get()->toArray();
    $this->withoutExceptionHandling();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);

    expect(fn () => $component->call('mountAction', $action, ['term' => $id]))
        ->toThrow(ModelNotFoundException::class);
    expect(TaxonomyTerm::orderBy('id')->get()->toArray())->toBe($before);
})->with(['editTerm', 'deleteTerm'])->with(['suffix', 'fraction', 'array', 'boolean', 'empty', 'zero', 'overflow']);

it('accepts string IDs for the intended action record', function (string $action): void {
    ['taxonomy' => $taxonomy, 'moving' => $moving, 'first' => $other] = OrderedTaxonomyTreeFixture::create();
    $beforeOther = $other->fresh()->only(['name', 'slug', 'parent_id', 'position']);
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);
    $component->callAction(
        TestAction::make($action)->arguments(['term' => (string) $moving->id]),
        data: $action === 'editTerm' ? ['name' => 'Changed', 'slug' => $moving->slug, 'parent_id' => $moving->parent_id] : []
    )
        ->assertHasNoFormErrors();

    expect($other->fresh()->only(['name', 'slug', 'parent_id', 'position']))->toBe($beforeOther);
    if ($action === 'editTerm') {
        expect($moving->fresh()->name)->toBe('Changed');
    } else {
        expect(TaxonomyTerm::find($moving->id))->toBeNull();
    }
})->with(['editTerm', 'deleteTerm']);
