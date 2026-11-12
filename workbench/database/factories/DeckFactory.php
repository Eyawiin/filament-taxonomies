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
        return [
            'name' => fake()->randomElement(['Bicycle', 'Tally-Ho', 'Bee']) . ' ' . fake()->words(2, true),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Assign existing demo terms after the Deck has an identity.
     *
     * @param  list<string>  $themes  Theme slugs; ancestors are not added automatically.
     */
    public function withDemoTerms(array $themes = ['lilo-and-stitch', 'wolves'], string $brand = 'bicycle-rider-back-red'): static
    {
        return $this->afterCreating(function (Deck $deck) use ($themes, $brand): void {
            foreach (['themes' => $themes, 'brands' => [$brand]] as $slug => $terms) {
                $taxonomy = Taxonomy::where('slug', $slug)->firstOrFail();
                $deck->syncTaxonomyTerms($taxonomy, $taxonomy->terms()->whereIn('slug', $terms)->pluck('id')->all());
            }
        });
    }

    /** @param list<string> $slugs Existing large-catalog terms, including any desired ancestors. */
    public function withLargeDemoTerms(array $slugs): static
    {
        return $this->afterCreating(function (Deck $deck) use ($slugs): void {
            $taxonomy = Taxonomy::where('slug', LargeDemoTaxonomyFactory::SLUG)->firstOrFail();
            $deck->syncTaxonomyTerms($taxonomy, $taxonomy->terms()->whereIn('slug', $slugs)->pluck('id')->all());
        });
    }
}
