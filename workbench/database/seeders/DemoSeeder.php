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
    /** @var array<string, array{string, string, list<string>, string}> Name, description, theme slugs and brand slug. */
    public const DECKS = [
        'deck-0' => ['Bicycle Lilo & Stitch', 'A Disney deck with Stitch on the aces.', ['lilo-and-stitch'], 'bicycle-rider-back-red'],
        'deck-1' => ['Tally-Ho The Lion King', 'Pride Rock in gold foil on a blue Circle Back.', ['the-lion-king'], 'tally-ho-circle-back-blue'],
        'deck-2' => ['Bee The Mandalorian', 'A Star Wars deck on Bee stock.', ['the-mandalorian'], 'bee-club-special-red'],
        'deck-3' => ['Bicycle Wolves & Owls', 'Night animals with a blue Rider Back.', ['wolves', 'owls'], 'bicycle-rider-back-blue'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['themes', 'brands'] as $kind) {
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
            foreach (self::DECKS as $key => [$name, $description, $themes, $brand]) {
                if (! Deck::where('demo_key', $key)->exists()) {
                    DeckFactory::new()->withDemoTerms($themes, $brand)
                        ->create(['name' => $name, 'description' => $description, 'demo_key' => $key]);
                }
            }
        });
    }
}
