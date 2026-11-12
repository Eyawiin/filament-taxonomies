<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\FormFixture;
use Workbench\App\Models\Deck;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function (): void {
    foreach (FormFixture::create() as $key => $value) {
        $this->{$key} = $value;
    }
});

it('seeds two nested taxonomies and repeatable decks without erasing manual edits or assignments', function (): void {
    $deck = Deck::where('name', 'Bicycle Lilo & Stitch')->firstOrFail();
    expect($deck->termsForTaxonomy($this->themes)->pluck('taxonomy_terms.id')->all())->toBe([$this->liloAndStitch->id])
        ->and($deck->termsForTaxonomy($this->brands)->pluck('taxonomy_terms.id')->all())->toBe([$this->riderBackRed->id])
        ->and(Deck::where('demo_key', 'deck-3')->firstOrFail()->termsForTaxonomy($this->themes)->pluck('taxonomy_terms.slug')->sort()->values()->all())
        ->toBe(['owls', 'wolves']);
    $deck->update(['name' => 'Renamed demo deck']);
    $deck->syncTaxonomyTerms($this->themes, [$this->wolves->id]);
    $this->liloAndStitch->update(['name' => 'Edited Lilo & Stitch']);
    $this->seed(DemoSeeder::class);
    expect(Taxonomy::count())->toBe(2)->and(TaxonomyTerm::count())->toBe(24)->and(Deck::count())->toBe(4)
        ->and($this->liloAndStitch->fresh()->name)->toBe('Edited Lilo & Stitch')
        ->and($deck->fresh()->termsForTaxonomy($this->themes)->pluck('taxonomy_terms.id')->all())->toBe([$this->wolves->id]);
});
