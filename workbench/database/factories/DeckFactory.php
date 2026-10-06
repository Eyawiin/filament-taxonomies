<?php

namespace Workbench\Database\Factories;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Deck;

/** @extends Factory<Deck> */
class DeckFactory extends Factory
{
    protected $model = Deck::class;

    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'description' => fake()->sentence()];
    }

    /** Assign existing demo terms after the Deck has an identity. */
    public function withDemoTerms(): static
    {
        return $this->afterCreating(function (Deck $deck): void {
            foreach (['demo-topics' => ['grammar', 'algebra'], 'demo-levels' => ['a1']] as $slug => $terms) {
                $taxonomy = Taxonomy::where('slug', $slug)->firstOrFail();
                $deck->syncTaxonomyTerms($taxonomy, $taxonomy->terms()->whereIn('slug', $terms)->pluck('id')->all());
            }
        });
    }

    /** @param list<string> $slugs Existing large-playground terms, including any desired ancestors. */
    public function withLargeDemoTerms(array $slugs): static
    {
        return $this->afterCreating(function (Deck $deck) use ($slugs): void {
            $taxonomy = Taxonomy::where('slug', LargeDemoTaxonomyFactory::SLUG)->firstOrFail();
            $deck->syncTaxonomyTerms($taxonomy, $taxonomy->terms()->whereIn('slug', $slugs)->pluck('id')->all());
        });
    }
}
