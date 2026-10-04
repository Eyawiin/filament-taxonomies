<?php

namespace Workbench\Database\Seeders;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Deck;
use Workbench\Database\Factories\DeckFactory;
use Workbench\Database\Factories\DemoTaxonomyFactory;

/** Local playground only. Re-running preserves existing decks and their assignments. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['topics', 'levels'] as $kind) {
                $factory = DemoTaxonomyFactory::new()->{$kind}();
                $attributes = $factory->raw();
                $taxonomy = Taxonomy::where('slug', $attributes['slug'])->first();
                if ($taxonomy === null) {
                    $factory->create();
                } else {
                    // Run the same tree factory callbacks on the existing taxonomy.
                    $factory->populate($taxonomy);
                }
            }
            foreach (['English basics', 'Math warm-up', 'German verbs', 'Biology cards'] as $index => $name) {
                if (! Deck::where('demo_key', 'deck-' . $index)->exists()) {
                    DeckFactory::new()->withDemoTerms()->create(['name' => $name, 'demo_key' => 'deck-' . $index]);
                }
            }
        });
    }
}
