<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\Deck;
use Workbench\Database\Seeders\DemoSeeder;

$app = require __DIR__ . '/bootstrap.php';
@mkdir(dirname(__DIR__) . '/build', 0755, true);
touch(dirname(__DIR__) . '/build/browser.sqlite');
if (! Schema::hasTable('taxonomies')) {
    foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') as $migration) {
        (require $migration)->up();
    }
}
if (! Schema::hasTable('taxonomy_term_assignments')) {
    (require dirname(__DIR__) . '/database/migrations/0004_create_taxonomy_term_assignments_table.php')->up();
}
Taxonomy::unguard();
$taxonomy = Taxonomy::firstOrCreate(['id' => 1], ['name' => 'Browser topics', 'slug' => 'browser-topics']);
foreach ([
    [1, 'Country', null],
    [2, 'Italy', 1],
    [3, 'Rome', 2],
    [4, 'France', 1],
    [5, '<img src=x onerror=alert(1)>', null],
] as [$id, $name, $parent]) {
    $taxonomy->terms()->updateOrCreate(['id' => $id], ['name' => $name, 'slug' => 'term-' . $id, 'parent_id' => $parent, 'position' => $id]);
}
if (! Schema::hasTable('decks')) {
    (require dirname(__DIR__) . '/workbench/database/migrations/2026_10_04_000000_create_decks_table.php')->up();
}
$app->make(DemoSeeder::class)->run();
// Reset only this disposable browser fixture, never the persistent workbench database.
foreach (Deck::whereNotNull('demo_key')->get() as $deck) {
    foreach (['demo-topics' => ['grammar', 'algebra'], 'demo-levels' => ['a1']] as $slug => $terms) {
        $taxonomy = Taxonomy::where('slug', $slug)->firstOrFail();
        $deck->syncTaxonomyTerms($taxonomy, $taxonomy->terms()->whereIn('slug', $terms)->pluck('id')->all());
    }
}
$app->make(Kernel::class)->call('filament:assets');
echo "Browser fixture and published assets ready.\n";
