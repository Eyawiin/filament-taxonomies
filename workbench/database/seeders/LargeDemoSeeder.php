<?php

namespace Workbench\Database\Seeders;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Deck;
use Workbench\Database\Factories\DeckFactory;
use Workbench\Database\Factories\LargeDemoTaxonomyFactory;

/** Optional local stress playground. Re-running preserves existing deck assignments. */
class LargeDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(DemoSeeder::class);
            $factory = LargeDemoTaxonomyFactory::new();
            $taxonomy = Taxonomy::where('slug', LargeDemoTaxonomyFactory::SLUG)->first();
            if ($taxonomy === null) {
                $factory->create();
            } else {
                $factory->populate($taxonomy);
            }

            foreach (self::scenarios() as $key => [$name, $description, $slugs]) {
                if (! Deck::where('demo_key', $key)->exists()) {
                    DeckFactory::new()->withDemoTerms()->withLargeDemoTerms($slugs)->create([
                        'name' => $name, 'description' => $description, 'demo_key' => $key,
                    ]);
                }
            }
        });
    }

    /** @return array<string, array{string, string, list<string>}> */
    private static function scenarios(): array
    {
        $catalog = LargeDemoTaxonomyFactory::catalog();
        $deep = [array_key_first($catalog), ...LargeDemoTaxonomyFactory::deepSlugs()];

        // Each theme with its first two series and all their editions: 6 × 15 terms.
        $broad = [];
        foreach ($catalog as $theme => $series) {
            $broad[] = $theme;
            foreach (array_slice($series, 0, 2) as $seriesSlug) {
                $broad[] = $seriesSlug;
                foreach (array_keys(LargeDemoTaxonomyFactory::EDITIONS) as $edition) {
                    $broad[] = $seriesSlug . '-' . $edition;
                }
            }
        }

        return [
            'large-tree-deep' => ['Playground: 25 levels', 'Follow the selected Disney branch through 25 levels. Try removing a chip.', $deep],
            'large-tree-broad' => ['Playground: 90 selections', 'Six themes with repeated edition names and long names. Test chips, search and removal.', $broad],
            'large-tree-empty' => ['Playground: empty selection', 'Browse or search all 534 terms and build your own selection.', []],
        ];
    }
}
