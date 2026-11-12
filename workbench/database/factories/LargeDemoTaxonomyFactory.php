<?php

namespace Workbench\Database\Factories;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A deck catalog large enough to load on demand: six themes with twelve series each,
 * six editions per series, and a 25-level branch below the first theme.
 *
 * @extends Factory<Taxonomy>
 */
class LargeDemoTaxonomyFactory extends Factory
{
    public const SLUG = 'large-catalog';

    public const TERM_COUNT = 534;

    public const MAX_DEPTH = 25;

    /** Every series has these editions; the repeated names test paths in search results. */
    public const EDITIONS = [
        'standard' => 'Standard edition',
        'gilded' => 'Gilded edition',
        'foil' => 'Foil edition',
        'limited' => 'Limited edition',
        'marked' => 'Marked deck',
        'long-name' => 'A deliberately long edition name to test wrapping on small screens',
    ];

    private const THEMES = [
        'Disney' => ['Lilo & Stitch', 'The Lion King', 'Frozen', 'Moana', 'Aladdin', 'Mulan',
            'Tangled', 'Encanto', 'Zootopia', 'Coco', 'Toy Story', 'The Little Mermaid'],
        'Marvel' => ['Avengers', 'Spider-Man', 'X-Men', 'Iron Man', 'Black Panther', 'Guardians of the Galaxy',
            'Thor', 'Captain America', 'Doctor Strange', 'Deadpool', 'Fantastic Four', 'Daredevil'],
        'Star Wars' => ['A New Hope', 'The Empire Strikes Back', 'Return of the Jedi', 'The Phantom Menace',
            'Attack of the Clones', 'Revenge of the Sith', 'The Force Awakens', 'Rogue One', 'The Mandalorian',
            'Andor', 'Ahsoka', 'The Clone Wars'],
        'Nature' => ['Wolves', 'Owls', 'Foxes', 'Bears', 'Butterflies', 'Flowers',
            'Trees', 'Oceans', 'Mountains', 'Deserts', 'Planets', 'Stars'],
        'Art' => ['Art Nouveau', 'Art Deco', 'Baroque', 'Bauhaus', 'Pop Art', 'Ukiyo-e',
            'Tarot', 'Botanical', 'Steampunk', 'Cyberpunk', 'Minimalist', 'Watercolor'],
        'Vintage' => ['1900s', '1910s', '1920s', '1930s', '1940s', '1950s',
            '1960s', '1970s', '1980s', '1990s', 'Casino', 'Advertising'],
    ];

    protected $model = Taxonomy::class;

    public function definition(): array
    {
        return ['name' => 'Large catalog', 'slug' => self::SLUG];
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

    /**
     * Theme slugs with the slugs of their series, in tree order.
     *
     * @return array<string, list<string>>
     */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (self::THEMES as $theme => $series) {
            $catalog[Str::slug($theme)] = array_map(
                static fn (string $name): string => Str::slug($theme) . '-' . Str::slug($name),
                $series,
            );
        }

        return $catalog;
    }

    /** @return list<string> The 25-level branch below the first theme, from the top down. */
    public static function deepSlugs(): array
    {
        return array_map(static fn (int $level): string => 'deep-' . sprintf('%02d', $level), range(1, self::MAX_DEPTH - 1));
    }

    /** @return list<array{string, string, ?string}> */
    private static function terms(): array
    {
        $catalog = self::catalog();
        $terms = [];
        foreach (self::THEMES as $theme => $series) {
            $themeSlug = Str::slug($theme);
            $terms[] = [$theme, $themeSlug, null];
            foreach ($series as $index => $name) {
                $seriesSlug = $catalog[$themeSlug][$index];
                $terms[] = [$name, $seriesSlug, $themeSlug];
                foreach (self::EDITIONS as $key => $edition) {
                    $terms[] = [$edition, $seriesSlug . '-' . $key, $seriesSlug];
                }
            }
        }

        $parent = array_key_first($catalog);
        foreach (self::deepSlugs() as $index => $slug) {
            $level = $index + 1;
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
