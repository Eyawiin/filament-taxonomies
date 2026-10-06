<?php

namespace Workbench\Database\Factories;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Taxonomy> */
class LargeDemoTaxonomyFactory extends Factory
{
    public const SLUG = 'demo-large-tree';

    public const TERM_COUNT = 534;

    public const MAX_DEPTH = 25;

    protected $model = Taxonomy::class;

    public function definition(): array
    {
        return ['name' => 'Demo Large Tree', 'slug' => self::SLUG];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Taxonomy $taxonomy) => $this->populate($taxonomy));
    }

    /** Add missing fixture terms while preserving existing names, parents and positions. */
    public function populate(Taxonomy $taxonomy): void
    {
        DemoTaxonomyFactory::ensureTerms($taxonomy, self::terms());
    }

    /** @return list<array{string, string, ?string}> */
    private static function terms(): array
    {
        $terms = [];
        $leafNames = ['Overview', 'Basics', 'Examples', 'Practice', 'Advanced review',
            'A deliberately long term name to test wrapping on small screens'];

        foreach (['Science', 'Languages', 'Humanities', 'Technology', 'Arts', 'Society'] as $rootIndex => $name) {
            $root = 'root-' . ($rootIndex + 1);
            $terms[] = [$name, $root, null];
            for ($branch = 1; $branch <= 12; $branch++) {
                $branchSlug = $root . '-subject-' . sprintf('%02d', $branch);
                $terms[] = ['Subject ' . sprintf('%02d', $branch), $branchSlug, $root];
                foreach ($leafNames as $leafIndex => $leafName) {
                    $terms[] = [$leafName, $branchSlug . '-term-' . ($leafIndex + 1), $branchSlug];
                }
            }
        }

        $parent = 'root-1';
        for ($level = 1; $level < self::MAX_DEPTH; $level++) {
            $slug = 'deep-' . sprintf('%02d', $level);
            $name = 'Deep level ' . sprintf('%02d', $level + 1);
            if ($level % 6 === 0) {
                $name .= ' — A longer hierarchy label for narrow-screen testing';
            }
            $terms[] = [$name, $slug, $parent];
            $parent = $slug;
        }

        return $terms;
    }
}
