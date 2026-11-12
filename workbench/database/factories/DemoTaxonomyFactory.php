<?php

namespace Workbench\Database\Factories;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Demo taxonomies for a collection of playing-card decks.
 *
 * @extends Factory<Taxonomy>
 */
class DemoTaxonomyFactory extends Factory
{
    protected $model = Taxonomy::class;

    public function definition(): array
    {
        return ['name' => 'Demo ' . fake()->unique()->word(), 'slug' => 'demo-' . fake()->unique()->slug()];
    }

    /** What a deck shows; decks usually get several themes. */
    public function themes(): static
    {
        return $this->state(['name' => 'Themes', 'slug' => 'themes'])->withTree([
            ['Pop culture', 'pop-culture', null], ['Disney', 'disney', 'pop-culture'],
            ['Lilo & Stitch', 'lilo-and-stitch', 'disney'], ['The Lion King', 'the-lion-king', 'disney'],
            ['Star Wars', 'star-wars', 'pop-culture'], ['The Mandalorian', 'the-mandalorian', 'star-wars'],
            ['Nature', 'nature', null], ['Animals', 'animals', 'nature'],
            ['Wolves', 'wolves', 'animals'], ['Owls', 'owls', 'animals'],
            ['Space', 'space', 'nature'], ['Planets', 'planets', 'space'],
        ]);
    }

    /** Brand, line and back color; a deck has exactly one. */
    public function brands(): static
    {
        return $this->state(['name' => 'Brands', 'slug' => 'brands'])->withTree([
            ['Bicycle', 'bicycle', null], ['Rider Back', 'bicycle-rider-back', 'bicycle'],
            ['Red', 'bicycle-rider-back-red', 'bicycle-rider-back'], ['Blue', 'bicycle-rider-back-blue', 'bicycle-rider-back'],
            ['Tally-Ho', 'tally-ho', null], ['Circle Back', 'tally-ho-circle-back', 'tally-ho'],
            ['Red', 'tally-ho-circle-back-red', 'tally-ho-circle-back'], ['Blue', 'tally-ho-circle-back-blue', 'tally-ho-circle-back'],
            ['Bee', 'bee', null], ['Club Special', 'bee-club-special', 'bee'],
            ['Red', 'bee-club-special-red', 'bee-club-special'], ['Blue', 'bee-club-special-blue', 'bee-club-special'],
        ]);
    }

    /** Seed the named demo tree on an existing taxonomy without using Factory internals. */
    public function populate(Taxonomy $taxonomy): void
    {
        $this->callAfterCreating(collect([$taxonomy]));
    }

    /** @param list<array{string, string, ?string}> $terms */
    private function withTree(array $terms): static
    {
        return $this->afterCreating(function (Taxonomy $taxonomy) use ($terms): void {
            self::ensureTerms($taxonomy, $terms);
        });
    }

    /** Add missing demo terms, preserving existing manual edits and managed sibling order.
     * @param  list<array{string, string, ?string}>  $terms
     */
    public static function ensureTerms(Taxonomy $taxonomy, array $terms): void
    {
        foreach ($terms as [$name, $slug, $parentSlug]) {
            if ($taxonomy->terms()->where('slug', $slug)->exists()) {
                continue;
            }
            $parent = $parentSlug === null ? null : $taxonomy->terms()->where('slug', $parentSlug)->firstOrFail();
            app(TaxonomyTreeService::class)->createTerm($taxonomy, $name, $slug, $parent);
        }
    }
}
