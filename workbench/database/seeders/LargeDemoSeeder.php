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
        $deep = ['root-1'];
        for ($level = 1; $level < LargeDemoTaxonomyFactory::MAX_DEPTH; $level++) {
            $deep[] = 'deep-' . sprintf('%02d', $level);
        }

        $broad = [];
        for ($root = 1; $root <= 6; $root++) {
            $rootSlug = 'root-' . $root;
            $broad[] = $rootSlug;
            for ($branch = 1; $branch <= 2; $branch++) {
                $branchSlug = $rootSlug . '-subject-' . sprintf('%02d', $branch);
                $broad[] = $branchSlug;
                for ($leaf = 1; $leaf <= 6; $leaf++) {
                    $broad[] = $branchSlug . '-term-' . $leaf;
                }
            }
        }

        return [
            'large-tree-deep' => ['Playground: 25 levels', 'Follow the selected Science branch through 25 levels. Try removing a branch and undoing it.', $deep],
            'large-tree-broad' => ['Playground: 90 selections', 'Six branches with duplicate term labels and long names. Test selection review, search and removal.', $broad],
            'large-tree-empty' => ['Playground: empty selection', 'Browse or search all 534 terms and build your own selection.', []],
        ];
    }
}
