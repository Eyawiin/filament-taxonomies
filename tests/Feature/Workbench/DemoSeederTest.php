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
    $deck = Deck::where('name', 'English basics')->firstOrFail();
    $deck->update(['name' => 'Renamed demo deck']);
    $deck->syncTaxonomyTerms($this->topics, [$this->algebra->id]);
    $this->grammar->update(['name' => 'Edited grammar']);
    $this->seed(DemoSeeder::class);
    expect(Taxonomy::count())->toBe(2)->and(TaxonomyTerm::count())->toBe(24)->and(Deck::count())->toBe(4)
        ->and($this->grammar->fresh()->name)->toBe('Edited grammar')
        ->and($deck->fresh()->termsForTaxonomy($this->topics)->pluck('taxonomy_terms.id')->all())->toBe([$this->algebra->id]);
});
