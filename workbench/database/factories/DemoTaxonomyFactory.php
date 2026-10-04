<?php

namespace Workbench\Database\Factories;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Taxonomy> */
class DemoTaxonomyFactory extends Factory
{
    protected $model = Taxonomy::class;

    public function definition(): array
    {
        return ['name' => 'Demo ' . fake()->unique()->word(), 'slug' => 'demo-' . fake()->unique()->slug()];
    }

    public function topics(): static
    {
        return $this->state(['name' => 'Demo Topics', 'slug' => 'demo-topics'])->withTree([
            ['Languages', 'languages', null], ['English', 'english', 'languages'],
            ['Grammar', 'grammar', 'english'], ['Vocabulary', 'vocabulary', 'english'],
            ['German', 'german', 'languages'], ['Verbs', 'verbs', 'german'],
            ['Science', 'science', null], ['Mathematics', 'mathematics', 'science'],
            ['Algebra', 'algebra', 'mathematics'], ['Geometry', 'geometry', 'mathematics'],
            ['Biology', 'biology', 'science'], ['Cells', 'cells', 'biology'],
        ]);
    }

    public function levels(): static
    {
        return $this->state(['name' => 'Demo Levels', 'slug' => 'demo-levels'])->withTree([
            ['Beginner', 'beginner', null], ['Foundation', 'foundation', 'beginner'],
            ['A1', 'a1', 'foundation'], ['A2', 'a2', 'foundation'],
            ['Intermediate', 'intermediate', null], ['Independent', 'independent', 'intermediate'],
            ['B1', 'b1', 'independent'], ['B2', 'b2', 'independent'],
            ['Advanced', 'advanced', null], ['Proficient', 'proficient', 'advanced'],
            ['C1', 'c1', 'proficient'], ['C2', 'c2', 'proficient'],
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
